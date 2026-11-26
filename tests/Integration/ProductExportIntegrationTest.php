<?php

namespace Tests\Integration;

use App\Core\Database;
use App\Services\ProductExportService;
use App\Services\ProductImportService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;

/**
 * Export du catalogue produits vers Excel, en base de données.
 *
 * Le test clé est le ROUND-TRIP : on exporte les produits, on modifie
 * le fichier exporté, on le ré-importe (mode « update ») et on vérifie
 * que les modifications sont bien prises en compte.
 */
class ProductExportIntegrationTest extends TestCase
{
    /** Marqueur « toutes les boutiques » (super_admin). */
    private const ALL_SHOPS = -1;

    private static $pdo;
    private $shopId;
    private $categoryId;
    private $createdBarcodes = [];

    public static function setUpBeforeClass(): void
    {
        self::$pdo = Database::getInstance()->getConnection();
    }

    protected function setUp(): void
    {
        $code = 'EXP_' . strtoupper(bin2hex(random_bytes(3)));
        self::$pdo->prepare(
            'INSERT INTO shops (nom, code, actif) VALUES (?, ?, 1)'
        )->execute(['Boutique Test Export', $code]);
        $this->shopId = (int)self::$pdo->lastInsertId();

        self::$pdo->prepare(
            'INSERT INTO categories (category, shop_id) VALUES (?, ?)'
        )->execute(['CatExport', $this->shopId]);
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
        if ($this->shopId) {
            self::$pdo->prepare('DELETE FROM shops WHERE id = ?')->execute([$this->shopId]);
            $this->shopId = 0;
        }
    }

    /**
     * Crée un produit de test et enregistre son code-barres pour le nettoyage.
     */
    private function createProduct(string $suffix, string $nom, float $prix, float $stock = 0): string
    {
        $barcode = 'EXP' . $suffix . random_int(100000, 999999);
        $this->createdBarcodes[] = $barcode;

        self::$pdo->prepare(
            'INSERT INTO produits (code_barres, nom, category_id, shop_id, prix, stock, stock_minimum, image, taxe_id, product_type, prod_service, remise_type, remise_value, taxe_specifique_type, taxe_specifique_value)
             VALUES (?, ?, ?, ?, ?, ?, ?, "", 1, "unite", "BIE", "%", 0, "%", 0)'
        )->execute([$barcode, $nom, $this->categoryId, $this->shopId, $prix, $stock, 0]);
        $id = (int)self::$pdo->lastInsertId();

        if ($stock > 0) {
            self::$pdo->prepare(
                'INSERT INTO product_batches (product_id, batch_number, stock, date_expiration, date_reception)
                 VALUES (?, NULL, ?, NULL, CURDATE())'
            )->execute([$id, $stock]);
            self::$pdo->prepare('UPDATE produits SET stock = ? WHERE id = ?')->execute([$stock, $id]);
        }

        return $barcode;
    }

    /**
     * Exporte les produits et renvoie le binaire du fichier .xlsx.
     */
    private function exportBinary(array $filters = [], array $options = [], $shopId = null): string
    {
        $service = new ProductExportService();

        ob_start();
        $target = ($shopId === self::ALL_SHOPS) ? null : ($shopId ?? $this->shopId);

        $service->export($target, $filters, $options);
        return (string)ob_get_clean();
    }

    /**
     * Écrit un binaire dans un .xlsx temporaire et renvoie le chemin.
     */
    private function saveTemp(string $binary): string
    {
        $path = tempnam(sys_get_temp_dir(), 'export_test_') . '.xlsx';
        file_put_contents($path, $binary);

        return $path;
    }

    private function findProduct(string $barcode): ?array
    {
        $stmt = self::$pdo->prepare('SELECT * FROM produits WHERE code_barres = ?');
        $stmt->execute([$barcode]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function testLExportProduitUnFichierExcelValide(): void
    {
        $this->createProduct('A', 'Produit Export A', 1000);

        $binary = $this->exportBinary();

        $this->assertNotEmpty($binary, 'L\'export ne doit pas être vide.');
        $this->assertSame('PK', substr($binary, 0, 2), 'Le fichier doit être un XLSX valide.');
    }

    public function testLExportContientLesEntetesDuModeleDImport(): void
    {
        $this->createProduct('B', 'Produit Export B', 500);

        $path = $this->saveTemp($this->exportBinary());

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();

        $this->assertSame('Produits', $sheet->getTitle());

        $headers = $sheet->rangeToArray('A1:N1')[0];
        $this->assertSame('code_barres', $headers[0]);
        $this->assertSame('nom', $headers[1]);
        $this->assertSame('categorie', $headers[2]);
        $this->assertSame('prix', $headers[3]);
        $this->assertSame('date_expiration', $headers[13]);

        $spreadsheet->disconnectWorksheets();
        @unlink($path);
    }

    public function testLExportContientLesProduitsDeLaBoutique(): void
    {
        $barcode = $this->createProduct('C', 'Produit Export C', 2500, 42);

        $path = $this->saveTemp($this->exportBinary());

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();

        // La valeur BRUTE (non formatée) doit être un vrai nombre : c'est
        // elle que l'import lira. Le format d'affichage (séparateur de
        // milliers, locale) ne doit pas altérer la donnée.
        $raw = $sheet->rangeToArray('A2:N2', null, false, false)[0];
        $this->assertIsFloat($raw[3], 'Le prix doit être stocké comme un nombre.');
        $this->assertSame(2500.0, $raw[3]);
        $this->assertSame(42.0, (float)$raw[4]);

        // La valeur formatée reste lisible pour l'utilisateur (séparateur
        // de milliers + 2 décimales).
        $formatted = $sheet->rangeToArray('A2:N2', null, false, true)[0];
        $this->assertMatchesRegularExpression('/2[,.]?500[.,]00/', (string)$formatted[3]);

        // Les autres colonnes.
        $this->assertSame($barcode, $raw[0]);
        $this->assertSame('Produit Export C', $raw[1]);
        $this->assertSame('CatExport', $raw[2]);

        $spreadsheet->disconnectWorksheets();
        @unlink($path);
    }

    public function testLesFiltresDeLaPageSontAppliques(): void
    {
        $this->createProduct('D', 'Lait Special', 100);
        $this->createProduct('E', 'Pain Special', 200);

        // Filtre par recherche.
        $path = $this->saveTemp($this->exportBinary(['search' => 'Lait']));
        $spreadsheet = IOFactory::load($path);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, false, true, false);
        $this->assertCount(2, $rows, 'Un seul produit doit correspondre à « Lait ».');
        $this->assertSame('Lait Special', $rows[1][1]);
        $spreadsheet->disconnectWorksheets();
        @unlink($path);

        // Filtre par catégorie : la catégorie n'existe pas → aucun résultat.
        $path = $this->saveTemp($this->exportBinary(['category' => 'Categorie Inexistante']));
        $spreadsheet = IOFactory::load($path);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, false, true, false);
        $this->assertCount(1, $rows, 'Seul l\'en-tête doit rester.');
        $spreadsheet->disconnectWorksheets();
        @unlink($path);
    }

    /**
         * ROUND-TRIP : export → modification du fichier → ré-import (mode update).
         */
    public function testLeRoundTripExportImportFonctionne(): void
    {
        $barcode = $this->createProduct('F', 'Produit Round Trip', 1000, 10);

        // 1) Export.
        $path = $this->saveTemp($this->exportBinary());

        // 2) Modification du fichier exporté (prix + nom).
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->getCell('B2')->setValue('Produit Renomme');
        $sheet->getCell('D2')->setValue(3999.99);
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();

        // 3) Ré-import en mode « update ».
        $importService = new ProductImportService();
        $result = $importService->importFile($this->shopId, $path, ['mode' => 'update']);

        $this->assertSame(1, $result['summary']['updated'], 'Le produit doit être mis à jour.');
        $this->assertSame(0, $result['summary']['errors']);

        // 4) Vérification en base.
        $product = $this->findProduct($barcode);
        $this->assertSame('Produit Renomme', $product['nom']);
        $this->assertSame(3999.99, (float)$product['prix']);

        @unlink($path);
    }

    public function testLaFeuilleDetailEstOptionnelle(): void
    {
        $this->createProduct('G', 'Produit Export G', 100);

        // Sans l'option : une seule feuille.
        $path = $this->saveTemp($this->exportBinary());
        $spreadsheet = IOFactory::load($path);
        $this->assertSame(1, $spreadsheet->getSheetCount());
        $spreadsheet->disconnectWorksheets();
        @unlink($path);

        // Avec l'option : deux feuilles.
        $path = $this->saveTemp($this->exportBinary([], ['include_details' => true]));
        $spreadsheet = IOFactory::load($path);
        $this->assertSame(2, $spreadsheet->getSheetCount());
        $this->assertSame('Détail', $spreadsheet->getSheet(1)->getTitle());
        $spreadsheet->disconnectWorksheets();
        @unlink($path);
    }

    /**
     * RÉGRESSION : `fromArray()` PERD la valeur 0.0 (cellule écrite vide).
     * Un produit à stock 0 ou remise 0 doit pourtant être exporté avec « 0 ».
     */
    public function testLesValeursZeroSontConservees(): void
    {
        // Produit sans lot de stock → total_stock = 0.
        $this->createProduct('H', 'Produit Stock Zero', 500);

        $path = $this->saveTemp($this->exportBinary());

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $raw = $sheet->rangeToArray('A2:N2', null, false, false)[0];

        // Colonne E = stock (index 4), K = remise_value (index 10).
        $this->assertSame(0.0, (float)$raw[4], 'Le stock à 0 doit être conservé.');
        $this->assertSame(0.0, (float)$raw[10], 'La remise à 0 doit être conservée.');

        // Ce ne sont pas des cellules vides.
        $this->assertNotNull($raw[4], 'La cellule de stock ne doit pas être vide.');
        $this->assertNotNull($raw[10], 'La cellule de remise ne doit pas être vide.');

        $spreadsheet->disconnectWorksheets();
        @unlink($path);
    }
    /**
     * SUPER_ADMIN : l'export sans filtre doit couvrir TOUTES les boutiques
     * (et non seulement les produits orphelins).
     */
    public function testLExportSansFiltreCouvreToutesLesBoutiques(): void
    {
        $code = 'EXP2_' . strtoupper(bin2hex(random_bytes(3)));
        self::$pdo->prepare('INSERT INTO shops (nom, code, actif) VALUES (?, ?, 1)')
            ->execute(['Boutique Test Export 2', $code]);
        $shop2Id = (int)self::$pdo->lastInsertId();

        try {
            $this->createProduct('I', 'Produit Boutique 1', 100);
            $bc2 = 'EXPJ' . random_int(100000, 999999);
            $this->createdBarcodes[] = $bc2;
            self::$pdo->prepare(
                'INSERT INTO produits (code_barres, nom, category_id, shop_id, prix, stock, stock_minimum, image, taxe_id, product_type, prod_service, remise_type, remise_value, taxe_specifique_type, taxe_specifique_value)
                 VALUES (?, ?, ?, ?, ?, 5, 1, "", 1, "unite", "BIE", "%", 0, "%", 0)'
            )->execute([$bc2, 'Produit Boutique 2', $this->categoryId, $shop2Id, 200]);

            // Export multi-boutiques : $shopId = null (comme le super_admin).
            $path = $this->saveTemp($this->exportBinary([], [], self::ALL_SHOPS));
            $spreadsheet = IOFactory::load($path);
            $rows = $spreadsheet->getActiveSheet()->toArray(null, false, true, false);

            $this->assertCount(3, $rows, 'Les 2 boutiques doivent être exportées.');

            // Une colonne « boutique » est ajoutée en fin de ligne.
            $this->assertSame('boutique', $rows[0][14] ?? null, 'La colonne boutique doit être presente.');

            $shops = array_map(fn ($r) => $r[14], array_slice($rows, 1));
            $this->assertContains('Boutique Test Export', $shops);
            $this->assertContains('Boutique Test Export 2', $shops);

            $spreadsheet->disconnectWorksheets();
            @unlink($path);
        } finally {
            self::$pdo->prepare('DELETE FROM product_batches WHERE product_id IN (SELECT id FROM produits WHERE shop_id = ?)')->execute([$shop2Id]);
            self::$pdo->prepare('DELETE FROM produits WHERE shop_id = ?')->execute([$shop2Id]);
            self::$pdo->prepare('DELETE FROM shops WHERE id = ?')->execute([$shop2Id]);
        }
    }

    /**
     * SUPER_ADMIN : avec un filtre boutique, on n'exporte qu'une boutique
     * et la colonne « boutique » n'est PAS ajoutee (export mono-boutique).
     */
    public function testLExportFiltreParBoutique(): void
    {
        $code = 'EXP3_' . strtoupper(bin2hex(random_bytes(3)));
        self::$pdo->prepare('INSERT INTO shops (nom, code, actif) VALUES (?, ?, 1)')
            ->execute(['Boutique Test Export 3', $code]);
        $shop2Id = (int)self::$pdo->lastInsertId();

        try {
            $this->createProduct('K', 'Produit Boutique 1', 100);
            $bc2 = 'EXPL' . random_int(100000, 999999);
            $this->createdBarcodes[] = $bc2;
            self::$pdo->prepare(
                'INSERT INTO produits (code_barres, nom, category_id, shop_id, prix, stock, stock_minimum, image, taxe_id, product_type, prod_service, remise_type, remise_value, taxe_specifique_type, taxe_specifique_value)
                 VALUES (?, ?, ?, ?, ?, 5, 1, "", 1, "unite", "BIE", "%", 0, "%", 0)'
            )->execute([$bc2, 'Produit Boutique 3', $this->categoryId, $shop2Id, 200]);

            // Export de la boutique 2 uniquement.
            $path = $this->saveTemp($this->exportBinary(['shop_id' => $shop2Id], [], self::ALL_SHOPS));
            $spreadsheet = IOFactory::load($path);
            $rows = $spreadsheet->getActiveSheet()->toArray(null, false, true, false);

            $this->assertCount(2, $rows, 'Un seul produit doit etre exporte.');
            $this->assertSame('Produit Boutique 3', $rows[1][1]);
            $this->assertNull($rows[0][14] ?? null, 'Pas de colonne boutique en mono-boutique.');

            $spreadsheet->disconnectWorksheets();
            @unlink($path);
        } finally {
            self::$pdo->prepare('DELETE FROM product_batches WHERE product_id IN (SELECT id FROM produits WHERE shop_id = ?)')->execute([$shop2Id]);
            self::$pdo->prepare('DELETE FROM produits WHERE shop_id = ?')->execute([$shop2Id]);
            self::$pdo->prepare('DELETE FROM shops WHERE id = ?')->execute([$shop2Id]);
        }
    }
}
