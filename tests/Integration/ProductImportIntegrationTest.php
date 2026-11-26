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

    /**
     * Crée un .csv temporaire (séparateur `;`).
     */
    private function makeCsv(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'import_int_csv_') . '.csv';
        file_put_contents($path, $content);
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

    /**
     * SUPER_ADMIN : l'import doit rattacher les produits à la boutique
     * de destination (et jamais les laisser sans boutique).
     */
    public function testLesProduitsSontRattachesALaBoutiqueDeDestination(): void
    {
        $barcode = 'IMP' . random_int(1000000000, 9999999999);
        $this->createdBarcodes[] = $barcode;

        $path = $this->makeXlsx(
            ['code_barres', 'nom', 'categorie', 'prix'],
            [[$barcode, 'Produit Destine', 'CatImport', '500']]
        );

        $service = new ProductImportService();
        $result = $service->importFile($this->shopId, $path);

        $this->assertSame(1, $result['summary']['created']);

        $stmt = self::$pdo->prepare('SELECT shop_id FROM produits WHERE code_barres = ?');
        $stmt->execute([$barcode]);
        $shopId = $stmt->fetchColumn();

        $this->assertNotNull($shopId, 'Le produit ne doit PAS être orphelin (shop_id NULL).');
        $this->assertSame((int)$this->shopId, (int)$shopId);
    }

    /**
     * RÉGRESSION : une seule ligne INVALIDIE ne doit pas faire croire à
     * un fichier vide.
     *
     * ⚠️ L'import en flux compte les lignes LUES, pas les produits
     *    valides. Compter les produits valides faisait déclencher le
     *    retour « fichier vide », qui REMPLAÇAIT le résumé des erreurs
     *    par un résumé vide : l'utilisateur ne voyait plus POURQUOI sa
     *    ligne avait été rejetée.
     *
     *    Le cas le plus fréquent : la catégorie n'existe pas dans la
     *    boutique.
     */
    public function testUneLigneInvalideEstSignaleeDansLeResume(): void
    {
        $barcode = 'IMP' . random_int(1000000000, 9999999999);
        $this->createdBarcodes[] = $barcode;

        // La catégorie « CategorieInexistanteXYZ » n'existe pas.
        $path = $this->makeXlsx(
            ['code_barres', 'nom', 'categorie', 'prix'],
            [[$barcode, 'Produit Sans Categorie', 'CategorieInexistanteXYZ', '500']]
        );

        $service = new ProductImportService();
        $result = $service->importFile($this->shopId, $path);

        // ⚠️ L'erreur doit être COMPTÉE…
        $this->assertSame(1, $result['summary']['errors'],
            'la ligne invalide doit être comptée dans le résumé');
        $this->assertSame(0, $result['summary']['created']);

        // …et DÉTAILLÉE dans le rapport.
        $this->assertNotEmpty($result['rows'], 'le rapport doit contenir la ligne rejetée');
        $this->assertSame('error', $result['rows'][0]['status']);
        $this->assertStringContainsString(
            'introuvable',
            $result['rows'][0]['message'],
            'le message doit dire POURQUOI la ligne a été rejetée'
        );

        // Et le fichier n'est PAS vide : il ne faut pas le dire.
        $this->assertStringNotContainsString(
            'vide',
            (string)($result['error'] ?? ''),
            'une ligne invalide ne doit pas être signalée comme un fichier vide'
        );

        $stmt = self::$pdo->prepare('SELECT COUNT(*) FROM produits WHERE code_barres = ?');
        $stmt->execute([$barcode]);
        $this->assertSame(0, (int)$stmt->fetchColumn(), 'aucun produit ne doit être créé');
    }

    /**
     * SUPER_ADMIN : une catégorie d'une AUTRE boutique ne doit pas être
     * utilisée (sinon le produit est rattaché à la mauvaise catégorie).
     */
    public function testUneCategorieDUneAutreBoutiqueEstRefusee(): void
    {
        // Catégorie appartenant à une autre boutique.
        $otherCode = 'IMP2_' . strtoupper(bin2hex(random_bytes(3)));
        self::$pdo->prepare('INSERT INTO shops (nom, code, actif) VALUES (?, ?, 1)')
            ->execute(['Boutique Test Import 2', $otherCode]);
        $otherShopId = (int)self::$pdo->lastInsertId();

        self::$pdo->prepare('INSERT INTO categories (category, shop_id) VALUES (?, ?)')
            ->execute(['CatAutreBoutique', $otherShopId]);

        try {
            $barcode = 'IMP' . random_int(1000000000, 9999999999);
            $this->createdBarcodes[] = $barcode;

            $path = $this->makeXlsx(
                ['code_barres', 'nom', 'categorie', 'prix'],
                [[$barcode, 'Produit Mauvaise Categorie', 'CatAutreBoutique', '500']]
            );

            $service = new ProductImportService();
            $result = $service->importFile($this->shopId, $path);

            $this->assertSame(0, $result['summary']['created'], 'Le produit ne doit pas être créé.');
            $this->assertSame(1, $result['summary']['errors']);

            $stmt = self::$pdo->prepare('SELECT COUNT(*) FROM produits WHERE code_barres = ?');
            $stmt->execute([$barcode]);
            $this->assertSame(0, (int)$stmt->fetchColumn(), 'Aucun produit ne doit être créé.');
        } finally {
            self::$pdo->prepare('DELETE FROM categories WHERE shop_id = ?')->execute([$otherShopId]);
            self::$pdo->prepare('DELETE FROM shops WHERE id = ?')->execute([$otherShopId]);
        }
    }

    /**
     * RÉGRESSION — « Duplicate entry '3017620000036' for key
     * 'produits.code_barres' » en mode « mise à jour ».
     *
     * ⚠️ Le cas réel : un fichier fournisseur de codes-barres EAN-13
     *    (NUMÉRIQUES) réimporté en mode « mettre à jour ».
     *
     *    La cause : le map code-barres → id est fusionné avec
     *    `array_merge()`, qui RÉINDEXE les clés numériques (PHP caste une
     *    clé « chaîne numérique » en entier). Le map perdait ses clés, le
     *    lookup rendait `null` pour des produits PRÉSENTS, et l'import
     *    tentait un INSERT → violation de l'UNIQUE sur TOUTES les lignes.
     *
     *    Les anciens tests utilisaient des code-barres ALPHANUMÉRIQUES
     *    (« IMP123… ») : ils restaient des clés de type chaîne et
     *    survivaient à `array_merge()`. C'est ce qui a masqué le bug.
     */
    public function testUnReimportEnMiseAJourAvecDesCodeBarresNumeriquesNeProvoquePasDeDoublon(): void
    {
        // Code-barres numériques (EAN-13), comme dans le fichier réel.
        $codes = [
            '3017620000010',
            '3017620000028',
            '3017620000036',
            '3017620000044',
        ];
        foreach ($codes as $code) {
            $this->createdBarcodes[] = $code;
        }

        // 1er import : les produits sont créés.
        $path = $this->makeCsv(
            "code_barres;nom;categorie;prix;stock\n"
            . "3017620000010;\"Coca-Cola 33cl Lot 2\";CatImport;8019.50;18\n"
            . "3017620000028;\"Sprite 50cl Lot 3\";CatImport;15939.00;31\n"
            . "3017620000036;\"Fanta Orange 1L Lot 4\";CatImport;23857.00;44\n"
            . "3017620000044;\"Pepsi 1.5L Lot 5\";CatImport;12000.00;12\n"
        );

        $service = new ProductImportService();
        $premier = $service->importFile($this->shopId, $path);

        $this->assertTrue($premier['success']);
        $this->assertSame(4, $premier['summary']['created']);
        $this->assertSame(0, $premier['summary']['errors']);

        // 2e import du MÊME fichier, en mode « mise à jour ».
        $second = $service->importFile($this->shopId, $path, ['mode' => 'update']);

        // ⚠️ AUCUNE erreur : c'est le cœur de la régression.
        $this->assertSame(
            0,
            $second['summary']['errors'],
            'Le réimport ne doit produire aucune erreur SQL (Duplicate entry).'
        );
        $this->assertSame(
            4,
            $second['summary']['updated'],
            'Les 4 produits existants doivent être mis à jour.'
        );
        $this->assertSame(0, $second['summary']['created']);

        // Aucun doublon en base : l'UNIQUE tient toujours.
        $stmt = self::$pdo->prepare(
            'SELECT code_barres, COUNT(*) AS c FROM produits
             WHERE code_barres IN (?, ?, ?, ?) GROUP BY code_barres'
        );
        $stmt->execute($codes);
        $rows = $stmt->fetchAll();
        $this->assertCount(4, $rows, 'Chaque code-barres doit apparaître une seule fois.');
        foreach ($rows as $row) {
            $this->assertSame(1, (int)$row['c'], 'Doublon en base pour ' . $row['code_barres']);
        }
    }

    /**
     * RÉGRESSION — le cas du fichier RÉEL : plus de 500 lignes (donc
     * plusieurs blocs de lookup), avec des code-barres numériques déjà
     * présents en base.
     *
     * ⚠️ C'est ce qui a produit « 5 000 ERREURS » sur l'écran : chaque
     *    bloc de 500 perdait les clés numériques de son lookup.
     */
    public function testUnReimportDePlusDe500LignesNumeriquesNeProvoquePasDErreur(): void
    {
        $nb = 600;   // > 500 : force le passage à un second bloc de lookup

        $lignes = "code_barres;nom;categorie;prix;stock\n";
        for ($i = 0; $i < $nb; $i++) {
            $code = '301762' . str_pad((string)$i, 7, '0', STR_PAD_LEFT);
            $this->createdBarcodes[] = $code;
            $lignes .= $code . ';Produit ' . $i . ';CatImport;1000;5' . "\n";
        }

        $path = $this->makeCsv($lignes);

        $service = new ProductImportService();
        $premier = $service->importFile($this->shopId, $path);
        $this->assertTrue($premier['success'], 'Le premier import doit réussir.');
        $this->assertSame($nb, $premier['summary']['created']);
        $this->assertSame(0, $premier['summary']['errors']);

        // Réimport en mode « mise à jour » : c'est le cas qui échouait.
        $second = $service->importFile($this->shopId, $path, ['mode' => 'update']);

        $this->assertSame(
            0,
            $second['summary']['errors'],
            'Aucune erreur SQL ne doit être levée sur un réimport multi-blocs.'
        );
        $this->assertSame(
            $nb,
            $second['summary']['updated'],
            'Tous les produits existants doivent être mis à jour.'
        );
    }

    /**
     * Sans boutique de destination, aucune catégorie ne doit être proposée
     * (sinon le produit pourrait être rattaché à une catégorie d'ailleurs).
     */
    public function testSansBoutiqueAucuneCategorieNEstChargee(): void
    {
        $service = new ProductImportService();

        $reflection = new \ReflectionMethod($service, 'loadCategories');
        $reflection->setAccessible(true);
        $categories = $reflection->invoke($service, null);

        $this->assertSame([], $categories, 'Aucune catégorie ne doit être chargée sans boutique.');
    }
}
