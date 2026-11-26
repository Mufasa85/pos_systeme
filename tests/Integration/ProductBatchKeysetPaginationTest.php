<?php

namespace Tests\Integration;

use App\Core\Database;
use App\Models\ProductBatch;
use PHPUnit\Framework\TestCase;

/**
 * Non-régression de la PAGINATION KEYSET de la page des lots (R4).
 *
 * ⚠️ Contexte : l'OFFSET oblige MySQL à PRODUIRE puis JETER les lignes
 *    précédentes — mesuré à 177 ms à la page 1000, contre ~2 ms en
 *    keyset, quel que soit le numéro de page.
 *
 *    Le keyset repart du dernier couple (date_expiration, id) de la page
 *    précédente.
 *
 * ⚠️ Le `id` est le TIE-BREAKER : sans lui, deux lots expirant le même
 *    jour ont le même couple de tri, MySQL les ordonne arbitrairement, et
 *    la pagination SAUTE des lignes ou en RÉPÈTE d'une page à l'autre.
 *    C'est exactement ce que vérifie
 *    `testToutesLesPagesCouvrentLaListeSansTrouNiDoublon`.
 */
class ProductBatchKeysetPaginationTest extends TestCase
{
    private static $pdo;
    private $shopId;
    private $categoryId;
    private $productId;
    private $batchIds = [];

    public static function setUpBeforeClass(): void
    {
        self::$pdo = Database::getInstance()->getConnection();
    }

    protected function setUp(): void
    {
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

        $code = 'KEY_' . strtoupper(bin2hex(random_bytes(3)));
        self::$pdo->prepare('INSERT INTO shops (nom, code, actif) VALUES (?, ?, 1)')
            ->execute(['Boutique Keyset', $code]);
        $this->shopId = (int) self::$pdo->lastInsertId();

        self::$pdo->prepare('INSERT INTO categories (category, shop_id) VALUES (?, ?)')
            ->execute(['Catégorie Keyset ' . bin2hex(random_bytes(3)), $this->shopId]);
        $this->categoryId = (int) self::$pdo->lastInsertId();

        self::$pdo->prepare(
            'INSERT INTO produits (code_barres, nom, category_id, shop_id, prix, stock, stock_minimum, image, taxe_id)
             VALUES (?, ?, ?, ?, 1000, 0, 5, NULL, 1)'
        )->execute(['KEY-' . strtoupper(bin2hex(random_bytes(5))), 'Produit keyset', $this->categoryId, $this->shopId]);
        $this->productId = (int) self::$pdo->lastInsertId();

        // 60 lots : 20 avec des dates DIFFÉRENTES, et 40 qui expirent le
        // MÊME jour (le cas qui casse la pagination sans tie-breaker).
        $model = new ProductBatch();
        for ($i = 0; $i < 60; $i++) {
            $jour = $i < 20 ? $i + 1 : 100;   // 40 lots au jour 100
            $batchId = $model->create([
                'product_id' => $this->productId,
                'batch_number' => 'KEY-LOT-' . $i,
                'stock' => 10.0,
                'date_expiration' => date('Y-m-d', strtotime('+' . $jour . ' days')),
            ]);
            $this->batchIds[] = $batchId;
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->batchIds as $id) {
            self::$pdo->prepare('DELETE FROM product_batches WHERE id = ?')->execute([$id]);
        }
        self::$pdo->prepare('DELETE FROM produits WHERE id = ?')->execute([$this->productId]);
        self::$pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$this->categoryId]);
        self::$pdo->prepare('DELETE FROM shops WHERE id = ?')->execute([$this->shopId]);
        $this->batchIds = [];
    }

    /**
     * Le keyset doit avancer : la page 2 commence après le dernier élément
     * de la page 1, sans le répéter.
     */
    public function testLeKeysetAvanceSansRepeter(): void
    {
        $model = new ProductBatch();

        $page1 = $model->getPage($this->shopId, 20, 0, 'keyset', null, null, true);
        $this->assertCount(20, $page1['rows']);

        $dernier = end($page1['rows']);
        $page2 = $model->getPage(
            $this->shopId, 20, 0, 'keyset',
            $dernier['date_expiration'], (int)$dernier['id'], true
        );

        $ids1 = array_map('intval', array_column($page1['rows'], 'id'));
        $ids2 = array_map('intval', array_column($page2['rows'], 'id'));

        $this->assertSame([], array_intersect($ids1, $ids2),
            'la page 2 ne doit pas répéter un lot de la page 1');

        // Et l'ordre doit être respecté : la page 2 commence après.
        $this->assertGreaterThan(
            (int)$dernier['id'],
            $ids2[0] ?? 0,
            'la page 2 doit commencer après le dernier lot de la page 1'
        );
    }

    /**
     * LE test critique : parcourir TOUTES les pages et vérifier que
     * l'union égale exactement la liste complète.
     *
     * Sans tie-breaker (`b.id`), ce test échoue : 40 lots expirent le même
     * jour, MySQL les ordonne arbitrairement, et une page peut répéter un
     * lot et en sauter un autre.
     */
    public function testToutesLesPagesCouvrentLaListeSansTrouNiDoublon(): void
    {
        $model = new ProductBatch();

        $complete = $model->getPage($this->shopId, 200, 0, 'offset', null, null, true);
        $attendu = array_map('intval', array_column($complete['rows'], 'id'));
        $this->assertCount(60, $attendu, 'le jeu de données doit contenir 60 lots');

        $collecte = [];
        $exp = null;
        $id  = null;
        $pages = 0;

        do {
            $page = $model->getPage($this->shopId, 20, 0, 'keyset', $exp, $id, true);
            if (empty($page['rows'])) {
                break;
            }
            foreach ($page['rows'] as $row) {
                $collecte[] = (int)$row['id'];
            }
            $dernier = end($page['rows']);
            $exp = $dernier['date_expiration'];
            $id  = (int)$dernier['id'];
            $pages++;
            $this->assertLessThan(10, $pages, 'pagination infinie');
        } while (count($page['rows']) === 20);

        $this->assertCount(count($attendu), $collecte,
            'chaque lot doit apparaître exactement une fois');
        $this->assertSame(count($collecte), count(array_unique($collecte)),
            'aucun doublon entre les pages');

        sort($collecte);
        $tri = $attendu;
        sort($tri);
        $this->assertSame($tri, $collecte,
            'la pagination keyset doit couvrir la liste entière, sans trou ni doublon');
    }

    /**
     * Le mode OFFSET doit rendre exactement la même liste que le keyset.
     */
    public function testLeModeOffsetRendLaMemeListe(): void
    {
        $model = new ProductBatch();

        $parOffset = $model->getPage($this->shopId, 60, 0, 'offset', null, null, true);
        $parKeyset = $model->getPage($this->shopId, 60, 0, 'keyset', null, null, true);

        $this->assertSame(
            array_map('intval', array_column($parOffset['rows'], 'id')),
            array_map('intval', array_column($parKeyset['rows'], 'id')),
            'offset et keyset doivent rendre la même liste, dans le même ordre'
        );
    }

    /**
     * Le total doit être indépendant du mode de pagination.
     */
    public function testLeTotalEstIdentiqueDansLesDeuxModes(): void
    {
        $model = new ProductBatch();

        $a = $model->getPage($this->shopId, 10, 0, 'offset', null, null, true);
        $b = $model->getPage($this->shopId, 10, 0, 'keyset', null, null, true);

        $this->assertSame($a['total'], $b['total']);
        $this->assertSame(60, $a['total']);
    }

    /**
     * Les bornes doivent être appliquées : un limit fou ne ramène pas
     * tout, un offset négatif est normalisé.
     */
    public function testLesBornesSontAppliquees(): void
    {
        $model = new ProductBatch();

        $this->assertLessThanOrEqual(1, count($model->getPage($this->shopId, 0, 0, 'keyset', null, null, true)['rows']));
        $this->assertLessThanOrEqual(200, $model->getPage($this->shopId, 5000, 0, 'keyset', null, null, true)['limit']);
        $this->assertGreaterThanOrEqual(0, $model->getPage($this->shopId, 50, -100, 'offset', null, null, true)['offset']);
    }

    /**
     * Le filtre « seulement les lots en stock » doit exclure les lots
     * vides.
     */
    public function testLeFiltreStockEstApplique(): void
    {
        $model = new ProductBatch();

        // On vide un lot.
        $lot = $this->batchIds[0];
        self::$pdo->prepare('UPDATE product_batches SET stock = 0 WHERE id = ?')->execute([$lot]);

        $avecStock = $model->getPage($this->shopId, 200, 0, 'offset', null, null, true);
        $idsAvec = array_map('intval', array_column($avecStock['rows'], 'id'));
        $this->assertNotContains((int)$lot, $idsAvec, 'un lot vide ne doit pas être listé');

        $sansStock = $model->getPage($this->shopId, 200, 0, 'offset', null, null, false);
        $idsSans = array_map('intval', array_column($sansStock['rows'], 'id'));
        $this->assertContains((int)$lot, $idsSans, 'sans le filtre, le lot vide doit être listé');
    }
}
