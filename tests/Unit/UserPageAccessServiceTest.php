<?php

namespace Tests\Unit;

use App\Core\Database;
use App\Models\Shop;
use App\Models\UserPageAccess;
use App\Services\UserPageAccessService;
use PHPUnit\Framework\TestCase;

/**
 * Regles de lecture de la politique d'acces aux pages, par UTILISATEUR.
 *
 * Le service est la source UNIQUE de verite : il connait le catalogue des
 * pages et la visibilite d'une page pour un utilisateur donne.
 *
 * Ces tests verrouillent les invariants :
 *   - l'absence de blocage = page visible ;
 *   - un blocage ne concerne QUE l'utilisateur concerne ;
 *   - le `super_admin` n'est JAMAIS bloque ;
 *   - tout cas indefini est REFUSE (fail-closed).
 *
 * Migration : migrations/2026_11_18_user_page_access.sql
 */
class UserPageAccessServiceTest extends TestCase
{
    private static $pdo;
    private $shopId;
    private $adminId;
    private $vendeurId;
    private $superAdminId;

    public static function setUpBeforeClass(): void
    {
        self::$pdo = Database::getInstance()->getConnection();
    }

    protected function setUp(): void
    {
        $code = 'UPASVC_' . strtoupper(bin2hex(random_bytes(3)));
        $this->shopId = (int)(new Shop())->create([
            'nom'   => 'Boutique Test Service Pages',
            'code'  => $code,
            'actif' => 1,
        ]);

        $this->adminId = $this->creerUtilisateur('admin', $this->shopId);
        $this->vendeurId = $this->creerUtilisateur('vendeur', $this->shopId);
        // Le super_admin n'est rattache a aucune boutique (cf. le projet).
        $this->superAdminId = $this->creerUtilisateur('super_admin', null);
    }

    protected function tearDown(): void
    {
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
        $username = 'upasvc_' . strtolower(bin2hex(random_bytes(4)));
        $stmt = self::$pdo->prepare(
            'INSERT INTO utilisateurs (nom_utilisateur, mot_de_passe, nom_complet, role, shop_id, actif)
             VALUES (?, \'x\', ?, ?, ?, 1)'
        );
        $stmt->execute([$username, 'Test Service Pages', $role, $shopId]);
        return (int)self::$pdo->lastInsertId();
    }

    private function bloquer(int $userId, string $pageKey): void
    {
        (new UserPageAccess())->create($userId, $pageKey, $this->superAdminId);
    }

    // ── Catalogue ───────────────────────────────────────────────────────

    public function testLeCatalogueContientLesDeuxFamilles(): void
    {
        $catalog = UserPageAccessService::getCatalog();

        $this->assertArrayHasKey('caisse', $catalog);
        $this->assertArrayHasKey('hotel_reception', $catalog);
        $this->assertSame(UserPageAccessService::FAMILY_GENERAL, $catalog['caisse']['family']);
        $this->assertSame(UserPageAccessService::FAMILY_HOTEL, $catalog['hotel_reception']['family']);
    }

    public function testLeCatalogueCouvreLesVingtEtUnePages(): void
    {
        $catalog = UserPageAccessService::getCatalog();

        $this->assertCount(
            21,
            $catalog,
            'Le catalogue doit contenir 12 pages generales + 9 pages Hotel.'
        );
    }

    public function testGetPageKeysSepareLesFamilles(): void
    {
        $general = UserPageAccessService::getPageKeys(UserPageAccessService::FAMILY_GENERAL);
        $hotel = UserPageAccessService::getPageKeys(UserPageAccessService::FAMILY_HOTEL);

        $this->assertCount(12, $general);
        $this->assertCount(9, $hotel);
        $this->assertContains('caisse', $general);
        $this->assertContains('hotel_reception', $hotel);
        $this->assertNotContains('hotel_reception', $general);
    }

    public function testUnePageInconnueNestPasAuCatalogue(): void
    {
        $this->assertFalse(UserPageAccessService::isKnownPage('inexistant'));
        $this->assertTrue(UserPageAccessService::isKnownPage('caisse'));
    }

    public function testUneFamilleInconnueEstRefusee(): void
    {
        $this->assertFalse(UserPageAccessService::isKnownFamily('inconnue'));
        $this->assertTrue(UserPageAccessService::isKnownFamily(UserPageAccessService::FAMILY_HOTEL));
    }


    // ── Contexte utilisateur ────────────────────────────────────────────

    public function testLeContexteUtilisateurExposeRoleEtBoutique(): void
    {
        $context = UserPageAccessService::resolveUserContext($this->vendeurId);

        $this->assertIsArray($context);
        $this->assertSame('vendeur', $context['role']);
        $this->assertSame($this->shopId, (int)$context['shop_id']);
    }

    public function testUnUtilisateurInexistantEstRefuse(): void
    {
        $this->assertFalse(UserPageAccessService::resolveUserContext(999999999));
    }

    public function testUnChangementDeRoleEstPrisEnCompteSansReconnexion(): void
    {
        // Le role est relu EN BASE, pas dans la session.
        self::$pdo->prepare('UPDATE utilisateurs SET role = ? WHERE id = ?')
            ->execute(['admin', $this->vendeurId]);

        $context = UserPageAccessService::resolveUserContext($this->vendeurId);

        $this->assertIsArray($context);
        $this->assertSame('admin', $context['role']);
    }

    public function testLeSuperAdminEstDetecteEnBase(): void
    {
        $this->assertTrue(UserPageAccessService::isSuperAdmin($this->superAdminId));
        $this->assertFalse(UserPageAccessService::isSuperAdmin($this->vendeurId));
        $this->assertFalse(UserPageAccessService::isSuperAdmin($this->adminId));
    }

    // ── Visibilite ───────────────────────────────────────────────────────

    public function testUnUtilisateurSansBlocageExposeToutesLesPages(): void
    {
        $service = new UserPageAccessService();

        $map = $service->getVisibilityMap($this->vendeurId);

        $this->assertCount(21, $map);
        foreach ($map as $pageKey => $visible) {
            $this->assertTrue($visible, 'La page « ' . $pageKey . ' » doit etre visible.');
        }
    }

    public function testUnBlocageRetireLaPage(): void
    {
        $this->bloquer($this->vendeurId, 'rapports');
        $service = new UserPageAccessService();

        $map = $service->getVisibilityMap($this->vendeurId);

        $this->assertFalse($map['rapports'], 'La page bloquee doit etre masquee.');
        $this->assertTrue($map['caisse'], 'Les autres pages restent visibles.');
    }

    public function testUnBlocageNeConcerneQueLUtilisateurConcerne(): void
    {
        $this->bloquer($this->vendeurId, 'rapports');
        $service = new UserPageAccessService();

        $this->assertFalse($service->isPageAllowedForUser('rapports', $this->vendeurId));
        $this->assertTrue(
            $service->isPageAllowedForUser('rapports', $this->adminId),
            'Le blocage ne doit pas toucher les autres utilisateurs de la boutique.'
        );
    }

    public function testLeSuperAdminNEstJamaisBloque(): void
    {
        // On tente de bloquer le super_admin : l'ecriture doit etre refusee.
        $service = new UserPageAccessService();
        $ok = $service->setPageBlocked($this->superAdminId, 'rapports', true, $this->superAdminId);

        $this->assertFalse($ok, 'Le super_admin ne doit jamais pouvoir etre bloque.');

        $this->assertTrue($service->isPageAllowedForUser('rapports', $this->superAdminId));
        $this->assertTrue($service->isPageAllowedForUser('produits', $this->superAdminId));
    }

    public function testUnUtilisateurInconnuEstRefuse(): void
    {
        $service = new UserPageAccessService();

        $this->assertFalse($service->isPageAllowedForUser('rapports', 999999999));
    }

    public function testUnePageInconnueEstRefusee(): void
    {
        $service = new UserPageAccessService();

        $this->assertFalse($service->isPageAllowedForUser('page_inconnue', $this->vendeurId));
    }

    // ── Ecriture ─────────────────────────────────────────────────────────

    public function testSetPageBlockedBloquePuisDebloque(): void
    {
        $service = new UserPageAccessService();

        $this->assertTrue($service->setPageBlocked($this->vendeurId, 'rapports', true, $this->superAdminId));
        $this->assertFalse($service->isPageAllowedForUser('rapports', $this->vendeurId));

        $this->assertTrue($service->setPageBlocked($this->vendeurId, 'rapports', false, $this->superAdminId));
        $this->assertTrue($service->isPageAllowedForUser('rapports', $this->vendeurId));
    }

    public function testSetPageBlockedRefuseUnePageInconnue(): void
    {
        $service = new UserPageAccessService();

        $this->assertFalse($service->setPageBlocked($this->vendeurId, 'page_inconnue', true, $this->superAdminId));
    }

    public function testUpdateBlockedPagesNeTouchePasLesAutresFamilles(): void
    {
        $service = new UserPageAccessService();

        // On bloque d'abord une page HOTEL.
        $this->bloquer($this->vendeurId, 'hotel_reception');

        // On remplace ensuite les blocages de la famille GENERALE.
        $ok = $service->updateBlockedPages(
            $this->vendeurId,
            UserPageAccessService::FAMILY_GENERAL,
            ['rapports' => true, 'caisse' => false],
            $this->superAdminId
        );

        $this->assertTrue($ok);

        $blocked = $service->blockedPageKeys($this->vendeurId);
        $this->assertContains('rapports', $blocked);
        $this->assertNotContains('caisse', $blocked);
        $this->assertContains(
            'hotel_reception',
            $blocked,
            'La famille Hotel ne doit pas etre modifiee.'
        );
    }

    // ── Accès par défaut (floutage de la modale) ───────────────────────

    public function testUnVendeurSimpleNAccedeQuAuxPagesDeSonPerimetre(): void
    {
        // Vendeur : acces_caisse = 1, tous les autres droits à 0.
        $vendeur = [
            'role'             => 'vendeur',
            'hotel_role'       => null,
            'acces_caisse'     => 1,
            'acces_produits'   => 0,
            'acces_historique' => 0,
            'acces_pressing'   => 0,
            'acces_analytics'  => 0,
            'acces_paie'       => 0,
        ];

        // La caisse est son poste de travail : elle reste dans son périmètre.
        $this->assertTrue(UserPageAccessService::isPageAvailableByDefault('caisse', $vendeur));

        // Les modules dont il n'a pas le droit sont hors périmètre.
        $this->assertFalse(UserPageAccessService::isPageAvailableByDefault('produits', $vendeur));
        $this->assertFalse(UserPageAccessService::isPageAvailableByDefault('historique', $vendeur));
        $this->assertFalse(UserPageAccessService::isPageAvailableByDefault('paie', $vendeur));

        // Les pages d'administration sont réservées aux admins.
        $this->assertFalse(UserPageAccessService::isPageAvailableByDefault('utilisateurs', $vendeur));
        $this->assertFalse(UserPageAccessService::isPageAvailableByDefault('parametres', $vendeur));
        $this->assertFalse(UserPageAccessService::isPageAvailableByDefault('categories', $vendeur));
        $this->assertFalse(UserPageAccessService::isPageAvailableByDefault('taxes', $vendeur));

        // `rapports` : la page est visible, mais ses APIs exigent admin.
        $this->assertFalse(UserPageAccessService::isPageAvailableByDefault('rapports', $vendeur));

        // `analytics` : la page redirige les non-admins.
        $this->assertFalse(UserPageAccessService::isPageAvailableByDefault('analytics', $vendeur));
    }

    public function testUnVendeurNAccedePasAuxPagesHotelParDefaut(): void
    {
        $vendeur = [
            'role'       => 'vendeur',
            'hotel_role' => null,
        ];

        $this->assertFalse(UserPageAccessService::isPageAvailableByDefault('hotel_reception', $vendeur));
        $this->assertFalse(UserPageAccessService::isPageAvailableByDefault('hotel_etages', $vendeur));
    }

    public function testUnAdminAAccesAToutesLesPagesParDefaut(): void
    {
        $admin = [
            'role'       => 'admin',
            'hotel_role' => null,
        ];

        foreach (UserPageAccessService::getCatalog() as $pageKey => $_) {
            $this->assertTrue(
                UserPageAccessService::isPageAvailableByDefault($pageKey, $admin),
                'Un admin doit avoir toutes les pages dans son perimeter.'
            );
        }
    }

    public function testLaGouvernanteNAccedeQuAuxPagesHotelDeSonRole(): void
    {
        $gouvernante = [
            'role'       => 'vendeur',
            'hotel_role' => 'gouvernante',
        ];

        // Étages, chambres et clients : son outil de travail.
        $this->assertTrue(UserPageAccessService::isPageAvailableByDefault('hotel_etages', $gouvernante));
        $this->assertTrue(UserPageAccessService::isPageAvailableByDefault('hotel_chambres', $gouvernante));
        $this->assertTrue(UserPageAccessService::isPageAvailableByDefault('hotel_guests', $gouvernante));

        // Réception, facturation, tableau de bord : hors de son périmètre.
        $this->assertFalse(UserPageAccessService::isPageAvailableByDefault('hotel_reception', $gouvernante));
        $this->assertFalse(UserPageAccessService::isPageAvailableByDefault('hotel_facturation', $gouvernante));
        $this->assertFalse(UserPageAccessService::isPageAvailableByDefault('hotel_tableau_bord', $gouvernante));
    }

    public function testLeManagerHotelNAccedePasALaGestionDesRoles(): void
    {
        $manager = [
            'role'       => 'vendeur',
            'hotel_role' => 'manager',
        ];

        $this->assertTrue(UserPageAccessService::isPageAvailableByDefault('hotel_tableau_bord', $manager));
        $this->assertTrue(UserPageAccessService::isPageAvailableByDefault('hotel_reception', $manager));

        // La gestion des rôles hôtel est une page super_admin.
        $this->assertFalse(UserPageAccessService::isPageAvailableByDefault('hotel_roles', $manager));
    }

    public function testUnePageInconnueEstHorsPerimetre(): void
    {
        $this->assertFalse(
            UserPageAccessService::isPageAvailableByDefault('page_inconnue', ['role' => 'admin'])
        );
    }


    // ── Validation des changements ──────────────────────────────────────

    public function testLaValidationRejetteUneFamilleInconnue(): void
    {
        $result = UserPageAccessService::validateChanges('famille_inconnue', [
            'rapports' => true,
        ]);

        $this->assertFalse($result['success']);
    }

    public function testLaValidationRejetteUnePageInconnue(): void
    {
        $result = UserPageAccessService::validateChanges(
            UserPageAccessService::FAMILY_GENERAL,
            ['page_inconnue' => true]
        );

        $this->assertFalse($result['success']);
    }

    public function testLaValidationRejetteUnePageDUneAutreFamille(): void
    {
        $result = UserPageAccessService::validateChanges(
            UserPageAccessService::FAMILY_GENERAL,
            ['hotel_reception' => true]
        );

        $this->assertFalse(
            $result['success'],
            'Une page Hotel ne peut pas etre configuree dans la famille generale.'
        );
    }

    public function testLaValidationRejetteUneValeurNonBooleenne(): void
    {
        $result = UserPageAccessService::validateChanges(
            UserPageAccessService::FAMILY_GENERAL,
            ['rapports' => 'oui']
        );

        $this->assertFalse($result['success']);
    }

    public function testLaValidationAccepteUnPayloadCorrect(): void
    {
        $result = UserPageAccessService::validateChanges(
            UserPageAccessService::FAMILY_GENERAL,
            ['rapports' => true, 'paie' => false]
        );

        $this->assertTrue($result['success']);
    }
}
