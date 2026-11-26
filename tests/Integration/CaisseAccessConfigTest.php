<?php

namespace Tests\Integration;

use App\Core\Database;
use App\Models\User;
use PHPUnit\Framework\TestCase;

/**
 * Cycle de vie du droit d'accès à la page /caisse (POS).
 *
 * Vérifie que la valeur est bien persistée et relue :
 *   - par `User::update()` (chemin générique, utilisé par /utilisateurs) ;
 *   - par `User::setCaisseAccess()` (chemin scopé, utilisé par
 *     /hotel/roles → POST /api/hotel/caisse-access/update) ;
 *   - et exposée par `User::allForShopWithHotelRole()` (affichage manager).
 */
class CaisseAccessConfigTest extends TestCase
{
    private static $pdo;
    private $shopId;
    private $userId;

    public static function setUpBeforeClass(): void
    {
        self::$pdo = Database::getInstance()->getConnection();
    }

    protected function setUp(): void
    {
        $code = 'CFGCAISSE_' . strtoupper(bin2hex(random_bytes(3)));
        self::$pdo->prepare(
            'INSERT INTO shops (nom, code, actif) VALUES (?, ?, 1)'
        )->execute(['Boutique Test Config Caisse', $code]);
        $this->shopId = (int)self::$pdo->lastInsertId();

        $username = 'cfg_caisse_' . strtoupper(bin2hex(random_bytes(4)));
        self::$pdo->prepare(
            "INSERT INTO utilisateurs (nom_utilisateur, mot_de_passe, nom_complet, role, hotel_role, shop_id, actif, acces_caisse)
             VALUES (?, 'x', 'Test Config Caisse', 'vendeur', 'gouvernante', ?, 1, 1)"
        )->execute([$username, $this->shopId]);
        $this->userId = (int)self::$pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        if ($this->userId) {
            self::$pdo->prepare('DELETE FROM utilisateurs WHERE id = ?')->execute([$this->userId]);
            $this->userId = 0;
        }
        if ($this->shopId) {
            self::$pdo->prepare('DELETE FROM shops WHERE id = ?')->execute([$this->shopId]);
            $this->shopId = 0;
        }
    }

    public function testLaValeurParDefautEstLAccesAutorise(): void
    {
        $row = self::$pdo->prepare('SELECT acces_caisse FROM utilisateurs WHERE id = ?');
        $row->execute([$this->userId]);

        $this->assertSame(1, (int)$row->fetch()['acces_caisse']);
    }

    public function testLeChangementEstPersisteParUpdate(): void
    {
        $userModel = new User();

        $userModel->update($this->userId, ['acces_caisse' => 0]);

        $this->assertFalse(
            $userModel->canAccessCaisse($this->userId),
            'Le retrait via update() doit être pris en compte.'
        );

        $userModel->update($this->userId, ['acces_caisse' => 1]);

        $this->assertTrue(
            $userModel->canAccessCaisse($this->userId),
            'La réactivation via update() doit être prise en compte.'
        );
    }

    public function testLeChangementEstPersisteParSetCaisseAccess(): void
    {
        $userModel = new User();

        $ok = $userModel->setCaisseAccess($this->userId, false, $this->shopId);
        $this->assertTrue($ok);

        $row = self::$pdo->prepare('SELECT acces_caisse FROM utilisateurs WHERE id = ?');
        $row->execute([$this->userId]);
        $this->assertSame(0, (int)$row->fetch()['acces_caisse']);
    }

    public function testLeListingExposeLaColonne(): void
    {
        $userModel = new User();
        $userModel->setCaisseAccess($this->userId, false, $this->shopId);

        $rows = $userModel->allForShopWithHotelRole($this->shopId);

        $found = null;
        foreach ($rows as $row) {
            if ((int)$row['id'] === $this->userId) {
                $found = $row;
                break;
            }
        }

        $this->assertNotNull($found, 'L\'utilisateur doit figurer dans le listing de la boutique.');
        $this->assertArrayHasKey('acces_caisse', $found);
        $this->assertSame(0, (int)$found['acces_caisse']);
    }

    public function testUnManagerNePeutPasModifierUnUtilisateurDUneAutreBoutique(): void
    {
        // Autre boutique
        $code = 'AUTRE_' . strtoupper(bin2hex(random_bytes(3)));
        self::$pdo->prepare('INSERT INTO shops (nom, code, actif) VALUES (?, ?, 1)')
            ->execute(['Boutique Tiers', $code]);
        $otherShopId = (int)self::$pdo->lastInsertId();

        try {
            $userModel = new User();
            $ok = $userModel->setCaisseAccess($this->userId, false, $otherShopId);

            $this->assertFalse($ok, 'Le scope multi-tenant doit être appliqué.');
            $this->assertTrue(
                $userModel->canAccessCaisse($this->userId),
                'Le droit doit rester inchangé après une tentative hors scope.'
            );
        } finally {
            self::$pdo->prepare('DELETE FROM shops WHERE id = ?')->execute([$otherShopId]);
        }
    }

    // ── Modules additionnels (migration 2026_11_15) ────────────────

    public function testTousLesModulesSontExposesParLeListing(): void
    {
        $userModel = new User();
        $rows = $userModel->allForShopWithHotelRole($this->shopId);

        $found = null;
        foreach ($rows as $row) {
            if ((int)$row['id'] === $this->userId) {
                $found = $row;
                break;
            }
        }

        $this->assertNotNull($found);
        foreach (array_keys(User::MODULES_ACCES) as $module) {
            $column = User::MODULES_ACCES[$module];
            $this->assertArrayHasKey(
                $column,
                $found,
                'Le listing doit exposer la colonne « ' . $column . ' ».'
            );
        }
    }

    public function testLesModulesSontModifiablesIndependamment(): void
    {
        $userModel = new User();

        foreach (array_keys(User::MODULES_ACCES) as $module) {
            $ok = $userModel->setModuleAccess($module, $this->userId, false, $this->shopId);
            $this->assertTrue($ok, 'Le module « ' . $module . ' » doit être modifiable.');

            $column = User::MODULES_ACCES[$module];
            $row = self::$pdo->prepare("SELECT {$column} AS v FROM utilisateurs WHERE id = ?");
            $row->execute([$this->userId]);
            $this->assertSame(
                0,
                (int)$row->fetch()['v'],
                'La colonne « ' . $column . ' » doit être mise à jour.'
            );

            // Remise en état pour le module suivant.
            $userModel->setModuleAccess($module, $this->userId, true, $this->shopId);
        }
    }

    public function testLesDroitsSontPersistesParUpdatePourChaqueModule(): void
    {
        $userModel = new User();

        foreach (array_keys(User::MODULES_ACCES) as $module) {
            $userModel->update($this->userId, ['acces_' . $module => 0]);

            $this->assertFalse(
                $userModel->canAccessModule($module, $this->userId),
                'Le retrait via update() doit être pris en compte pour « ' . $module . ' ».'
            );

            $userModel->update($this->userId, ['acces_' . $module => 1]);

            $this->assertTrue(
                $userModel->canAccessModule($module, $this->userId),
                'La réactivation via update() doit être prise en compte pour « ' . $module . ' ».'
            );
        }
    }
}
