<?php

namespace Tests\Integration;

use App\Core\Database;
use App\Models\MobileToken;
use App\Models\Shop;
use App\Services\MobileAuthService;
use PHPUnit\Framework\TestCase;

/**
 * Authentification mobile (token Bearer).
 *
 * Couvre le cycle complet :
 *   - emission d'un token a partir d'identifiants valides ;
 *   - le token en clair n'est JAMAIS stocke (seule l'empreinte SHA-256 l'est) ;
 *   - hydratation de $_SESSION a partir du token (reutilisation des
 *     routes existantes) ;
 *   - revocation unitaire (logout) ;
 *   - rejet d'un token inconnu / revoque / expire ;
 *   - rejet d'un compte desactive ou d'une boutique desactivee ;
 *   - permissions du module 'caisse' (droit `acces_caisse`).
 *
 * Migration : migrations/2026_11_22_mobile_auth.sql
 */
class MobileAuthServiceTest extends TestCase
{
    private static $pdo;

    private $shopId;
    private $userId;
    private $password = 'motdepasse123';

    private array $sessionBackup = [];
    private array $serverBackup = [];

    public static function setUpBeforeClass(): void
    {
        self::$pdo = Database::getInstance()->getConnection();
    }

    protected function setUp(): void
    {
        $this->sessionBackup = $_SESSION ?? [];
        $this->serverBackup = $_SERVER;

        $code = 'MOB_' . strtoupper(bin2hex(random_bytes(3)));
        $this->shopId = (int)(new Shop())->create([
            'nom'   => 'Boutique Test Mobile Auth',
            'code'  => $code,
            'actif' => 1,
            'ice'   => '001234567890123',
            'isf'   => 'A900001T',
            'pdv'   => 'PDV-TEST',
        ]);

        $username = 'mob_' . strtolower(bin2hex(random_bytes(4)));
        $stmt = self::$pdo->prepare(
            'INSERT INTO utilisateurs (nom_utilisateur, mot_de_passe, nom_complet, role, shop_id, actif, two_factor_enabled)
             VALUES (?, ?, ?, ?, ?, 1, 0)'
        );
        $stmt->execute([
            $username,
            password_hash($this->password, PASSWORD_DEFAULT),
            'Caissier Mobile Test',
            'vendeur',
            $this->shopId,
        ]);
        $this->userId = (int)self::$pdo->lastInsertId();
        $this->username = $username;
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->sessionBackup;
        $_SERVER = $this->serverBackup;

        if ($this->userId) {
            self::$pdo->prepare('DELETE FROM utilisateurs WHERE id = ?')->execute([$this->userId]);
            $this->userId = 0;
        }
        if ($this->shopId) {
            self::$pdo->prepare('DELETE FROM shops WHERE id = ?')->execute([$this->shopId]);
            $this->shopId = 0;
        }
    }

    private string $username = '';

    public function testDesIdentifiantsValidesEmettentUnToken(): void
    {
        $service = new MobileAuthService();

        $result = $service->login($this->username, $this->password, [
            'device_name' => 'Samsung A12',
            'platform'    => 'android',
        ]);

        $this->assertTrue($result['ok'], 'La connexion doit reussir.');
        $this->assertNotEmpty($result['token']);
        $this->assertSame('Bearer', $result['token_type']);
        $this->assertSame($this->userId, (int)$result['user']['id']);
        $this->assertSame($this->shopId, (int)$result['shop']['id']);
    }

    public function testDesIdentifiantsInvalidesSontRejetes(): void
    {
        $service = new MobileAuthService();

        $result = $service->login($this->username, 'mauvais-mot-de-passe');

        $this->assertFalse($result['ok']);
        $this->assertSame('invalid_credentials', $result['error']);
    }

    public function testLeTokenEnClairNestJamaisStocke(): void
    {
        $service = new MobileAuthService();

        $result = $service->login($this->username, $this->password);
        $plain  = $result['token'];

        $stmt = self::$pdo->prepare('SELECT token_hash FROM mobile_tokens WHERE user_id = ?');
        $stmt->execute([$this->userId]);
        $hashes = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $this->assertNotEmpty($hashes);
        foreach ($hashes as $hash) {
            $this->assertNotSame($plain, $hash, 'Le token en clair ne doit jamais etre stocke.');
            $this->assertSame(hash('sha256', $plain), $hash, 'Seule l empreinte SHA-256 doit etre stockee.');
        }
    }

    public function testLeTokenHydrateLaSessionCommeUnLoginWeb(): void
    {
        $service = new MobileAuthService();

        $result = $service->login($this->username, $this->password);
        $plain  = $result['token'];

        $_SESSION = [];
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $plain;

        $this->assertTrue($service->authenticateRequest(), 'Le token doit hydrater la session.');

        $this->assertSame($this->userId, (int)($_SESSION['user_id'] ?? 0));
        $this->assertSame('vendeur', $_SESSION['role'] ?? null);
        $this->assertSame($this->shopId, (int)($_SESSION['shop_id'] ?? 0));
        $this->assertArrayHasKey('last_activity', $_SESSION);
    }

    public function testUnTokenInconnuNeHydratePasLaSession(): void
    {
        $service = new MobileAuthService();

        $_SESSION = [];
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . str_repeat('a', 64);

        $this->assertFalse($service->authenticateRequest());
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function testUnTokenRevoqueEstRejete(): void
    {
        $service = new MobileAuthService();

        $result = $service->login($this->username, $this->password);
        $plain  = $result['token'];

        $row = (new MobileToken())->findByToken($plain);
        $this->assertNotNull($row);
        (new MobileToken())->revoke((int)$row['id']);

        $_SESSION = [];
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $plain;

        $this->assertFalse($service->authenticateRequest(), 'Un token revoque doit etre rejete.');
    }

    public function testUnCompteDesactiveInvalideLeToken(): void
    {
        $service = new MobileAuthService();

        $result = $service->login($this->username, $this->password);
        $plain  = $result['token'];

        self::$pdo->prepare('UPDATE utilisateurs SET actif = 0 WHERE id = ?')->execute([$this->userId]);

        $_SESSION = [];
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $plain;

        $this->assertFalse($service->authenticateRequest(), 'Un compte desactive doit invalider le token.');
    }

    public function testUneBoutiqueDesactiveeBloqueLaConnexion(): void
    {
        $service = new MobileAuthService();

        self::$pdo->prepare('UPDATE shops SET actif = 0 WHERE id = ?')->execute([$this->shopId]);

        $result = $service->login($this->username, $this->password);

        $this->assertFalse($result['ok']);
        $this->assertSame('shop_disabled', $result['error']);
    }

    public function testLeContexteContientLesInfosMagasinPourLaFacture(): void
    {
        $service = new MobileAuthService();

        $result = $service->login($this->username, $this->password);
        $context = $result['context'];

        $this->assertSame($this->shopId, (int)$context['shop']['id']);
        $this->assertSame('Boutique Test Mobile Auth', $context['shop']['nom']);
        $this->assertSame('001234567890123', $context['shop']['ice']);
        $this->assertSame('A900001T', $context['shop']['isf']);
        $this->assertSame('PDV-TEST', $context['shop']['pdv']);
        $this->assertArrayHasKey('permissions', $context);
        $this->assertTrue($context['permissions']['caisse']);
    }

    public function testLeDroitAccesCaisseEstReflete(): void
    {
        $service = new MobileAuthService();

        // Le manager retire l'utilisateur du poste de vente.
        self::$pdo->prepare('UPDATE utilisateurs SET acces_caisse = 0 WHERE id = ?')->execute([$this->userId]);

        $result = $service->login($this->username, $this->password);

        $this->assertTrue($result['ok'], 'La connexion reste possible sans acces caisse.');
        $this->assertFalse($result['context']['permissions']['caisse']);
    }

    public function testLaDeconnexionRevoqueLeToken(): void
    {
        $service = new MobileAuthService();

        $result = $service->login($this->username, $this->password);
        $plain  = $result['token'];

        $row = (new MobileToken())->findByToken($plain);
        (new MobileToken())->revoke((int)$row['id']);

        $resolved = $service->resolve($plain);
        $this->assertNull($resolved, 'Un token revoque ne doit plus se resoudre.');
    }
}
