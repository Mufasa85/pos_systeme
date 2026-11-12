<?php

namespace Tests\Integration;

use App\Core\Database;
use App\Services\ProductImportService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;

/**
 * Import réel de produits depuis un fichier Excel, en base de données.
 *
 * Le test travaille dans une boutique temporaire et supprime toutes les
 * données créées (produits, lots, catégories) au tearDown.
 */
class ProductImportIntegrationTest extends TestCase
{
    private static $pdo;
    private $shopId;
    private $categoryId;
    private $tmpFiles = [];
    private $createdBarcodes = [];

    public static function setUpBeforeClass(): void
    {
        self::$pdo = Database::getInstance()->getConnection();
    }

    protected function setUp(): void
    {
        $code = 'IMP_' . strtoupper(bin2hex(random_bytes(3)));
        self::$pdo->prepare(
            'INSERT INTO shops (nom, code, actif) VALUES (?, ?, 1)'
        )->execute(['Boutique Test Import', $code]);
        $this->shopId = (int)self::$pdo->lastInsertId();

        self::$pdo->prepare(
            'INSERT INTO categories (category, shop_id) VALUES (?, ?)'
        )->execute(['CatImport', $this->shopId]);
        $this->categoryId = (int)self::$pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        $this->tmpFiles = [];

        // Supprime les produits importés (les lots partent en cascade).
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
        if ($this->shopId) {
            self::$pdo->prepare('DELETE FROM shops WHERE id = ?')->execute([$this->shopId]);
            $this->shopId = 0;
        }
    }

    /**
     * Crée un .xlsx temporaire.
     */
    private function makeXlsx(array $headers, array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headers, null, 'A1');
        if (!empty($rows)) {
            $sheet->fromArray($rows, null, 'A2');
        }

        $path = tempnam(sys_get_temp_dir(), 'import_int_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();

        $this->tmpFiles[] = $path;

        return $path;
    }

    private function findProduct(string $barcode): ?array
    {
        $stmt = self::$pdo->prepare('SELECT * FROM produits WHERE code_barres = ?');
        $stmt->execute([$barcode]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function testDesProduitsSontCreesAvecLeStockInitial(): void
    {
        $barcode = 'IMP' . random_int(1000000000, 9999999999);
        $this->createdBarcodes[] = $barcode;

        $path = $this->makeXlsx(
            ['code_barres', 'nom', 'categorie', 'prix', 'stock', 'stock_minimum'],
            [[$barcode, 'Produit Test Import', 'CatImport', '1500,50', '20', '5']]
        );

        $service = new ProductImportService();
        $result = $service->importFile($this->shopId, $path);

        $this->assertTrue($result['success'], 'L\'import doit réussir.');
        $this->assertSame(1, $result['summary']['created']);
        $this->assertSame(0, $result['summary']['errors']);

        $product = $this->findProduct($barcode);
        $this->assertNotNull($product, 'Le produit doit exister en base.');
        $this->assertSame('Produit Test Import', $product['nom']);
        $this->assertSame(1500.50, (float)$product['prix']);
        $this->assertSame((int)$this->categoryId, (int)$product['category_id']);
        $this->assertSame((int)$this->shopId, (int)$product['shop_id']);
        $this->assertSame(20.0, (float)$product['stock']);
        $this->assertSame(5.0, (float)$product['stock_minimum']);

        // Le lot de stock doit avoir été créé.
        $stmt = self::$pdo->prepare('SELECT * FROM product_batches WHERE product_id = ?');
        $stmt->execute([$product['id']]);
        $batches = $stmt->fetchAll();
        $this->assertCount(1, $batches);
        $this->assertSame(20.0, (float)$batches[0]['stock']);
    }

    public function testUneLigneInvalideNeBloquePasLesAutres(): void
    {
        $good = 'IMP' . random_int(1000000000, 9999999999);
        $this->createdBarcodes[] = $good;

        $path = $this->makeXlsx(
            ['code_barres', 'nom', 'categorie', 'prix'],
            [
                [$good, 'Produit Valide', 'CatImport', '500'],
                ['IMP0000000001', '', 'CatImport', '300'],      // nom manquant
                ['IMP0000000002', 'Sans Prix', 'CatImport', ''], // prix manquant
                ['IMP0000000003', 'Mauvaise Categorie', 'Inexistante', '100'],
            ]
        );

        $service = new ProductImportService();
        $result = $service->importFile($this->shopId, $path);

        $this->assertSame(1, $result['summary']['created']);
        $this->assertSame(3, $result['summary']['errors']);
        $this->assertNotNull($this->findProduct($good));
    }

    public function testUnProduitExistantEstIgnoreeEnModeCreate(): void
    {
        $barcode = 'IMP' . random_int(1000000000, 9999999999);
        $this->createdBarcodes[] = $barcode;

        $path = $this->makeXlsx(
            ['code_barres', 'nom', 'categorie', 'prix'],
            [[$barcode, 'Premier Nom', 'CatImport', '100']]
        );

        $service = new ProductImportService();
        $service->importFile($this->shopId, $path);

        // Second import du même code-barres : doit être ignoré.
        $path2 = $this->makeXlsx(
            ['code_barres', 'nom', 'categorie', 'prix'],
            [[$barcode, 'Second Nom', 'CatImport', '200']]
        );

        $result = $service->importFile($this->shopId, $path2);

        $this->assertSame(0, $result['summary']['created']);
        $this->assertSame(1, $result['summary']['skipped']);

        $product = $this->findProduct($barcode);
        $this->assertSame('Premier Nom', $product['nom']);
    }

    public function testUnProduitExistantEstMisAJourEnModeUpdate(): void
    {
        $barcode = 'IMP' . random_int(1000000000, 9999999999);
        $this->createdBarcodes[] = $barcode;

        $path = $this->makeXlsx(
            ['code_barres', 'nom', 'categorie', 'prix'],
            [[$barcode, 'Premier Nom', 'CatImport', '100']]
        );

        $service = new ProductImportService();
        $service->importFile($this->shopId, $path);

        $path2 = $this->makeXlsx(
            ['code_barres', 'nom', 'categorie', 'prix'],
            [[$barcode, 'Nom Modifie', 'CatImport', '250']]
        );

        $result = $service->importFile($this->shopId, $path2, ['mode' => 'update']);

        $this->assertSame(1, $result['summary']['updated']);
        $this->assertSame(0, $result['summary']['created']);

        $product = $this->findProduct($barcode);
        $this->assertSame('Nom Modifie', $product['nom']);
        $this->assertSame(250.0, (float)$product['prix']);
    }

    public function testUneCategorieAbsenteEstCreeeSurOption(): void
    {
        $barcode = 'IMP' . random_int(1000000000, 9999999999);
        $this->createdBarcodes[] = $barcode;
        $newCategory = 'Nouvelle Cat ' . random_int(1000, 9999);

        $path = $this->makeXlsx(
            ['code_barres', 'nom', 'categorie', 'prix'],
            [[$barcode, 'Produit Nouvelle Cat', $newCategory, '100']]
        );

        $service = new ProductImportService();
        $result = $service->importFile($this->shopId, $path, ['create_categories' => true]);

        $this->assertSame(1, $result['summary']['created']);
        $this->assertSame(0, $result['summary']['errors']);

        // Nettoyage de la catégorie créée par le test.
        $stmt = self::$pdo->prepare('DELETE FROM categories WHERE category = ? AND shop_id = ?');
        $stmt->execute([$newCategory, $this->shopId]);
    }

    public function testUnCodeBarresVideEstGenereAutomatiquement(): void
    {
        $path = $this->makeXlsx(
            ['nom', 'categorie', 'prix'],
            [['Produit Sans Code Barres', 'CatImport', '700']]
        );

        $service = new ProductImportService();
        $result = $service->importFile($this->shopId, $path);

        $this->assertSame(1, $result['summary']['created']);

        $stmt = self::$pdo->prepare('SELECT code_barres FROM produits WHERE nom = ? AND shop_id = ?');
        $stmt->execute(['Produit Sans Code Barres', $this->shopId]);
        $barcode = $stmt->fetchColumn();

        $this->assertNotEmpty($barcode, 'Un code-barres doit être généré.');
        $this->createdBarcodes[] = $barcode;
    }
}
