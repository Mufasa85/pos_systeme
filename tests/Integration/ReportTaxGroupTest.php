<?php

namespace Tests\Integration;

use App\Core\Database;
use App\Models\Report;
use App\Models\Sale;
use App\Models\SaleDetail;
use PHPUnit\Framework\TestCase;

/**
 * NON-RÉGRESSION : le rapport « Par groupe de taxation » d'une vente POS.
 *
 * Bug observé (2026-11-06) : une vente POS d'un produit du GROUPE B (16 %)
 * s'affichait dans le rapport avec un taux de « 0.00% » et une taxe de 0,
 * alors que la facture portait bien une TVA de 16 % (bloc « Par type de
 * facture » correct, bloc « Par groupe de taxation » faux).
 *
 * CAUSE RACINE : `details_vente.tax_rate` a été créée avec `DEFAULT 0.00`
 * et le POS n'écrivait pas cette colonne → MySQL y stockait 0 (et non NULL).
 * La logique du rapport interprétait ce 0 comme « ligne réellement non
 * taxée » et écrasait le taux du produit.
 *
 * Ce test verrouille les cas :
 *   1. vente POS avec snapshot du taux   → taux + taxe corrects ;
 *   2. vente POS LEGACY (sans snapshot)   → repli sur la taxe du produit ;
 *   3. ligne EXONÉRÉE (groupe A, taux 0) → taux 0 conservé ;
 *   4. TTC du groupe = HT + taxe.
 */
class ReportTaxGroupTest extends TestCase
{
    private static $pdo;
    private $ids = [];

    public static function setUpBeforeClass(): void
    {
        self::$pdo = Database::getInstance()->getConnection();
    }

    protected function setUp(): void
    {
        // ── Taxes de test ──────────────────────────────────────────────
        // Groupe B = 16 % (le groupe du cas utilisateur).
        self::$pdo->prepare(
            "INSERT INTO taxes (groupe_taxe, etiquette, description, taux)
             SELECT 'Groupe B', 'B', 'Taxable 16%', 16
             WHERE NOT EXISTS (SELECT 1 FROM taxes WHERE etiquette = 'B')"
        )->execute();
        $this->ids['taxeB'] = (int)self::$pdo
            ->query("SELECT id FROM taxes WHERE etiquette = 'B' ORDER BY id LIMIT 1")->fetchColumn();

        // Groupe A = 0 % (exonéré) : sert au test du cas 3.
        self::$pdo->prepare(
            "INSERT INTO taxes (groupe_taxe, etiquette, description, taux)
             SELECT 'Groupe A', 'A', 'Exonere', 0
             WHERE NOT EXISTS (SELECT 1 FROM taxes WHERE etiquette = 'A')"
        )->execute();
        $this->ids['taxeA'] = (int)self::$pdo
            ->query("SELECT id FROM taxes WHERE etiquette = 'A' ORDER BY id LIMIT 1")->fetchColumn();

        // ── Catégorie + produits ───────────────────────────────────────
        // ⚠️ La colonne est `categories.category` (et non `nom`).
        $catId = self::$pdo->query("SELECT id FROM categories ORDER BY id LIMIT 1")->fetchColumn();
        if (!$catId) {
            self::$pdo->prepare("INSERT INTO categories (category) VALUES ('__RTG__')")->execute();
            $catId = (int)self::$pdo->lastInsertId();
        }
        $this->ids['categorie'] = (int)$catId;

        $this->creerProduit('RTG_B', $this->ids['taxeB']);
        $this->creerProduit('RTG_A', $this->ids['taxeA']);

        // ── Vendeur (ventes.vendeur_id NOT NULL + FK) ──────────────────
        $stmt = self::$pdo->prepare("SELECT id FROM utilisateurs ORDER BY id LIMIT 1");
        $stmt->execute();
        $userId = $stmt->fetchColumn();
        if (!$userId) {
            $this->markTestSkipped('Aucun utilisateur en base : test non applicable.');
        }
        $this->ids['user'] = (int)$userId;
    }

    protected function tearDown(): void
    {
        $stmt = self::$pdo->prepare("SELECT id FROM ventes WHERE numero_facture LIKE '__RTG_%'");
        $stmt->execute();
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $venteId) {
            self::$pdo->prepare("DELETE FROM details_vente WHERE vente_id = ?")->execute([$venteId]);
            self::$pdo->prepare("DELETE FROM ventes WHERE id = ?")->execute([$venteId]);
        }
        foreach (['produitB', 'produitA'] as $key) {
            if (!empty($this->ids[$key])) {
                self::$pdo->prepare("DELETE FROM produits WHERE id = ?")->execute([$this->ids[$key]]);
            }
        }
    }

    private function creerProduit(string $prefixe, int $taxeId): void
    {
        $nom = $prefixe . '_' . bin2hex(random_bytes(3));
        self::$pdo->prepare(
            "INSERT INTO produits (code_barres, nom, category_id, prix, stock, taxe_id)
             VALUES (?, ?, ?, 1000, 100, ?)"
        )->execute([$nom, $nom, $this->ids['categorie'], $taxeId]);
        $this->ids[$prefixe === 'RTG_B' ? 'produitB' : 'produitA'] = (int)self::$pdo->lastInsertId();
    }

    /**
     * Crée une vente POS d'une ligne de 1 000 HT.
     *
     * ⚠️ ISOLEMENT : la base de test contient d'autres ventes (fixtures
     * d'autres tests) à la même date. On place donc la vente sur une
     * fenêtre horaire qui lui est propre (ex. 03:17:xx) pour que le
     * rapport ne melange pas les deux.
     *
     * @param string      $produitKey  'produitB' ou 'produitA'
     * @param float|null  $taxRate     Snapshot du taux (null = ligne LEGACY)
     * @param string|null $etiquette   Étiquette figée (null = non figée)
     */
    private function creerVentePos(string $produitKey, ?float $taxRate, ?string $etiquette = null): int
    {
        $numero = '__RTG_' . bin2hex(random_bytes(4));
        $saleId = (new Sale())->create([
            'numero_facture' => $numero,
            'invoice_type'   => 'FV',
            'client_id'      => null,
            'sejour_id'      => null,
            'sous_total_ht'  => 1000,
            'tva'            => 160,
            'total'          => 1160,
            'vendeur_id'     => $this->ids['user'],
            'date'           => $this->dateTest(),
        ]);

        (new SaleDetail())->create([
            'vente_id'             => $saleId,
            'produit_id'           => $this->ids[$produitKey],
            'quantite'             => 1,
            'prix'                 => 1000,
            'remise_type'          => '%',
            'remise_value'         => 0,
            'taxe_specifique_type' => '%',
            'taxe_specifique_value' => 0,
            'tax_rate'             => $taxRate,
            'tax_etiquette'        => $etiquette,
            'tax_groupe'           => $etiquette !== null ? 'Groupe ' . $etiquette : null,
        ]);

        return $saleId;
    }

    /**
     * Date de test isolée : chaque test utilise une tranche horaire qui lui
     * est propre (minute pseudo-aléatoire entre 03:00 et 03:59), de sorte que
     * les fixtures des autres tests (créées à 07:xx) ne polluent pas les
     * agrégats du rapport.
     */
    private function dateTest(): string
    {
        if (!isset($this->ids['minute'])) {
            $this->ids['minute'] = random_int(0, 59);
        }
        return date('Y-m-d') . ' 03:' . str_pad((string)$this->ids['minute'], 2, '0', STR_PAD_LEFT)
             . ':' . str_pad((string)random_int(0, 59), 2, '0', STR_PAD_LEFT);
    }

    /** Fenêtre horaire isolée qui contient uniquement les ventes de ce test. */
    private function periode(): array
    {
        if (!isset($this->ids['minute'])) {
            $this->ids['minute'] = random_int(0, 59);
        }
        $minute = str_pad((string)$this->ids['minute'], 2, '0', STR_PAD_LEFT);
        return [
            date('Y-m-d') . ' 03:' . $minute . ':00',
            date('Y-m-d') . ' 03:' . $minute . ':59',
        ];
    }

    public function testVentePosAvecSnapshotAfficheLeTauxEtLaTaxeDuProduit(): void
    {
        $this->creerVentePos('produitB', 16.0, 'B');

        $groupe = $this->groupeB();

        $this->assertNotNull($groupe, 'Le groupe B doit être présent dans le rapport.');
        $this->assertEquals(16.0, (float)$groupe['taux'], 'Le taux doit être 16 %.');
        $this->assertEquals('B', $groupe['etiquette'], 'L\'étiquette doit être B.');
        $this->assertEquals('Groupe B', $groupe['groupe_taxe'], 'Le groupe doit être Groupe B.');
        $this->assertEqualsWithDelta(
            160.0, (float)$groupe['total_tax'], 0.01,
            'La taxe doit être 1000 × 16 % = 160.'
        );
    }

    public function testVentePosLegacyRetombeSurLaTaxeDuProduit(): void
    {
        // Ligne SANS snapshot (créée avant le correctif) : le rapport doit
        // retrouver le taux du produit via `produits.taxe_id` → `taxes`.
        $this->creerVentePos('produitB', null);

        $groupe = $this->groupeB();

        $this->assertNotNull($groupe, 'Le groupe B doit être présent dans le rapport.');
        $this->assertEquals(
            16.0, (float)$groupe['taux'],
            'Une ligne legacy doit récupérer le taux de son produit (16 %), pas 0.'
        );
        $this->assertEquals('B', $groupe['etiquette'], 'L\'étiquette doit venir du produit.');
        $this->assertEqualsWithDelta(
            160.0, (float)$groupe['total_tax'], 0.01,
            'La taxe doit être calculée même pour une ligne legacy.'
        );
    }

    public function testUneLigneExonereeConserveUnTauxDeZero(): void
    {
        // Produit du groupe A (taux 0) avec un snapshot EXPLICITE (étiquette
        // « A ») : la ligne est réellement exonérée → le taux doit rester 0.
        $this->creerVentePos('produitA', 0.0, 'A');

        $groupeA = $this->groupe('A');

        $this->assertNotNull($groupeA, 'Le groupe A doit être présent dans le rapport.');
        $this->assertEqualsWithDelta(
            0.0, (float)$groupeA['total_tax'], 0.01,
            'Une ligne exonérée ne doit pas générer de taxe.'
        );
    }

    public function testLeTtcDuGroupeCorrespondAuHtPlusLaTaxe(): void
    {
        $this->creerVentePos('produitB', 16.0, 'B');

        $groupe = $this->groupeB();

        $this->assertNotNull($groupe);
        $this->assertEqualsWithDelta(
            (float)$groupe['total_ht'] + (float)$groupe['total_tax'],
            (float)$groupe['total_ttc'],
            0.01,
            'Le TTC du groupe doit être HT + taxe.'
        );
    }

    public function testUneLigneLegacyAvecProduitExonereResteATauxZero(): void
    {
        // Produit du groupe A (taux 0) SANS snapshot : le repli sur le produit
        // donne 0 → la ligne reste sans taxe (et non un taux erroné).
        $this->creerVentePos('produitA', null);

        $groupeA = $this->groupe('A');

        $this->assertNotNull($groupeA, 'Le groupe A doit être présent dans le rapport.');
        $this->assertSame(0.0, (float)$groupeA['taux']);
        $this->assertEqualsWithDelta(
            0.0, (float)$groupeA['total_tax'], 0.01,
            'Un produit exonéré ne doit jamais produire de taxe.'
        );
    }

    /** Récupère la ligne du groupe B (16 %) du rapport sur la période en cours. */
    private function groupeB(): ?array
    {
        return $this->groupe('B', 16.0);
    }

    /** Récupère la ligne d'un groupe du rapport sur la période en cours. */
    private function groupe(string $etiquette, ?float $taux = null): ?array
    {
        $groupes = (new Report())->getSalesByTaxGroup(null, ...$this->periode());
        foreach ($groupes as $g) {
            if ((string)$g['etiquette'] !== $etiquette) {
                continue;
            }
            if ($taux !== null && (float)$g['taux'] !== $taux) {
                continue;
            }
            return $g;
        }
        return null;
    }
}
