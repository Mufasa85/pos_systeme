<?php

namespace App\Services;

use App\Core\Database;
use PhpOffice\PhpSpreadsheet\Shared\Date as SharedDate;

/**
 * Répare les codes-barres corrompus par l'import Excel.
 *
 * ── Contexte ────────────────────────────────────────────────────────────
 * L'import de produits lisait la valeur AFFICHÉE de la cellule
 * « code_barres » au lieu de sa valeur RÉELLE. Or Excel formate
 * automatiquement en date (m/d/yyyy) les codes-barres numériques à
 * 13 chiffres : « 8426423308202 » s'affichait « 5/17/23070765319 » et
 * c'est cette chaîne qui était enregistrée en base.
 *
 * Ce service détecte ces codes-barres « en forme de date » et les
 * reconvertit en leur valeur numérique d'origine.
 *
 * ⚠️ La reconversion est EXACTE (et non une approximation) : la valeur
 *    affichée « m/d/yyyy » est le sérial Excel du code-barres. On vérifie
 *    systématiquement l'aller-retour (sérial → affichage → sérial) avant
 *    d'écrire quoi que ce soit.
 *
 * Le service est utilisé par :
 *   - scripts/repair_barcodes_importes.php (CLI, mutualisé)
 *   - POST /api/produit/repair-barcodes (route de production)
 */
class BarcodeRepairService
{
    /** @var \PDO */
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Nombre de jours depuis l'époque Unix pour une date civile (algorithme
     * de Howard Hinnant, « days_from_civil »).
     */
    public static function daysFromCivil(int $y, int $m, int $d): int
    {
        $y -= ($m <= 2) ? 1 : 0;
        $era = intdiv($y, 400);
        $yoe = $y - $era * 400;
        $doy = intdiv(153 * ($m + ($m > 2 ? -3 : 9)) + 2, 5) + $d - 1;
        $doe = $yoe * 365 + intdiv($yoe, 4) - intdiv($yoe, 100) + $doy;

        return $era * 146097 + $doe - 719468;
    }

    /**
     * Reconvertit une valeur affichée « m/d/yyyy » en sérial Excel.
     *
     * @return int|null  Le sérial d'origine, ou null si la valeur n'est pas
     *                   une date affichée exploitable.
     */
    public static function serialFromDateDisplay(string $displayed): ?int
    {
        if (!preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4,})$#', trim($displayed), $m)) {
            return null;
        }

        $month = (int) $m[1];
        $day = (int) $m[2];
        $year = (int) $m[3];

        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return null;
        }

        // 25569 = nombre de jours entre l'époque Excel (1900) et l'époque Unix.
        $serial = self::daysFromCivil($year, $month, $day) + 25569;

        // Vérification aller-retour : l'affichage doit être reproduit à l'identique.
        $check = SharedDate::excelToDateTimeObject((float) $serial);
        $same = $check->format('n') === (string) $month
            && $check->format('j') === (string) $day
            && $check->format('Y') === (string) $year;

        return $same ? $serial : null;
    }

    /**
     * Détecte les codes-barres corrompus et prépare le plan de réparation.
     *
     * Un code-barres corrompu a exactement la forme « m/d/yyyy » (le
     * code-barres d'origine est numérique, donc sa valeur affichée est une
     * date).
     *
     * @param int|null $shopId    Restreint à une boutique (null = toutes)
     * @param bool     $resolve   Autorise la résolution des collisions
     * @param int[]    $deleteIds Produits à supprimer pour libérer le
     *                             code-barres (avec $resolve)
     *
     * @return array{plan:array, duplicates:array, skipped:array, detected:int}
     */
    public function buildPlan(?int $shopId = null, bool $resolve = false, array $deleteIds = []): array
    {
        $sql = "SELECT id, code_barres, nom, shop_id
                FROM produits
                WHERE code_barres REGEXP '^[0-9]{1,2}/[0-9]{1,2}/[0-9]{4,}$'";
        $params = [];

        if ($shopId !== null) {
            $sql .= ' AND shop_id = ?';
            $params[] = $shopId;
        }

        $sql .= ' ORDER BY id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $corrupted = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $plan = [];
        $duplicates = [];
        $skipped = [];

        foreach ($corrupted as $row) {
            $original = self::serialFromDateDisplay((string) $row['code_barres']);

            if ($original === null) {
                $skipped[] = [
                    'id' => (int) $row['id'],
                    'code_barres' => (string) $row['code_barres'],
                    'nom' => (string) $row['nom'],
                    'reason' => 'Valeur affichée non convertible.',
                ];
                continue;
            }

            $newBarcode = (string) $original;

            // Collision : un produit portant déjà ce code-barres existe.
            $collisionStmt = $this->pdo->prepare(
                'SELECT id, code_barres, nom, shop_id FROM produits WHERE code_barres = ? AND id <> ?'
            );
            $collisionStmt->execute([$newBarcode, $row['id']]);
            $collision = $collisionStmt->fetch(\PDO::FETCH_ASSOC);

            if ($collision) {
                $resolved = false;

                // Résolution manuelle demandée : l'utilisateur désigne le
                // produit à supprimer pour libérer le code-barres.
                if ($resolve && $deleteIds !== []
                    && in_array((int) $collision['id'], $deleteIds, true)) {
                    $resolved = true;
                }

                // Sinon : le produit qui occupe le code-barres est-il un
                // doublon du produit corrompu (même nom, même boutique) ?
                // C'est un artefact d'un import antérieur (le fichier
                // fournisseur a changé de code-barres entre deux imports).
                if (!$resolved && $resolve) {
                    $sameShop = (string) $row['shop_id'] === (string) $collision['shop_id'];
                    $sameName = trim((string) $row['nom']) === trim((string) $collision['nom']);
                    $resolved = $sameShop && $sameName;
                }

                if ($resolved) {
                    $duplicates[] = [
                        'id' => (int) $collision['id'],
                        'code_barres' => (string) $collision['code_barres'],
                        'nom' => (string) $collision['nom'],
                    ];

                    $plan[] = [
                        'id' => (int) $row['id'],
                        'old' => (string) $row['code_barres'],
                        'new' => $newBarcode,
                        'nom' => (string) $row['nom'],
                    ];
                    continue;
                }

                $skipped[] = [
                    'id' => (int) $row['id'],
                    'code_barres' => (string) $row['code_barres'],
                    'nom' => (string) $row['nom'],
                    'reason' => 'Collision avec le produit #' . (int) $collision['id']
                        . ' (' . $collision['nom'] . ').',
                    'collision_id' => (int) $collision['id'],
                    'collision_nom' => (string) $collision['nom'],
                ];
                continue;
            }

            $plan[] = [
                'id' => (int) $row['id'],
                'old' => (string) $row['code_barres'],
                'new' => $newBarcode,
                'nom' => (string) $row['nom'],
            ];
        }

        return [
            'plan' => $plan,
            'duplicates' => $duplicates,
            'skipped' => $skipped,
            'detected' => count($corrupted),
        ];
    }

    /**
     * Applique le plan de réparation (transactionnel).
     *
     * @param array $plan       Plan issu de buildPlan()
     * @param array $duplicates Doublons à supprimer (issus de buildPlan())
     *
     * @return array{repaired:int, deleted:int}
     */
    public function applyPlan(array $plan, array $duplicates): array
    {
        if ($plan === [] && $duplicates === []) {
            return ['repaired' => 0, 'deleted' => 0];
        }

        $this->pdo->beginTransaction();

        try {
            // Les doublons sont supprimés AVANT la mise à jour : ils occupent
            // encore le code-barres cible (contrainte UNIQUE).
            $deleted = 0;
            if ($duplicates !== []) {
                $deleteBatch = $this->pdo->prepare('DELETE FROM product_batches WHERE product_id = ?');
                $deleteProduct = $this->pdo->prepare('DELETE FROM produits WHERE id = ?');

                foreach ($duplicates as $duplicate) {
                    $deleteBatch->execute([$duplicate['id']]);
                    $deleteProduct->execute([$duplicate['id']]);
                    $deleted++;
                }
            }

            $update = $this->pdo->prepare('UPDATE produits SET code_barres = ? WHERE id = ?');
            $repaired = 0;
            foreach ($plan as $item) {
                $update->execute([$item['new'], $item['id']]);
                $repaired++;
            }

            $this->pdo->commit();

            return ['repaired' => $repaired, 'deleted' => $deleted];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Exporte les lignes concernées (avant réparation) au format SQL, afin
     * de pouvoir restaurer manuellement les données supprimées.
     */
    public function exportBackup(array $plan, array $duplicates): string
    {
        $lines = [];

        if ($plan !== []) {
            $ids = implode(', ', array_map(static fn (array $r): int => $r['id'], $plan));
            $stmt = $this->pdo->query("SELECT id, code_barres FROM produits WHERE id IN ({$ids})");
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $lines[] = "UPDATE `produits` SET `code_barres` = '"
                    . str_replace("'", "''", (string) $row['code_barres'])
                    . "' WHERE `id` = " . (int) $row['id'] . ';';
            }
        }

        if ($duplicates !== []) {
            $lines[] = '';
            $lines[] = '-- Doublons supprimés (restauration manuelle requise)';

            $dupIds = implode(', ', array_map(static fn (array $r): int => $r['id'], $duplicates));
            $stmt = $this->pdo->query(
                "SELECT id, code_barres, nom, category_id, shop_id, prix, stock
                 FROM produits WHERE id IN ({$dupIds})"
            );
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $values = [];
                foreach (['code_barres', 'nom', 'category_id', 'shop_id', 'prix', 'stock'] as $column) {
                    $values[] = "'" . str_replace("'", "''", (string) $row[$column]) . "'";
                }
                $lines[] = 'INSERT INTO `produits` '
                    . '(`id`, `code_barres`, `nom`, `category_id`, `shop_id`, `prix`, `stock`)'
                    . ' VALUES (' . (int) $row['id'] . ', ' . implode(', ', $values) . ');';
            }

            $stmt = $this->pdo->query(
                "SELECT id, product_id, batch_number, stock, date_expiration, date_reception
                 FROM product_batches WHERE product_id IN ({$dupIds})"
            );
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $lines[] = '-- product_batches: ' . json_encode($row, JSON_UNESCAPED_UNICODE);
            }
        }

        return implode("\n", $lines) . "\n";
    }
}
