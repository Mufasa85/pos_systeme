<?php

namespace Tests\Integration;

use App\Core\Database;
use App\Models\ProductBatch;
use PHPUnit\Framework\TestCase;

/**
 * Gestion du stock lors de l'imputation d'extras depuis /caisse.
 *
 * Un extra consomme PHYSIQUEMENT un produit (minibar, room service…) :
 * le stock doit être contrôlé PUIS déduit au moment de l'imputation,
 * comme pour une vente POS classique.
 *
 * Ce test verrouille le comportement via l'endpoint réel
 * `POST /api/hotel/sejours/{id}/extras-from-cart`
 * (HotelExtrasController::extrasFromCart) :
 *   1. Imputation possible → stock déduit (FIFO sur les lots), ligne
 *      hotel_extras créée.
 *   2. Stock insuffisant → REFUS (HTTP 409 / error='stock_insuffisant'),
 *      stock INTACT et AUCUNE ligne hotel_extras créée (atomicité).
 *   3. Plusieurs articles dont un en rupture → rien n'est déduit pour
 *      les autres non plus (pas de déduction partielle).
 *   4. Les lots périmés ne comptent pas comme stock disponible.
 */
class HotelExtrasStockTest extends TestCase
{
    private static $pdo;
    private $ids = [];

    public static function setUpBeforeClass(): void
    {
        self::$pdo = Database::getInstance()->getConnection();
    }

    protected function setUp(): void
    {
        // ── Boutique avec accès Extras activé ──────────────────────────
        $shopCode = 'EXTSTK_' . strtoupper(bin2hex(random_bytes(3)));
        self::$pdo->prepare(
            "INSERT INTO shops (nom, code, actif, acces_extras) VALUES (?, ?, 1, 1)"
        )->execute(['Boutique Test Extras Stock', $shopCode]);
        $this->ids['shop'] = (int)self::$pdo->lastInsertId();

        // ── Utilisateur caissier rattaché à la boutique ───────────────
        $username = 'extstk_' . strtolower(bin2hex(random_bytes(3)));
        self::$pdo->prepare(
            "INSERT INTO utilisateurs (nom_utilisateur, mot_de_passe, nom_complet, role, actif, shop_id)
             VALUES (?, 'x', 'Caissier Test Extras', 'vendeur', 1, ?)"
        )->execute([$username, $this->ids['shop']]);
        $this->ids['user'] = (int)self::$pdo->lastInsertId();

        // ── Catégorie + taxe (réutilisation si possible) ──────────────
        $catId = (int)self::$pdo->query("SELECT id FROM categories ORDER BY id LIMIT 1")->fetchColumn();
        if (!$catId) {
            self::$pdo->prepare("INSERT INTO categories (category, shop_id) VALUES ('__extstk_cat__', ?)")
                ->execute([$this->ids['shop']]);
            $catId = (int)self::$pdo->lastInsertId();
            $this->ids['categorieCreee'] = $catId;
        }
        $this->ids['category'] = $catId;

        $taxId = (int)self::$pdo->query("SELECT id FROM taxes ORDER BY id LIMIT 1")->fetchColumn();
        $this->ids['tax'] = $taxId ?: 1;

        // ── Produit avec 2 lots (FIFO) ─────────────────────────────────
        $this->ids['produit'] = $this->createProduct('Coca-Cola Test', 10, 2);
        $this->ids['lot1'] = $this->createBatch($this->ids['produit'], 3, '2027-01-01');
        $this->ids['lot2'] = $this->createBatch($this->ids['produit'], 2, null);

        // ── Produit en rupture (aucun lot) ─────────────────────────────
        $this->ids['produitRupture'] = $this->createProduct('Eau Test Rupture', 5, 0);

        // ── Guest / Chambre / Séjour ──────────────────────────────────
        self::$pdo->prepare("INSERT INTO hotel_guests (nom, prenom) VALUES ('__extstk__', 'G')")->execute();
        $this->ids['guest'] = (int)self::$pdo->lastInsertId();

        $numeroChambre = 'TS' . str_pad((string)random_int(1, 99999), 5, '0', STR_PAD_LEFT);
        self::$pdo->prepare(
            "INSERT INTO hotel_chambres (numero, type, prix_base, etat) VALUES (?, 'simple', 1000, 'disponible')"
        )->execute([$numeroChambre]);
        $this->ids['chambre'] = (int)self::$pdo->lastInsertId();

        self::$pdo->prepare(
            "INSERT INTO hotel_sejours (chambre_id, guest_id, date_arrivee, date_depart_prevue, statut, prix_par_nuit)
             VALUES (?, ?, NOW(), DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'en_cours', 1000)"
        )->execute([$this->ids['chambre'], $this->ids['guest']]);
        $this->ids['sejour'] = (int)self::$pdo->lastInsertId();

        // ── Session caissier (les endpoints exigent l'authentification) ─
        $_SESSION['user_id']      = $this->ids['user'];
        $_SESSION['shop_id']      = $this->ids['shop'];
        $_SESSION['role']         = 'vendeur';
        $_SESSION['last_activity'] = time();
    }

    protected function tearDown(): void
    {
        // Nettoyage : extras → séjour → guest → chambre, lots → produit,
        // utilisateur → boutique.
        self::$pdo->prepare("DELETE FROM hotel_extras WHERE sejour_id = ?")->execute([$this->ids['sejour']]);
        self::$pdo->prepare("DELETE FROM hotel_factures WHERE sejour_id = ?")->execute([$this->ids['sejour']]);
        self::$pdo->prepare("DELETE FROM hotel_sejours WHERE id = ?")->execute([$this->ids['sejour']]);
        self::$pdo->prepare("DELETE FROM hotel_guests WHERE id = ?")->execute([$this->ids['guest']]);
        self::$pdo->prepare("DELETE FROM hotel_chambres WHERE id = ?")->execute([$this->ids['chambre']]);

        foreach (['lot1', 'lot2'] as $lotKey) {
            if (!empty($this->ids[$lotKey])) {
                self::$pdo->prepare("DELETE FROM product_batches WHERE id = ?")->execute([$this->ids[$lotKey]]);
            }
        }
        foreach (['produit', 'produitRupture'] as $prodKey) {
            if (!empty($this->ids[$prodKey])) {
                self::$pdo->prepare("DELETE FROM produits WHERE id = ?")->execute([$this->ids[$prodKey]]);
            }
        }
        if (!empty($this->ids['categorieCreee'])) {
            self::$pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$this->ids['categorieCreee']]);
        }
        self::$pdo->prepare("DELETE FROM utilisateurs WHERE id = ?")->execute([$this->ids['user']]);
        self::$pdo->prepare("DELETE FROM shops WHERE id = ?")->execute([$this->ids['shop']]);

        unset($_SESSION['user_id'], $_SESSION['shop_id'], $_SESSION['role'], $_SESSION['last_activity']);
    }

    // ── Helpers de fixtures ───────────────────────────────────────────

    private function createProduct(string $nom, float $prix, float $stockInitial): int
    {
        $codeBarres = 'EXTSTK' . strtoupper(bin2hex(random_bytes(4)));
        self::$pdo->prepare(
            "INSERT INTO produits (code_barres, nom, category_id, shop_id, prix, stock, stock_minimum, taxe_id, product_type)
             VALUES (?, ?, ?, ?, ?, ?, 0, ?, 'unite')"
        )->execute([
            $codeBarres,
            $nom,
            $this->ids['category'],
            $this->ids['shop'],
            $prix,
            $stockInitial,
            $this->ids['tax'],
        ]);
        return (int)self::$pdo->lastInsertId();
    }

    private function createBatch(int $productId, float $stock, ?string $expiration): int
    {
        self::$pdo->prepare(
            "INSERT INTO product_batches (product_id, batch_number, stock, date_expiration, date_reception)
             VALUES (?, ?, ?, ?, CURDATE())"
        )->execute([
            $productId,
            'LOT-' . strtoupper(bin2hex(random_bytes(3))),
            $stock,
            $expiration,
        ]);
        return (int)self::$pdo->lastInsertId();
    }

    /**
     * Appelle l'endpoint réel et retourne [statusCode, payload décodé].
     *
     * Le body est transmis via $_POST : le contrôleur lit `php://input` puis
     * bascule sur $_POST (cf. HotelExtrasController::jsonInput) → le flux
     * est donc identique à celui d'un appel AJAX réel.
     */
    private function callExtrasFromCart(array $articles): array
    {
        $_POST = ['articles' => $articles];

        // En CLI, `http_response_code()` renvoie `false` tant qu'aucun code
        // n'a été explicitement posé : on l'initialise à 200 pour que le
        // contrôleur puisse le surcharger (409, 422…).
        http_response_code(200);

        ob_start();
        try {
            $controller = new \App\Controllers\HotelExtrasController();
            $controller->extrasFromCart(['id' => $this->ids['sejour']]);
        } finally {
            $output = ob_get_clean();
        }
        $_POST = [];

        $status = http_response_code();
        http_response_code(200); // reset pour le test suivant

        return [$status ?: 200, json_decode($output, true)];
    }

    // ── Tests ─────────────────────────────────────────────────────────

    public function testImputationReussieDeduitLeStock(): void
    {
        [$status, $data] = $this->callExtrasFromCart([
            [
                'produit_id' => $this->ids['produit'],
                'nom'        => 'Coca-Cola Test',
                'quantite'   => 2,
                'prix'       => 10,
            ],
        ]);

        $this->assertSame(200, $status, 'Une imputation avec stock suffisant doit réussir.');
        $this->assertTrue($data['success'] ?? false, 'La réponse doit être un succès.');
        $this->assertSame(1, $data['count'] ?? 0, 'Une ligne extra doit être créée.');

        // Le stock total doit avoir été déduit de 2 (5 → 3)
        $batch = new ProductBatch();
        $this->assertEqualsWithDelta(
            3.0,
            $batch->getTotalStock($this->ids['produit']),
            0.001,
            'Le stock doit être déduit de la quantité imputée.'
        );

        // FIFO : le lot le plus ancien (lot1, 3 unités) doit avoir été consommé en premier
        $lot1 = self::$pdo->prepare('SELECT stock FROM product_batches WHERE id = ?');
        $lot1->execute([$this->ids['lot1']]);
        $this->assertEqualsWithDelta(
            1.0,
            (float)$lot1->fetch()['stock'],
            0.001,
            'Le lot le plus ancien doit être décrémenté en premier (FIFO).'
        );
    }

    public function testStockInsuffisantEstRefuseEtNeDeduitRien(): void
    {
        [$status, $data] = $this->callExtrasFromCart([
            [
                'produit_id' => $this->ids['produitRupture'],
                'nom'        => 'Eau Test Rupture',
                'quantite'   => 3,
                'prix'       => 5,
            ],
        ]);

        $this->assertSame(409, $status, 'Un stock insuffisant doit renvoyer HTTP 409.');
        $this->assertFalse($data['success'] ?? true, 'La réponse doit être un échec.');
        $this->assertSame('stock_insuffisant', $data['error'] ?? null, 'Le code erreur doit identifier la cause.');
        $this->assertStringContainsString('Eau Test Rupture', $data['message'] ?? '', 'Le message doit nommer le produit en rupture.');

        // Aucune ligne extra ne doit avoir été créée
        $count = self::$pdo->prepare('SELECT COUNT(*) FROM hotel_extras WHERE sejour_id = ?');
        $count->execute([$this->ids['sejour']]);
        $this->assertSame(0, (int)$count->fetchColumn(), 'Aucune ligne hotel_extras ne doit être créée en cas de rupture.');
    }

    public function testAucuneDeductionPartielleQuandUnProduitEstEnRupture(): void
    {
        [$status] = $this->callExtrasFromCart([
            // Produit OK en premier : ne doit PAS être déduit si le 2e est en rupture
            [
                'produit_id' => $this->ids['produit'],
                'nom'        => 'Coca-Cola Test',
                'quantite'   => 1,
                'prix'       => 10,
            ],
            [
                'produit_id' => $this->ids['produitRupture'],
                'nom'        => 'Eau Test Rupture',
                'quantite'   => 1,
                'prix'       => 5,
            ],
        ]);

        $this->assertSame(409, $status, 'Le lot complet doit être refusé si un seul produit est en rupture.');

        $batch = new ProductBatch();
        $this->assertEqualsWithDelta(
            5.0,
            $batch->getTotalStock($this->ids['produit']),
            0.001,
            'Aucun stock ne doit être déduit si la transaction est annulée (atomicité).'
        );
    }

    public function testStockDisponibleExclutLesLotsPerimes(): void
    {
        // Produit avec un lot périmé uniquement
        $produitPerime = $this->createProduct('Produit Perime Test', 5, 1);
        $this->createBatch($produitPerime, 4, '2020-01-01');

        $batch = new ProductBatch();
        $this->assertEqualsWithDelta(
            0.0,
            $batch->getAvailableStock($produitPerime),
            0.001,
            'Un lot périmé ne doit pas compter comme stock disponible.'
        );
        $this->assertEqualsWithDelta(
            4.0,
            $batch->getTotalStock($produitPerime),
            0.001,
            'Le stock total inclut les lots périmés (information), mais pas le disponible.'
        );

        // Imputation refusée : le stock disponible est nul
        [$status] = $this->callExtrasFromCart([
            [
                'produit_id' => $produitPerime,
                'nom'        => 'Produit Perime Test',
                'quantite'   => 1,
                'prix'       => 5,
            ],
        ]);
        $this->assertSame(409, $status, 'Un produit dont le stock est périmé ne doit pas être imputable.');

        // Nettoyage du produit supplémentaire
        self::$pdo->prepare('DELETE FROM product_batches WHERE product_id = ?')->execute([$produitPerime]);
        self::$pdo->prepare('DELETE FROM produits WHERE id = ?')->execute([$produitPerime]);
    }
}
