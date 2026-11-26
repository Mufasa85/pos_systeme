<?php

namespace Tests\Integration;

use App\Core\Database;
use App\Models\Product;
use PHPUnit\Framework\TestCase;

/**
 * Non-régression de la PAGINATION SERVEUR du catalogue.
 *
 * ⚠️ Contexte : à 50 000 produits, `/produits` rendait TOUTES les lignes
 * (116 Mo de HTML, 50 000 <tr>) et le filtre parcourait le DOM à chaque
 * frappe (150 000 querySelector). La page charge désormais UNE page de
 * produits, filtrée côté serveur.
 *
 * Ces tests verrouillent trois choses :
 *   1. `getPage()` renvoie exactement ce que `getAllFiltered()` renvoie,
 *      mais paginé ;
 *   2. les bornes sont appliquées (un `limit` fou ne ramène pas tout) ;
 *   3. les colonnes dénormalisées (stock) sont cohérentes avec les lots.
 */
class ProductPaginationTest extends TestCase
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
        $code = 'PAG_' . strtoupper(bin2hex(random_bytes(3)));
        self::$pdo->prepare('INSERT INTO shops (nom, code, actif) VALUES (?, ?, 1)')
            ->execute(['Boutique Pagination', $code]);
        $this->shopId = (int)self::$pdo->lastInsertId();

        $codeInactive = 'PAGI_' . strtoupper(bin2hex(random_bytes(3)));
        self::$pdo->prepare('INSERT INTO shops (nom, code, actif) VALUES (?, ?, 0)')
            ->execute(['Boutique Pagination Inactive', $codeInactive]);
        $this->inactiveShopId = (int)self::$pdo->lastInsertId();

        self::$pdo->prepare('INSERT INTO categories (category, shop_id) VALUES (?, ?)')
            ->execute(['CatPagination', $this->shopId]);
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

    /** Crée un produit avec ses lots. */
    private function createProduct(string $barcode, ?int $shopId, float $stock, float $stockMinimum = 5.0): int
    {
        $this->createdBarcodes[] = $barcode;
        self::$pdo->prepare(
            'INSERT INTO produits (code_barres, nom, category_id, shop_id, prix, stock, stock_minimum, taxe_id)
             VALUES (?, ?, ?, ?, 100, 0, ?, 1)'
        )->execute([$barcode, 'Produit ' . $barcode, $this->categoryId, $shopId, $stockMinimum]);
        $id = (int)self::$pdo->lastInsertId();

        if ($stock > 0) {
            self::$pdo->prepare(
                'INSERT INTO product_batches (product_id, batch_number, stock, date_expiration, date_reception)
                 VALUES (?, ?, ?, NULL, CURDATE())'
            )->execute([$id, 'LOT-' . $barcode, $stock]);
        }

        // Aligne les colonnes dénormalisées (comme le fait
        // ProductBatch::recalculateProductStock(), le point d'écriture
        // unique de la dénormalisation).
        $batchModel = new \App\Models\ProductBatch();
        $batchModel->recalculateProductStock($id);

        return $id;
    }

    public function testUnePageRenvoieLeNombreDeProduitsDemandes(): void
    {
        for ($i = 0; $i < 7; $i++) {
            $this->createProduct('PAG' . $i . random_int(100000, 999999), $this->shopId, 50);
        }

        $result = (new Product())->getPage([], 3, 0, $this->shopId, false);

        $this->assertCount(3, $result['rows']);
        $this->assertSame(7, $result['total']);
        $this->assertSame(3, $result['limit']);
        $this->assertSame(0, $result['offset']);
    }

    public function testLesPagesSuccessivesSeSuiventSansChevauchement(): void
    {
        $expected = [];
        for ($i = 0; $i < 7; $i++) {
            $barcode = 'PAG' . $i . random_int(100000, 999999);
            $this->createProduct($barcode, $this->shopId, 50);
            $expected[] = $barcode;
        }
        sort($expected);

        $product = new Product();
        $seen = [];
        foreach ([0, 3, 6] as $offset) {
            $page = $product->getPage([], 3, $offset, $this->shopId, false);
            foreach ($page['rows'] as $row) {
                $seen[] = $row['code_barres'];
            }
        }
        sort($seen);

        $this->assertSame($expected, $seen, 'Les pages doivent couvrir chaque produit une seule fois.');
    }

    public function testLeFiltreServeurRestreintLeTotal(): void
    {
        $this->createProduct('PAGCHERCHEA' . random_int(100000, 999999), $this->shopId, 50);
        $this->createProduct('PAGCHERCHEB' . random_int(100000, 999999), $this->shopId, 50);
        $this->createProduct('PAGAUTRE' . random_int(100000, 999999), $this->shopId, 50);

        $product = new Product();
        $all = $product->getPage([], 50, 0, $this->shopId, false);
        $this->assertSame(3, $all['total']);

        $filtered = $product->getPage(['search' => 'PAGCHERCHE'], 50, 0, $this->shopId, false);
        $this->assertSame(2, $filtered['total'], 'Le filtre doit restreindre le total.');
        $this->assertCount(2, $filtered['rows']);
    }

    public function testLeFiltreCategorieRestreintLeTotal(): void
    {
        $this->createProduct('PAGCATA' . random_int(100000, 999999), $this->shopId, 50);

        $product = new Product();
        $filtered = $product->getPage(['category' => 'CatPagination'], 50, 0, $this->shopId, false);
        $this->assertSame(1, $filtered['total']);

        $other = $product->getPage(['category' => 'CategorieInexistante'], 50, 0, $this->shopId, false);
        $this->assertSame(0, $other['total']);
    }

    public function testLesBornesDeLimitEtOffsetSontAppliquees(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->createProduct('PAGB' . $i . random_int(100000, 999999), $this->shopId, 50);
        }
        $product = new Product();

        // limit = 0 → au moins 1 (une page vide n'aurait aucun sens)
        $r = $product->getPage([], 0, 0, $this->shopId, false);
        $this->assertSame(1, $r['limit']);

        // limit géant → plafonné à MAX_PAGE_SIZE (2 000).
        //
        // ⚠️ Pourquoi 2 000 et pas 200 : la CAISSE charge le catalogue
        //    ENTIER par blocs via cette méthode. Avec un plafond de 200,
        //    il lui fallait 250 requêtes HTTP pour 50 000 produits
        //    (~98 s d'attente, mesuré) : le coût dominant est la
        //    latence réseau, pas le SQL. À 2 000 : 25 requêtes, ~10 s.
        $r = $product->getPage([], 99999, 0, $this->shopId, false);
        $this->assertSame(
            Product::MAX_PAGE_SIZE,
            $r['limit'],
            'Le limit doit être plafonné à MAX_PAGE_SIZE.'
        );

        // ⚠️ Le plafond est PARAMÉTRABLE : la page `/produits` garde 200
        //    (elle n'affiche que 50 lignes à l'écran).
        $r = $product->getPage([], 99999, 0, $this->shopId, false, 200);
        $this->assertSame(200, $r['limit'], 'Un plafond explicite doit être respecté.');

        // offset négatif → 0
        $r = $product->getPage([], 50, -5, $this->shopId, false);
        $this->assertSame(0, $r['offset'], 'Un offset négatif doit être ramené à 0.');
    }

    public function testGetPageEtGetAllFilteredRendentLesMemesValeurs(): void
    {
        $this->createProduct('PAGEQ1' . random_int(100000, 999999), $this->shopId, 50);
        $this->createProduct('PAGEQ2' . random_int(100000, 999999), $this->shopId, 2);
        $this->createProduct('PAGEQ3' . random_int(100000, 999999), $this->shopId, 0);

        $product = new Product();
        $paged = $product->getPage([], 50, 0, $this->shopId, false);
        $all = $product->getAllFiltered($this->shopId, false);

        $this->assertSame(count($all), $paged['total']);
        $this->assertSame(count($all), count($paged['rows']));

        // Les valeurs de stock doivent être identiques.
        $allById = [];
        foreach ($all as $row) {
            $allById[$row['id']] = $row;
        }
        foreach ($paged['rows'] as $row) {
            $this->assertSame(
                (float)$allById[$row['id']]['total_stock'],
                (float)$row['total_stock'],
                'Le stock doit être identique entre les deux méthodes.'
            );
            $this->assertSame(
                (float)$allById[$row['id']]['available_stock'],
                (float)$row['available_stock'],
                'Le stock disponible doit être identique entre les deux méthodes.'
            );
        }
    }

    /**
     * Non-régression de la dénormalisation : après une écriture de lot,
     * les colonnes dénormalisées doivent être à jour.
     */
    public function testLesColonnesDenormaliseesSontAJourApresUneEcritureDeLot(): void
    {
        $id = $this->createProduct('PAGDEN' . random_int(100000, 999999), $this->shopId, 10);

        $row = self::$pdo->prepare('SELECT stock, available_stock FROM produits WHERE id = ?');
        $row->execute([$id]);
        $before = $row->fetch();
        $this->assertSame(10.0, (float)$before['stock']);
        $this->assertSame(10.0, (float)$before['available_stock']);

        // Un nouveau lot de 5 unités → les deux colonnes doivent bouger.
        (new \App\Models\ProductBatch())->create([
            'product_id' => $id,
            'batch_number' => 'LOT2',
            'stock' => 5,
            'date_expiration' => null,
            'date_reception' => date('Y-m-d'),
        ]);

        $row->execute([$id]);
        $after = $row->fetch();
        $this->assertSame(15.0, (float)$after['stock'], 'stock doit suivre la somme des lots.');
        $this->assertSame(15.0, (float)$after['available_stock'], 'available_stock doit suivre les lots.');
    }

    /**
     * Un lot PÉRIMÉ ne doit pas compter dans `available_stock`, mais si
     * dans `stock`.
     */
    public function testUnLotPerimeNAffectePasLeStockDisponible(): void
    {
        $this->createdBarcodes[] = 'PAGPER' . random_int(100000, 999999);
        self::$pdo->prepare(
            'INSERT INTO produits (code_barres, nom, category_id, shop_id, prix, stock, stock_minimum, taxe_id)
             VALUES (?, ?, ?, ?, 100, 0, 5, 1)'
        )->execute([
            'PAGPER' . random_int(100000, 999999),
            'Produit Perime',
            $this->categoryId,
            $this->shopId,
        ]);
        $id = (int)self::$pdo->lastInsertId();

        self::$pdo->prepare(
            'INSERT INTO product_batches (product_id, batch_number, stock, date_expiration, date_reception)
             VALUES (?, ?, ?, DATE_SUB(CURDATE(), INTERVAL 3 DAY), CURDATE())'
        )->execute([$id, 'LOTPER', 40]);

        (new \App\Models\ProductBatch())->recalculateProductStock($id);

        $row = self::$pdo->prepare('SELECT stock, available_stock, nearest_expiration_date FROM produits WHERE id = ?');
        $row->execute([$id]);
        $r = $row->fetch();

        $this->assertSame(40.0, (float)$r['stock'], 'Le stock total compte le lot périmé.');
        $this->assertSame(0.0, (float)$r['available_stock'], 'Le stock disponible exclut le lot périmé.');
        $this->assertNull($r['nearest_expiration_date'], 'Aucun lot valide → pas de péremption.');
    }

    /**
     * Le produit d'une boutique inactive ne doit pas apparaître (comme
     * pour `getAllFiltered()`).
     */
    public function testLesProduitsDeBoutiqueInactiveSontExclus(): void
    {
        $this->createProduct('PAGINACTIF' . random_int(100000, 999999), $this->inactiveShopId, 50);

        $product = new Product();
        $paged = $product->getPage([], 50, 0, $this->shopId, false);
        $all = $product->getAllFiltered($this->shopId, false);

        $this->assertSame(count($all), $paged['total']);
        $this->assertSame(
            0,
            count($paged['rows']),
            'Un produit de boutique inactive ne doit pas être listé.'
        );
    }
}
