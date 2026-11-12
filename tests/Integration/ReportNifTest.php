<?php

namespace Tests\Integration;

use App\Core\Database;
use App\Services\ReportService;
use PHPUnit\Framework\TestCase;

/**
 * NON-RÉGRESSION : le NIF affiché dans l'en-tête du rapport /rapport.
 *
 * EXIGENCE : le rapport doit afficher EXACTEMENT le même NIF que l'en-tête
 * de la facture finale.
 *
 * Le NIF de la facture finale vient de `STORE_INFO.isf`, alimenté par
 * `GET /api/settings` → `store_isf` (cf. `SettingsController::index()`) :
 *
 *   1. `shops.isf` de la boutique de la session ;
 *   2. pour un SUPER_ADMIN, `company_info.isf` REMPLACE la valeur ci-dessus
 *      (le fallback entreprise écrase la boutique dans le contrôleur) ;
 *   3. sinon `settings.store_isf` ;
 *   4. sinon `company_info.isf`.
 *
 * Ce test vérifie l'ÉQUIVALENCE DIRECTE entre les deux résolutions (celle
 * du rapport et celle de la facture), et non une règle codée en dur : c'est
 * la garantie que le rapport ne pourra pas diverger de la facture.
 */
class ReportNifTest extends TestCase
{
    private static $pdo;
    private $sessionBackup = [];

    public static function setUpBeforeClass(): void
    {
        self::$pdo = Database::getInstance()->getConnection();
    }

    protected function setUp(): void
    {
        $this->sessionBackup = $_SESSION ?? [];
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->sessionBackup;
    }

    /**
     * Reproduit la résolution de `GET /api/settings` (source du NIF affiché
     * dans l'en-tête de la facture finale).
     */
    private function nifFactureFinale(?int $shopId, string $role): string
    {
        // 1. Les réglages de la table `settings`.
        $settings = [];
        $sql = $shopId
            ? 'SELECT setting_key, value FROM settings WHERE shop_id = ? OR shop_id IS NULL'
            : 'SELECT setting_key, value FROM settings';
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($shopId ? [$shopId] : []);
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $settings[$row['setting_key']] = $row['value'];
        }

        // 2. Les informations de la boutique de la session.
        if ($shopId) {
            $stmt = self::$pdo->prepare('SELECT * FROM shops WHERE id = ?');
            $stmt->execute([$shopId]);
            $shop = $stmt->fetch();
            if ($shop) {
                $settings = array_merge($settings, ['store_isf' => $shop['isf'] ?? '']);
            }
        }

        // 3. Pour le super_admin, l'entreprise REMPLACE la boutique.
        if ($role === 'super_admin') {
            $stmt = self::$pdo->prepare('SELECT * FROM company_info WHERE id = 1');
            $stmt->execute();
            $company = $stmt->fetch();
            if ($company && !empty($company['isf'])) {
                $settings['store_isf'] = $company['isf'];
            }
        }

        return (string)($settings['store_isf'] ?? '');
    }

    /**
     * Vendeur de test AUTONOME : on crée une boutique avec un ISF connu et
     * un utilisateur associé.
     *
     * ⚠️ On ne dépend pas de l'état de la base : d'autres tests (ex.
     * `UserDeletionTest`) suppriment des utilisateurs et des boutiques, et la
     * base de test peut ne plus contenir AUCUN vendeur rattaché à une
     * boutique pourvue d'un ISF.
     *
     * @return array{0: int, 1: int, 2: string} [userId, shopId, isf]
     */
    private function creerVendeurAvecBoutique(): array
    {
        $isf = '__NIF_TEST_' . bin2hex(random_bytes(3));

        // `shops.code` est NOT NULL + UNIQUE → on fournit un code unique.
        $stmt = self::$pdo->prepare(
            "INSERT INTO shops (nom, code, isf, actif) VALUES (?, ?, ?, 1)"
        );
        $stmt->execute([
            '__rapport_nif__',
            '__RNIF' . bin2hex(random_bytes(3)),
            $isf,
        ]);
        $shopId = (int)self::$pdo->lastInsertId();

        $stmt = self::$pdo->prepare(
            "INSERT INTO utilisateurs (nom_utilisateur, nom_complet, mot_de_passe, role, actif, shop_id)
             VALUES (?, ?, 'x', 'vendeur', 1, ?)"
        );
        $stmt->execute([
            '__rtg_nif_' . bin2hex(random_bytes(3)),
            'Vendeur Rapport NIF',
            $shopId,
        ]);
        $userId = (int)self::$pdo->lastInsertId();

        return [$userId, $shopId, $isf];
    }

    /** Supprime les données créées par creerVendeurAvecBoutique(). */
    private function purgerVendeur(int $userId, int $shopId): void
    {
        self::$pdo->prepare('DELETE FROM utilisateurs WHERE id = ?')->execute([$userId]);
        self::$pdo->prepare('DELETE FROM shops WHERE id = ?')->execute([$shopId]);
    }

    public function testLeNifDuRapportEstCeluiDeLUtilisateurConnecte(): void
    {
        // Vendeur de test AUTONOME : son NIF doit être celui de SA boutique.
        [$userId, $shopId, $isf] = $this->creerVendeurAvecBoutique();

        try {
            $_SESSION = [
                'user_id'         => $userId,
                'nom_utilisateur' => 'vendeur_test',
                'nom_complet'     => 'Vendeur Test',
                'role'            => 'vendeur',
                'shop_id'         => $shopId,
                'agent_code'      => 'AGTEST',
            ];

            $data = (new ReportService())->generate(
                $shopId,
                'Z',
                date('Y-m-d 00:00:00'),
                date('Y-m-d 23:59:59')
            );

            $this->assertSame(
                $isf,
                $data['header']['nif'],
                'Le NIF du rapport doit être celui de l\'utilisateur connecté.'
            );
            $this->assertSame(
                $isf,
                $data['header']['isf'],
                'Le champ ISF doit porter le même NIF (alias dans ce projet).'
            );

            // ── ÉQUIVALENCE avec l'en-tête de la facture finale ──────────
            $this->assertSame(
                $this->nifFactureFinale($shopId, 'vendeur'),
                $data['header']['nif'],
                'Le rapport doit afficher EXACTEMENT le NIF de la facture finale.'
            );
        } finally {
            $this->purgerVendeur($userId, $shopId);
        }
    }

    public function testLeSuperAdminRecupereLeNifDeLEentreprise(): void
    {
        // Entreprise de test AUTONOME : la table `company_info` peut être
        // vide dans la base de test (elle n'est pas peuplée par les seeds).
        //
        // ⚠️ `CompanyInfo::get()` lit `WHERE id = 1` : on crée donc la ligne
        // avec cet id explicite (et on la supprime à la fin).
        $companyIsf = '__COMPANY_NIF_' . bin2hex(random_bytes(3));
        $stmt = self::$pdo->query('SELECT isf FROM company_info WHERE id = 1');
        $existe = $stmt->fetchColumn();
        $companyDejaLa = ($existe !== false);
        $isfDorigine = $companyDejaLa ? $existe : null;

        if (!$companyDejaLa) {
            $stmt = self::$pdo->prepare(
                "INSERT INTO company_info (id, name, isf) VALUES (1, '__rapport_nif__', ?)"
            );
            $stmt->execute([$companyIsf]);
        } else {
            $stmt = self::$pdo->prepare('UPDATE company_info SET isf = ? WHERE id = 1');
            $stmt->execute([$companyIsf]);
        }

        // Super_admin AUTONOME : la base de test peut ne plus contenir aucun
        // super_admin (d'autres tests suppriment les utilisateurs).
        $stmt = self::$pdo->prepare(
            "INSERT INTO utilisateurs (nom_utilisateur, nom_complet, mot_de_passe, role, actif)
             VALUES (?, 'Admin Rapport NIF', 'x', 'super_admin', 1)"
        );
        $stmt->execute(['__rnif_admin_' . bin2hex(random_bytes(3))]);
        $userId = (int)self::$pdo->lastInsertId();

        // On lui affecte une boutique : c'est le cas le plus discriminant,
        // car le NIF de l'ENTREPRISE doit primer sur celui de la boutique
        // (comme dans `/api/settings`).
        [$shopUserId, $shopId, $isfBoutique] = $this->creerVendeurAvecBoutique();
        self::$pdo->prepare('UPDATE utilisateurs SET shop_id = ? WHERE id = ?')
            ->execute([$shopId, $userId]);

        try {
            $_SESSION = [
                'user_id'         => $userId,
                'nom_utilisateur' => 'admin_test',
                'nom_complet'     => 'Admin Test',
                'role'            => 'super_admin',
                'shop_id'         => $shopId,
                'agent_code'      => 'AGTEST',
            ];

            $data = (new ReportService())->generate(
                $shopId,
                'Z',
                date('Y-m-d 00:00:00'),
                date('Y-m-d 23:59:59')
            );

            $this->assertSame(
                $companyIsf,
                $data['header']['nif'],
                'Un super_admin doit obtenir le NIF de l\'entreprise.'
            );

            // ── ÉQUIVALENCE avec l'en-tête de la facture finale ──────────
            $this->assertSame(
                $this->nifFactureFinale($shopId, 'super_admin'),
                $data['header']['nif'],
                'Le rapport doit afficher EXACTEMENT le NIF de la facture finale.'
            );
            $this->assertNotSame(
                $isfBoutique,
                $data['header']['nif'],
                'Le NIF de l\'entreprise doit primer sur celui de la boutique.'
            );
        } finally {
            self::$pdo->prepare('DELETE FROM utilisateurs WHERE id = ?')->execute([$userId]);
            $this->purgerVendeur($shopUserId, $shopId);
            if ($companyDejaLa) {
                // On restaure l'entreprise d'origine (on n'a fait qu'un UPDATE).
                $stmt = self::$pdo->prepare('UPDATE company_info SET isf = ? WHERE id = 1');
                $stmt->execute([$isfDorigine]);
            } else {
                self::$pdo->prepare('DELETE FROM company_info WHERE id = 1')->execute();
            }
        }
    }

    public function testLeARapportUtiliseLeMemeNif(): void
    {
        [$userId, $shopId, $isf] = $this->creerVendeurAvecBoutique();

        try {
            $_SESSION = [
                'user_id'         => $userId,
                'nom_utilisateur' => 'vendeur_test',
                'nom_complet'     => 'Vendeur Test',
                'role'            => 'vendeur',
                'shop_id'         => $shopId,
                'agent_code'      => 'AGTEST',
            ];

            $data = (new ReportService())->generateAReport($shopId);

            $this->assertSame(
                $isf,
                $data['header']['nif'],
                'L\'A-rapport doit porter le NIF de l\'utilisateur connecté.'
            );
            $this->assertSame($isf, $data['header']['isf']);
        } finally {
            $this->purgerVendeur($userId, $shopId);
        }
    }
}
