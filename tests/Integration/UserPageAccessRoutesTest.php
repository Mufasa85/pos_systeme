<?php

namespace Tests\Integration;

use App\Controllers\PageController;
use App\Core\Database;
use App\Core\Router;
use App\Models\Shop;
use App\Models\UserPageAccess;
use PHPUnit\Framework\TestCase;

/**
 * Garde de la politique d'acces aux pages, par UTILISATEUR.
 *
 * La garde doit s'executer AVANT l'instanciation du controleur : une page
 * bloquee ne doit jamais charger de donnee metier.
 *
 * Ces tests couvrent :
 *   - une route hors politique : comportement normal ;
 *   - une page bloquee : 403 ;
 *   - une API bloquee : 403 + JSON `page_not_allowed` ;
 *   - le super_admin : jamais bloque ;
 *   - un utilisateur inconnu : fail-closed ;
 *   - l'API de reglage : reservee au super_admin.
 *
 * Migration : migrations/2026_11_18_user_page_access.sql
 */
class UserPageAccessRoutesTest extends TestCase
{
    private static $pdo;
    private $shopId;
    private $adminId;
    private $vendeurId;
    private $superAdminId;

    private array $sessionBackup = [];
    private array $serverBackup = [];
    private array $getBackup = [];
    private array $postBackup = [];

    public static function setUpBeforeClass(): void
    {
        self::$pdo = Database::getInstance()->getConnection();
    }

    protected function setUp(): void
    {
        $this->sessionBackup = $_SESSION ?? [];
        $this->serverBackup = $_SERVER;
        $this->getBackup = $_GET;
        $this->postBackup = $_POST;

        // En CLI, `SCRIPT_NAME` pointe sur le binaire de PHPUnit : le base
        // path du routeur doit etre neutralise pour que les URL de test
        // correspondent.
        \App\App::setRouterBasePath('');

        $code = 'UPART_' . strtoupper(bin2hex(random_bytes(3)));
        $this->shopId = (int)(new Shop())->create([
            'nom'   => 'Boutique Test Garde Pages',
            'code'  => $code,
            'actif' => 1,
        ]);

        $this->adminId = $this->creerUtilisateur('admin', $this->shopId);
        $this->vendeurId = $this->creerUtilisateur('vendeur', $this->shopId);
        $this->superAdminId = $this->creerUtilisateur('super_admin', null);
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->sessionBackup;
        $_SERVER = $this->serverBackup;
        $_GET = $this->getBackup;
        $_POST = $this->postBackup;
        http_response_code(200);

        foreach ([$this->adminId, $this->vendeurId, $this->superAdminId] as $userId) {
            if ($userId) {
                self::$pdo->prepare('DELETE FROM utilisateurs WHERE id = ?')->execute([$userId]);
            }
        }
        $this->adminId = 0;
        $this->vendeurId = 0;
        $this->superAdminId = 0;

        if ($this->shopId) {
            self::$pdo->prepare('DELETE FROM shops WHERE id = ?')->execute([$this->shopId]);
            $this->shopId = 0;
        }
    }

    private function creerUtilisateur(string $role, ?int $shopId): int
    {
        $username = 'upart_' . strtolower(bin2hex(random_bytes(4)));
        $stmt = self::$pdo->prepare(
            'INSERT INTO utilisateurs (nom_utilisateur, mot_de_passe, nom_complet, role, shop_id, actif)
             VALUES (?, \'x\', ?, ?, ?, 1)'
        );
        $stmt->execute([$username, 'Test Garde Pages', $role, $shopId]);
        return (int)self::$pdo->lastInsertId();
    }

    private function bloquer(int $userId, string $pageKey): void
    {
        (new UserPageAccess())->create($userId, $pageKey, $this->superAdminId);
    }

    /**
     * Cible du controleur de test : elle ne doit JAMAIS etre atteinte si
     * la garde fonctionne (le controleur n'est meme pas instancié).
     */
    public static function cibleDeTest(): void
    {
        echo 'CIBLE_ATEIGNTE';
    }

    /**
     * Enregistre une route de test et la declenche via le routeur.
     *
     * @return array{status: int, body: string}
     */
    private function appelerRoute(string $route, string $policyKey, string $policyMode = 'exclusive'): array
    {
        $_SERVER['REQUEST_URI'] = $route;
        $_SERVER['REQUEST_METHOD'] = 'GET';

        Router::get($route, [UserPageAccessRoutesTest::class, 'cibleDeTest'], $policyKey, $policyMode);

        ob_start();
        try {
            Router::matcher();
        } finally {
            $body = (string)ob_get_clean();
            $status = http_response_code();
            http_response_code(200);
        }

        // En CLI, `http_response_code()` renvoie `false` quand aucun code n'a
        // ete pose explicitement.
        if ($status === false) {
            $status = 200;
        }

        return ['status' => (int)$status, 'body' => $body];
    }

    /**
     * Une page reelle et son API sont bloquees pour l'utilisateur bloque.
     */
    public function testUnePageReelleEtSonApiSontBloquees(): void
    {
        $this->bloquer($this->vendeurId, 'rapports');

        $_SESSION = ['user_id' => $this->vendeurId, 'role' => 'vendeur', 'shop_id' => $this->shopId];

        // La PAGE `/rapports` renvoie 403.
        $page = $this->appelerRoute('/rapports', 'rapports');
        $this->assertSame(403, $page['status']);

        // L'API `/api/reports/history` renvoie 403 + JSON.
        $api = $this->appelerRoute('/api/reports/history', 'rapports');
        $this->assertSame(403, $api['status']);
        $payload = json_decode($api['body'], true);
        $this->assertIsArray($payload);
        $this->assertSame('page_not_allowed', $payload['error']);
    }

    /**
     * Le blocage est INDIVIDUEL : les autres utilisateurs de la boutique
     * conservent l'acces a la page.
     */
    public function testLeBlocageNeConcerneQueLUtilisateurBloque(): void
    {
        $this->bloquer($this->vendeurId, 'rapports');

        $_SESSION = ['user_id' => $this->adminId, 'role' => 'admin', 'shop_id' => $this->shopId];

        $result = $this->appelerRoute('/api/policy-test-admin-libre', 'rapports');

        $this->assertSame(200, $result['status']);
        $this->assertStringContainsString('CIBLE_ATEIGNTE', $result['body']);
    }

    /**
     * Une LECTURE PARTAGEE reste accessible si AU MOINS UNE page lectrice
     * est autorisee (le catalogue alimente la caisse ET la page Produits).
     */
    public function testUneLecturePartageeResteAccessibleSiUnePageLectriceEstAutorisee(): void
    {
        // Seule la page `produits` est bloquee : la `caisse` reste ouverte.
        $this->bloquer($this->vendeurId, 'produits');

        $_SESSION = ['user_id' => $this->vendeurId, 'role' => 'vendeur', 'shop_id' => $this->shopId];

        $result = $this->appelerRoute('/api/produits', 'caisse|produits', 'shared_read');

        $this->assertSame(
            200,
            $result['status'],
            'La lecture du catalogue doit rester disponible pour la caisse.'
        );
    }

    /**
     * Une lecture partagee est bloquee si TOUTES ses pages lectrices sont
     * bloquees.
     */
    public function testUneLecturePartageeEstBloqueeSiToutesLesPagesLectricesSontBloquees(): void
    {
        $this->bloquer($this->vendeurId, 'caisse');
        $this->bloquer($this->vendeurId, 'produits');

        $_SESSION = ['user_id' => $this->vendeurId, 'role' => 'vendeur', 'shop_id' => $this->shopId];

        $result = $this->appelerRoute('/api/produits', 'caisse|produits', 'shared_read');

        $this->assertSame(403, $result['status']);
    }

    public function testUneRouteHorsPolitiqueFonctionneNormalement(): void
    {
        $_SESSION = ['user_id' => $this->vendeurId, 'role' => 'vendeur', 'shop_id' => $this->shopId];

        $result = $this->appelerRoute('/api/policy-test-hors-politique', '');

        $this->assertSame(200, $result['status']);
        $this->assertStringContainsString('CIBLE_ATEIGNTE', $result['body']);
    }

    public function testUnePageBloqueeRenvoie403(): void
    {
        $this->bloquer($this->vendeurId, 'rapports');

        $_SESSION = ['user_id' => $this->vendeurId, 'role' => 'vendeur', 'shop_id' => $this->shopId];

        $result = $this->appelerRoute('/api/policy-test-page-bloquee', 'rapports');

        $this->assertSame(403, $result['status']);
        $this->assertStringNotContainsString(
            'CIBLE_ATEIGNTE',
            $result['body'],
            'Le controleur ne doit pas etre execute si la page est bloquee.'
        );
    }

    public function testUneApiBloqueeRenvoie403Json(): void
    {
        $this->bloquer($this->vendeurId, 'produits');

        $_SESSION = ['user_id' => $this->vendeurId, 'role' => 'vendeur', 'shop_id' => $this->shopId];

        $result = $this->appelerRoute('/api/policy-test-api-bloquee', 'produits');

        $this->assertSame(403, $result['status']);

        $payload = json_decode($result['body'], true);
        $this->assertIsArray($payload, 'La reponse doit etre un JSON valide.');
        $this->assertFalse($payload['success']);
        $this->assertSame('page_not_allowed', $payload['error']);
    }

    public function testLeSuperAdminJamaisBloque(): void
    {
        $this->bloquer($this->vendeurId, 'rapports');

        $_SESSION = ['user_id' => $this->superAdminId, 'role' => 'super_admin', 'shop_id' => null];

        $result = $this->appelerRoute('/api/policy-test-super-admin', 'rapports');

        $this->assertSame(200, $result['status']);
        $this->assertStringContainsString('CIBLE_ATEIGNTE', $result['body']);
    }

    public function testUnUtilisateurInconnuEstRefuse(): void
    {
        $_SESSION = ['user_id' => 999999999, 'role' => 'vendeur', 'shop_id' => $this->shopId];

        $result = $this->appelerRoute('/api/policy-test-inconnu', 'rapports');

        $this->assertSame(403, $result['status']);
    }

    public function testUneErreurSqlEstFailClosed(): void
    {
        // On simule une panne d'infrastructure : la table des blocages est
        // renommee le temps du test, puis restauree.
        self::$pdo->exec('RENAME TABLE user_page_access TO user_page_access_tmp');

        try {
            $_SESSION = ['user_id' => $this->vendeurId, 'role' => 'vendeur', 'shop_id' => $this->shopId];

            $result = $this->appelerRoute('/api/policy-test-erreur-sql', 'rapports');

            $this->assertSame(
                403,
                $result['status'],
                'Une erreur SQL doit fermer la porte, pas l ouvrir.'
            );
            $this->assertStringNotContainsString('CIBLE_ATEIGNTE', $result['body']);
        } finally {
            self::$pdo->exec('RENAME TABLE user_page_access_tmp TO user_page_access');
        }
    }

    /**
     * COHERENCE MENU / BACKEND : la carte de visibilite qui alimente la
     * sidebar doit correspondre EXACTEMENT a la decision du backend.
     */
    public function testLaCarteDeVisibiliteConcordeAvecLeBackend(): void
    {
        $this->bloquer($this->vendeurId, 'rapports');

        $service = new \App\Services\UserPageAccessService();

        // Ce que voit la sidebar.
        $map = $service->getVisibilityMap($this->vendeurId);

        $this->assertFalse($map['rapports'], 'La sidebar doit masquer la page.');
        $this->assertTrue($map['caisse'], 'Les autres pages restent visibles.');

        // Ce que decide le backend, pour la meme page.
        $this->assertFalse(
            $service->isPageAllowedForUser('rapports', $this->vendeurId),
            'Le backend doit refuser la meme page.'
        );
        $this->assertTrue(
            $service->isPageAllowedForUser('caisse', $this->vendeurId),
            'Le backend doit autoriser la meme page que la sidebar.'
        );
    }

    // ── API de reglage (super_admin) ────────────────────────────────────

    /**
     * Appelle l'API de reglage via le controleur.
     *
     * @return array{status: int, payload: array<string, mixed>}
     */
    private function appelerApi(array $post, string $sessionRole, int $sessionUserId, ?int $sessionShopId): array
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $post;
        $_SESSION = [
            'user_id' => $sessionUserId,
            'role'    => $sessionRole,
            'shop_id' => $sessionShopId,
        ];

        http_response_code(200);
        ob_start();
        try {
            (new PageController())->apiUserPageAccessUpdate();
        } finally {
            $body = (string)ob_get_clean();
        }

        $status = http_response_code();
        http_response_code(200);

        return [
            'status'  => $status ?: 200,
            'payload' => json_decode($body, true) ?? [],
        ];
    }

    public function testLeSuperAdminPeutBloquerUnePage(): void
    {
        $result = $this->appelerApi(
            [
                'user_id'  => (string)$this->vendeurId,
                'page_key' => 'rapports',
                'blocked'  => '1',
                'shop_id'  => (string)$this->shopId,
            ],
            'super_admin',
            $this->superAdminId,
            null
        );

        $this->assertSame(200, $result['status']);
        $this->assertTrue($result['payload']['success']);

        $service = new \App\Services\UserPageAccessService();
        $this->assertFalse(
            $service->isPageAllowedForUser('rapports', $this->vendeurId),
            'Le blocage doit etre effectif.'
        );
    }

    public function testLeSuperAdminPeutDebloquerUnePage(): void
    {
        $this->bloquer($this->vendeurId, 'rapports');

        $result = $this->appelerApi(
            [
                'user_id'  => (string)$this->vendeurId,
                'page_key' => 'rapports',
                'blocked'  => '0',
                'shop_id'  => (string)$this->shopId,
            ],
            'super_admin',
            $this->superAdminId,
            null
        );

        $this->assertSame(200, $result['status']);
        $this->assertTrue($result['payload']['success']);

        $service = new \App\Services\UserPageAccessService();
        $this->assertTrue($service->isPageAllowedForUser('rapports', $this->vendeurId));
    }

    public function testUnAdminNePeutPasBloquerUnePage(): void
    {
        $result = $this->appelerApi(
            [
                'user_id'  => (string)$this->vendeurId,
                'page_key' => 'rapports',
                'blocked'  => '1',
                'shop_id'  => (string)$this->shopId,
            ],
            'admin',
            $this->adminId,
            $this->shopId
        );

        $this->assertSame(403, $result['status']);
        $this->assertFalse($result['payload']['success']);

        $service = new \App\Services\UserPageAccessService();
        $this->assertTrue(
            $service->isPageAllowedForUser('rapports', $this->vendeurId),
            'Un admin ne doit pas pouvoir bloquer une page.'
        );
    }

    public function testUnVendeurNePeutPasBloquerUnePage(): void
    {
        $result = $this->appelerApi(
            [
                'user_id'  => (string)$this->vendeurId,
                'page_key' => 'rapports',
                'blocked'  => '1',
                'shop_id'  => (string)$this->shopId,
            ],
            'vendeur',
            $this->vendeurId,
            $this->shopId
        );

        $this->assertSame(403, $result['status']);
        $this->assertFalse($result['payload']['success']);
    }

    public function testLeBlocageViaLApiEstAudite(): void
    {
        $result = $this->appelerApi(
            [
                'user_id'  => (string)$this->vendeurId,
                'page_key' => 'rapports',
                'blocked'  => '1',
                'shop_id'  => (string)$this->shopId,
            ],
            'super_admin',
            $this->superAdminId,
            null
        );

        $this->assertSame(200, $result['status']);
        $this->assertTrue($result['payload']['success']);

        $row = self::$pdo->prepare(
            "SELECT * FROM audit_logs
             WHERE entity = 'user_page_access' AND entity_id = ?
             ORDER BY id DESC LIMIT 1"
        );
        $row->execute([$this->vendeurId]);
        $entry = $row->fetch();

        $this->assertNotEmpty($entry, 'Le blocage doit etre audite.');

        $details = json_decode((string)$entry['details'], true);
        $this->assertSame('rapports', $details['page_key']);
        $this->assertTrue($details['blocked']);
        $this->assertFalse($details['previous_blocked']);
    }


    public function testUnePageInconnueEstRefuseeParLApi(): void
    {
        $result = $this->appelerApi(
            [
                'user_id'  => (string)$this->vendeurId,
                'page_key' => 'page_inconnue',
                'blocked'  => '1',
                'shop_id'  => (string)$this->shopId,
            ],
            'super_admin',
            $this->superAdminId,
            null
        );

        $this->assertSame(400, $result['status']);
        $this->assertFalse($result['payload']['success']);
    }

    public function testLApiRefuseUnUtilisateurHorsBoutique(): void
    {
        // L'admin cible un utilisateur d'une AUTRE boutique.
        $autreCode = 'UPAHORS_' . strtoupper(bin2hex(random_bytes(3)));
        $stmt = self::$pdo->prepare('INSERT INTO shops (nom, code, actif) VALUES (?, ?, 1)');
        $stmt->execute(['Boutique Hors Perimetre', $autreCode]);
        $autreShopId = (int)self::$pdo->lastInsertId();

        try {
            $result = $this->appelerApi(
                [
                    'user_id'  => (string)$this->vendeurId,
                    'page_key' => 'rapports',
                    'blocked'  => '1',
                    'shop_id'  => (string)$autreShopId,
                ],
                'super_admin',
                $this->superAdminId,
                null
            );

            $this->assertSame(403, $result['status']);
            $this->assertFalse($result['payload']['success']);

            $service = new \App\Services\UserPageAccessService();
            $this->assertTrue($service->isPageAllowedForUser('rapports', $this->vendeurId));
        } finally {
            self::$pdo->prepare('DELETE FROM shops WHERE id = ?')->execute([$autreShopId]);
        }
    }

    // ── Écran « Pages bloquées » (super_admin) ──────────────────────────
    /**
     * Appelle l'ACTION `/pages-bloquees` (garde de rôle incluse).
     *
     * Utilisée pour vérifier le refus : la garde renvoie une 403 AVANT tout
     * rendu, donc le corps de la réponse est la page 403.
     *
     * @return array{status: int, body: string}
     */
    private function appelerActionEcran(string $sessionRole, int $sessionUserId, ?int $sessionShopId): array
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/pages-bloquees';
        $_GET = [];
        $_SESSION = [
            'user_id' => $sessionUserId,
            'role'    => $sessionRole,
            'shop_id' => $sessionShopId,
        ];

        http_response_code(200);
        ob_start();
        try {
            (new PageController())->pagesBloquees();
        } finally {
            $body = (string)ob_get_clean();
        }

        $status = http_response_code();
        http_response_code(200);

        return ['status' => $status ?: 200, 'body' => $body];
    }



    /**
     * Appelle l'écran `/pages-bloquees` via le controleur.
     *
     * On inspecte les DONNEES preparedness par le controleur (et non le
     * HTML) : `render()` inclut le layout avec `require_once`, donc le HTML
     * complet ne peut pas être capté plusieurs fois dans un meme process.
     *
     * @return array{status: int, data: array<string, mixed>}
     */
    private function appelerEcran(string $sessionRole, int $sessionUserId, ?int $sessionShopId, ?int $targetShopId): array
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/pages-bloquees';
        $_GET = $targetShopId ? ['shop_id' => (string)$targetShopId] : [];
        $_SESSION = [
            'user_id' => $sessionUserId,
            'role'    => $sessionRole,
            'shop_id' => $sessionShopId,
        ];

        http_response_code(200);
        $controller = new PageController();
        $data = $controller->buildPagesBloqueesData((int)($targetShopId ?? 0));
        $status = http_response_code();
        http_response_code(200);

        return ['status' => $status ?: 200, 'data' => $data];
    }

    public function testLeSuperAdminVoitLEcranPagesBloquees(): void
    {
        $this->bloquer($this->vendeurId, 'rapports');

        $result = $this->appelerEcran('super_admin', $this->superAdminId, null, $this->shopId);

        $this->assertSame(200, $result['status']);

        $data = $result['data'];
        $this->assertCount(
            12,
            $data['pagesCatalog'],
            'Cet ecran ne propose que les pages POS (12), pas les pages Hotel.'
        );
        $this->assertNotEmpty($data['pageUsers'], 'Les utilisateurs de la boutique doivent etre listes.');
        $this->assertSame($this->shopId, (int)$data['pageShopId']);

        // Le vendeur est bloque sur « rapports ».
        $this->assertContains('rapports', $data['blockedByUser'][$this->vendeurId] ?? []);
        // L'admin ne l'est pas.
        $this->assertSame([], $data['blockedByUser'][$this->adminId] ?? []);
    }

    public function testLEcranPagesBloqueesIgnoreLesBlocagesHotel(): void
    {
        // Un blocage HOTEL ne doit PAS apparaître sur l'écran POS.
        $this->bloquer($this->vendeurId, 'hotel_reception');

        $result = $this->appelerEcran('super_admin', $this->superAdminId, null, $this->shopId);

        $data = $result['data'];
        $this->assertSame(
            [],
            $data['blockedByUser'][$this->vendeurId] ?? [],
            'Les blocages Hotel ne doivent pas etre listes sur l ecran POS.'
        );
        $this->assertArrayNotHasKey(
            'hotel_reception',
            $data['pagesCatalog'],
            'Le catalogue de cet ecran ne doit contenir que les pages POS.'
        );
    }

    public function testLeCatalogueHotelNeContientQueLesPagesHotel(): void
    {
        $catalog = \App\Services\UserPageAccessService::getCatalogForFamily(
            \App\Services\UserPageAccessService::FAMILY_HOTEL
        );

        $this->assertCount(9, $catalog, 'La famille Hotel contient 9 pages.');
        $this->assertArrayHasKey('hotel_reception', $catalog);
        $this->assertArrayNotHasKey('rapports', $catalog);
        $this->assertArrayNotHasKey('caisse', $catalog);
    }

    public function testLeCatalogueGeneralNeContientQueLesPagesPos(): void
    {
        $catalog = \App\Services\UserPageAccessService::getCatalogForFamily(
            \App\Services\UserPageAccessService::FAMILY_GENERAL
        );

        $this->assertCount(12, $catalog, 'La famille generale contient 12 pages.');
        $this->assertArrayHasKey('rapports', $catalog);
        $this->assertArrayNotHasKey('hotel_reception', $catalog);
    }


    public function testUnAdminEstRefuseSurLEcranPagesBloquees(): void
    {
        $result = $this->appelerActionEcran('admin', $this->adminId, $this->shopId);

        $this->assertSame(403, $result['status']);
        $this->assertStringNotContainsString(
            'pages-bloquees-wrap',
            $result['body'],
            'Un admin ne doit pas voir l ecran reserve au super_admin.'
        );
    }

    public function testUnVendeurEstRefuseSurLEcranPagesBloquees(): void
    {
        $result = $this->appelerActionEcran('vendeur', $this->vendeurId, $this->shopId);

        $this->assertSame(403, $result['status']);
        $this->assertStringNotContainsString(
            'pages-bloquees-wrap',
            $result['body'],
            'Un vendeur ne doit pas voir l ecran reserve au super_admin.'
        );
    }

    public function testLEcranPagesBloqueesAfficheLEBlocageDuBonUtilisateur(): void
    {
        $this->bloquer($this->vendeurId, 'rapports');

        $result = $this->appelerEcran('super_admin', $this->superAdminId, null, $this->shopId);

        $data = $result['data'];

        // Le vendeur bloque porte « rapports ».
        $this->assertSame(['rapports'], $data['blockedByUser'][$this->vendeurId] ?? []);
        // L'admin (non bloque) n'a aucune page bloquee.
        $this->assertSame([], $data['blockedByUser'][$this->adminId] ?? []);
    }

}
