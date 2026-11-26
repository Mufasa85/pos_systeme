<?php

namespace Tests\Integration;

use App\Core\Database;
use App\Models\Product;
use PHPUnit\Framework\TestCase;

/**
 * Non-régression du correctif de charge R5 : le tableau de bord ne charge
 * plus tout le catalogue.
 *
 * `countFiltered()` et `getLowStock()` doivent rendre EXACTEMENT ce que
 * rendaient :
 *   - `getAll($shopId)` puis `count($produits)` pour le compteur ;
 *   - `getAll($shopId)` puis `array_filter(..., total_stock <= stock_minimum)`
 *     pour la liste des stocks faibles.
 *
 * C'est le garde-fou : si quelqu'un change le filtre des boutiques
 * inactives ou la définition du stock faible, ces assertions tombent.
 */
class ProductDashboardCountTest extends TestCase
{
    private static $pdo;
    private $shopId;
    private $inactiveShopId;
    private $categoryId;
    private $createdBarcodes = [];

    public static function setUpBeforeClass(): void
    {
        self::$pdo = Database::getInstance()->getConnection();
    }

    protected function setUp(): void
    {
        $code = 'DASH_' . strtoupper(bin2hex(random_bytes(3)));
        self::$pdo->prepare(
            'INSERT INTO shops (nom, code, actif) VALUES (?, ?, 1)'
        )->execute(['Boutique Dashboard', $code]);
        $this->shopId = (int)self::$pdo->lastInsertId();

        $codeInactive = 'DASHI_' . strtoupper(bin2hex(random_bytes(3)));
        self::$pdo->prepare(
            'INSERT INTO shops (nom, code, actif) VALUES (?, ?, 0)'
        )->execute(['Boutique Dashboard Inactive', $codeInactive]);
        $this->inactiveShopId = (int)self::$pdo->lastInsertId();

        self::$pdo->prepare(
            'INSERT INTO categories (category, shop_id) VALUES (?, ?)'
        )->execute(['CatDashboard', $this->shopId]);
        $this->categoryId = (int)self::$pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        foreach ($this->createdBarcodes as $barcode) {
            $ids = self::$pdo->prepare('SELECT id FROM produits WHERE code_barres = ?');
            $ids->execute([$barcode]);
            foreach ($ids->fetchAll() as $row) {
                self::$pdo->prepare('DELETE FROM product_batches WHERE product_id = ?')->execute([$row['id']]);
                self::$pdo->prepare('DELETE FROM produits WHERE id = ?')->execute([$row['id']]);
            }
        }
        $this->createdBarcodes = [];

        if ($this->categoryId) {
            self::$pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$this->categoryId]);
            $this->categoryId = 0;
        }
        foreach ([$this->shopId, $this->inactiveShopId] as $sid) {
            if ($sid) {
                self::$pdo->prepare('DELETE FROM shops WHERE id = ?')->execute([$sid]);
            }
        }
        $this->shopId = 0;
        $this->inactiveShopId = 0;
    }

    /**
     * Crée un produit avec ses lots.
     *
     * @param array<int,array{stock:float,exp:string|null}> $batches
     */
    private function createProduct(string $barcode, ?int $shopId, float $stockMinimum, array $batches): int
    {
        $this->createdBarcodes[] = $barcode;
        self::$pdo->prepare(
            'INSERT INTO produits (code_barres, nom, category_id, shop_id, prix, stock, stock_minimum, taxe_id)
             VALUES (?, ?, ?, ?, 100, 0, ?, 1)'
        )->execute([$barcode, 'Produit ' . $barcode, $this->categoryId, $shopId, $stockMinimum]);
        $id = (int)self::$pdo->lastInsertId();

        $total = 0.0;
        foreach ($batches as $b) {
            self::$pdo->prepare(
                'INSERT INTO product_batches (product_id, batch_number, stock, date_expiration, date_reception)
                 VALUES (?, ?, ?, ?, CURDATE())'
            )->execute([$id, 'LOT-' . uniqid(), $b['stock'], $b['exp']]);
            $total += $b['stock'];
        }
        // `produits.stock` est la colonne dénormalisée : on l'aligne.
        self::$pdo->prepare('UPDATE produits SET stock = ? WHERE id = ?')->execute([$total, $id]);

        return $id;
    }

    public function testLeCompteurEstEgalAuCompteDeGetAll(): void
    {
        // 3 produits en stock normal, 1 en stock faible, 1 en boutique inactive.
        $this->createProduct('DSH' . random_int(100000, 999999), $this->shopId, 5, [['stock' => 100.0, 'exp' => null]]);
        $this->createProduct('DSH' . random_int(100000, 999999), $this->shopId, 5, [['stock' => 100.0, 'exp' => null]]);
        $this->createProduct('DSH' . random_int(100000, 999999), $this->shopId, 5, [['stock' => 100.0, 'exp' => null]]);
        $this->createProduct('DSH' . random_int(100000, 999999), $this->shopId, 5, [['stock' => 2.0, 'exp' => null]]);
        $this->createProduct('DSHI' . random_int(100000, 999999), $this->inactiveShopId, 5, [['stock' => 100.0, 'exp' => null]]);

        $product = new Product();

        $expected = count($product->getAll($this->shopId));
        $actual = $product->countFiltered($this->shopId);

        $this->assertSame($expected, $actual, 'Le compteur doit être identique à count(getAll()).');
        $this->assertSame(4, $actual, 'Les 4 produits de la boutique active doivent être comptés.');
    }

    public function testLesStocksFaiblesSontIdentiquesALAncienArrayFilter(): void
    {
        // Seuils : 5. Stock 2 → faible. Stock 5 → faible (<=). Stock 6 → OK.
        $faibleId = $this->createProduct('DSF' . random_int(100000, 999999), $this->shopId, 5, [['stock' => 2.0, 'exp' => null]]);
        $limiteId = $this->createProduct('DSF' . random_int(100000, 999999), $this->shopId, 5, [['stock' => 5.0, 'exp' => null]]);
        $this->createProduct('DSF' . random_int(100000, 999999), $this->shopId, 5, [['stock' => 6.0, 'exp' => null]]);
        // Sans lot : stock 0 → faible.
        $this->createProduct('DSF' . random_int(100000, 999999), $this->shopId, 5, []);

        $product = new Product();

        // Ancien comportement (à reproduire à l'identique).
        $produits = $product->getAll($this->shopId);
        $expected = array_filter($produits, function ($p) {
            $totalStock = $p['total_stock'] ?? $p['stock'];
            return $totalStock <= $p['stock_minimum'];
        });
        $expectedIds = array_map('intval', array_column($expected, 'id'));
        sort($expectedIds);

        $actual = $product->getLowStock($this->shopId);
        $actualIds = array_map('intval', array_column($actual, 'id'));
        sort($actualIds);

        $this->assertSame(
            $expectedIds,
            $actualIds,
            'La liste des stocks faibles doit être identique à l\'ancien array_filter().'
        );
        $this->assertContains($faibleId, $actualIds, 'Le produit à 2 unités doit être signalé.');
        $this->assertContains($limiteId, $actualIds, 'Le produit à exactement le seuil doit être signalé (<=).');
    }

    public function testLesStocksFaiblesExposentLesChampsAttendusParLaVue(): void
    {
        $this->createProduct('DSV' . random_int(100000, 999999), $this->shopId, 5, [['stock' => 1.0, 'exp' => null]]);

        $rows = (new Product())->getLowStock($this->shopId);

        $this->assertNotEmpty($rows);
        $row = $rows[0];
        // La vue dashboard.php lit : nom, total_stock, stock, stock_minimum.
        foreach (['id', 'nom', 'code_barres', 'prix', 'stock_minimum', 'total_stock'] as $key) {
            $this->assertArrayHasKey($key, $row, "La colonne « {$key} » doit être présente pour la vue.");
        }
        $this->assertSame(1.0, (float)$row['total_stock']);
        $this->assertSame(5, (int)$row['stock_minimum']);
    }

    public function testLeCompteurIgnoreLesBoutiquesInactivesCommeGetAll(): void
    {
        $this->createProduct('DSX' . random_int(100000, 999999), $this->shopId, 5, [['stock' => 50.0, 'exp' => null]]);
        $this->createProduct('DSXI' . random_int(100000, 999999), $this->inactiveShopId, 5, [['stock' => 50.0, 'exp' => null]]);

        $product = new Product();

        $this->assertSame(
            count($product->getAll($this->shopId)),
            $product->countFiltered($this->shopId)
        );
        $this->assertSame(1, $product->countFiltered($this->shopId));
    }

    /**
     * Cas super_admin : `$shopId` est null → tout le catalogue est compté,
     * y compris les produits sans boutique rattachée.
     */
    public function testLeCompteurSansBoutiqueCompteToutLeCatalogue(): void
    {
        $this->createProduct('DSA' . random_int(100000, 999999), $this->shopId, 5, [['stock' => 50.0, 'exp' => null]]);
        // Produit sans boutique rattachée : il reste visible (s.id IS NULL).
        $this->createProduct('DSAN' . random_int(100000, 999999), null, 5, [['stock' => 50.0, 'exp' => null]]);

        $product = new Product();

        $this->assertSame(
            count($product->getAll(null)),
            $product->countFiltered(null),
            'Sans boutique, le compteur doit couvrir tout le catalogue.'
        );
    }

    public function testLesStocksFaiblesSansBoutiqueCouvrentToutLeCatalogue(): void
    {
        $this->createProduct('DSW' . random_int(100000, 999999), $this->shopId, 5, [['stock' => 50.0, 'exp' => null]]);
        $this->createProduct('DSWO' . random_int(100000, 999999), null, 5, [['stock' => 1.0, 'exp' => null]]);

        $product = new Product();

        $produits = $product->getAll(null);
        $expected = array_filter($produits, function ($p) {
            $totalStock = $p['total_stock'] ?? $p['stock'];
            return $totalStock <= $p['stock_minimum'];
        });
        $expectedIds = array_map('intval', array_column($expected, 'id'));
        sort($expectedIds);

        $actualIds = array_map('intval', array_column($product->getLowStock(null), 'id'));
        sort($actualIds);

        $this->assertSame($expectedIds, $actualIds);
    }
}
