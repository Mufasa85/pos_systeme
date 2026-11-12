<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Tax;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as SharedDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Import de produits depuis un fichier Excel (.xlsx/.xls) ou CSV.
 *
 * ── Colonnes reconnues (en-têtes) ─────────────────────────────────────
 * Les en-têtes sont normalisés (minuscules, sans accents, sans ponctuation)
 * puis confrontés à une liste d'alias. Exemples :
 *
 *   code_barres           → code_barres, code barre, barcode, ean, reference
 *   nom                   → nom, produit, designation, libelle
 *   categorie             → categorie, famille, rayon
 *   prix                  → prix, prix de vente, prix unitaire, pv
 *   stock                 → stock, stock initial, quantite
 *   stock_minimum         → stock minimum, seuil, seuil d alerte
 *   taxe                  → taxe, groupe de taxe, etiquette
 *   product_type          → type de vente, unite, coupe
 *   prod_service          → type de service, bie, ser, tax
 *   remise_type           → type de remise
 *   remise_value          → remise, valeur de remise
 *   taxe_specifique_type  → type taxe specifique
 *   taxe_specifique_value → taxe specifique
 *   date_expiration       → date expiration, peremption
 *
 * Seules `nom`, `categorie` et `prix` sont obligatoires : le reste a un
 * défaut raisonnable (code-barres auto, stock 0, taxe A, catégorie par défaut).
 *
 * ── Comportement ───────────────────────────────────────────────────────
 * Chaque ligne est traitée indépendamment : une ligne invalide n'annule pas
 * les autres. Les doublons de code-barres sont ignorés (mode `create`) ou
 * mis à jour (mode `update`).
 */
class ProductImportService
{
    /** Alias d'en-têtes acceptés, par colonne logique. */
    private const HEADER_ALIASES = [
        'code_barres' => ['codebarres', 'codebarre', 'code', 'barcode', 'ean', 'ean13', 'reference', 'ref'],
        'nom' => ['nom', 'nomduproduit', 'produit', 'designation', 'libelle', 'name'],
        'categorie' => ['categorie', 'category', 'famille', 'rayon'],
        'prix' => ['prix', 'prixdevente', 'prixvente', 'prixunitaire', 'pv', 'price'],
        'stock' => ['stock', 'stockinitial', 'quantite', 'qte', 'quantiteinitiale'],
        'stock_minimum' => ['stockminimum', 'stockmin', 'seuil', 'seuildalerte'],
        'taxe' => ['taxe', 'groupedetaxe', 'grouptaxe', 'etiquette', 'taxgroup'],
        // ⚠️ Le nom canonique de la colonne DOIT figurer dans la liste,
        // sinon la valeur est silencieusement ignorée (défaut appliqué).
        'product_type' => ['producttype', 'typedevente', 'typevente', 'type', 'unitetype'],
        'prod_service' => ['prodservice', 'typesdeservice', 'typedeservice', 'service'],
        'remise_type' => ['remisetype', 'typederemise'],
        'remise_value' => ['remisevalue', 'remise', 'valeurremise', 'pourcentageremise'],
        'taxe_specifique_type' => ['taxespecifiquetype', 'typespecificite'],
        'taxe_specifique_value' => ['taxespecifiquevalue', 'taxespecifique', 'valeurtaxespecifique'],
        'date_expiration' => ['dateexpiration', 'expiration', 'peremption', 'dateperemption'],
    ];

    /** Types de vente acceptés (colonne `produits.product_type`). */
    private const PRODUCT_TYPES = ['unite', 'coupe', 'poids'];

    /** Types de service acceptés (colonne ENUM `produits.prod_service`). */
    private const PROD_SERVICES = ['BIE', 'SER', 'TAX'];

    /** Nombre max de lignes retournées à l'interface (le reste est compté). */
    private const MAX_REPORTED_ROWS = 200;

    private $productModel;
    private $batchModel;
    private $categoryModel;
    private $taxModel;

    public function __construct()
    {
        $this->productModel = new Product();
        $this->batchModel = new ProductBatch();
        $this->categoryModel = new Category();
        $this->taxModel = new Tax();
    }

    // MARK: Import

    /**
     * Importe un fichier produit et renvoie un rapport détaillé.
     *
     * @param int|string|null $shopId  Boutique cible (null = boutique de la session)
     * @param string $filePath         Chemin du fichier uploadé (temporaire)
     * @param array $options          mode, create_categories, category_id, tax_id
     *
     * @return array{success:bool, summary:array, rows:array, error?:string}
     */
    public function importFile($shopId, string $filePath, array $options = [])
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            return [
                'success' => false,
                'error' => 'Fichier illisible',
                'summary' => $this->emptySummary(),
                'rows' => [],
            ];
        }

        $mode = ($options['mode'] ?? 'create') === 'update' ? 'update' : 'create';
        $createCategories = !empty($options['create_categories']);
        $defaultCategoryId = isset($options['category_id']) ? (int)$options['category_id'] : 0;
        $defaultTaxId = isset($options['tax_id']) ? (int)$options['tax_id'] : 0;

        try {
            $rows = $this->readRows($filePath);
        } catch (\Throwable $e) {
            error_log('ProductImportService::readRows error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Impossible de lire le fichier : ' . $e->getMessage(),
                'summary' => $this->emptySummary(),
                'rows' => [],
            ];
        }

        if (empty($rows)) {
            return [
                'success' => false,
                'error' => 'Le fichier est vide ou ne contient aucune ligne de données',
                'summary' => $this->emptySummary(),
                'rows' => [],
            ];
        }

        // Contexte de résolution (catégories / taxes) chargé une seule fois.
        $categories = $this->loadCategories($shopId);
        $taxes = $this->loadTaxes();

        $summary = $this->emptySummary();
        $report = [];
        $seenBarcodes = [];

        foreach ($rows as $row) {
            $line = $row['line'];
            $cells = $row['cells'];

            try {
                $product = $this->buildProduct($cells, $shopId, $categories, $taxes, [
                    'default_category_id' => $defaultCategoryId,
                    'default_tax_id' => $defaultTaxId,
                    'create_categories' => $createCategories,
                ]);
            } catch (\Throwable $e) {
                $summary['errors']++;
                $this->addReport($report, [
                    'line' => $line,
                    'status' => 'error',
                    'nom' => $this->readString($cells, 'nom'),
                    'message' => $e->getMessage(),
                ]);
                continue;
            }

            // Contrôle des doublons à l'intérieur du fichier lui-même.
            $barcodeKey = $product['code_barres'];
            if (isset($seenBarcodes[$barcodeKey])) {
                $summary['skipped']++;
                $this->addReport($report, [
                    'line' => $line,
                    'status' => 'skipped',
                    'nom' => $product['nom'],
                    'message' => 'Code-barres en double dans le fichier (ligne ' . $seenBarcodes[$barcodeKey] . ')',
                ]);
                continue;
            }
            $seenBarcodes[$barcodeKey] = $line;

            $existing = $this->productModel->findByBarcode($barcodeKey);

            if ($existing && $mode === 'create') {
                $summary['skipped']++;
                $this->addReport($report, [
                    'line' => $line,
                    'status' => 'skipped',
                    'nom' => $product['nom'],
                    'message' => 'Produit déjà existant (code-barres ' . $barcodeKey . ')',
                ]);
                continue;
            }

            try {
                if ($existing) {
                    $this->updateProduct((int)$existing['id'], $product);
                    $summary['updated']++;
                    $status = 'updated';
                    $message = 'Produit mis à jour';
                } else {
                    $this->createProduct($product);
                    $summary['created']++;
                    $status = 'created';
                    $message = 'Produit créé';
                }
            } catch (\Throwable $e) {
                error_log('ProductImportService::write error (ligne ' . $line . '): ' . $e->getMessage());
                $summary['errors']++;
                $this->addReport($report, [
                    'line' => $line,
                    'status' => 'error',
                    'nom' => $product['nom'],
                    'message' => 'Enregistrement impossible : ' . $e->getMessage(),
                ]);
                continue;
            }

            $summary['total']++;
            $this->addReport($report, [
                'line' => $line,
                'status' => $status,
                'nom' => $product['nom'],
                'message' => $message,
            ]);
        }

        return [
            'success' => ($summary['created'] + $summary['updated']) > 0,
            'summary' => $summary,
            'rows' => $report,
        ];
    }

    // MARK: Modèle

    /**
     * Génère le fichier modèle (.xlsx) contenant les en-têtes attendus,
     * une ligne d'exemple et un onglet « Aide ». Le contenu est envoyé
     * directement en sortie HTTP.
     */
    public function sendTemplate(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Produits');

        $headers = array_keys(self::HEADER_ALIASES);
        $sheet->fromArray($headers, null, 'A1');

        $example = [
            '5400141352573',
            'Lait Frais 1L',
            'Comestible',
            '800',
            '24',
            '5',
            'A',
            'unite',
            'BIE',
            '%',
            '0',
            '%',
            '0',
            '',
        ];
        $sheet->fromArray($example, null, 'A2');

        // Mise en forme de l'en-tête + largeurs de colonnes lisibles.
        $sheet->getStyle('A1:N1')->getFont()->setBold(true);
        $sheet->getStyle('A1:N1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF0B5E88');
        $sheet->getStyle('A1:N1')->getFont()->getColor()->setARGB('FFFFFFFF');

        $widths = [18, 30, 20, 14, 12, 14, 16, 14, 16, 14, 16, 18, 18, 18];
        foreach ($widths as $i => $width) {
            $sheet->getColumnDimensionByColumn($i + 1)->setWidth($width);
        }

        // Deuxième feuille : rappel des règles.
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Aide');
        $sheet2->fromArray([
            ['Colonne', 'Obligatoire', 'Description'],
            ['code_barres', 'Non', 'Généré automatiquement si vide. Doit être unique.'],
            ['nom', 'Oui', 'Nom du produit.'],
            ['categorie', 'Oui', 'Catégorie existante (insensible à la casse).'],
            ['prix', 'Oui', 'Prix de vente en Fc.'],
            ['stock', 'Non', 'Stock initial. 0 par défaut.'],
            ['stock_minimum', 'Non', "Seuil d'alerte de stock. 0 par défaut."],
            ['taxe', 'Non', 'Étiquette (A, B, C…) ou groupe de taxe. Groupe A par défaut.'],
            ['product_type', 'Non', 'unite ou coupe.'],
            ['prod_service', 'Non', 'BIE, SER ou TAX.'],
            ['remise_type', 'Non', '% ou CDF.'],
            ['remise_value', 'Non', 'Valeur de la remise.'],
            ['taxe_specifique_type', 'Non', '% ou CDF.'],
            ['taxe_specifique_value', 'Non', 'Valeur de la taxe spécifique.'],
            ['date_expiration', 'Non', 'Format YYYY-MM-DD ou JJ/MM/AAAA.'],
        ], null, 'A1');
        $sheet2->getStyle('A1:C1')->getFont()->setBold(true);
        $sheet2->getColumnDimensionByColumn(1)->setWidth(26);
        $sheet2->getColumnDimensionByColumn(2)->setWidth(14);
        $sheet2->getColumnDimensionByColumn(3)->setWidth(60);

        // `createSheet()` bascule la feuille active : on revient sur « Produits »
        // pour que le modèle s'ouvre sur les en-têtes.
        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'modele_import_produits.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    // MARK: Lecture du fichier

    /**
     * Lit le fichier et renvoie la liste des lignes de données.
     *
     * @return array<int, array{line:int, cells:array}>
     */
    private function readRows(string $filePath): array
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($extension === 'csv' || $extension === 'txt') {
            return $this->readCsvRows($filePath);
        }

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $this->readSheetRows($sheet);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $rows;
    }

    /**
     * Extrait les lignes d'une feuille Excel : la 1re ligne non vide joue le
     * rôle d'en-tête, les suivantes sont les données.
     *
     * ⚠️ `setIterateOnlyExistingCells(true)` est INDISPENSABLE : par défaut,
     * l'itérateur CRÉE les cellules manquantes, ce qui corrompt la
     * collection de cellules de la feuille et fait échouer la lecture des
     * lignes suivantes (« Worksheet no longer exists »).
     */
    private function readSheetRows(Worksheet $sheet): array
    {
        $rows = [];
        $headerMap = null;

        foreach ($sheet->getRowIterator() as $row) {
            $rowIndex = $row->getRowIndex();
            $cells = [];

            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(true);
            foreach ($cellIterator as $cell) {
                $cells[$cell->getColumn()] = $cell;
            }

            if ($headerMap === null) {
                if (!$this->rowHasData($cells)) {
                    continue; // ligne vide avant l'en-tête
                }
                $headerMap = $this->mapHeaders($cells);
                continue;
            }

            if (!$this->rowHasData($cells)) {
                continue; // ligne vide intercalée
            }

            $rows[] = [
                'line' => $rowIndex,
                'cells' => $this->extractCells($cells, $headerMap),
            ];
        }

        return $rows;
    }

    /**
     * Variante CSV (séparateur `;`, comme le module paie).
     */
    private function readCsvRows(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \RuntimeException("Impossible d'ouvrir le fichier");
        }

        $rows = [];
        $headerMap = null;
        $lineNumber = 0;

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $lineNumber++;

            if ($row === [null] || $row === false) {
                continue;
            }

            if ($headerMap === null) {
                if (!$this->rowHasData($row)) {
                    continue;
                }
                $headerMap = $this->mapHeaders($row);
                continue;
            }

            if (!$this->rowHasData($row)) {
                continue;
            }

            $rows[] = [
                'line' => $lineNumber,
                'cells' => $this->extractCells($row, $headerMap),
            ];
        }

        fclose($handle);

        return $rows;
    }

    /** Une ligne est vide si toutes ses cellules sont vides. */
    private function rowHasData($row): bool
    {
        foreach ($row as $value) {
            if ($value instanceof Cell) {
                if (trim((string)$value->getFormattedValue()) !== '') {
                    return true;
                }
                continue;
            }
            if (trim((string)$value) !== '') {
                return true;
            }
        }
        return false;
    }

    /**
     * Associe chaque colonne logique à sa position, via la liste d'alias.
     */
    private function mapHeaders($cells): array
    {
        $map = [];
        $index = 0;

        foreach ($cells as $value) {
            $index++;
            if ($value instanceof Cell) {
                $value = $value->getFormattedValue();
            }
            $key = $this->normalizeHeader((string)$value);
            if ($key === '') {
                continue;
            }
            foreach (self::HEADER_ALIASES as $column => $aliases) {
                if (in_array($key, $aliases, true) && !isset($map[$column])) {
                    $map[$column] = $index;
                    break;
                }
            }
        }

        return $map;
    }

    /** Normalise un en-tête : minuscules, sans accents, sans ponctuation. */
    private function normalizeHeader(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        // Supprime les accents.
        $value = strtr($value, [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'è' => 'e', 'é' => 'e',
            'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'ú' => 'u',
            'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'ÿ' => 'y', 'ñ' => 'n', 'ç' => 'c',
        ]);
        $value = preg_replace('/[^a-zA-Z0-9]+/', '', $value);

        return strtolower($value);
    }

    /**
     * Extrait, pour chaque colonne logique, la valeur de la cellule.
     *
     * Les cellules sont converties en tableaux simples
     * (`value` / `formatted` / `is_date`) : les objets `Cell` de
     * PhpSpreadsheet ne sont plus valides une fois la feuille déconnectée
     * (`disconnectWorksheets()`), or ils sont lus bien après le chargement.
     *
     * @return array<string, array{value:mixed, formatted:string, is_date:bool}>
     */
    private function extractCells($cells, array $map): array
    {
        $extracted = [];
        $isCellObjects = false;
        foreach ($cells as $value) {
            if ($value instanceof Cell) {
                $isCellObjects = true;
                break;
            }
        }

        foreach ($map as $column => $position) {
            if ($isCellObjects) {
                // Position 1-based des colonnes Excel.
                $columnLetter = Coordinate::stringFromColumnIndex($position);
                $cell = $cells[$columnLetter] ?? null;
                $extracted[$column] = $cell instanceof Cell
                    ? [
                        'value' => $cell->getValue(),
                        'formatted' => (string)$cell->getFormattedValue(),
                        'is_date' => SharedDate::isDateTime($cell),
                    ]
                    : ['value' => null, 'formatted' => '', 'is_date' => false];
            } else {
                $raw = $cells[$position - 1] ?? null;
                $extracted[$column] = [
                    'value' => $raw,
                    'formatted' => $raw === null ? '' : trim((string)$raw),
                    'is_date' => false,
                ];
            }
        }

        return $extracted;
    }

    // MARK: Construction du produit

    /**
     * Transforme une ligne de fichier en tableau de données produit.
     * Lève une \RuntimeException si la ligne est invalide.
     */
    private function buildProduct(array $cells, $shopId, array &$categories, array $taxes, array $options): array
    {
        $nom = trim($this->readString($cells, 'nom'));
        if ($nom === '') {
            throw new \RuntimeException('Nom du produit manquant');
        }
        if (mb_strlen($nom) > 100) {
            $nom = mb_substr($nom, 0, 100);
        }

        // ── Catégorie ──
        $categoryId = 0;
        $categoryLabel = trim($this->readString($cells, 'categorie'));
        if ($categoryLabel !== '') {
            $categoryId = $this->resolveCategoryId($categoryLabel, $categories);
            if ($categoryId === 0 && !empty($options['create_categories'])) {
                $categoryId = $this->createCategory($categoryLabel, $shopId, $categories);
            }
        }
        if ($categoryId === 0 && !empty($options['default_category_id'])) {
            $categoryId = (int)$options['default_category_id'];
        }
        if ($categoryId === 0) {
            throw new \RuntimeException('Catégorie "' . $categoryLabel . '" introuvable');
        }

        // ── Prix ──
        $prix = $this->readNumber($cells, 'prix');
        if ($prix === null) {
            throw new \RuntimeException('Prix manquant ou invalide');
        }
        if ($prix < 0) {
            throw new \RuntimeException('Prix négatif');
        }

        // ── Stock ──
        $stock = $this->readNumber($cells, 'stock');
        $stock = $stock === null ? 0 : $stock;
        if ($stock < 0) {
            $stock = 0;
        }
        $stockMinimum = $this->readNumber($cells, 'stock_minimum');
        $stockMinimum = $stockMinimum === null ? 0 : $stockMinimum;
        if ($stockMinimum < 0) {
            $stockMinimum = 0;
        }

        // ── Code-barres ──
        $codeBarres = trim($this->readString($cells, 'code_barres'));
        if ($codeBarres === '') {
            $codeBarres = $this->generateBarcode();
        }
        if (mb_strlen($codeBarres) > 50) {
            throw new \RuntimeException('Code-barres trop long (50 caractères maximum)');
        }

        // ── Taxe ──
        $taxeId = $this->resolveTaxId($this->readString($cells, 'taxe'), $taxes);
        if ($taxeId === 0 && !empty($options['default_tax_id'])) {
            $taxeId = (int)$options['default_tax_id'];
        }
        if ($taxeId === 0) {
            $taxeId = $this->fallbackTaxId($taxes);
        }

        // ── Type de vente ──
        $productType = strtolower(trim($this->readString($cells, 'product_type')));
        if (!in_array($productType, self::PRODUCT_TYPES, true)) {
            $productType = 'unite';
        }

        // ── Type de service ──
        $prodService = strtoupper(trim($this->readString($cells, 'prod_service')));
        if (!in_array($prodService, self::PROD_SERVICES, true)) {
            $prodService = null;
        }

        // ── Remise ──
        $remiseType = strtoupper(trim($this->readString($cells, 'remise_type')));
        if (!in_array($remiseType, ['%', 'CDF'], true)) {
            $remiseType = '%';
        }
        $remiseValue = $this->readNumber($cells, 'remise_value');
        $remiseValue = $remiseValue === null ? 0 : $remiseValue;
        if ($remiseType === '%') {
            $remiseValue = max(0, min(100, $remiseValue));
        } else {
            $remiseValue = max(0, min($remiseValue, $prix));
        }

        // ── Taxe spécifique ──
        $taxeSpecType = strtoupper(trim($this->readString($cells, 'taxe_specifique_type')));
        if (!in_array($taxeSpecType, ['%', 'CDF'], true)) {
            $taxeSpecType = '%';
        }
        $taxeSpecValue = $this->readNumber($cells, 'taxe_specifique_value');
        $taxeSpecValue = $taxeSpecValue === null ? 0 : $taxeSpecValue;
        if ($taxeSpecType === '%') {
            $taxeSpecValue = max(0, min(100, $taxeSpecValue));
        } else {
            $taxeSpecValue = max(0, min($taxeSpecValue, $prix));
        }

        // ── Date d'expiration ──
        $dateExpiration = $this->readDate($cells, 'date_expiration');

        return [
            'code_barres' => $codeBarres,
            'nom' => $nom,
            'category_id' => $categoryId,
            'shop_id' => $shopId,
            'prix' => round($prix, 2),
            'stock' => $stock,
            'stock_minimum' => $stockMinimum,
            'image' => '',
            'taxe_id' => $taxeId,
            'product_type' => $productType,
            'prod_service' => $prodService,
            'remise_type' => $remiseType,
            'remise_value' => round($remiseValue, 2),
            'taxe_specifique_type' => $taxeSpecType,
            'taxe_specifique_value' => round($taxeSpecValue, 2),
            'date_expiration' => $dateExpiration,
        ];
    }

    // MARK: Écriture

    /** Écrit un nouveau produit + son lot de stock initial. */
    private function createProduct(array $data): void
    {
        $id = $this->productModel->create($data);
        if (!$id) {
            throw new \RuntimeException('Échec de la création du produit');
        }

        if ((float)$data['stock'] > 0) {
            $this->batchModel->create([
                'product_id' => $id,
                'batch_number' => null,
                'stock' => (float)$data['stock'],
                'date_expiration' => $data['date_expiration'],
                'date_reception' => date('Y-m-d'),
            ]);
        }
    }

    /** Met à jour un produit existant (stock réinjecté via un nouveau lot). */
    private function updateProduct(int $id, array $data): void
    {
        $this->productModel->update($id, $data);

        if ((float)$data['stock'] > 0) {
            $this->batchModel->create([
                'product_id' => $id,
                'batch_number' => null,
                'stock' => (float)$data['stock'],
                'date_expiration' => $data['date_expiration'],
                'date_reception' => date('Y-m-d'),
            ]);
        }
    }

    // MARK: Résolution des référentiels

    /** Charge les catégories visibles de la boutique (indexées par nom normalisé). */
    private function loadCategories($shopId): array
    {
        $categories = [];
        try {
            $rows = $this->categoryModel->all($shopId ?: null);
        } catch (\Throwable $e) {
            error_log('ProductImportService::loadCategories error: ' . $e->getMessage());
            return $categories;
        }

        foreach ($rows as $row) {
            $name = (string)($row['category'] ?? '');
            $key = $this->normalizeHeader($name);
            if ($key === '' || isset($categories[$key])) {
                continue;
            }
            $categories[$key] = [
                'id' => (int)$row['id'],
                'name' => $name,
            ];
        }

        return $categories;
    }

    /** Charge les taxes (indexées par étiquette et par groupe). */
    private function loadTaxes(): array
    {
        $taxes = ['by_etiquette' => [], 'by_groupe' => [], 'all' => []];

        try {
            $rows = $this->taxModel->getAll();
        } catch (\Throwable $e) {
            error_log('ProductImportService::loadTaxes error: ' . $e->getMessage());
            return $taxes;
        }

        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $etiquette = strtoupper(trim((string)($row['etiquette'] ?? '')));
            $groupe = trim((string)($row['groupe_taxe'] ?? ''));
            $taxes['all'][$id] = $row;
            if ($etiquette !== '' && !isset($taxes['by_etiquette'][$etiquette])) {
                $taxes['by_etiquette'][$etiquette] = $id;
            }
            $key = $this->normalizeHeader($groupe);
            if ($key !== '' && !isset($taxes['by_groupe'][$key])) {
                $taxes['by_groupe'][$key] = $id;
            }
        }

        return $taxes;
    }

    /** Retrouve l'ID d'une catégorie à partir de son libellé. */
    private function resolveCategoryId(string $label, array $categories): int
    {
        $key = $this->normalizeHeader($label);
        if ($key === '') {
            return 0;
        }
        if (isset($categories[$key])) {
            return $categories[$key]['id'];
        }

        // Recherche "commence par" (tolérance aux libellés tronqués).
        foreach ($categories as $entry) {
            $nameKey = $this->normalizeHeader($entry['name']);
            if ($nameKey !== '' && (str_starts_with($nameKey, $key) || str_starts_with($key, $nameKey))) {
                return $entry['id'];
            }
        }

        return 0;
    }

    /** Crée une catégorie manquante et l'ajoute au cache local. */
    private function createCategory(string $label, $shopId, array &$categories): int
    {
        $key = $this->normalizeHeader($label);
        if ($key === '' || isset($categories[$key])) {
            return $categories[$key]['id'] ?? 0;
        }

        try {
            $this->categoryModel->add($label, $shopId ?: null);
            $id = (int)\App\Core\Database::getInstance()->lastInsertId();
        } catch (\Throwable $e) {
            error_log('ProductImportService::createCategory error: ' . $e->getMessage());
            return 0;
        }

        if ($id <= 0) {
            return 0;
        }

        $categories[$key] = ['id' => $id, 'name' => $label];

        return $id;
    }

    // MARK: Lecture des valeurs

    /** Retrouve l'ID d'une taxe à partir de son étiquette ou de son groupe. */
    private function resolveTaxId(string $label, array $taxes): int
    {
        $label = trim($label);
        if ($label === '') {
            return 0;
        }

        // ID numérique direct.
        if (ctype_digit($label) && isset($taxes['all'][(int)$label])) {
            return (int)$label;
        }

        $upper = strtoupper($label);
        if (isset($taxes['by_etiquette'][$upper])) {
            return $taxes['by_etiquette'][$upper];
        }

        $key = $this->normalizeHeader($label);
        if (isset($taxes['by_groupe'][$key])) {
            return $taxes['by_groupe'][$key];
        }

        // "Groupe B" / "B" : on retire le préfixe "groupe" pour l'étiquette.
        if (str_starts_with($key, 'groupe')) {
            $short = strtoupper(substr($key, 6));
            if (isset($taxes['by_etiquette'][$short])) {
                return $taxes['by_etiquette'][$short];
            }
        }

        return 0;
    }

    /** Taxe par défaut : le groupe A (exonéré) si disponible. */
    private function fallbackTaxId(array $taxes): int
    {
        if (isset($taxes['by_etiquette']['A'])) {
            return $taxes['by_etiquette']['A'];
        }
        $ids = array_keys($taxes['all']);
        sort($ids);

        return $ids ? (int)$ids[0] : 1;
    }

    /** Lit une valeur texte. */
    private function readString(array $cells, string $column): string
    {
        if (!isset($cells[$column])) {
            return '';
        }
        $cell = $cells[$column];
        if (!is_array($cell)) {
            return '';
        }

        $value = $cell['formatted'] ?? '';
        if ($value === '' && isset($cell['value']) && !is_string($cell['value'])) {
            if (is_float($cell['value']) && floor($cell['value']) === $cell['value']) {
                return (string)(int)$cell['value'];
            }
        }

        return trim((string)$value);
    }

    /** Lit une valeur numérique (accepte « 1 500,00 », « 1,500.00 », etc.). */
    private function readNumber(array $cells, string $column): ?float
    {
        if (!isset($cells[$column])) {
            return null;
        }
        $cell = $cells[$column];
        if (!is_array($cell)) {
            return null;
        }

        // Valeur brute numérique (cellule Excel typée nombre).
        $raw = $cell['value'] ?? null;
        if (is_int($raw) || is_float($raw)) {
            return (float)$raw;
        }
        if (is_string($raw) && is_numeric($raw)) {
            return (float)$raw;
        }

        $raw = trim((string)($cell['formatted'] ?? ''));
        if ($raw === '') {
            return null;
        }

        // Supprime les symboles monétaires, espaces (y compris insécables) et %.
        $raw = str_replace(["\u{00A0}", ' ', '%'], '', $raw);
        $raw = preg_replace('/[^0-9,.\-]/', '', $raw);
        if ($raw === '' || $raw === '-') {
            return null;
        }

        // Si virgule ET point : le dernier séparateur est le séparateur décimal.
        $lastComma = strrpos($raw, ',');
        $lastDot = strrpos($raw, '.');
        if ($lastComma !== false && $lastDot !== false) {
            if ($lastComma > $lastDot) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(',', '', $raw);
            }
        } elseif ($lastComma !== false) {
            // « 1500,50 » → décimal ; « 1,500 » → millier (3 décimales après).
            $decimals = strlen($raw) - $lastComma - 1;
            if ($decimals === 3 && substr_count($raw, ',') === 1) {
                $raw = str_replace(',', '', $raw);
            } else {
                $raw = str_replace(',', '.', $raw);
            }
        }

        return is_numeric($raw) ? (float)$raw : null;
    }

    /** Lit une date (cellule Excel sérielle ou chaîne). */
    private function readDate(array $cells, string $column): ?string
    {
        if (!isset($cells[$column])) {
            return null;
        }
        $cell = $cells[$column];
        if (!is_array($cell)) {
            return null;
        }

        // Cellule Excel typée date : sérial numérique.
        if (!empty($cell['is_date'])) {
            $raw = $cell['value'] ?? null;
            if (is_numeric($raw)) {
                return SharedDate::excelToDateTimeObject((float)$raw)->format('Y-m-d');
            }
            return $this->normalizeDateString((string)$raw);
        }

        $raw = $cell['value'] ?? null;
        if (is_int($raw) || is_float($raw)) {
            // Sérial Excel brut.
            return SharedDate::excelToDateTimeObject((float)$raw)->format('Y-m-d');
        }

        return $this->normalizeDateString((string)($cell['formatted'] ?? ''));
    }

    /** Convertit une date en 'Y-m-d' (accepte plusieurs formats). */
    private function normalizeDateString(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'Y/m/d', 'd/m/y', 'Y-m-d H:i:s'];
        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date instanceof \DateTime) {
                return $date->format('Y-m-d');
            }
        }

        try {
            return (new \DateTime($value))->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    // MARK: Divers

    /** Génère un code-barres unique (format numérique, 12 chiffres). */
    private function generateBarcode(): string
    {
        do {
            $barcode = (string)random_int(100000000000, 999999999999);
            $existing = $this->productModel->findByBarcode($barcode);
        } while ($existing);

        return $barcode;
    }

    /** Compteurs neutres. */
    private function emptySummary(): array
    {
        return [
            'total' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'categories_created' => 0,
        ];
    }

    /** Ajoute une ligne au rapport (plafonné à MAX_REPORTED_ROWS). */
    private function addReport(array &$report, array $row): void
    {
        if (count($report) < self::MAX_REPORTED_ROWS) {
            $report[] = $row;
        }
    }
}
