<?php

namespace Tests\Integration;

use App\Core\Database;
use App\Models\Product;
use App\Services\ProductImportService;
use PHPUnit\Framework\TestCase;

/**
 * Non-régression du correctif de charge R4 : la recherche BATCHÉE des
 * produits existants (Product::findIdsByBarcodes) doit se comporter
 * EXACTEMENT comme la recherche ligne par ligne qu'elle remplace
 * (Product::findByBarcode).
 *
 * ⚠️ Sémantique à préserver (mesurée sur le schéma réel) :
 *   - `produits.code_barres` est en `utf8mb4_0900_ai_ci` → MySQL compare
 *     SANS distinguer la casse ET les accents. Un tableau PHP ne le fait pas :
 *     la comparaison doit rester FAITE PAR MySQL.
 *   - un produit d'une boutique INACTIVE n'est PAS trouvé
 *     (`s.id IS NULL OR s.actif = 1`) ;
 *   - un produit sans boutique rattachée EST trouvé.
 *
 * Ces tests sont le garde-fou : si quelqu'un remplace un jour le lookup
 * batché par un tableau PHP, ces assertions tombent.
 */
class ProductBatchLookupTest extends TestCase
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
        $code = 'BLK_' . strtoupper(bin2hex(random_bytes(3)));
        self::$pdo->prepare(
            'INSERT INTO shops (nom, code, actif) VALUES (?, ?, 1)'
        )->execute(['Boutique Lookup', $code]);
        $this->shopId = (int)self::$pdo->lastInsertId();

        $codeInactive = 'BLKI_' . strtoupper(bin2hex(random_bytes(3)));
        self::$pdo->prepare(
            'INSERT INTO shops (nom, code, actif) VALUES (?, ?, 0)'
        )->execute(['Boutique Inactive', $codeInactive]);
        $this->inactiveShopId = (int)self::$pdo->lastInsertId();

        self::$pdo->prepare(
            'INSERT INTO categories (category, shop_id) VALUES (?, ?)'
        )->execute(['CatLookup', $this->shopId]);
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

    /** Crée un produit en base et renvoie son id. */
    private function createProduct(string $barcode, ?int $shopId): int
    {
        $this->createdBarcodes[] = $barcode;
        self::$pdo->prepare(
            'INSERT INTO produits (code_barres, nom, category_id, shop_id, prix, stock, stock_minimum, taxe_id)
             VALUES (?, ?, ?, ?, 100, 10, 1, 1)'
        )->execute([$barcode, 'Produit ' . $barcode, $this->categoryId, $shopId]);
        return (int)self::$pdo->lastInsertId();
    }

    /**
     * Le cœur du test : pour un ensemble de code-barres, le lookup batché
     * doit rendre EXACTEMENT le même id que le lookup unitaire.
     */
    private function assertBatchMatchesUnit(array $barcodes): void
    {
        $product = new Product();

        // Référence : l'ancien chemin, une requête par code-barres.
        $expected = [];
        foreach ($barcodes as $barcode) {
            $row = self::$pdo->prepare(
                'SELECT p.id FROM produits p
                 LEFT JOIN shops s ON p.shop_id = s.id
                 WHERE p.code_barres = ?
                   AND (s.id IS NULL OR s.actif = 1)'
            );
            $row->execute([$barcode]);
            $found = $row->fetch();
            $expected[$barcode] = $found ? (int)$found['id'] : null;
        }

        // Le nouveau chemin : un seul appel batché.
        $actual = $product->findIdsByBarcodes($barcodes);

        foreach ($expected as $barcode => $expectedId) {
            $this->assertArrayHasKey(
                $barcode,
                $actual,
                "Le map doit contenir une entrée pour le code-barres {$barcode}."
            );
            $this->assertSame(
                $expectedId,
                $actual[$barcode],
                "Écart de sémantique sur « {$barcode} » : unitaire=" .
                var_export($expectedId, true) . ' batch=' . var_export($actual[$barcode], true)
            );
        }
    }

    public function testUnCodeBarresExactEstTrouve(): void
    {
        $barcode = 'BLK' . random_int(1000000000, 9999999999);
        $id = $this->createProduct($barcode, $this->shopId);

        $result = (new Product())->findIdsByBarcodes([$barcode]);

        $this->assertSame($id, $result[$barcode]);
    }

    public function testLaCasseEstIgnoreeCommeEnBase(): void
    {
        $stored = 'BLK' . random_int(1000000000, 9999999999);
        $id = $this->createProduct($stored, $this->shopId);
        $saisi = strtolower($stored);

        $result = (new Product())->findIdsByBarcodes([$saisi]);

        // MySQL compare avec la collation utf8mb4_0900_ai_ci : insensible à
        // la casse. Le lookup doit donc retrouver le produit.
        $this->assertSame(
            $id,
            $result[$saisi],
            'Le lookup doit être insensible à la casse, comme la base.'
        );
    }

    public function testLesAccentsSontIgnoresCommeEnBase(): void
    {
        // ⚠️ Le suffixe doit être IDENTIQUE dans les deux chaînes : c'est
        //    l'accent qui est la seule différence testée.
        $suffix = (string)random_int(1000, 9999);
        $stored = 'BLKCAFÉ' . $suffix;
        $id = $this->createProduct($stored, $this->shopId);
        $saisi = 'blkcafe' . $suffix;

        $result = (new Product())->findIdsByBarcodes([$saisi]);

        $this->assertSame(
            $id,
            $result[$saisi],
            'Le lookup doit être insensible aux accents, comme la base (collation ai_ci).'
        );
    }

    public function testUnProduitDeBoutiqueInactiveEstIntrouvable(): void
    {
        $barcode = 'BLKI' . random_int(1000000000, 9999999999);
        $this->createProduct($barcode, $this->inactiveShopId);

        $result = (new Product())->findIdsByBarcodes([$barcode]);

        $this->assertNull(
            $result[$barcode],
            'Un produit d\'une boutique inactive doit rester introuvable.'
        );
    }

    public function testUnProduitSansBoutiqueRattacheeEstTrouve(): void
    {
        $barcode = 'BLKN' . random_int(1000000000, 9999999999);
        $id = $this->createProduct($barcode, null);

        $result = (new Product())->findIdsByBarcodes([$barcode]);

        $this->assertSame($id, $result[$barcode]);
    }

    public function testUnCodeBarresInconnuRenvoieNull(): void
    {
        $barcode = 'BLKX' . random_int(1000000000, 9999999999);

        $result = (new Product())->findIdsByBarcodes([$barcode]);

        $this->assertArrayHasKey($barcode, $result);
        $this->assertNull($result[$barcode]);
    }

    public function testUneListeVideRenvoieUnMapVide(): void
    {
        $this->assertSame([], (new Product())->findIdsByBarcodes([]));
    }

    public function testLesDoublonsDuFichierSontDedupliques(): void
    {
        $barcode = 'BLKD' . random_int(1000000000, 9999999999);
        $id = $this->createProduct($barcode, $this->shopId);

        $result = (new Product())->findIdsByBarcodes([$barcode, $barcode, $barcode]);

        $this->assertSame($id, $result[$barcode]);
    }

    /**
     * Le cas qui a fait échouer une première version de l'implémentation :
     * un produit d'une boutique inactive doit être RENVOYÉ avec un id null
     * (et non pas renvoyé du tout). S'il disparaissait du map, l'import
     * tenterait de le créer et échouerait sur l'UNIQUE global de
     * `code_barres`.
     */
    public function testUnProduitDeBoutiqueInactiveEstPresentDansLeMapAvecUnIdNul(): void
    {
        $barcode = 'BLKU' . random_int(1000000000, 9999999999);
        $this->createProduct($barcode, $this->inactiveShopId);

        $result = (new Product())->findIdsByBarcodes([$barcode]);

        $this->assertArrayHasKey(
            $barcode,
            $result,
            'Le code-barres doit avoir une entrée, même si le produit est introuvable.'
        );
        $this->assertNull($result[$barcode]);
    }

    /**
     * Équivalence complète : un lot de cas variés, comparé entre l'ancien
     * chemin unitaire et le nouveau chemin batché.
     */
    public function testLeLookupBatchedEstEquivalantAuLookupUnitaireSurDesCasVaries(): void
    {
        $exact = 'BLKE' . random_int(1000000000, 9999999999);
        $this->createProduct($exact, $this->shopId);

        $inactive = 'BLKV' . random_int(1000000000, 9999999999);
        $this->createProduct($inactive, $this->inactiveShopId);

        $orphan = 'BLKO' . random_int(1000000000, 9999999999);
        $this->createProduct($orphan, null);

        $this->assertBatchMatchesUnit([
            $exact,
            strtolower($exact),
            $inactive,
            $orphan,
            'BLK_INCONNU_' . random_int(1000, 9999),
        ]);
    }

    /**
     * Le lookup doit rester correct au-delà d'un lot de 500 code-barres
     * (le découpage en lots est le cœur du correctif de charge).
     */
    public function testLeLookupBatcheFonctionneAuDelaDUnLot(): void
    {
        $barcodes = [];
        $expectedCount = 0;

        // 600 code-barres : au moins 3 lots de 500.
        for ($i = 0; $i < 600; $i++) {
            $barcode = 'BLKB' . str_pad((string)$i, 6, '0', STR_PAD_LEFT) . random_int(10, 99);
            $barcodes[] = $barcode;
            // Un produit sur trois existe réellement.
            if ($i % 3 === 0) {
                $this->createProduct($barcode, $this->shopId);
                $expectedCount++;
            }
        }

        $result = (new Product())->findIdsByBarcodes($barcodes);

        $this->assertCount(count($barcodes), $result, 'Chaque code-barres doit avoir une entrée.');
        $found = 0;
        foreach ($result as $id) {
            if ($id !== null) {
                $found++;
            }
        }
        $this->assertSame($expectedCount, $found, 'Le nombre de produits trouvés doit être exact.');
    }

    /**
     * Non-régression fonctionnelle de l'import : un fichier contenant à la
     * fois un produit nouveau, un produit existant et un code-barres
     * existant en BOUTIQUE INACTIVE doit produire les mêmes compteurs
     * qu'avant le correctif.
     */
    public function testLImportCompteLesProduitsExistantsCommeAvant(): void
    {
        $existant = 'BLKIMP' . random_int(1000000000, 9999999999);
        $this->createProduct($existant, $this->shopId);

        $inactif = 'BLKIMPI' . random_int(1000000000, 9999999999);
        $this->createProduct($inactif, $this->inactiveShopId);

        $nouveau = 'BLKIMPN' . random_int(1000000000, 9999999999);
        $this->createdBarcodes[] = $nouveau;

        $path = tempnam(sys_get_temp_dir(), 'blk_imp_') . '.csv';
        $fh = fopen($path, 'w');
        fwrite($fh, "code_barres;nom;categorie;prix;stock\n");
        fwrite($fh, $existant . ";Nom existant;CatLookup;1500;10\n");
        fwrite($fh, $nouveau . ";Nom nouveau;CatLookup;2500;20\n");
        fclose($fh);

        $service = new ProductImportService();
        // ⚠️ `mode => 'update'` : en mode 'create' (défaut), un produit
        // déjà existant est IGNORÉ (compteur `skipped`), pas mis à jour.
        $result = $service->importFile($this->shopId, $path, ['mode' => 'update']);

        @unlink($path);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['summary']['created'], 'Le produit nouveau doit être créé.');
        $this->assertSame(1, $result['summary']['updated'], 'Le produit existant doit être mis à jour.');
        $this->assertSame(0, $result['summary']['errors']);

        // Le produit de la boutique inactive n'est pas « trouvé » : l'import
        // tente de le créer → il doit échouer sur l'UNIQUE global, pas
        // mettre à jour le produit d'une autre boutique.
        $row = self::$pdo->prepare('SELECT nom, shop_id FROM produits WHERE code_barres = ?');
        $row->execute([$inactif]);
        $inactifRow = $row->fetch();
        $this->assertNotNull($inactifRow);
        $this->assertSame(
            (int)$this->inactiveShopId,
            (int)$inactifRow['shop_id'],
            'Le produit d\'une boutique inactive ne doit pas être réassigné.'
        );
        $this->assertNotSame(
            'Nom nouveau',
            $inactifRow['nom'],
            'Le produit d\'une boutique inactive ne doit pas être écrasé.'
        );
    }
}
