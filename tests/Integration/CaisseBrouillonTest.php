<?php

namespace Tests\Integration;

use App\Core\Database;
use App\Models\CaisseBrouillon;
use PHPUnit\Framework\TestCase;

/**
 * Brouillons de panier de la caisse.
 *
 * Ce test verrouille les règles métier du brouillon via le contrôleur réel
 * (CaisseBrouillonController) :
 *   1. Création : un panier non vide est enregistré, avec un libellé auto
 *      si aucun n'est fourni.
 *   2. Un panier VIDE est refusé (422).
 *   3. Le stock n'est JAMAIS déduit à la sauvegarde (règle Q1).
 *   4. Le scoping boutique : un brouillon d'une autre boutique est INVISIBLE
 *      (404), pas seulement interdit (403).
 *   5. La reprise renvoie le payload, et le stock n'est TOUJOURS pas déduit.
 *   6. Un brouillon converti n'est JAMAIS purgé (règle Q3) ; un brouillon
 *      'ouvert' trop ancien EST purgé.
 *   7. Seul l'auteur ou un admin peut supprimer un brouillon.
 */
class CaisseBrouillonTest extends TestCase
{
    private static $pdo;
    private $ids = [];

    public static function setUpBeforeClass(): void
    {
        self::$pdo = Database::getInstance()->getConnection();
    }

    protected function setUp(): void
    {
        // ── Boutique A (active) ──────────────────────────────────────
        self::$pdo->prepare(
            "INSERT INTO shops (nom, code, actif) VALUES (?, ?, 1)"
        )->execute(['Boutique Brouillons A', 'BRD_A_' . strtoupper(bin2hex(random_bytes(3)))]);
        $this->ids['shopA'] = (int)self::$pdo->lastInsertId();

        // ── Boutique B (pour tester le scoping) ───────────────────────
        self::$pdo->prepare(
            "INSERT INTO shops (nom, code, actif) VALUES (?, ?, 1)"
        )->execute(['Boutique Brouillons B', 'BRD_B_' . strtoupper(bin2hex(random_bytes(3)))]);
        $this->ids['shopB'] = (int)self::$pdo->lastInsertId();

        // ── Caissier de la boutique A ────────────────────────────────
        $username = 'brd_' . strtolower(bin2hex(random_bytes(3)));
        self::$pdo->prepare(
            "INSERT INTO utilisateurs (nom_utilisateur, mot_de_passe, nom_complet, role, actif, shop_id)
             VALUES (?, 'x', 'Caissier Brouillons', 'vendeur', 1, ?)"
        )->execute([$username, $this->ids['shopA']]);
        $this->ids['user'] = (int)self::$pdo->lastInsertId();

        // ── Un second caissier (collègue) de la boutique A ───────────
        $username2 = 'brd_' . strtolower(bin2hex(random_bytes(3)));
        self::$pdo->prepare(
            "INSERT INTO utilisateurs (nom_utilisateur, mot_de_passe, nom_complet, role, actif, shop_id)
             VALUES (?, 'x', 'Collègue Brouillons', 'vendeur', 1, ?)"
        )->execute([$username2, $this->ids['shopA']]);
        $this->ids['collegue'] = (int)self::$pdo->lastInsertId();

        // ── Produit avec stock (pour vérifier l'absence de déduction) ─
        $catId = (int)self::$pdo->query("SELECT id FROM categories ORDER BY id LIMIT 1")->fetchColumn();
        if (!$catId) {
            self::$pdo->prepare("INSERT INTO categories (category, shop_id) VALUES ('__brd_cat__', ?)")
                ->execute([$this->ids['shopA']]);
            $catId = (int)self::$pdo->lastInsertId();
            $this->ids['categorieCreee'] = $catId;
        }
        $taxId = (int)self::$pdo->query("SELECT id FROM taxes ORDER BY id LIMIT 1")->fetchColumn();

        $codeBarres = 'BRDP' . strtoupper(bin2hex(random_bytes(4)));
        self::$pdo->prepare(
            "INSERT INTO produits (code_barres, nom, category_id, shop_id, prix, stock, stock_minimum, taxe_id, product_type)
             VALUES (?, 'Produit Brouillon Test', ?, ?, 10, 100, 0, ?, 'unite')"
        )->execute([$codeBarres, $catId, $this->ids['shopA'], $taxId ?: 1]);
        $this->ids['produit'] = (int)self::$pdo->lastInsertId();

        // ── Session caissier ─────────────────────────────────────────
        $_SESSION['user_id']       = $this->ids['user'];
        $_SESSION['shop_id']       = $this->ids['shopA'];
        $_SESSION['role']          = 'vendeur';
        $_SESSION['last_activity'] = time();
    }

    protected function tearDown(): void
    {
        self::$pdo->prepare("DELETE FROM caisse_brouillons WHERE shop_id IN (?, ?)")
            ->execute([$this->ids['shopA'], $this->ids['shopB']]);

        // Ventes de test (créées par creerVente())
        self::$pdo->prepare("DELETE FROM ventes WHERE shop_id = ?")->execute([$this->ids['shopA']]);

        self::$pdo->prepare("DELETE FROM produits WHERE id = ?")->execute([$this->ids['produit']]);
        if (!empty($this->ids['categorieCreee'])) {
            self::$pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$this->ids['categorieCreee']]);
        }
        foreach (['user', 'collegue'] as $key) {
            if (!empty($this->ids[$key])) {
                self::$pdo->prepare("DELETE FROM utilisateurs WHERE id = ?")->execute([$this->ids[$key]]);
            }
        }
        foreach (['shopA', 'shopB'] as $key) {
            self::$pdo->prepare("DELETE FROM shops WHERE id = ?")->execute([$this->ids[$key]]);
        }

        unset($_SESSION['user_id'], $_SESSION['shop_id'], $_SESSION['role'], $_SESSION['last_activity']);
    }

    // ── Helpers ─────────────────────────────────────────────────────

    /**
     * Appelle le contrôleur réel et retourne [statusCode, payload décodé].
     *
     * Le body est transmis via $_POST : le contrôleur lit `php://input` puis
     * bascule sur $_POST (cf. CaisseBrouillonController::jsonInput) → le flux
     * est donc identique à celui d'un appel AJAX réel.
     */
    private function call(string $method, array $params = [], array $post = []): array
    {
        $_POST = $post;

        // En CLI, `http_response_code()` renvoie `false` tant qu'aucun code
        // n'a été explicitement posé : on l'initialise à 200 pour que le
        // contrôleur puisse le surcharger (403, 404, 422…).
        http_response_code(200);

        ob_start();
        try {
            $controller = new \App\Controllers\CaisseBrouillonController();
            $controller->$method($params);
        } finally {
            $output = ob_get_clean();
        }
        $_POST = [];

        $status = http_response_code();
        http_response_code(200); // reset pour le test suivant

        return [$status ?: 200, json_decode($output, true)];
    }

    /**
     * Articles de test (panier non vide).
     */
    private function articles(): array
    {
        return [
            [
                'produit_id' => $this->ids['produit'],
                'nom'        => 'Produit Brouillon Test',
                'prix'       => 10,
                'quantite'   => 3,
                'tax_rate'   => 16,
            ],
        ];
    }

    /**
     * Stock total du produit de test (doit rester intact).
     */
    private function stockProduit(): float
    {
        $stmt = self::$pdo->prepare('SELECT stock FROM produits WHERE id = ?');
        $stmt->execute([$this->ids['produit']]);
        $row = $stmt->fetch();

        return (float)($row['stock'] ?? 0);
    }

    /**
     * Crée une vente minimale et renvoie son ID.
     *
     * Nécessaire pour `markConverted()` : la FK `caisse_brouillons.vente_id`
     * pointe vers `ventes.id`, on ne peut donc pas pointer vers un ID fictif.
     */
    private function creerVente(): int
    {
        $numero = 'BRD/' . strtoupper(bin2hex(random_bytes(4)));
        self::$pdo->prepare(
            "INSERT INTO ventes (numero_facture, invoice_type, sous_total_ht, tva, total, vendeur_id, shop_id)
             VALUES (?, 'FV', 10, 0, 10, ?, ?)"
        )->execute([$numero, $this->ids['user'], $this->ids['shopA']]);

        return (int)self::$pdo->lastInsertId();
    }

    // ── Tests ───────────────────────────────────────────────────────

    public function testCreationEnregistreLePanierAvecLibelleAuto(): void
    {
        $stockAvant = $this->stockProduit();

        [$status, $data] = $this->call('store', [], [
            'articles' => $this->articles(),
        ]);

        $this->assertSame(200, $status, 'La création doit réussir.');
        $this->assertTrue($data['success'] ?? false, 'La réponse doit être un succès.');
        $this->assertGreaterThan(0, $data['id'] ?? 0, 'Un ID de brouillon doit être renvoyé.');

        // Libellé auto-généré à partir des articles
        $this->assertNotEmpty($data['libelle'] ?? '', 'Un libellé auto doit être généré.');
        $this->assertStringContainsString(
            'Produit Brouillon Test',
            $data['libelle'],
            'Le libellé auto doit citer le produit.'
        );

        // Le brouillon est bien en base, avec le bon total
        $brouillon = (new CaisseBrouillon())->findById((int)$data['id'], $this->ids['shopA']);
        $this->assertNotNull($brouillon, 'Le brouillon doit être persisté.');
        $this->assertSame(
            CaisseBrouillon::STATUS_OUVERT,
            $brouillon['status'],
            'Un nouveau brouillon est "ouvert".'
        );
        $this->assertEqualsWithDelta(
            30.0,
            (float)$brouillon['total'],
            0.001,
            'Le total doit être prix × quantité.'
        );
        $this->assertSame(1, (int)$brouillon['nb_articles'], 'Le nombre d\'articles doit être mémorisé.');

        // ⚠️ Règle Q1 : le stock n'est PAS déduit à la sauvegarde.
        $this->assertEqualsWithDelta(
            $stockAvant,
            $this->stockProduit(),
            0.001,
            'Un brouillon ne doit JAMAIS déduire le stock.'
        );
    }

    public function testPanierVideEstRefuse(): void
    {
        [$status, $data] = $this->call('store', [], ['articles' => []]);

        $this->assertSame(422, $status, 'Un panier vide doit être refusé (422).');
        $this->assertFalse($data['success'] ?? true, 'La réponse doit être un échec.');
    }

    public function testBrouillonDUneAutreBoutiqueEstInvisible(): void
    {
        // On crée un brouillon dans la boutique B
        self::$pdo->prepare(
            "INSERT INTO caisse_brouillons (shop_id, user_id, libelle, payload, total, nb_articles)
             VALUES (?, ?, 'Brouillon B', ?, 10, 1)"
        )->execute([
            $this->ids['shopB'],
            $this->ids['user'],
            json_encode(['articles' => [['produit_id' => 1, 'nom' => 'X', 'prix' => 10, 'quantite' => 1]]]),
        ]);
        $idB = (int)self::$pdo->lastInsertId();

        // Le caissier de la boutique A ne doit PAS le voir (404, pas 403 :
        // on ne confirme pas l'existence d'une donnée d'une autre boutique).
        [$status, $data] = $this->call('show', ['id' => $idB]);
        $this->assertSame(404, $status, 'Un brouillon d\'une autre boutique doit être invisible (404).');
        $this->assertFalse($data['success'] ?? true, 'La réponse doit être un échec.');

        // Et il ne doit pas apparaître dans la liste
        [$statusList, $dataList] = $this->call('index');
        $this->assertSame(200, $statusList);
        $ids = array_column($dataList['data'] ?? [], 'id');
        $this->assertNotContains($idB, $ids, 'La liste doit être scoped à la boutique.');
    }

    public function testRestaurationRenvoieLePayloadSansDeduireLeStock(): void
    {
        [$status, $data] = $this->call('store', [], [
            'articles' => $this->articles(),
            'libelle'  => 'Panier test',
        ]);
        $this->assertSame(200, $status);

        $id = (int)$data['id'];
        $stockAvant = $this->stockProduit();

        [$statusRestore, $dataRestore] = $this->call('restore', ['id' => $id]);

        $this->assertSame(200, $statusRestore, 'La restauration doit réussir.');
        $this->assertTrue($dataRestore['success'] ?? false);
        $this->assertCount(1, $dataRestore['payload']['articles'] ?? [], 'Le payload doit contenir les articles.');
        $this->assertSame(
            $this->ids['produit'],
            (int)($dataRestore['payload']['articles'][0]['produit_id'] ?? 0),
            'Le produit doit être restitué.'
        );
        $this->assertEqualsWithDelta(
            3.0,
            (float)($dataRestore['payload']['articles'][0]['quantite'] ?? 0),
            0.001,
            'La quantité doit être restituée.'
        );

        // ⚠️ Toujours pas de déduction : la facture n'est pas encore validée.
        $this->assertEqualsWithDelta(
            $stockAvant,
            $this->stockProduit(),
            0.001,
            'La reprise ne doit pas déduire le stock.'
        );
    }

    public function testBrouillonConvertiNestJamaisPurgé(): void
    {
        // Un brouillon 'converti' (lié à une facture) : il doit survivre à la purge.
        self::$pdo->prepare(
            "INSERT INTO caisse_brouillons (shop_id, user_id, libelle, payload, total, nb_articles, status, updated_at)
             VALUES (?, ?, 'Brouillon facturé', ?, 10, 1, 'converti', DATE_SUB(NOW(), INTERVAL 400 DAY))"
        )->execute([
            $this->ids['shopA'],
            $this->ids['user'],
            json_encode(['articles' => [['produit_id' => 1, 'nom' => 'X', 'prix' => 10, 'quantite' => 1]]]),
        ]);
        $idConverti = (int)self::$pdo->lastInsertId();

        // Un brouillon 'ouvert' ancien : celui-là doit être purgé.
        self::$pdo->prepare(
            "INSERT INTO caisse_brouillons (shop_id, user_id, libelle, payload, total, nb_articles, status, updated_at)
             VALUES (?, ?, 'Brouillon abandonné', ?, 10, 1, 'ouvert', DATE_SUB(NOW(), INTERVAL 400 DAY))"
        )->execute([
            $this->ids['shopA'],
            $this->ids['user'],
            json_encode(['articles' => [['produit_id' => 1, 'nom' => 'X', 'prix' => 10, 'quantite' => 1]]]),
        ]);
        $idAbandonne = (int)self::$pdo->lastInsertId();

        $model = new CaisseBrouillon();
        $purges = $model->purgeExpired(CaisseBrouillon::PURGE_JOURS);

        $this->assertGreaterThanOrEqual(1, $purges, 'Le brouillon abandonné doit être purgé.');

        // Le brouillon converti doit toujours exister
        $converti = self::$pdo->prepare('SELECT id FROM caisse_brouillons WHERE id = ?');
        $converti->execute([$idConverti]);
        $this->assertNotFalse(
            $converti->fetch(),
            'Un brouillon converti ne doit JAMAIS être purgé (il est lié à une facture).'
        );

        // Le brouillon abandonné doit avoir disparu
        $abandonne = self::$pdo->prepare('SELECT id FROM caisse_brouillons WHERE id = ?');
        $abandonne->execute([$idAbandonne]);
        $this->assertFalse(
            $abandonne->fetch(),
            'Un brouillon ouvert de plus de 30 jours doit être purgé.'
        );
    }

    public function testSeulLAuteurOuUnAdminPeutSupprimer(): void
    {
        [$status, $data] = $this->call('store', [], [
            'articles' => $this->articles(),
        ]);
        $id = (int)$data['id'];

        // Un COLLÈGUE (autre caissier de la même boutique) ne peut pas supprimer.
        $_SESSION['user_id'] = $this->ids['collegue'];
        [$statusColl, $dataColl] = $this->call('destroy', ['id' => $id]);
        $this->assertSame(403, $statusColl, 'Un collègue ne doit pas pouvoir supprimer le brouillon d\'autrui.');
        $this->assertFalse($dataColl['success'] ?? true);

        // L'auteur, lui, peut.
        $_SESSION['user_id'] = $this->ids['user'];
        [$statusAuteur, $dataAuteur] = $this->call('destroy', ['id' => $id]);
        $this->assertSame(200, $statusAuteur, 'L\'auteur doit pouvoir supprimer son brouillon.');
        $this->assertTrue($dataAuteur['success'] ?? false);
    }

    public function testCompteurNeCompteQueLesBrouillonsOuverts(): void
    {
        [$status, $data] = $this->call('store', [], ['articles' => $this->articles()]);
        $this->assertSame(200, $status);
        $id = (int)$data['id'];

        [$statusCompteur, $dataCompteur] = $this->call('compteur');
        $this->assertSame(200, $statusCompteur);
        $this->assertSame(1, (int)($dataCompteur['count'] ?? -1), 'Le compteur doit voir 1 brouillon ouvert.');

        // Une fois converti, il ne compte plus.
        (new CaisseBrouillon())->markConverted($id, $this->creerVente());

        [, $dataCompteur2] = $this->call('compteur');
        $this->assertSame(0, (int)($dataCompteur2['count'] ?? -1), 'Un brouillon converti ne compte plus.');
    }
}
