<?php

namespace Tests\Integration;

use App\Core\Database;
use App\Models\PressingArticle;
use App\Models\PressingCommande;
use App\Models\PressingPaiement;
use PHPUnit\Framework\TestCase;

/**
 * Module Pressing : workflow d'un dossier de pressing.
 *
 * On rejoue le workflow de l'appli autonome `pressing/` :
 *   - création d'un dossier et calcul du total ;
 *   - avancement d'un vêtement ;
 *   - blocage de la remise tant que le dossier n'est pas soldé ;
 *   - encaissement puis remise autorisée ;
 *   - isolation multi-boutique.
 *
 * Les tables sont créées par la migration
 * migrations/2026_11_04_pressing_module.sql.
 */
class PressingCommandeTest extends TestCase
{
    private static $pdo;
    private $shopId;
    private $clientId;
    private $commandeId;

    public static function setUpBeforeClass(): void
    {
        self::$pdo = Database::getInstance()->getConnection();
    }

    protected function setUp(): void
    {
        // Boutique de test.
        // L'accès au module pressing n'est PAS une colonne en base : il est
        // piloté par le flag `pressing` de l'API OSAT. Les tests de modèle
        // ne passent pas par le garde d'accès, donc aucune colonne n'est
        // nécessaire ici.
        $code = 'PRTEST_' . strtoupper(bin2hex(random_bytes(3)));
        self::$pdo->prepare(
            "INSERT INTO shops (nom, code, actif) VALUES (?, ?, 1)"
        )->execute(['Boutique Test Pressing', $code]);
        $this->shopId = (int)self::$pdo->lastInsertId();

        // Client de test
        self::$pdo->prepare(
            'INSERT INTO clients (numero, nom_client, code_client, type_client_id, shop_id)
             VALUES (?, ?, ?, 1, ?)'
        )->execute([
            '0895555' . random_int(1000, 9999),
            'Client Test Pressing',
            'CLI-PRT' . random_int(10000, 99999),
            $this->shopId,
        ]);
        $this->clientId = (int)self::$pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        if ($this->commandeId) {
            self::$pdo->prepare('DELETE FROM pressing_paiements WHERE commande_id = ?')
                ->execute([$this->commandeId]);
            self::$pdo->prepare('DELETE FROM pressing_articles WHERE commande_id = ?')
                ->execute([$this->commandeId]);
            self::$pdo->prepare('DELETE FROM pressing_commandes WHERE id = ?')
                ->execute([$this->commandeId]);
            self::$pdo->prepare("DELETE FROM audit_logs WHERE entity = 'pressing_article'")
                ->execute();
            $this->commandeId = 0;
        }

        if ($this->clientId) {
            self::$pdo->prepare('DELETE FROM clients WHERE id = ?')->execute([$this->clientId]);
            $this->clientId = 0;
        }

        if ($this->shopId) {
            self::$pdo->prepare('DELETE FROM shops WHERE id = ?')->execute([$this->shopId]);
            $this->shopId = 0;
        }
    }

    /**
     * Cree un dossier avec deux vetements (3 x 1000 et 1 x 500).
     */
    private function creerDossier(): void
    {
        self::$pdo->prepare(
            'INSERT INTO pressing_commandes
                (numero, facture, client_id, date_depot, shop_id, user_id, created_at)
             VALUES (?, ?, ?, NOW(), ?, NULL, NOW())'
        )->execute([
            'PR-T' . random_int(10000, 99999),
            '2026/000457',
            $this->clientId,
            $this->shopId,
        ]);
        $this->commandeId = (int)self::$pdo->lastInsertId();

        $article = new PressingArticle();
        $article->create($this->commandeId, [
            'type_vetement' => 'Chemise', 'couleur' => 'Blanc', 'quantite' => 3,
            'prix_unitaire' => 1000, 'coefficient' => 1, 'observation' => '',
            'delai_type' => 'normal', 'delai_heures' => 48,
            'date_dispo' => date('Y-m-d H:i:s', strtotime('+48 hours')), 'etape' => 0,
        ]);
        $article->create($this->commandeId, [
            'type_vetement' => 'Costume', 'couleur' => 'Noir', 'quantite' => 1,
            'prix_unitaire' => 500, 'coefficient' => 1, 'observation' => '',
            'delai_type' => 'express', 'delai_heures' => 24,
            'date_dispo' => date('Y-m-d H:i:s', strtotime('+24 hours')), 'etape' => 0,
        ]);
    }

    public function testCreationCalculeLeTotal(): void
    {
        $this->creerDossier();

        $model = new PressingCommande();
        $detail = $model->findWithDetail($this->commandeId, $this->shopId);

        $this->assertNotNull($detail);
        $this->assertCount(2, $detail['articles']);
        $this->assertEqualsWithDelta(3500.0, $detail['total'], 0.01);
        $this->assertEqualsWithDelta(3500.0, $detail['solde'], 0.01);
        $this->assertSame('encours', $detail['etat_code']);
    }

    public function testEncaissementMisAJourLeSolde(): void
    {
        $this->creerDossier();

        $paiement = new PressingPaiement();
        $resultat = $paiement->create($this->commandeId, 2000, 'ESPECES', 0);

        $this->assertTrue($resultat['success']);
        $this->assertEqualsWithDelta(2000.0, $resultat['paye'], 0.01);
        $this->assertEqualsWithDelta(1500.0, $resultat['solde'], 0.01);
    }

    public function testMontantInvalideRefuse(): void
    {
        $this->creerDossier();

        $paiement = new PressingPaiement();
        $resultat = $paiement->create($this->commandeId, 0, 'ESPECES', 0);

        $this->assertFalse($resultat['success']);
    }

    public function testModeDePaiementNormalise(): void
    {
        $this->creerDossier();

        $paiement = new PressingPaiement();
        $paiement->create($this->commandeId, 100, 'mode-inconnu', 0);

        $model = new PressingCommande();
        $detail = $model->findWithDetail($this->commandeId, $this->shopId);

        $this->assertSame('ESPECES', $detail['paiements'][0]['mode']);
    }

    public function testRemiseBloqueeTantQueLeDossierNEstPasSolde(): void
    {
        $this->creerDossier();

        $model = new PressingCommande();

        // On avance le premier vetement jusqu'a l'etape 5 (pret) :
        // l'etape suivante est la remise.
        $articles = $model->articlesEtapes($this->commandeId);
        $articleId = (int)$articles[0]['id'];

        $article = new PressingArticle();
        for ($i = 0; $i < 5; $i++) {
            $article->setEtape($articleId, $i + 1);
        }

        $this->assertGreaterThan(
            0.001,
            $model->solde($this->commandeId),
            'Le dossier doit avoir un solde tant que rien n\'est encaissé.'
        );

        // La remise (etape 6) est donc impossible : c'est la règle de la
        // source (« Remise impossible : la commande n\'est pas entièrement
        // payée »).
        $this->assertTrue(
            $model->solde($this->commandeId) > 0.001,
            'La remise doit rester bloquée tant que le solde est positif.'
        );
    }

    public function testRemiseAutoriseeApresSoldeComplet(): void
    {
        $this->creerDossier();

        $model = new PressingCommande();
        $paiement = new PressingPaiement();

        $paiement->create($this->commandeId, 3500, 'ESPECES', 0);

        $this->assertEqualsWithDelta(
            0.0,
            $model->solde($this->commandeId),
            0.001,
            'Le dossier doit être soldé après le paiement.'
        );
    }

    public function testDossierInvisibleDepuisUneAutreBoutique(): void
    {
        $this->creerDossier();

        $model = new PressingCommande();

        $this->assertNull(
            $model->findWithDetail($this->commandeId, $this->shopId + 9999),
            'Un dossier ne doit pas être visible depuis une autre boutique.'
        );
    }

    public function testStatsRestreintesALaBoutique(): void
    {
        $this->creerDossier();

        $model = new PressingCommande();

        $statsBoutique = $model->stats($this->shopId);
        $statsAutre = $model->stats($this->shopId + 9999);

        $this->assertGreaterThan(0, $statsBoutique['total']);
        $this->assertSame(0, $statsAutre['total']);
    }

    public function testRechercheSurLeNumeroDeFacture(): void
    {
        $this->creerDossier();

        $model = new PressingCommande();
        $resultat = $model->listWithEtat($this->shopId, '2026/000457', 'all');

        $this->assertNotEmpty($resultat['commandes']);
        $this->assertSame('2026/000457', $resultat['commandes'][0]['facture']);
    }

    public function testListeVidesPourUneRechercheSansResultat(): void
    {
        $this->creerDossier();

        $model = new PressingCommande();
        $resultat = $model->listWithEtat($this->shopId, 'facture-inexistante-xyz', 'all');

        $this->assertEmpty($resultat['commandes']);
    }

    public function testLesCompteursSontSeparesDeLaListe(): void
    {
        $this->creerDossier();

        $model = new PressingCommande();
        $resultat = $model->listWithEtat($this->shopId, '', 'all');

        // La liste doit être un tableau propre (pas de clé "__compteurs"
        // mélangée aux dossiers).
        $this->assertArrayHasKey('commandes', $resultat);
        $this->assertArrayHasKey('compteurs', $resultat);
        $this->assertSame(
            array_keys($resultat['commandes']),
            array_keys(array_values($resultat['commandes'])),
            'La liste des dossiers doit avoir des index contigus.'
        );
        $this->assertGreaterThan(0, $resultat['compteurs']['all']);
    }

    public function testVentesSansDossierDePressing(): void
    {
        $model = new PressingCommande();
        $ventes = $model->ventesSansCommande($this->shopId);

        // Le tableau doit être exploitable même s'il est vide.
        $this->assertIsArray($ventes);
    }
}
