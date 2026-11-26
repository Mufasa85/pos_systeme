<?php

namespace Tests\Integration;

use App\Controllers\UserController;
use App\Core\Database;
use App\Models\User;
use PHPUnit\Framework\TestCase;

/**
 * Création d'un utilisateur par le super_admin (POST /api/create/user).
 *
 * ⚠️ RÉGRESSION 2026-11-23 : le super_admin ne pouvait plus créer un
 * utilisateur. Le contrôleur appelait
 * `sanitaze($_POST['shop_id'] ?? null)` alors que la méthode était typée
 * `sanitaze(string $input)`. Un champ OPTIONNEL absent suffisait à lever
 * un TypeError et à afficher une page blanche :
 *
 *   Fatal error: Uncaught TypeError: App\Controllers\Controller::sanitaze():
 *   Argument #1 ($input) must be of type string, null given, called in
 *   .../app/controllers/UserController.php on line 42
 *
 * Ces tests vérifient :
 *   - la création SANS `shop_id` ne lève aucune exception (super_admin) ;
 *   - la boutique de rattachement est TOUJOURS résolue (jamais NULL) ;
 *   - les entrées invalides (rôle hors ENUM, doublon, mot de passe court)
 *     produisent une erreur JSON lisible, pas une page blanche ;
 *   - un `admin` reste cantonné à SA boutique (anti-évasion multi-tenant) ;
 *   - la 2FA exige un numéro de téléphone (le code OTP part par SMS).
 */
class UserCreationTest extends TestCase
{
    private static $pdo;
    private $shopId;
    private $superAdminId;
    private $adminId;
    private array $sessionBackup = [];
    private array $postBackup = [];
    private array $serverBackup = [];

    public static function setUpBeforeClass(): void
    {
        self::$pdo = Database::getInstance()->getConnection();
    }

    protected function setUp(): void
    {
        $this->sessionBackup = $_SESSION ?? [];
        $this->postBackup = $_POST;
        $this->serverBackup = $_SERVER;

        $_SERVER['REQUEST_METHOD'] = 'POST';

        // Boutique de test
        $code = 'UCRE_' . strtoupper(bin2hex(random_bytes(3)));
        self::$pdo->prepare('INSERT INTO shops (nom, code, actif) VALUES (?, ?, 1)')
            ->execute(['Boutique Test Creation', $code]);
        $this->shopId = (int)self::$pdo->lastInsertId();

        $this->superAdminId = $this->creerUtilisateur('super_admin', null);
        $this->adminId = $this->creerUtilisateur('admin', $this->shopId);
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->sessionBackup;
        $_POST = $this->postBackup;
        $_SERVER = $this->serverBackup;
        http_response_code(200);

        // Nettoyage : les utilisateurs créés par les tests + les fixtures.
        self::$pdo->exec(
            "DELETE FROM utilisateurs
             WHERE nom_utilisateur LIKE 'ucrt_%' OR id IN (" .
             (int)$this->superAdminId . ', ' . (int)$this->adminId . ')'
        );

        if ($this->shopId) {
            self::$pdo->prepare('DELETE FROM shops WHERE id = ?')->execute([$this->shopId]);
            $this->shopId = 0;
        }
    }

    private function creerUtilisateur(string $role, ?int $shopId): int
    {
        $username = 'ucrt_' . strtolower(bin2hex(random_bytes(4)));
        self::$pdo->prepare(
            'INSERT INTO utilisateurs (nom_utilisateur, mot_de_passe, nom_complet, role, shop_id, actif)
             VALUES (?, \'x\', ?, ?, ?, 1)'
        )->execute([$username, 'Test Creation', $role, $shopId]);

        return (int)self::$pdo->lastInsertId();
    }

    /**
     * Appelle POST /api/create/user via le contrôleur.
     *
     * @param array<string, mixed> $post
     * @param array<string, mixed> $session
     * @return array{status: int, payload: array<string, mixed>}
     */
    private function appelerCreate(array $post, array $session): array
    {
        $_POST = $post;
        $_SESSION = $session;

        http_response_code(200);
        ob_start();
        try {
            (new UserController())->create();
        } finally {
            $raw = (string)ob_get_clean();
        }

        $status = http_response_code();
        http_response_code(200);

        $payload = json_decode($raw, true);

        return [
            'status'  => $status ?: 200,
            'payload' => is_array($payload) ? $payload : [],
        ];
    }

    /** @return array<string, mixed> */
    private function sessionSuperAdmin(): array
    {
        return [
            'user_id' => $this->superAdminId,
            'role'    => 'super_admin',
            'shop_id' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function sessionAdmin(): array
    {
        return [
            'user_id' => $this->adminId,
            'role'    => 'admin',
            'shop_id' => $this->shopId,
        ];
    }

    /**
     * Formulaire de création type.
     *
     * `shop_id` est présent PAR DÉFAUT : un utilisateur appartient toujours
     * à une boutique. Le cas « sans boutique » se teste explicitement via
     * `formulaireSansBoutique()`.
     *
     * @return array<string, string>
     */
    private function formulaire(array $overrides = []): array
    {
        return array_merge([
            'username' => 'ucrt_' . strtolower(bin2hex(random_bytes(4))),
            'fullname' => 'Utilisateur Cree',
            'password' => 'motdepasse123',
            'role'     => 'vendeur',
            'actif'    => '1',
            'shop_id'  => (string)$this->shopId,
        ], $overrides);
    }

    /**
     * Formulaire SANS boutique : reproduit le cas qui plantait (le champ
     * optionnel `shop_id` n'est pas envoyé).
     *
     * @return array<string, string>
     */
    private function formulaireSansBoutique(array $overrides = []): array
    {
        $formulaire = $this->formulaire($overrides);
        unset($formulaire['shop_id']);

        return $formulaire;
    }

    /**
     * L'UTILISATEUR LE PLUS IMPORTANT : la création SANS `shop_id` (champ
     * optionnel absent) ne doit plus lever de TypeError — elle doit
     * renvoyer une erreur MÉTIER lisible (« boutique non sélectionnée »).
     */
    public function testLaCreationSansShopIdNeLeveAucuneException(): void
    {
        $result = $this->appelerCreate($this->formulaireSansBoutique(), $this->sessionSuperAdmin());

        $this->assertNotSame(
            500,
            $result['status'],
            'La création ne doit pas produire une erreur serveur.'
        );
        $this->assertSame(400, $result['status']);
        $this->assertFalse($result['payload']['success'] ?? true);
        $this->assertSame('shop_required', $result['payload']['code'] ?? '');
        $this->assertNotEmpty($result['payload']['message'] ?? '', 'Un message métier doit être fourni.');
    }

    /**
     * Règle métier : un utilisateur APPARTIENT TOUJOURS à une boutique.
     * Aucun repli automatique n'est fait : sans boutique choisie, la
     * création est refusée (pas de compte orphelin en base).
     */
    public function testAucuneBoutiqueNEstDevineeSilElleNestPasChoisie(): void
    {
        $before = (int)self::$pdo->query(
            "SELECT COUNT(*) FROM utilisateurs WHERE nom_utilisateur LIKE 'ucrt_%'"
        )->fetchColumn();

        $result = $this->appelerCreate($this->formulaireSansBoutique(), $this->sessionSuperAdmin());

        $this->assertSame(400, $result['status']);

        $after = (int)self::$pdo->query(
            "SELECT COUNT(*) FROM utilisateurs WHERE nom_utilisateur LIKE 'ucrt_%'"
        )->fetchColumn();

        $this->assertSame(
            $before,
            $after,
            'Aucun utilisateur ne doit être créé sans boutique de rattachement.'
        );
    }

    /**
     * La boutique de rattachement ne doit JAMAIS être NULL une fois le
     * compte créé.
     */
    public function testLaBoutiqueDeRattachementEstJamaisNulle(): void
    {
        $result = $this->appelerCreate($this->formulaire(), $this->sessionSuperAdmin());

        $this->assertTrue($result['payload']['success'] ?? false, json_encode($result['payload']));

        $row = self::$pdo->prepare('SELECT shop_id FROM utilisateurs WHERE id = ?');
        $row->execute([(int)$result['payload']['id']]);
        $shopId = $row->fetch()['shop_id'] ?? null;

        $this->assertNotNull($shopId, 'Un utilisateur créé doit être rattaché à une boutique.');
        $this->assertGreaterThan(0, (int)$shopId);
    }

    /**
     * Le `shop_id` explicite est respecté (le super_admin choisit la
     * boutique dans le formulaire).
     */
    public function testLeShopIdFourniEstRespecte(): void
    {
        $result = $this->appelerCreate(
            $this->formulaire(['shop_id' => (string)$this->shopId]),
            $this->sessionSuperAdmin()
        );

        $this->assertTrue($result['payload']['success'] ?? false, json_encode($result['payload']));

        $row = self::$pdo->prepare('SELECT shop_id FROM utilisateurs WHERE id = ?');
        $row->execute([(int)$result['payload']['id']]);

        $this->assertSame($this->shopId, (int)$row->fetch()['shop_id']);
    }

    /**
     * Un `admin` reste cantonné à SA boutique : le `shop_id` reçu est
     * IGNORÉ (anti-évasion multi-tenant).
     */
    public function testUnAdminResteCantonneASaBoutique(): void
    {
        // Autre boutique, choisie frauduleusement.
        $otherCode = 'UCRE_AUTRE_' . strtoupper(bin2hex(random_bytes(3)));
        self::$pdo->prepare('INSERT INTO shops (nom, code, actif) VALUES (?, ?, 1)')
            ->execute(['Boutique Tiers', $otherCode]);
        $otherShopId = (int)self::$pdo->lastInsertId();

        try {
            $result = $this->appelerCreate(
                $this->formulaire(['shop_id' => (string)$otherShopId]),
                $this->sessionAdmin()
            );

            $this->assertTrue($result['payload']['success'] ?? false, json_encode($result['payload']));

            $row = self::$pdo->prepare('SELECT shop_id FROM utilisateurs WHERE id = ?');
            $row->execute([(int)$result['payload']['id']]);

            $this->assertSame(
                $this->shopId,
                (int)$row->fetch()['shop_id'],
                'Un admin ne doit jamais créer un utilisateur hors de sa boutique.'
            );
        } finally {
            self::$pdo->prepare('DELETE FROM shops WHERE id = ?')->execute([$otherShopId]);
        }
    }

    /**
     * Un doublon de nom d'utilisateur doit renvoyer une erreur JSON 409
     * lisible — pas une PDOException (page blanche).
     */
    public function testUnNomUtilisateurDejaPrisEstRefuseAvecUneErreurLisible(): void
    {
        $username = 'ucrt_' . strtolower(bin2hex(random_bytes(4)));

        $first = $this->appelerCreate(
            $this->formulaire(['username' => $username]),
            $this->sessionSuperAdmin()
        );
        $this->assertTrue($first['payload']['success'] ?? false, json_encode($first['payload']));

        $second = $this->appelerCreate(
            $this->formulaire(['username' => $username]),
            $this->sessionSuperAdmin()
        );

        $this->assertSame(409, $second['status']);
        $this->assertFalse($second['payload']['success'] ?? true);
        $this->assertNotEmpty($second['payload']['error'] ?? '');
    }

    /**
     * Un rôle hors de l'ENUM (`utilisateurs.role`) doit être refusé AVANT
     * l'INSERT, sinon MySQL lève une erreur SQL (page blanche).
     */
    public function testUnRoleInvalideEstRefuse(): void
    {
        $result = $this->appelerCreate(
            $this->formulaire(['role' => 'super_admin']),
            $this->sessionSuperAdmin()
        );

        $this->assertSame(403, $result['status']);
        $this->assertNotEmpty($result['payload']['error'] ?? '');
    }

    public function testUnRoleHorsEnumEstRefuse(): void
    {
        $result = $this->appelerCreate(
            $this->formulaire(['role' => 'inconnu']),
            $this->sessionSuperAdmin()
        );

        $this->assertSame(400, $result['status']);
        $this->assertNotEmpty($result['payload']['error'] ?? '');
    }

    public function testUnMotDePasseTropCourtEstRefuse(): void
    {
        $result = $this->appelerCreate(
            $this->formulaire(['password' => '123']),
            $this->sessionSuperAdmin()
        );

        $this->assertSame(400, $result['status']);
        $this->assertNotEmpty($result['payload']['error'] ?? '');
    }

    public function testDesChampsObligatoiresManquantsSontRefuses(): void
    {
        $result = $this->appelerCreate(
            $this->formulaire(['fullname' => '']),
            $this->sessionSuperAdmin()
        );

        $this->assertSame(400, $result['status']);
        $this->assertNotEmpty($result['payload']['error'] ?? '');
    }

    /**
     * La 2FA est livrée par SMS : sans numéro de téléphone, le compte
     * serait inutilisable à la connexion.
     */
    public function testLa2faExigeUnNumeroDeTelephone(): void
    {
        $result = $this->appelerCreate(
            $this->formulaire(['two_factor_enabled' => '1']),
            $this->sessionSuperAdmin()
        );

        $this->assertSame(400, $result['status']);
        $this->assertNotEmpty($result['payload']['error'] ?? '');
    }

    /**
     * La 2FA est bien enregistrée quand un téléphone est fourni.
     */
    public function testLa2faEstEnregistreeQuandUnTelephoneEstFourni(): void
    {
        $result = $this->appelerCreate(
            $this->formulaire([
                'two_factor_enabled' => '1',
                'telephone'          => '0810000100',
            ]),
            $this->sessionSuperAdmin()
        );

        $this->assertTrue($result['payload']['success'] ?? false, json_encode($result['payload']));

        $row = self::$pdo->prepare('SELECT two_factor_enabled FROM utilisateurs WHERE id = ?');
        $row->execute([(int)$result['payload']['id']]);

        $this->assertSame(1, (int)$row->fetch()['two_factor_enabled']);
    }

    /**
     * Le mot de passe saisi doit être HACHÉ et vérifiable : il ne doit
     * jamais être stocké en clair (ni altéré par un assainissement HTML).
     */
    public function testLeMotDePasseEstHacheEtVerifiable(): void
    {
        $password = 'a&b<c>123';

        $result = $this->appelerCreate(
            $this->formulaire(['password' => $password]),
            $this->sessionSuperAdmin()
        );

        $this->assertTrue($result['payload']['success'] ?? false, json_encode($result['payload']));

        $row = self::$pdo->prepare('SELECT mot_de_passe FROM utilisateurs WHERE id = ?');
        $row->execute([(int)$result['payload']['id']]);
        $hash = (string)$row->fetch()['mot_de_passe'];

        $this->assertNotSame($password, $hash, 'Le mot de passe ne doit jamais être stocké en clair.');
        $this->assertTrue(
            password_verify($password, $hash),
            'Le mot de passe doit être vérifiable tel qu\'il a été saisi.'
        );
    }

    /**
     * L'utilisateur créé doit être complet : rôle, boutique et code agent.
     */
    public function testLUtilisateurCreeEstComplet(): void
    {
        $result = $this->appelerCreate($this->formulaire(), $this->sessionSuperAdmin());

        $this->assertTrue($result['payload']['success'] ?? false, json_encode($result['payload']));

        $employee = (new User())->exist((int)$result['payload']['id']);

        $this->assertNotNull($employee);
        $this->assertSame('vendeur', $employee['role']);
        $this->assertNotEmpty($employee['agent_code'], 'Un code agent doit être attribué.');
    }

    /**
     * Un `admin` NE DOIT PAS créer d'admin : le rôle est verrouillé.
     */
    public function testUnVendeurNePeutPasCreerUnAdmin(): void
    {
        $vendeurId = $this->creerUtilisateur('vendeur', $this->shopId);

        $result = $this->appelerCreate(
            $this->formulaire(['role' => 'admin']),
            [
                'user_id' => $vendeurId,
                'role'    => 'vendeur',
                'shop_id' => $this->shopId,
            ]
        );

        $this->assertSame(403, $result['status']);
        $this->assertFalse($result['payload']['success'] ?? true);
    }

    /**
     * Un `admin` sans boutique en session ne peut pas créer d'utilisateur :
     * le message l'invite à contacter le super_admin (et non à choisir une
     * boutique — ce n'est pas son rôle).
     */
    public function testUnAdminSansBoutiqueEnSessionEstRefuseAvecUnMessageDedie(): void
    {
        $result = $this->appelerCreate(
            $this->formulaire(),
            [
                'user_id' => $this->adminId,
                'role'    => 'admin',
                'shop_id' => null,
            ]
        );

        $this->assertSame(400, $result['status']);
        $this->assertSame('shop_required', $result['payload']['code'] ?? '');
        $this->assertStringContainsString(
            'super_admin',
            (string)($result['payload']['message'] ?? ''),
            'Le message doit inviter l\'admin à contacter le super_admin.'
        );
    }
}
