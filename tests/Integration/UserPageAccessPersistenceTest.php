<?php

namespace Tests\Integration;

use App\Core\Database;
use App\Models\Shop;
use App\Models\UserPageAccess;
use App\Services\UserPageAccessService;
use PHPUnit\Framework\TestCase;

/**
 * Persistance des blocages de pages (`user_page_access`).
 *
 * Ces tests verrouillent le comportement de la table :
 *   - l'ABSENCE de blocage = page visible (semantique de la politique) ;
 *   - un blocage porte un couple (utilisateur, page) ;
 *   - le couple est UNIQUE ;
 *   - les blocages sont supprimes avec l'utilisateur (FK ON DELETE CASCADE) ;
 *   - un blocage SURVIT a la suppression de son auteur (l'historique ne doit
 *     pas disparaitre quand un compte est supprime).
 *
 * Migration : migrations/2026_11_18_user_page_access.sql
 */
class UserPageAccessPersistenceTest extends TestCase
{
    private static $pdo;
    private $shopId;
    private $adminId;
    private $vendeurId;

    public static function setUpBeforeClass(): void
    {
        self::$pdo = Database::getInstance()->getConnection();
    }

    protected function setUp(): void
    {
        $code = 'UPA_' . strtoupper(bin2hex(random_bytes(3)));
        $shopModel = new Shop();
        $this->shopId = (int)$shopModel->create([
            'nom'   => 'Boutique Test Pages Bloquees',
            'code'  => $code,
            'actif' => 1,
        ]);

        $this->adminId = $this->creerUtilisateur('admin', 'Admin Pages');
        $this->vendeurId = $this->creerUtilisateur('vendeur', 'Vendeur Pages');
    }

    protected function tearDown(): void
    {
        // Les utilisateurs sont supprimes AVANT la boutique : la FK
        // `created_by` est en ON DELETE SET NULL, le blocage doit donc
        // survivre a cette suppression (verifie par un test dedie).
        foreach ([$this->adminId, $this->vendeurId] as $userId) {
            if ($userId) {
                self::$pdo->prepare('DELETE FROM utilisateurs WHERE id = ?')->execute([$userId]);
            }
        }
        $this->adminId = 0;
        $this->vendeurId = 0;

        if ($this->shopId) {
            self::$pdo->prepare('DELETE FROM shops WHERE id = ?')->execute([$this->shopId]);
            $this->shopId = 0;
        }
    }

    private function creerUtilisateur(string $role, string $nomComplet): int
    {
        $username = 'upa_' . strtolower(bin2hex(random_bytes(4)));
        $stmt = self::$pdo->prepare(
            'INSERT INTO utilisateurs (nom_utilisateur, mot_de_passe, nom_complet, role, shop_id, actif)
             VALUES (?, \'x\', ?, ?, ?, 1)'
        );
        $stmt->execute([$username, $nomComplet, $role, $this->shopId]);
        return (int)self::$pdo->lastInsertId();
    }

    public function testUnUtilisateurSansBlocageNaPasDeLigne(): void
    {
        $model = new UserPageAccess();

        $this->assertSame(
            [],
            $model->allForUser($this->vendeurId),
            'Un utilisateur sans blocage ne doit avoir aucune ligne.'
        );
    }

    public function testUnBlocageEstPersiste(): void
    {
        $model = new UserPageAccess();

        $id = $model->create($this->vendeurId, 'rapports', $this->adminId);
        $this->assertGreaterThan(0, $id);

        $rows = $model->allForUser($this->vendeurId);
        $this->assertCount(1, $rows);
        $this->assertSame('rapports', $rows[0]['page_key']);
        $this->assertSame($this->vendeurId, (int)$rows[0]['user_id']);
    }

    public function testUnBlocageEstUniqueParUtilisateurEtPage(): void
    {
        $model = new UserPageAccess();

        $model->create($this->vendeurId, 'rapports', $this->adminId);
        // Le doublon ne doit pas lever d'exception (idempotence).
        $model->create($this->vendeurId, 'rapports', $this->adminId);

        $this->assertCount(
            1,
            $model->allForUser($this->vendeurId),
            'Le couple (utilisateur, page) doit rester unique.'
        );
    }

    public function testLeBlocageNeConcerneQueSonUtilisateur(): void
    {
        $model = new UserPageAccess();

        $model->create($this->vendeurId, 'rapports', $this->adminId);

        $this->assertCount(1, $model->allForUser($this->vendeurId));
        $this->assertCount(0, $model->allForUser($this->adminId));
    }

    public function testLaSuppressionDuBlocageLibereLaPage(): void
    {
        $model = new UserPageAccess();

        $model->create($this->vendeurId, 'rapports', $this->adminId);
        $model->delete($this->vendeurId, 'rapports');

        $this->assertSame([], $model->allForUser($this->vendeurId));
    }

    public function testLeBlocageEstSupprimeAvecLUtilisateur(): void
    {
        $model = new UserPageAccess();

        $model->create($this->vendeurId, 'rapports', $this->adminId);
        $this->assertCount(1, $model->allForUser($this->vendeurId));

        self::$pdo->prepare('DELETE FROM utilisateurs WHERE id = ?')->execute([$this->vendeurId]);

        $this->assertSame(
            [],
            $model->allForUser($this->vendeurId),
            'Le blocage doit disparaitre avec son utilisateur (FK ON DELETE CASCADE).'
        );
    }

    public function testLeBlocageSurvitALaSuppressionDeSonAuteur(): void
    {
        $model = new UserPageAccess();

        $model->create($this->vendeurId, 'rapports', $this->adminId);

        // On supprime l'AUTEUR du blocage, pas l'utilisateur bloque.
        self::$pdo->prepare('DELETE FROM utilisateurs WHERE id = ?')->execute([$this->adminId]);

        $rows = $model->allForUser($this->vendeurId);
        $this->assertCount(1, $rows, 'Le blocage doit survivre a la suppression de son auteur.');
        $this->assertNull(
            $rows[0]['created_by'],
            'La FK created_by doit passer a NULL (ON DELETE SET NULL).'
        );
    }


    public function testLeRemplacementNeToucheQueLaFamilleConcernee(): void
    {
        $model = new UserPageAccess();

        // Blocage dans la famille GENERALE et dans la famille HOTEL.
        $model->create($this->vendeurId, 'rapports', $this->adminId);
        $model->create($this->vendeurId, 'hotel_reception', $this->adminId);

        $ok = $model->replaceForUserAndPages(
            $this->vendeurId,
            ['produits'],
            $this->adminId,
            UserPageAccessService::getPageKeys(UserPageAccessService::FAMILY_GENERAL)
        );

        $this->assertTrue($ok);

        $keys = $model->blockedPageKeysForUser($this->vendeurId);
        $this->assertContains('produits', $keys);
        $this->assertNotContains('rapports', $keys);
        $this->assertContains(
            'hotel_reception',
            $keys,
            'Le blocage Hotel ne doit pas etre touche.'
        );
    }

    public function testLeChangementEstAudite(): void
    {
        $service = new UserPageAccessService();

        $service->updateBlockedPages(
            $this->vendeurId,
            UserPageAccessService::FAMILY_GENERAL,
            ['rapports' => true],
            $this->adminId
        );

        $rows = self::$pdo->prepare(
            "SELECT * FROM audit_logs
             WHERE entity = 'user_page_access' AND entity_id = ?
             ORDER BY id DESC"
        );
        $rows->execute([$this->vendeurId]);
        $entries = $rows->fetchAll();

        $this->assertNotEmpty($entries, 'Le changement doit etre audite.');

        $details = json_decode((string)$entries[0]['details'], true);
        $this->assertSame('rapports', $details['page_key']);
        $this->assertTrue($details['blocked']);
        $this->assertFalse($details['previous_blocked']);
    }

    public function testUneSauvegardeSansChangementNeGenerePasDAudit(): void
    {
        $service = new UserPageAccessService();

        $service->updateBlockedPages(
            $this->vendeurId,
            UserPageAccessService::FAMILY_GENERAL,
            ['rapports' => true],
            $this->adminId
        );

        $row = self::$pdo->prepare(
            "SELECT COUNT(*) AS n FROM audit_logs
             WHERE entity = 'user_page_access' AND entity_id = ?"
        );
        $row->execute([$this->vendeurId]);
        $countBefore = (int)$row->fetch()['n'];

        // Deuxieme sauvegarde identique : aucune difference, aucun nouvel audit.
        $service->updateBlockedPages(
            $this->vendeurId,
            UserPageAccessService::FAMILY_GENERAL,
            ['rapports' => true],
            $this->adminId
        );

        $row->execute([$this->vendeurId]);
        $this->assertSame(
            $countBefore,
            (int)$row->fetch()['n'],
            'Seules les differences doivent etre auditees.'
        );
    }

    public function testLesUtilisateursBloquesSurUnePageSontListesParBoutique(): void
    {
        $model = new UserPageAccess();

        $model->create($this->vendeurId, 'rapports', $this->adminId);

        $ids = $model->blockedUserIdsForPageAndShop('rapports', $this->shopId);

        $this->assertContains($this->vendeurId, $ids);
        $this->assertNotContains($this->adminId, $ids);
    }
}
