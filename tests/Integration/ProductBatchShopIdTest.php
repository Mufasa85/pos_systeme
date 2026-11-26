<?php

namespace Tests\Integration;

use App\Core\Database;
use App\Models\Product;
use App\Models\ProductBatch;
use PHPUnit\Framework\TestCase;

/**
 * Non-régression de la dé-normalisation de `product_batches.shop_id` (R3).
 *
 * ⚠️ Contexte : la page des lots filtre sur la boutique via une
 * JOINTURE. Mesuré à 50 000 produits / 85 000 lots, 5 boutiques :
 *
 *   jointure (b.shop_id via produits) → 187,1 ms  (Using temporary;
 *                                               Using filesort)
 *   dé-normalisé (b.shop_id)         →   1,7 ms
 *
 * On dé-normalise donc `shop_id` dans `product_batches`
 * (migrations/2026_11_25_product_batches_shop_id_denormalise.sql).
 *
 * ⚠️ LE RISQUE DE CETTE DÉ-NORMALISATION est la DIVERGENCE :
 *    un lot rattaché à une boutique différente de son produit
 *    apparaît dans les lots de l'une mais pas de l'autre — une fuite de
 *    données inter-boutiques, SILENCIEUSE. Ces tests la traquent.
 *
 * Ce fichier suppose que la migration a été appliquée à la base de
 * test. Si la colonne n'existe pas, les tests sont SKIPPÉS (pas
 * échoués) : la migration est un prérequis de déploiement, pas un
 * bug du code.
 */
class ProductBatchShopIdTest extends TestCase
{
    private static $pdo;
    private $shopA;
    private $shopB;
    private $categoryId;
    private $productIds = [];
    private $batchIds = [];

    public static function setUpBeforeClass(): void
    {
        self::$pdo = Database::getInstance()->getConnection();
    }

    protected function setUp(): void
    {
        // La colonne doit exister : sinon on ne peut rien tester.
        $exists = (int) self::$pdo->query(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'product_batches'
               AND column_name = 'shop_id'"
        )->fetchColumn();

        if ($exists === 0) {
            $this->markTestSkipped(
                'La migration product_batches.shop_id n\'est pas appliquée '
                . 'à la base de test (prérequis de déploiement).'
            );
        }

        foreach (['A', 'B'] as $suffix) {
            $code = 'R3' . $suffix . '_' . strtoupper(bin2hex(random_bytes(3)));
            self::$pdo->prepare('INSERT INTO shops (nom, code, actif) VALUES (?, ?, 1)')
                ->execute(['Boutique R3 ' . $suffix, $code]);
            $id = (int) self::$pdo->lastInsertId();
            if ($suffix === 'A') {
                $this->shopA = $id;
            } else {
                $this->shopB = $id;
            }
        }

        self::$pdo->prepare('INSERT INTO categories (category, shop_id) VALUES (?, ?)')
            ->execute(['Catégorie R3 ' . bin2hex(random_bytes(3)), $this->shopA]);
        $this->categoryId = (int) self::$pdo->lastInsertId();

        // 3 produits : un en boutique A, un en boutique B, un sans boutique.
        $this->productIds[] = $this->creerProduit('R3 produit A', $this->shopA);
        $this->productIds[] = $this->creerProduit('R3 produit B', $this->shopB);
        $this->productIds[] = $this->creerProduit('R3 produit sans boutique', null);
    }

    private function creerProduit(string $nom, ?int $shopId): int
    {
        self::$pdo->prepare(
            'INSERT INTO produits (code_barres, nom, category_id, shop_id, prix, stock, stock_minimum, image, taxe_id)
             VALUES (?, ?, ?, ?, 1000, 0, 5, NULL, 1)'
        )->execute([
            'R3-' . strtoupper(bin2hex(random_bytes(6))),
            $nom,
            $this->categoryId,
            $shopId,
        ]);
        return (int) self::$pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        foreach ($this->batchIds as $id) {
            self::$pdo->prepare('DELETE FROM product_batches WHERE id = ?')->execute([$id]);
        }
        foreach ($this->productIds as $id) {
            self::$pdo->prepare('DELETE FROM produits WHERE id = ?')->execute([$id]);
        }
        self::$pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$this->categoryId]);
        self::$pdo->prepare('DELETE FROM shops WHERE id IN (?, ?)')->execute([$this->shopA, $this->shopB]);
        $this->batchIds = [];
        $this->productIds = [];
    }

    /**
     * Un lot créé par le modèle doit porter la boutique de son produit.
     */
    public function testUnLotCreePorteLaBoutiqueDeSonProduit(): void
    {
        $model = new ProductBatch();

        $batchId = $model->create([
            'product_id' => $this->productIds[0],   // boutique A
            'batch_number' => 'R3-TEST-1',
            'stock' => 10.0,
            'date_expiration' => null,
        ]);
        $this->batchIds[] = $batchId;

        $row = self::$pdo->query(
            'SELECT shop_id FROM product_batches WHERE id = ' . (int)$batchId
        )->fetch();

        $this->assertSame((int)$this->shopA, (int)$row['shop_id'],
            'le lot doit porter la boutique de son produit');
    }

    /**
     * Le cas « produit sans boutique » : shop_id doit rester NULL, pas 0.
     */
    public function testUnLotDeProduitSansBoutiqueANullShopId(): void
    {
        $model = new ProductBatch();

        $batchId = $model->create([
            'product_id' => $this->productIds[2],   // shop_id NULL
            'batch_number' => 'R3-TEST-SANS-BOUTIQUE',
            'stock' => 5.0,
            'date_expiration' => null,
        ]);
        $this->batchIds[] = $batchId;

        $row = self::$pdo->query(
            'SELECT shop_id FROM product_batches WHERE id = ' . (int)$batchId
        )->fetch();

        $this->assertNull($row['shop_id'],
            'un lot de produit sans boutique doit avoir shop_id NULL (jamais 0)');
    }

    /**
     * Aucun lot ne doit porter une boutique différente de son produit.
     * C'est le test qui garantit l'absence de fuite inter-boutiques.
     */
    public function testAucunLotNeDivergeDeLaBoutiqueDeSonProduit(): void
    {
        $model = new ProductBatch();

        foreach ($this->productIds as $productId) {
            $batchId = $model->create([
                'product_id' => $productId,
                'batch_number' => 'R3-TEST-' . $productId,
                'stock' => 3.0,
                'date_expiration' => null,
            ]);
            $this->batchIds[] = $batchId;
        }

        $divergences = self::$pdo->query(
            'SELECT COUNT(*) FROM product_batches b
               JOIN produits p ON p.id = b.product_id
             WHERE NOT (b.shop_id <=> p.shop_id)'
        )->fetchColumn();

        $this->assertSame(0, (int)$divergences,
            'chaque lot doit porter exactement la boutique de son produit');
    }

    /**
     * Quand le produit change de boutique, ses lots doivent suivre.
     * C'est le scénario de fuite inter-boutiques par excellence.
     */
    public function testLesLotsSuiventLeProduitQuandIlChangeDeBoutique(): void
    {
        $model = new ProductBatch();
        $productModel = new Product();

        $batchId = $model->create([
            'product_id' => $this->productIds[0],   // boutique A
            'batch_number' => 'R3-TEST-MIGRATION',
            'stock' => 7.0,
            'date_expiration' => null,
        ]);
        $this->batchIds[] = $batchId;

        // Le produit passe en boutique B.
        $before = self::$pdo->query(
            'SELECT shop_id FROM produits WHERE id = ' . (int)$this->productIds[0]
        )->fetch();
        $this->assertSame((int)$this->shopA, (int)$before['shop_id']);

        $productModel->update($this->productIds[0], [
            'code_barres' => 'R3-DEPLACE-' . strtoupper(bin2hex(random_bytes(4))),
            'nom' => 'R3 produit A renomme',
            'category_id' => $this->categoryId,
            'shop_id' => $this->shopB,
            'prix' => 1000,
            'stock_minimum' => 5,
            'image' => '',
            'taxe_id' => 1,
            // Product::update() lit ces champs pour reconstruire la ligne ;
            // sans eux, la réécriture peut laisser `product_type` vide.
            'product_type' => 'unite',
            'prod_service' => null,
            'remise_type' => '%',
            'remise_value' => 0,
            'taxe_specifique_type' => '%',
            'taxe_specifique_value' => 0,
            'date_expiration' => null,
        ]);

        // Le lot doit avoir suivi.
        $lot = self::$pdo->query(
            'SELECT shop_id FROM product_batches WHERE id = ' . (int)$batchId
        )->fetch();

        $this->assertSame((int)$this->shopB, (int)$lot['shop_id'],
            'le lot doit suivre le produit vers sa nouvelle boutique');

        $divergences = self::$pdo->query(
            'SELECT COUNT(*) FROM product_batches b
               JOIN produits p ON p.id = b.product_id
             WHERE NOT (b.shop_id <=> p.shop_id)'
        )->fetchColumn();
        $this->assertSame(0, (int)$divergences, 'aucune divergence après changement de boutique');
    }

    /**
     * `syncShopIdForProduct()` doit corriger les lots d'un produit dont
     * la colonne a été désynchronisée (cas d'un script SQL direct).
     */
    public function testSyncShopIdCorrigeUneDesynchronisation(): void
    {
        $model = new ProductBatch();

        $batchId = $model->create([
            'product_id' => $this->productIds[0],
            'batch_number' => 'R3-TEST-SYNC',
            'stock' => 4.0,
            'date_expiration' => null,
        ]);
        $this->batchIds[] = $batchId;

        // On désynchronise volontairement (comme le ferait un script SQL).
        self::$pdo->prepare('UPDATE product_batches SET shop_id = ? WHERE id = ?')
            ->execute([$this->shopB, $batchId]);

        $model->syncShopIdForProduct($this->productIds[0]);

        $row = self::$pdo->query(
            'SELECT shop_id FROM product_batches WHERE id = ' . (int)$batchId
        )->fetch();
        $this->assertSame((int)$this->shopA, (int)$row['shop_id'],
            'la synchronisation doit réaligner le lot sur son produit');
    }

    /**
     * La page des lots ne doit montrer QUE les lots de la boutique
     * demandée — jamais ceux d'une autre boutique.
     */
    public function testLaPageDesLotsEstIsoleeParBoutique(): void
    {
        $model = new ProductBatch();

        $lotA = $model->create([
            'product_id' => $this->productIds[0],
            'batch_number' => 'R3-ISO-A',
            'stock' => 10.0,
            'date_expiration' => date('Y-m-d', strtotime('+10 days')),
        ]);
        $lotB = $model->create([
            'product_id' => $this->productIds[1],
            'batch_number' => 'R3-ISO-B',
            'stock' => 20.0,
            'date_expiration' => date('Y-m-d', strtotime('+20 days')),
        ]);
        $this->batchIds[] = $lotA;
        $this->batchIds[] = $lotB;

        $page = $model->getPage($this->shopA, 50, 0, 'keyset', null, null, true);
        $ids = array_map('intval', array_column($page['rows'], 'id'));

        $this->assertContains((int)$lotA, $ids,
            'le lot de la boutique A doit être listé (boutique ' . $this->shopA
            . ', total=' . $page['total'] . ', ids=' . implode(',', $ids) . ')');
        $this->assertNotContains((int)$lotB, $ids,
            'le lot de la boutique B ne doit JAMAIS apparaître dans les lots de A');
    }

    /**
     * Un produit sans boutique : ses lots n'apparaissent dans AUCUNE
     * boutique (shop_id NULL ne matche aucun `shop_id = X`).
     */
    public function testLesLotsSansBoutiqueNApparaissentDansAucuneBoutique(): void
    {
        $model = new ProductBatch();

        $lot = $model->create([
            'product_id' => $this->productIds[2],
            'batch_number' => 'R3-ISO-SANS-BOUTIQUE',
            'stock' => 8.0,
            'date_expiration' => date('Y-m-d', strtotime('+5 days')),
        ]);
        $this->batchIds[] = $lot;

        foreach ([$this->shopA, $this->shopB] as $shopId) {
            $page = $model->getPage($shopId, 50, 0, 'keyset', null, null, true);
            $this->assertNotContains($lot, array_column($page['rows'], 'id'),
                'un lot sans boutique ne doit apparaître dans aucune boutique');
        }
    }
}
