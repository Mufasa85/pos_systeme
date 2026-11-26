<?php

namespace Tests\Integration;

use App\Controllers\PageController;
use App\Core\Database;
use PHPUnit\Framework\TestCase;

/**
 * Régression : écriture des rôles/droits depuis /hotel/roles.
 *
 * ⚠️ BUG FIX 2026-11-14 : un `super_admin` n'a AUCUNE boutique en session
 * (`$_SESSION['shop_id']` est NULL au login) alors que la page /hotel/roles
 * lui permet de choisir la boutique affichée via `?shop_id=`.
 * Les endpoints d'écriture se basaient sur `$_SESSION['shop_id']` seul →
 * toute modification échouait avec « Boutique non déterminée ».
 *
 * Ces tests vérifient que :
 *   - le super_admin peut écrire dans la boutique qu'il a affichée ;
 *   - l'admin reste cantonné à SA boutique (anti-évasion multi-tenant) ;
 *   - le rôle hôtel suit la même règle que les droits d'accès aux modules.
 */
class HotelRolesScopeTest extends TestCase
{
    private static $pdo;
    private array $sessionBackup = [];
    private array $postBackup = [];
    private array $getBackup = [];
    private array $serverBackup = [];
    private array $shopIds = [];
    private array $userIds = [];

    public static function setUpBeforeClass(): void
    {
        self::$pdo = Database::getInstance()->getConnection();
    }

    protected function setUp(): void
    {
        $this->sessionBackup = $_SESSION ?? [];
        $this->postBackup = $_POST;
        $this->getBackup = $_GET;
        $this->serverBackup = $_SERVER;

        // Les endpoints écritures exigent une requête POST.
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $this->shopIds['a'] = $this->creerBoutique('SCOPE_A');
        $this->shopIds['b'] = $this->creerBoutique('SCOPE_B');
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->sessionBackup;
        $_POST = $this->postBackup;
        $_GET = $this->getBackup;
        $_SERVER = $this->serverBackup;
        http_response_code(200);

        foreach (array_reverse($this->userIds) as $userId) {
            self::$pdo->prepare('DELETE FROM utilisateurs WHERE id = ?')->execute([$userId]);
        }
        $this->userIds = [];

        foreach (array_reverse($this->shopIds) as $shopId) {
            self::$pdo->prepare('DELETE FROM shops WHERE id = ?')->execute([$shopId]);
        }
        $this->shopIds = [];
    }

    private function creerBoutique(string $suffix): int
    {
        $code = 'SCOPE_' . $suffix . '_' . strtoupper(bin2hex(random_bytes(3)));
        $stmt = self::$pdo->prepare(
            'INSERT INTO shops (nom, code, actif) VALUES (?, ?, 1)'
        );
        $stmt->execute(['Boutique Scope ' . $suffix, $code]);
        return (int)self::$pdo->lastInsertId();
    }

    private function creerUtilisateur(int $shopId, string $role = 'vendeur'): int
    {
        $username = 'scope_' . strtolower(bin2hex(random_bytes(4)));
        $stmt = self::$pdo->prepare(
            "INSERT INTO utilisateurs (nom_utilisateur, mot_de_passe, nom_complet, role, hotel_role, shop_id, actif, acces_caisse)
             VALUES (?, 'x', 'User Scope', ?, NULL, ?, 1, 1)"
        );
        $stmt->execute([$username, $role, $shopId]);
        $id = (int)self::$pdo->lastInsertId();
        $this->userIds[] = $id;
        return $id;
    }

    /**
     * @return array{payload: array, status: int}
     */
    private function appeler(PageController $controller, string $method): array
    {
        ob_start();
        try {
            $controller->$method();
        } finally {
            $raw = (string)ob_get_clean();
            $status = http_response_code();
            http_response_code(200);
        }

        $payload = json_decode($raw, true);
        $this->assertIsArray($payload, 'Le contrôleur doit répondre en JSON.');

        // En CLI, `http_response_code()` renvoie `false` quand aucun code n'a été
        // fixé explicitement (le contrôleur ne pose pas de 200 sur le chemin
        // succès : PHP renvoie 200 par défaut en contexte web).
        if ($status === false) {
            $status = 200;
        }

        return ['payload' => $payload, 'status' => (int)$status];
    }

    /**
     * Le super_admin n'a pas de boutique en session : c'est exactement le
     * contexte qui provoquait « Boutique non déterminée ».
     */
    public function testLeSuperAdminEcritDansLaBoutiqueAffichee(): void
    {
        $userId = $this->creerUtilisateur($this->shopIds['b']);

        $_SESSION = [
            'user_id' => 999999,
            'role' => 'super_admin',
            'shop_id' => null,
        ];
        $_POST = [
            'user_id' => (string)$userId,
            'module' => 'caisse',
            'acces' => '0',
            'shop_id' => (string)$this->shopIds['b'],
        ];

        $result = $this->appeler(new PageController(), 'apiHotelUpdateCaisseAccess');

        $this->assertSame(200, $result['status']);
        $this->assertTrue(
            $result['payload']['success'],
            'Le super_admin doit pouvoir écrire dans la boutique affichée : '
            . json_encode($result['payload'])
        );
        $this->assertStringNotContainsString(
            'Boutique non déterminée',
            json_encode($result['payload'])
        );

        $row = self::$pdo->prepare('SELECT acces_caisse FROM utilisateurs WHERE id = ?');
        $row->execute([$userId]);
        $this->assertSame(
            0,
            (int)$row->fetch()['acces_caisse'],
            'Le droit doit réellement avoir été enregistré.'
        );
    }

    /**
     * Le rôle hôtel suit exactement la même règle que les droits de modules.
     */
    public function testLeSuperAdminEcritLeRoleHotelDansLaBoutiqueAffichee(): void
    {
        $userId = $this->creerUtilisateur($this->shopIds['b']);

        $_SESSION = [
            'user_id' => 999999,
            'role' => 'super_admin',
            'shop_id' => null,
        ];
        $_POST = [
            'user_id' => (string)$userId,
            'hotel_role' => 'receptionniste',
            'shop_id' => (string)$this->shopIds['b'],
        ];

        $result = $this->appeler(new PageController(), 'apiHotelUpdateRole');

        $this->assertSame(200, $result['status']);
        $this->assertTrue(
            $result['payload']['success'],
            'Le super_admin doit pouvoir écrire le rôle hôtel : '
            . json_encode($result['payload'])
        );

        $row = self::$pdo->prepare('SELECT hotel_role FROM utilisateurs WHERE id = ?');
        $row->execute([$userId]);
        $this->assertSame('receptionniste', $row->fetch()['hotel_role']);
    }

    /**
     * Anti-évasion multi-tenant : un `admin` ne peut PAS écrire dans une
     * autre boutique en forçant `shop_id` — le périmètre reste SA boutique.
     */
    public function testUnAdminNePeutPasEcrireDansUneAutreBoutique(): void
    {
        $userId = $this->creerUtilisateur($this->shopIds['b']);

        $_SESSION = [
            'user_id' => 888888,
            'role' => 'admin',
            'shop_id' => $this->shopIds['a'],
        ];
        $_POST = [
            'user_id' => (string)$userId,
            'module' => 'caisse',
            'acces' => '0',
            // Tentative d'écriture hors périmètre : doit être ignorée.
            'shop_id' => (string)$this->shopIds['b'],
        ];

        $result = $this->appeler(new PageController(), 'apiHotelUpdateCaisseAccess');

        $this->assertFalse(
            $result['payload']['success'],
            'Un admin ne doit pas modifier un utilisateur hors de sa boutique.'
        );

        $row = self::$pdo->prepare('SELECT acces_caisse FROM utilisateurs WHERE id = ?');
        $row->execute([$userId]);
        $this->assertSame(
            1,
            (int)$row->fetch()['acces_caisse'],
            'Le droit doit rester inchangé après une tentative hors périmètre.'
        );
    }

    /**
     * Un `admin` sans boutique en session ne peut rien écrire : le message
     * « Boutique non déterminée » reste le comportement attendu (fail-closed).
     */
    public function testUnAdminSansBoutiqueEnSessionEstRefuse(): void
    {
        $userId = $this->creerUtilisateur($this->shopIds['a']);

        $_SESSION = [
            'user_id' => 777777,
            'role' => 'admin',
            'shop_id' => null,
        ];
        $_POST = [
            'user_id' => (string)$userId,
            'module' => 'caisse',
            'acces' => '0',
        ];

        $result = $this->appeler(new PageController(), 'apiHotelUpdateCaisseAccess');

        $this->assertSame(400, $result['status']);
        $this->assertFalse($result['payload']['success']);
        $this->assertSame('Boutique non déterminée', $result['payload']['message']);
    }
}
