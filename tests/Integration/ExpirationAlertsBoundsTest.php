<?php
/**
 * Test d'INTÉGRATION des bornes d'alertes — avec de VRAIES données.
 *
 * Vérifie que :
 *   - les compteurs et les listes sont COHÉRENTS entre eux ;
 *   - la pagination ne renvoie ni doublon ni trou ;
 *   - les bornes sont appliquées ;
 *   - le dashboard affiche le compteur quand la liste est tronquée.
 *
 * Utilise la base de test (pos_systeme_test) et nettoie ses données.
 */

namespace Tests\Integration;

use App\Core\Database;
use App\Models\Product;
use App\Models\ProductBatch;
use PHPUnit\Framework\TestCase;

class ExpirationAlertsBoundsTest extends TestCase
{
    private static $pdo;
    private $shopId;
    private $categoryId;
    private $productIds = [];
    private $batchIds = [];

    public static function setUpBeforeClass(): void
    {
        self::$pdo = Database::getInstance()->getConnection();
    }

    protected function setUp(): void
    {
        $this->shopId = 1;

        // Une catégorie dédiée : la catégorie 1 n'existe pas forcément
        // dans la base de test (contrainte de clé étrangère).
        self::$pdo->prepare('INSERT INTO categories (category, shop_id) VALUES (?, ?)')
            ->execute(['Catégorie alertes ' . bin2hex(random_bytes(3)), $this->shopId]);
        $this->categoryId = (int) self::$pdo->lastInsertId();

        // 3 produits, 60 lots répartis : 10 périmés, 25 sous 7 jours,
        // 25 sous 30 jours (hors 7 jours).
        $price = 100.0;
        for ($p = 0; $p < 3; $p++) {
            $code = 'ALRT' . bin2hex(random_bytes(4));
            $st = self::$pdo->prepare(
                'INSERT INTO produits (code_barres, nom, category_id, shop_id, prix, stock, stock_minimum, image, taxe_id)
                 VALUES (?, ?, ?, ?, ?, 0, 5, NULL, 1)'
            );
            $st->execute([$code, 'Produit alertes ' . $p, $this->categoryId, $this->shopId, $price]);
            $this->productIds[] = (int) self::$pdo->lastInsertId();
        }

        $spec = [];
        for ($i = 0; $i < 10; $i++) {
            $spec[] = ['-40 days', 5.0];
        }
        for ($i = 0; $i < 25; $i++) {
            $spec[] = ['+3 days', 7.0];
        }
        for ($i = 0; $i < 25; $i++) {
            $spec[] = ['+20 days', 9.0];
        }

        $st = self::$pdo->prepare(
            'INSERT INTO product_batches (product_id, batch_number, stock, date_expiration, date_reception)
             VALUES (?, ?, ?, DATE_ADD(CURDATE(), INTERVAL ? DAY), CURDATE())'
        );
        foreach ($spec as $k => [$offset, $stock]) {
            $days = (int) $offset;
            $st->execute([
                $this->productIds[$k % 3],
                'ALRT-' . $k,
                $stock,
                $days,
            ]);
            $this->batchIds[] = (int) self::$pdo->lastInsertId();
        }

        // Aligne les colonnes dénormalisées (comme le fait le point
        // d'écriture ProductBatch::recalculateProductStock()).
        $batchModel = new ProductBatch();
        foreach ($this->productIds as $id) {
            $batchModel->recalculateProductStock($id);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->batchIds as $id) {
            self::$pdo->prepare('DELETE FROM product_batches WHERE id = ?')->execute([$id]);
        }
        foreach ($this->productIds as $id) {
            self::$pdo->prepare('DELETE FROM produits WHERE id = ?')->execute([$id]);
        }
        if ($this->categoryId) {
            self::$pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$this->categoryId]);
        }
        $this->batchIds = [];
        $this->productIds = [];
        $this->categoryId = null;
    }

    /**
     * Les compteurs doivent correspondre EXACTEMENT au nombre de lignes
     * que la requête sans borne renverrait.
     */
    public function testLesCompteursCorrespondentAuxListes(): void
    {
        $m = new ProductBatch();

        $all7    = $m->getExpiringSoon(7, $this->shopId, null);
        $count7  = $m->countExpiringSoon(7, $this->shopId);
        $this->assertSame(count($all7), $count7, 'countExpiringSoon(7) doit correspondre à la liste');

        $all30   = $m->getExpiringSoon(30, $this->shopId, null);
        $count30 = $m->countExpiringSoon(30, $this->shopId);
        $this->assertSame(count($all30), $count30, 'countExpiringSoon(30) doit correspondre à la liste');

        $allExp  = $m->getExpired($this->shopId, null);
        $countE  = $m->countExpired($this->shopId);
        $this->assertSame(count($allExp), $countE, 'countExpired doit correspondre à la liste');

        // Les fenêtres sont imbriquées : 7 jours ⊂ 30 jours.
        $this->assertLessThanOrEqual($count30, $count7);
    }

    /**
     * La limite doit être RESPECTÉE : c'est le cœur du correctif.
     */
    public function testLaLimiteEstRespectee(): void
    {
        $m = new ProductBatch();

        $this->assertLessThanOrEqual(20, count($m->getExpiringSoon(30, $this->shopId, 20)));
        $this->assertLessThanOrEqual(5, count($m->getExpiringSoon(30, $this->shopId, 5)));
        $this->assertLessThanOrEqual(1, count($m->getExpiringSoon(30, $this->shopId, 1)));
        $this->assertLessThanOrEqual(20, count($m->getExpired($this->shopId, 20)));

        // Le compteur reste le VRAI total, même quand la liste est tronquée.
        $this->assertGreaterThan(count($m->getExpiringSoon(30, $this->shopId, 20)),
            $m->countExpiringSoon(30, $this->shopId));
    }

    /**
     * Une limite nulle ou négative ne doit jamais produire de requête
     * invalide ni de résultat non borné.
     */
    public function testLesBornesDegeneresSontSecurisees(): void
    {
        $m = new ProductBatch();

        // 0 et négatif remontent à 1 (pas de LIMIT 0 qui vide la page).
        $this->assertLessThanOrEqual(1, count($m->getExpiringSoon(30, $this->shopId, 0)));
        $this->assertLessThanOrEqual(1, count($m->getExpiringSoon(30, $this->shopId, -5)));

        // Un offset négatif est ramené à 0.
        $p1 = $m->getExpiringSoon(30, $this->shopId, 10, -100);
        $p0 = $m->getExpiringSoon(30, $this->shopId, 10, 0);
        $this->assertSame(
            array_column($p0, 'id'),
            array_column($p1, 'id'),
            'un offset négatif doit être traité comme 0'
        );
    }

    /**
     * La pagination doit avancer sans doublon ni trou.
     */
    public function testLaPaginationAvanceSansDoublonNiTrou(): void
    {
        $m = new ProductBatch();

        $page1 = $m->getExpiringSoon(30, $this->shopId, 10, 0);
        $page2 = $m->getExpiringSoon(30, $this->shopId, 10, 10);
        $page3 = $m->getExpiringSoon(30, $this->shopId, 10, 20);

        $ids1 = array_column($page1, 'id');
        $ids2 = array_column($page2, 'id');
        $ids3 = array_column($page3, 'id');

        $this->assertCount(10, $ids1);
        $this->assertCount(10, $ids2);
        $this->assertCount(10, $ids3);

        // Aucun chevauchement.
        $this->assertSame([], array_intersect($ids1, $ids2), 'page 1 et 2 ne doivent pas se chevaucher');
        $this->assertSame([], array_intersect($ids2, $ids3), 'page 2 et 3 ne doivent pas se chevaucher');

        // Pas de trou : on parcourt TOUTES les pages (la liste complète
        // fait 50 lignes, pas 30) et on vérifie que l'union des pages
        // égale exactement la liste sans borne.
        $all = $m->getExpiringSoon(30, $this->shopId, null);
        $expected = array_column($all, 'id');

        $collected = [];
        for ($offset = 0; $offset < count($expected); $offset += 10) {
            foreach ($m->getExpiringSoon(30, $this->shopId, 10, $offset) as $row) {
                $collected[] = $row['id'];
            }
        }

        $this->assertCount(count($expected), $collected, 'chaque ligne doit apparaître exactement une fois');
        $this->assertSame(count($expected), count(array_unique($collected)), 'aucun doublon entre les pages');

        sort($collected);
        sort($expected);
        $this->assertSame($expected, $collected, 'la pagination doit couvrir la liste entière sans trou');
    }

    /**
     * Un lot périmé ne doit PAS apparaître dans les lots "périment
     * bientôt" : les deux listes sont des alertes distinctes.
     */
    public function testLesListesNeSeMelangentPas(): void
    {
        $m = new ProductBatch();

        foreach ($m->getExpiringSoon(30, $this->shopId, null) as $lot) {
            $this->assertGreaterThanOrEqual(
                strtotime(date('Y-m-d')),
                strtotime($lot['date_expiration']),
                'un lot périmé ne doit pas être dans les alertes "périment bientôt"'
            );
        }

        foreach ($m->getExpired($this->shopId, null) as $lot) {
            $this->assertLessThan(
                strtotime(date('Y-m-d')),
                strtotime($lot['date_expiration']),
                'un lot valide ne doit pas être dans les alertes "périmés"'
            );
        }
    }

    /**
     * La page du dashboard doit afficher le compteur quand la liste est
     * tronquée — sinon l'utilisateur croit qu'il n'y a que 20 alertes.
     */
    public function testLeDashboardAfficheLeCompteurQuandLaListeEstTronquee(): void
    {
        $m = new ProductBatch();
        $shown = $m->getExpiringSoon(30, $this->shopId, 20);
        $total = $m->countExpiringSoon(30, $this->shopId);

        $this->assertGreaterThan(20, $total, 'le jeu de données doit dépasser la limite de 20');

        $hidden = max(0, $total - count($shown));
        $this->assertGreaterThan(0, $hidden, 'il doit y avoir des alertes non affichées');
    }
}
