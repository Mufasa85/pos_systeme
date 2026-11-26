<?php

namespace Tests\Unit;

use App\Core\Database;
use App\Models\User;
use PHPUnit\Framework\TestCase;

/**
 * Contrôle d'accès à la page /caisse (POS) par utilisateur.
 *
 * La colonne `utilisateurs.acces_caisse` est posée par la migration
 * migrations/2026_11_14_user_caisse_access.sql (défaut 1 = accès autorisé).
 *
 * Ces tests vérifient le comportement du modèle :
 *   - utilisateur inexistant → accès refusé (fail-closed) ;
 *   - `acces_caisse` à 1 → accès accordé ;
 *   - `acces_caisse` à 0 → accès refusé ;
 *   - super_admin / admin → accès TOUJOURS accordé (jamais verrouillé) ;
 *   - le retrait est appliqué immédiatement.
 */
class CaisseAccessTest extends TestCase
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
        // Boutique de test
        $code = 'CAISSE_' . strtoupper(bin2hex(random_bytes(3)));
        self::$pdo->prepare(
            'INSERT INTO shops (nom, code, actif) VALUES (?, ?, 1)'
        )->execute(['Boutique Test Caisse', $code]);
        $this->shopId = (int)self::$pdo->lastInsertId();

        // Vendeur de test (rôle applicatif standard)
        $username = 'caisse_' . strtoupper(bin2hex(random_bytes(4)));
        self::$pdo->prepare(
            "INSERT INTO utilisateurs (nom_utilisateur, mot_de_passe, nom_complet, role, shop_id, actif, acces_caisse)
             VALUES (?, 'x', 'Test Caisse', 'vendeur', ?, 1, 1)"
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

    private function setCaisseAccess(bool $enabled): void
    {
        self::$pdo->prepare('UPDATE utilisateurs SET acces_caisse = ? WHERE id = ?')
            ->execute([$enabled ? 1 : 0, $this->userId]);
    }

    public function testAccesRefusePourUnUtilisateurInexistant(): void
    {
        $user = new User();

        $this->assertFalse(
            $user->canAccessCaisse(999999999),
            'Un utilisateur inexistant ne doit jamais accéder à la caisse.'
        );
    }

    public function testAccesRefusePourUnIdentifiantNul(): void
    {
        $user = new User();

        $this->assertFalse($user->canAccessCaisse(0));
    }

    public function testAccesAccordeParDefaut(): void
    {
        $user = new User();

        $this->assertTrue(
            $user->canAccessCaisse($this->userId),
            'Un utilisateur créé sans option doit conserver son accès à la caisse.'
        );
    }

    public function testAccesRefuseQuandLeDroitEstRetire(): void
    {
        $this->setCaisseAccess(false);
        $user = new User();

        $this->assertFalse(
            $user->canAccessCaisse($this->userId),
            'Un utilisateur retiré du POS ne doit plus accéder à la caisse.'
        );
    }

    public function testLeRetraitEstAppliqueImmediatement(): void
    {
        $user = new User();

        $this->assertTrue($user->canAccessCaisse($this->userId));

        $this->setCaisseAccess(false);

        $this->assertFalse(
            $user->canAccessCaisse($this->userId),
            'Le retrait de l\'accès doit être pris en compte immédiatement.'
        );

        $this->setCaisseAccess(true);

        $this->assertTrue(
            $user->canAccessCaisse($this->userId),
            'La réactivation doit être prise en compte immédiatement.'
        );
    }

    public function testLeRoleAdminConserveUnAccesPermanent(): void
    {
        // On force le rôle admin sur notre utilisateur de test.
        self::$pdo->prepare('UPDATE utilisateurs SET role = ? WHERE id = ?')
            ->execute(['admin', $this->userId]);
        $this->setCaisseAccess(false);

        $user = new User();

        $this->assertTrue(
            $user->canAccessCaisse($this->userId),
            'Un admin ne doit jamais être verrouillé hors du POS.'
        );
    }

    public function testSetCaisseAccessRefuseUnUtilisateurHorsBoutique(): void
    {
        $user = new User();

        $ok = $user->setCaisseAccess($this->userId, false, $this->shopId + 9999);

        $this->assertFalse(
            $ok,
            'Un manager ne doit pas pouvoir modifier un utilisateur d\'une autre boutique.'
        );

        // La valeur en base est inchangée.
        $row = self::$pdo->prepare('SELECT acces_caisse FROM utilisateurs WHERE id = ?');
        $row->execute([$this->userId]);
        $this->assertSame(1, (int)$row->fetch()['acces_caisse']);
    }

    public function testSetCaisseAccessAppliqueLeChangementDansLaBoutique(): void
    {
        $user = new User();

        $ok = $user->setCaisseAccess($this->userId, false, $this->shopId);

        $this->assertTrue($ok);
        $this->assertFalse($user->canAccessCaisse($this->userId));

        $ok = $user->setCaisseAccess($this->userId, true, $this->shopId);

        $this->assertTrue($ok);
        $this->assertTrue($user->canAccessCaisse($this->userId));
    }

    public function testSetCaisseAccessRefuseUnRoleDePilotage(): void
    {
        self::$pdo->prepare('UPDATE utilisateurs SET role = ? WHERE id = ?')
            ->execute(['admin', $this->userId]);

        $user = new User();

        $ok = $user->setCaisseAccess($this->userId, false, $this->shopId);

        $this->assertFalse(
            $ok,
            'L\'accès permanent des rôles de pilotage ne doit pas être modifiable.'
        );
    }

    // ── Modules additionnels (migration 2026_11_15) ────────────────

    /**
     * Tous les modules doivent exister dans la table des droits et être
     * par défaut AUTORISÉS (fail-open) : aucun compte existant ne doit
     * être bloqué par la migration.
     */
    public function testTousLesModulesSontAutorisesParDefaut(): void
    {
        $user = new User();

        foreach (array_keys(User::MODULES_ACCES) as $module) {
            $this->assertTrue(
                $user->canAccessModule($module, $this->userId),
                'Le module « ' . $module . ' » doit être autorisé par défaut.'
            );
        }
    }

    public function testChaqueModulePeutEtreBloqueIndependamment(): void
    {
        $user = new User();

        foreach (array_keys(User::MODULES_ACCES) as $module) {
            // On repart d'un état « autorisé » pour chaque module.
            self::$pdo->prepare(
                'UPDATE utilisateurs SET acces_caisse = 1, acces_produits = 1, acces_historique = 1,
                        acces_pressing = 1, acces_analytics = 1, acces_paie = 1 WHERE id = ?'
            )->execute([$this->userId]);

            $ok = $user->setModuleAccess($module, $this->userId, false, $this->shopId);
            $this->assertTrue($ok, 'Le module « ' . $module . ' » doit être modifiable.');

            $this->assertFalse(
                $user->canAccessModule($module, $this->userId),
                'Le module « ' . $module . ' » doit être refusé après retrait.'
            );

            // Les AUTRES modules ne doivent pas être affectés.
            foreach (array_keys(User::MODULES_ACCES) as $other) {
                if ($other === $module) {
                    continue;
                }
                $this->assertTrue(
                    $user->canAccessModule($other, $this->userId),
                    'Le module « ' . $other . ' » ne doit pas être affecté par le retrait de « ' . $module . ' ».'
                );
            }
        }
    }

    public function testUnModuleInconnuEstRefuse(): void
    {
        $user = new User();

        $this->assertFalse(
            $user->canAccessModule('module_inexistant', $this->userId),
            'Un module inconnu doit être refusé (fail-closed).'
        );
        $this->assertFalse(
            $user->setModuleAccess('module_inexistant', $this->userId, true, $this->shopId),
            'Un module inconnu ne doit pas être modifiable.'
        );
    }

    public function testLeRoleDePilotageConserveTousLesModules(): void
    {
        self::$pdo->prepare('UPDATE utilisateurs SET role = ? WHERE id = ?')
            ->execute(['admin', $this->userId]);
        self::$pdo->prepare(
            'UPDATE utilisateurs SET acces_caisse = 0, acces_produits = 0, acces_historique = 0,
                    acces_pressing = 0, acces_analytics = 0, acces_paie = 0 WHERE id = ?'
        )->execute([$this->userId]);

        $user = new User();

        foreach (array_keys(User::MODULES_ACCES) as $module) {
            $this->assertTrue(
                $user->canAccessModule($module, $this->userId),
                'Un admin ne doit jamais être verrouillé hors du module « ' . $module . ' ».'
            );
        }
    }

    public function testSetModuleAccessRefuseUnUtilisateurHorsBoutique(): void
    {
        $user = new User();

        $ok = $user->setModuleAccess('produits', $this->userId, false, $this->shopId + 9999);

        $this->assertFalse(
            $ok,
            'Un manager ne doit pas pouvoir modifier un utilisateur d\'une autre boutique.'
        );
        $this->assertTrue(
            $user->canAccessModule('produits', $this->userId),
            'Le droit doit rester inchangé après une tentative hors scope.'
        );
    }
}
