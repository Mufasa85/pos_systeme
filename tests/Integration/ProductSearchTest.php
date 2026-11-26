<?php

namespace Tests\Integration;

use App\Core\Database;
use App\Models\Product;
use PHPUnit\Framework\TestCase;

/**
 * Non-régression de la RECHERCHE EN DEUX TEMPS.
 *
 * ⚠️ Contexte : à 50 000 produits, la recherche par nom a été mesurée :
 *
 *   (p.nom LIKE 'X%')                       →  0,5 ms  type=range
 *   (p.nom LIKE 'X%' OR p.code_barres LIKE 'X%')
 *                                            → 12,4 ms  + "Using where"
 *   (p.nom LIKE '%X%')                      → 106 ms   scan complet
 *
 * Le `OR code_barres` dans la MÊME requête casse l'index. La stratégie
 * implémentée est donc : préfixe d'abord, repli infixe si vide, et
 * code-barres exact dans une requête SÉPARÉE.
 *
 * Ce test verrouille le COMPORTEMENT (trouve / ne trouve pas), pas la
 * performance (qui dépend de l'index, pas de la logique).
 */
class ProductSearchTest extends TestCase
{
    private static $pdo;
    private $shopId;
    private $categoryId;
    private $productIds = [];

    public static function setUpBeforeClass(): void
    {
        self::$pdo = Database::getInstance()->getConnection();
    }

    protected function setUp(): void
    {
        $code = 'SHR_' . strtoupper(bin2hex(random_bytes(3)));
        self::$pdo->prepare('INSERT INTO shops (nom, code, actif) VALUES (?, ?, 1)')
            ->execute(['Boutique Recherche', $code]);
        $this->shopId = (int) self::$pdo->lastInsertId();

        self::$pdo->prepare('INSERT INTO categories (category, shop_id) VALUES (?, ?)')
            ->execute(['Catégorie recherche ' . bin2hex(random_bytes(3)), $this->shopId]);
        $this->categoryId = (int) self::$pdo->lastInsertId();

        // Produits de test : des noms qui se recoupent, pour vérifier
        // que la recherche ne confond pas préfixe et infixe.
        $this->creerProduit('Coca-Cola Original 33cl', 'CB-COLA-ORIGINAL');
        $this->creerProduit('Coca-Cola Zero 33cl', 'CB-COLA-ZERO');
        $this->creerProduit('Sprite 1L', 'CB-SPRITE-1L');
        $this->creerProduit('Eau minérale Cristaline', 'CB-EAU-CRISTALINE');
        $this->creerProduit('Pain de mie complet', 'CB-PAIN-MIE');
    }

    private function creerProduit(string $nom, string $codeBarres): void
    {
        self::$pdo->prepare(
            'INSERT INTO produits (code_barres, nom, category_id, shop_id, prix, stock, stock_minimum, image, taxe_id)
             VALUES (?, ?, ?, ?, 1500, 10, 2, NULL, 1)'
        )->execute([$codeBarres, $nom, $this->categoryId, $this->shopId]);
        $this->productIds[] = (int) self::$pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        foreach ($this->productIds as $id) {
            self::$pdo->prepare('DELETE FROM produits WHERE id = ?')->execute([$id]);
        }
        self::$pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$this->categoryId]);
        self::$pdo->prepare('DELETE FROM shops WHERE id = ?')->execute([$this->shopId]);
        $this->productIds = [];
    }

    private function rechercher(string $terme): array
    {
        $model = new Product();
        $page = $model->getPage(['search' => $terme], 50, 0, $this->shopId);
        return array_column($page['rows'], 'nom');
    }

    /**
     * Un préfixe doit trouver les produits qui COMMENCENT par ce préfixe.
     */
    public function testLePrefixeTrouveLesProduits(): void
    {
        $noms = $this->rechercher('Coca');
        $this->assertContains('Coca-Cola Original 33cl', $noms);
        $this->assertContains('Coca-Cola Zero 33cl', $noms);
        $this->assertNotContains('Sprite 1L', $noms);
        $this->assertNotContains('Eau minérale Cristaline', $noms);
    }

    /**
     * Un terme AU MILIEU d'un nom doit être trouvé grâce au repli infixe.
     * C'est le cas d'usage « Cola » → « Coca-Cola ».
     */
    public function testLeRepliInfixeTrouveUnMotAuMilieu(): void
    {
        $noms = $this->rechercher('Cola');

        // « Cola » est au milieu de « Coca-Cola » : le préfixe ne
        // matche pas, le repli infixe doit trouver les deux produits.
        $this->assertContains('Coca-Cola Original 33cl', $noms);
        $this->assertContains('Coca-Cola Zero 33cl', $noms);
        $this->assertNotContains('Sprite 1L', $noms);
    }

    /**
     * La recherche par code-barres EXACT doit fonctionner — c'est le
     * chemin du scanner USB.
     */
    public function testLaRechercheParCodeBarresExactFonctionne(): void
    {
        $noms = $this->rechercher('CB-SPRITE-1L');
        $this->assertContains('Sprite 1L', $noms);
        $this->assertNotContains('Coca-Cola Zero 33cl', $noms);
    }

    /**
     * Un code-barres exact doit être trouvé même si un autre produit
     * commence par le même texte.
     */
    public function testLeCodeBarresExactPrioritaireSurLePrefixe(): void
    {
        // 'CB-SPRITE' est le début de 'CB-SPRITE-1L' mais n'est pas
        // un code-barres valide : on ne doit PAS le trouver.
        $noms = $this->rechercher('CB-SPRITE');
        $this->assertNotContains('Sprite 1L', $noms);
    }

    /**
     * Un terme qui n'existe pas ne doit rien retourner (et pas planter).
     */
    public function testUnTermeInexistantNeRetourneRien(): void
    {
        $noms = $this->rechercher('zzzzintrouvable');
        $this->assertSame([], $noms);
    }

    /**
     * La recherche ne doit pas déborder sur une AUTRE boutique.
     */
    public function testLaRechercheRespecteLaBoutique(): void
    {
        // Un produit identique dans une autre boutique.
        $code = 'SHR2_' . strtoupper(bin2hex(random_bytes(3)));
        self::$pdo->prepare('INSERT INTO shops (nom, code, actif) VALUES (?, ?, 1)')
            ->execute(['Autre Boutique', $code]);
        $otherShop = (int) self::$pdo->lastInsertId();

        self::$pdo->prepare(
            'INSERT INTO produits (code_barres, nom, category_id, shop_id, prix, stock, stock_minimum, image, taxe_id)
             VALUES (?, ?, ?, ?, 1500, 10, 2, NULL, 1)'
        )->execute(['CB-AUTRE-SHOP', 'Coca-Cola Autre Boutique', $this->categoryId, $otherShop]);
        $otherProduct = (int) self::$pdo->lastInsertId();

        try {
            $noms = $this->rechercher('Coca');
            $this->assertContains('Coca-Cola Original 33cl', $noms);
            $this->assertNotContains('Coca-Cola Autre Boutique', $noms);
        } finally {
            self::$pdo->prepare('DELETE FROM produits WHERE id = ?')->execute([$otherProduct]);
            self::$pdo->prepare('DELETE FROM shops WHERE id = ?')->execute([$otherShop]);
        }
    }

    /**
     * Le total annoncé doit correspondre au nombre de lignes trouvées
     * (quand on est sur la première page et que le total est < limit).
     */
    public function testLeTotalCorrespondAuxResultats(): void
    {
        $model = new Product();
        $page = $model->getPage(['search' => 'Coca'], 50, 0, $this->shopId);

        $this->assertSame(count($page['rows']), $page['total']);
        $this->assertSame(2, $page['total'], 'exactement 2 produits "Coca" dans cette boutique');
    }

    /**
     * Une recherche vide ne doit pas filtrer du tout.
     */
    public function testUneRechercheVideNeFiltreRien(): void
    {
        $model = new Product();
        $page = $model->getPage(['search' => ''], 50, 0, $this->shopId);
        $this->assertGreaterThanOrEqual(5, $page['total']);
    }
}
