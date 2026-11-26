<?php

namespace Tests\Integration;

use App\Core\Database;
use App\Models\Shop;
use App\Models\UserPageAccess;
use PHPUnit\Framework\TestCase;

/**
 * Mutation PARTAGEE : `POST /api/vente` sert la caisse ET les recharges.
 *
 * Une mutation partagee ne doit JAMAIS etre autorisee parce qu'une seule
 * page est ouverte : elle doit verifier la page REELLEMENT utilisee, deduite
 * du contexte metier (`providerService`).
 *
 * Ces tests verrouillent le comportement :
 *   - une vente de caisse est refusee si « caisse » est bloquee ;
 *   - une recharge reste autorisee si seule « caisse » est bloquee ;
 *   - une recharge est refusee si « recharges » est bloquee.
 *
 * Migration : migrations/2026_11_18_user_page_access.sql
 */
class UserPageAccessSharedWriteTest extends TestCase
{
    private static $pdo;
    private $shopId;
    private $vendeurId;
    private $superAdminId;

    private array $sessionBackup = [];
    private array $postBackup = [];

    public static function setUpBeforeClass(): void
    {
        self::$pdo = Database::getInstance()->getConnection();
    }

    protected function setUp(): void
    {
        $this->sessionBackup = $_SESSION ?? [];
        $this->postBackup = $_POST;

        $code = 'UPASW_' . strtoupper(bin2hex(random_bytes(3)));
        $this->shopId = (int)(new Shop())->create([
            'nom'   => 'Boutique Test Mutation Pages',
            'code'  => $code,
            'actif' => 1,
        ]);

        $this->vendeurId = $this->creerUtilisateur('vendeur', $this->shopId);
        $this->superAdminId = $this->creerUtilisateur('super_admin', null);
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->sessionBackup;
        $_POST = $this->postBackup;
        http_response_code(200);

        foreach ([$this->vendeurId, $this->superAdminId] as $userId) {
            if ($userId) {
                self::$pdo->prepare('DELETE FROM utilisateurs WHERE id = ?')->execute([$userId]);
            }
        }
        $this->vendeurId = 0;
        $this->superAdminId = 0;

        if ($this->shopId) {
            self::$pdo->prepare('DELETE FROM shops WHERE id = ?')->execute([$this->shopId]);
            $this->shopId = 0;
        }
    }

    private function creerUtilisateur(string $role, ?int $shopId): int
    {
        $username = 'upasw_' . strtolower(bin2hex(random_bytes(4)));
        $stmt = self::$pdo->prepare(
            'INSERT INTO utilisateurs (nom_utilisateur, mot_de_passe, nom_complet, role, shop_id, actif)
             VALUES (?, \'x\', ?, ?, ?, 1)'
        );
        $stmt->execute([$username, 'Test Mutation', $role, $shopId]);
        return (int)self::$pdo->lastInsertId();
    }

    private function bloquer(string $pageKey): void
    {
        (new UserPageAccess())->create($this->vendeurId, $pageKey, $this->superAdminId);
    }


    /**
     * Appelle `POST /api/vente` via le controleur.
     *
     * En CLI, `php://input` n'est pas alimenté : la payload est injectée via
     * la globale `__SALE_PAYLOAD__` (même pattern que
     * `HotelFactureController::store()`).
     *
     * @return array{status: int, body: string}
     */
    private function vendre(array $payload): array
    {
        $_SESSION = [
            'user_id' => $this->vendeurId,
            'role'    => 'vendeur',
            'shop_id' => $this->shopId,
        ];

        $GLOBALS['__SALE_PAYLOAD__'] = json_encode($payload);

        http_response_code(200);
        ob_start();
        try {
            $controller = new \App\Controllers\SaleController();
            $controller->create();
        } finally {
            $body = (string)ob_get_clean();
        }
        unset($GLOBALS['__SALE_PAYLOAD__']);

        $status = http_response_code();
        http_response_code(200);

        return ['status' => $status ?: 200, 'body' => $body];
    }

    private function panierCaisse(): array
    {
        return [
            'articles' => [
                ['produit_id' => 1, 'nom' => 'Article Test', 'quantite' => 1, 'prix' => 100],
            ],
            'sous_total_ht' => 100,
            'tva' => 16,
            'total' => 116,
            'type_facture' => 'FV',
        ];
    }

    private function panierRecharge(): array
    {
        return $this->panierCaisse() + [
            'providerService' => 'snelregideso',
        ];
    }

    public function testUneVenteCaisseEstRefuseeSiCaisseEstBloquee(): void
    {
        $this->bloquer('caisse');

        $result = $this->vendre($this->panierCaisse());

        $this->assertSame(403, $result['status']);

        $payload = json_decode($result['body'], true);
        $this->assertIsArray($payload);
        $this->assertSame('page_not_allowed', $payload['error']);
    }

    public function testUneRechargeResteAutoriseeSiSeuleCaisseEstBloquee(): void
    {
        $this->bloquer('caisse');

        // La recharge passe la GARDE DE POLITIQUE : elle n'est pas bloquée.
        // (La suite du flux de vente peut échouer pour une autre raison —
        // c'est ce qui compte ici : le refus ne vient pas de la politique.)
        $result = $this->vendre($this->panierRecharge());

        $payload = json_decode($result['body'], true);
        $this->assertIsArray($payload);

        $this->assertNotSame(
            'page_not_allowed',
            $payload['error'] ?? null,
            'La recharge ne doit pas etre refusee par la politique de la caisse.'
        );
    }

    public function testUneRechargeEstRefuseeSiRechargesEstBloquee(): void
    {
        $this->bloquer('recharges');

        $result = $this->vendre($this->panierRecharge());

        $this->assertSame(403, $result['status']);

        $payload = json_decode($result['body'], true);
        $this->assertIsArray($payload);
        $this->assertSame('page_not_allowed', $payload['error']);
    }

    public function testUneVenteCaisseResteAutoriseeSiSeuleRechargesEstBloquee(): void
    {
        $this->bloquer('recharges');

        $result = $this->vendre($this->panierCaisse());

        $payload = json_decode($result['body'], true);
        $this->assertIsArray($payload);

        $this->assertNotSame(
            'page_not_allowed',
            $payload['error'] ?? null,
            'La vente de caisse ne doit pas etre refusee par la politique des recharges.'
        );
    }
}
