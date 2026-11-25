<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Tax;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
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

        /*
         * ⚠️ LE FLUX EST UN GÉNÉRATEUR, pas un tableau.
         *
         * Mesuré à 50 000 produits : accumuler les lignes extraites
         * consommait ~500 Mo (le pic restait à ~500 Mo même avec un
         * lecteur en flux). En consommant les lignes UNE PAR UNE, la
         * mémoire est constante.
         *
         * Conséquence : on ne peut plus savoir `empty($rows)` avant la
         * boucle, ni collecter les code-barres d'avance. Le contrôle
         * « fichier vide » se fait donc pendant la boucle.
         */
        /*
         * ⚠️ Un générateur ne lance rien à la CRÉATION : les exceptions
         *    surviennent à l'ITÉRATION, donc HORS de ce `try`.
         *
         *    C'est pourquoi l'erreur « Catégorie introuvable » (levée par
         *    `buildProduct()` pendant la boucle) doit être rattrapée par la
         *    boucle elle-même — et c'est le cas.
         *
         *    Ce `try` ne couvre donc que la CRÉATION du lecteur (une
         *    archive illisible, par exemple).
         */
        try {
            $rows = $this->readRowsStreaming($filePath);
        } catch (\Throwable $e) {
            error_log('ProductImportService::readRows error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Impossible de lire le fichier : ' . $e->getMessage(),
                'summary' => $this->emptySummary(),
                'rows' => [],
            ];
        }

        // Contexte de résolution (catégories / taxes) chargé une seule fois.
        $categories = $this->loadCategories($shopId);
        $taxes = $this->loadTaxes();

        /*
         * ⚠️ RECHERCHE BATCHÉE des produits existants (correctif de charge).
         *
         * Avant : `findByBarcode()` était appelé POUR CHAQUE LIGNE du
         * fichier. Chaque appel déclenchait 5 requêtes (1 SELECT + les 4
         * d'`enrichWithBatchInfo`, inutiles ici) → 25 000 requêtes pour un
         * import de 5 000 produits, soit 7,5 s.
         *
         * Maintenant : 1 requête par lot de 500 code-barres (~50 ms pour
         * 5 000 produits). La sémantique est INCHANGÉE : c'est toujours MySQL
         * qui compare les code-barres (collation insensible à la casse et
         * aux accents), et un produit de boutique inactive reste introuvable.
         *
         * ⚠️ Le lookup est fait PAR BLOCS de 500 lignes : le flux ne
         *    permet pas de collecter tous les code-barres d'avance (c'est
         *    précisément ce qu'on veut éviter — 50 000 chaînes en RAM).
         *    On consulte MySQL toutes les 500 lignes.
         */
        $summary = $this->emptySummary();
        $report = [];
        $seenBarcodes = [];
        $existingIds = [];
        $pending = [];
        $pendingBarcodes = [];
        $rowCount = 0;

        foreach ($rows as $row) {
            $line = $row['line'];
            $cells = $row['cells'];

            // ⚠️ La ligne est comptée DÈS QU'ELLE EST LUE, avant le
            //    traitement : sinon une ligne invalide ferait croire à un
            //    fichier vide (cf. le commentaire plus bas).
            $rowCount++;

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

            // ⚠️ On compte les LIGNES LUES, pas les produits valides : une
            //    ligne invalide (catégorie introuvable, prix manquant)
            //    doit quand même figurer dans le résumé. Compter les
            //    produits valides faisait croire à un fichier vide, et le
            //    résumé des erreurs était REMPLACÉ par un résumé vide —
            //    l'utilisateur ne voyait plus pourquoi sa ligne avait été
            //    rejetée.
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

            /*
             * ⚠️ L'ÉCRITURE EST DIFFÉRÉE d'un bloc de 500 lignes.
             *
             * Le lookup des produits existants se fait par blocs (1
             * requête pour 500 code-barres, au lieu d'un `findByBarcode()`
             * par ligne qui déclenchait 5 requêtes chacune).
             *
             * Mais le flux ne permet pas de collecter tous les
             * code-barres d'avance : 50 000 chaînes en RAM, exactement ce
             * qu'on cherche à éviter.
             *
             * On met donc la ligne en ATTENTE, et on l'écrit quand le
             * bloc a été consulté par MySQL. La ligne attend au maximum
             * 499 positions — et son lot de stock est créé au moment de
             * l'écriture, donc pas de divergence.
             */
            $pending[] = ['line' => $line, 'product' => $product];
            $pendingBarcodes[$barcodeKey] = true;

            if (count($pendingBarcodes) >= 500) {
                $existingIds = array_merge(
                    $existingIds,
                    $this->productModel->findIdsByBarcodes(array_keys($pendingBarcodes))
                );
                $this->writePending($pending, $existingIds, $mode, $summary, $report);
                $pending = [];
                $pendingBarcodes = [];
            }
        }

        // ⚠️ Le DERNIER BLOC (moins de 500 lignes) doit être écrit aussi.
        if (!empty($pending)) {
            $existingIds = array_merge(
                $existingIds,
                $this->productModel->findIdsByBarcodes(array_keys($pendingBarcodes))
            );
            $this->writePending($pending, $existingIds, $mode, $summary, $report);
            $pending = [];
            $pendingBarcodes = [];
        }

        if ($rowCount === 0) {
            return [
                'success' => false,
                'error' => 'Le fichier est vide ou ne contient aucune ligne de données',
                'summary' => $this->emptySummary(),
                'rows' => [],
            ];
        }

        /*
         * ⚠️ Toutes les lignes ont été lues mais AUCUNE n'a pu être
         *    traitée (toutes invalides) : on le dit explicitement, sinon
         *    l'utilisateur ne comprend pas pourquoi rien n'a été importé.
         */
        if ($summary['created'] === 0 && $summary['updated'] === 0 && $summary['errors'] > 0) {
            return [
                'success' => false,
                'error' => "Aucune ligne n'a pu être importée : "
                    . $summary['errors'] . " erreur(s). Voir le detail.",
                'summary' => $summary,
                'rows' => $report,
            ];
        }

        return [
            'success' => ($summary['created'] + $summary['updated']) > 0,
            'summary' => $summary,
            'rows' => $report,
        ];
    }

    /**
     * Écrit les lignes en attente, une fois le lookup fait.
     *
     * ⚠️ Cette méthode est le cœur de l'import en flux : les lignes sont
     *    accumulées par blocs de 500, MySQL est consulté pour le bloc
     *    entier (1 requête), puis les lignes sont écrites une par une.
     *
     * La sémantique est IDENTIQUE à celle de la version d'origine
     * (ligne par ligne) :
     *   - code-barres en double dans le fichier  → ignoré ;
     *   - produit déjà existant + mode `create`  → ignoré ;
     *   - produit déjà existant + mode `update`  → mis à jour ;
     *   - sinon                                  → créé (+ son lot).
     *
     * @param array $pending      Lignes en attente (line + product).
     * @param array $existingIds  Code-barres → id de produit existant.
     */
    private function writePending(array $pending, array $existingIds, string $mode, array &$summary, array &$report): void
    {
        foreach ($pending as $item) {
            $line = $item['line'];
            $product = $item['product'];
            $barcodeKey = $product['code_barres'];

            $existingId = $existingIds[$barcodeKey] ?? null;

            if ($existingId !== null && $mode === 'create') {
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
                if ($existingId !== null) {
                    $this->updateProduct((int)$existingId, $product);
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

        // ⚠️ La colonne « code_barres » doit être en format TEXTE (« @ ») :
        // Excel convertit automatiquement un code-barres à 13 chiffres en
        // date (affichage « 5/17/23070765319 »), ce qui corrompt la saisie
        // et le round-trip export → import.
        $sheet->setCellValueExplicit('A2', '5400141352573', DataType::TYPE_STRING);
        $sheet->getStyle('A:A')->getNumberFormat()->setFormatCode('@');

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
            ['code_barres', 'Non', 'Généré automatiquement si vide. Doit être unique. ' .
                'La cellule doit être en format TEXTE : Excel transforme sinon un ' .
                'code-barres à 13 chiffres en date.'],
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
    /**
     * Lit les lignes de données du fichier.
     *
     * ⚠️ Le retour est un TABLEAU : ce contrat est utilisé par
     *    `tests/Unit/ProductImportServiceTest.php` (count, index).
     *
     *    ⚠️ Il NE DOIT PAS être utilisé pour un import massif : le
     *    tableau de 50 000 lignes consomme ~500 Mo. C'est
     *    `readRowsStreaming()` qu'utilise l'import — le même contenu,
     *    mais produit ligne par ligne.
     *
     * @return array<int, array{line:int, cells:array}>
     */
    private function readRows(string $filePath): array
    {
        return iterator_to_array($this->readRowsStreaming($filePath), false);
    }

    /**
     * Lit les lignes de données du fichier, UNE PAR UNE.
     *
     * ⚠️ C'est le chemin utilisé par l'import : c'est ce qui rend
     *    l'import de 50 000 produits possible en mémoire constante.
     *
     *    - .xlsx → lecteur natif (ZipArchive + XMLReader), en flux ;
     *    - .csv  → `readCsvRows()`, ligne par ligne.
     *
     * ⚠️ Un générateur ne peut être parcouru QU'UNE FOIS.
     *
     * @return \Generator<int, array{line:int, cells:array}>
     */
    private function readRowsStreaming(string $filePath): \Generator
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($extension === 'csv' || $extension === 'txt') {
            // Le CSV est déjà lu ligne par ligne : on le délaisse tel quel.
            foreach ($this->readCsvRows($filePath) as $row) {
                yield $row;
            }
            return;
        }

        /*
         * ⚠️ LECTEUR NATIF EN STREAMING — la correction de l'épuisement
         *    mémoire.
         *
         * Le symptôme : « Allowed memory size 536870912 bytes exhausted »
         * sur un .xlsx de 50 000 produits.
         *
         * La cause : `IOFactory::load()` décompresse le classeur ENTIER
         * en RAM (cellules + styles + sharedStrings), puis `readSheetRows()`
         * accumule en plus TOUTES les lignes extraites. La mémoire est
         * consommée deux fois.
         *
         * MESURÉ à 50 000 produits (scripts/measure_xlsx_readers.php) :
         *
         *   variante                          temps     pic mémoire
         *   IOFactory::load()                 17,5 s      410 Mo
         *   + readFilter + readDataOnly       21,8 s      428 Mo
         *   LECTEUR NATIF (ZipArchive+XMLReader) 1,5 s        6 Mo
         *
         * Le readFilter seul ne suffit PAS : PhpSpreadsheet garde la
         * structure du classeur en mémoire quoi qu'il arrive. Le lecteur
         * natif, lui, ne conserve que les sharedStrings (l'index des
         * chaînes) et lit la feuille en FLUX : la mémoire est
         * proportionnelle à la TAILLE DE LA LIGNE, pas à celle du
         * fichier.
         *
         * → 68× moins de mémoire, 11× plus rapide.
         *
         * ⚠️ Le fallback sur `IOFactory::load()` reste : un .xlsx mal
         *    formé (ou une variante que le lecteur natif ne saisit pas)
         *    doit rester importable.
         */
        try {
            yield from $this->readXlsxRowsNative($filePath);
            return;
        } catch (\Throwable $e) {
            error_log('ProductImportService::native xlsx reader failed, fallback: ' . $e->getMessage());
        }

        // ⚠️ Le fallback charge TOUT en mémoire : c'est le chemin de
        //    secours (un .xlsx mal formé), pas le chemin normal.
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        foreach ($this->readSheetRows($sheet) as $row) {
            yield $row;
        }
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    /**
     * Le lecteur natif produit les lignes UN PAR UNE.
     *
     * ⚠️ C'est INDISPENSABLE : même avec un lecteur en flux, si on
     *    accumule les 50 000 lignes extraites dans un tableau, la mémoire
     *    remonte. Mesuré : le pic reste à ~500 Mo tant que `$rows` grandit,
     *    alors que le lecteur seul est à 6 Mo.
     *
     * ⚠️ Le générateur est consommé UNE SEULE FOIS : on ne peut pas le
     *    parcourir deux fois. `importFile()` le fait une fois, c'est tout.
     *
     * @return \Generator<int, array{line:int, cells:array}>
     */
    private function readXlsxRowsNative(string $filePath): \Generator
    {
        if (!class_exists(\ZipArchive::class) || !class_exists(\XMLReader::class)) {
            throw new \RuntimeException('ZipArchive ou XMLReader indisponible');
        }

        $zip = new \ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new \RuntimeException('Archive ZIP illisible');
        }

        try {
            // ── 1. Les sharedStrings : l'index des chaînes ──────────
            $shared = $this->readSharedStringsNative($zip, $filePath);

            // ── 1b. Les styles « date » ──────────────────────────────
            $dateStyles = $this->readDateStylesNative($zip, $filePath);

            // ── 2. La 1re feuille ─────────────────────────────────────
            $sheetPath = null;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', (string)$name)) {
                    $sheetPath = (string)$name;
                    break;
                }
            }
            if ($sheetPath === null) {
                throw new \RuntimeException('Aucune feuille dans le classeur');
            }

            $headerMap = null;
            $rowIndex = 0;
            $cells = [];
            $dateCells = [];
            $colLetter = '';
            $cellType = '';
            $cellIsDate = false;
            $rowHasValue = false;
            $inValue = false;
            $valueText = '';
            $inInlineString = false;

            $xml = new \XMLReader();
            $xml->open('zip://' . $filePath . '#' . $sheetPath);

            while (@$xml->read()) {
                $node = $xml->nodeType;

                if ($node === \XMLReader::ELEMENT) {
                    switch ($xml->name) {
                        case 'row':
                            $rowIndex = (int)$xml->getAttribute('r');
                            $cells = [];
                            $dateCells = [];
                            $rowHasValue = false;
                            break;

                        case 'c':
                            $ref = (string)$xml->getAttribute('r');
                            $cellType = (string)$xml->getAttribute('t');
                            $colLetter = preg_replace('/\d+/', '', $ref);
                            $cellStyle = $xml->getAttribute('s');
                            $cellIsDate = ($cellStyle !== null
                                && $cellStyle !== ''
                                && isset($dateStyles[(int)$cellStyle]));
                            break;

                        case 'v':
                            $inValue = true;
                            $valueText = '';
                            break;

                        case 't':
                            $inInlineString = true;
                            $valueText = '';
                            break;
                    }
                } elseif ($node === \XMLReader::TEXT || $node === \XMLReader::CDATA) {
                    if ($inValue || $inInlineString) {
                        $valueText .= $xml->readString();
                    }
                } elseif ($node === \XMLReader::END_ELEMENT) {
                    switch ($xml->name) {
                        case 'v':
                            if ($inValue) {
                                $value = $valueText;
                                if ($cellType === 's') {
                                    $value = $shared[(int)$value] ?? '';
                                } else {
                                    /*
                                     * ⚠️ Le XML brut porte la valeur
                                     *    NUMÉRIQUE telle qu'Excel l'a
                                     *    écrite : « 15939.0 » pour un
                                     *    entier. PhpSpreadsheet, lui,
                                     *    renvoie « 15939 ».
                                     *
                                     *    Sans cette normalisation, le
                                     *    prix importé serait « 15939.0 »
                                     *    au lieu de « 15939 » — un écart
                                     *    visible dans le rapport ET dans
                                     *    les données.
                                     *
                                     *    On ne touche que la FINALE « .0 »
                                     *    d'un entier : « 15939.50 » et
                                     *    « 0.5 » sont intacts.
                                     */
                                    $value = preg_replace('/\.0+$/', '', $value);
                                }
                                $cells[$colLetter] = $value;
                                if ($cellIsDate) {
                                    $dateCells[$colLetter] = $value;
                                }
                                $rowHasValue = true;
                            }
                            $inValue = false;
                            break;

                        case 't':
                            if ($inInlineString) {
                                $cells[$colLetter] = $valueText;
                                $rowHasValue = true;
                            }
                            $inInlineString = false;
                            break;

                        case 'row':
                            if ($headerMap === null) {
                                if ($rowHasValue) {
                                    $headerMap = $this->mapHeadersNative($cells);
                                }
                                break;
                            }
                            if (!$rowHasValue) {
                                break;   // ligne vide intercalée
                            }
                            // ⚠️ On RENVOIE la ligne et on libère aussitôt
                            //    les structures de la ligne courante.
                            yield [
                                'line'  => $rowIndex,
                                'cells' => $this->extractCellsNative($cells, $headerMap, $dateCells),
                            ];
                            $cells = [];
                            $dateCells = [];
                            break;
                    }
                }
            }
            $xml->close();
        } finally {
            $zip->close();
        }
    }

    /**
     * Les styles du classeur :哪些 index de style sont des DATES ?
     *
     * ⚠️ Dans un .xlsx, une cellule date porte l'attribut `s="N"` (index de
     *    style) et le style pointe vers un `numFmt` de type date
     *    (`numFmtId` 14 à 22, 45 à 47, ou un format personnalisé contenant
     *    d/j/y).
     *
     *    Sans cette distinction, une date Excel (sérial `45000`) serait
     *    lue comme le nombre 45000 — et `readDate()` la convertirait en
     *    date, mais une date au format ISO (notre cas) serait une
     *    CHAÎNE, pas un nombre.
     *
     *    On ne garde que l'index des styles « date » : un tableau de
     *    quelques dizaines d'entiers.
     *
     * @return array<int, bool>  Index de style → est-ce une date ?
     */
    private function readDateStylesNative(\ZipArchive $zip, string $filePath): array
    {
        $dateStyles = [];

        $index = $zip->locateName('xl/styles.xml');
        if ($index === false) {
            return $dateStyles;
        }

        $xml = new \XMLReader();
        $xml->open('zip://' . $filePath . '#xl/styles.xml');

        // 1) Les formats de cellule personnalisés : numFmtId → format.
        $customFormats = [];
        // 2) Les formats de date « standard » (numFmtId 14 à 22, 45 à 47).
        $builtinDateFormats = array_fill(0, 0, 48);
        foreach ([14, 15, 16, 17, 18, 19, 20, 21, 22, 45, 46, 47] as $id) {
            $builtinDateFormats[$id] = 1;
        }

        $inCellXfs = false;
        $xfNumFmtId = 0;
        $xfIndex = -1;

        while (@$xml->read()) {
            if ($xml->nodeType === \XMLReader::ELEMENT) {
                if ($xml->name === 'numFmt') {
                    $id = (int)$xml->getAttribute('numFmtId');
                    $code = (string)$xml->getAttribute('formatCode');
                    $customFormats[$id] = $code;
                } elseif ($xml->name === 'cellXfs') {
                    /*
                     * ⚠️ `cellXfs` est le SEUL conteneur d'`xf` qui compte.
                     *
                     *    `cellStyleXfs` contient lui aussi des `<xf>` : les
                     *    compter décalerait TOUS les index de style d'une
                     *    position — une cellule `<c s="1">` pointerait alors
                     *    sur le mauvais format, et la détection de date
                     *    échouerait SILENCIEUSEMENT (une date Excel en
                     *    sérial serait importée comme un nombre).
                     *
                     *    Le compteur est donc remis à zéro À L'ENTRÉE de
                     *    `cellXfs`, et l'index est celui de la position
                     *    DANS `cellXfs` — exactement ce que l'attribut `s=`
                     *    d'une cellule désigne.
                     */
                    $inCellXfs = true;
                    $xfIndex = -1;
                } elseif ($xml->name === 'xf' && $inCellXfs) {
                    /*
                     * ⚠️ Le style est évalué ICI, à l'ELEMENT — pas à
                     *    l'END_ELEMENT.
                     *
                     *    En effet, dans un .xlsx les `<xf>` sont AUTO-
                     *    FERMANTS (`<xf numFmtId="14" …/>`) : il n'existe
                     *    aucun événement END_ELEMENT pour eux. Attendre la
                     *    fin de l'élément faisait échouer la détection
                     *    SILENCIEUSEMENT — une date Excel (sérial) était
                     *    alors importée comme un nombre.
                     */
                    $xfIndex++;
                    $xfNumFmtId = (int)$xml->getAttribute('numFmtId');

                    // Un style est « date » si son numFmt l'est.
                    $isDate = 0;
                    if (isset($builtinDateFormats[$xfNumFmtId]) && $builtinDateFormats[$xfNumFmtId]) {
                        $isDate = 1;
                    } elseif (isset($customFormats[$xfNumFmtId])) {
                        $code = strtolower($customFormats[$xfNumFmtId]);
                        // Un format de date contient d, m ou y (et pas
                        // seulement des « 0 » comme un format numérique).
                        if (preg_match('/[dmy]/', $code) && !preg_match('/^[^dmy]*$/', $code)) {
                            $isDate = 1;
                        }
                    }
                    if ($isDate) {
                        $dateStyles[$xfIndex] = true;
                    }
                }
            } elseif ($xml->nodeType === \XMLReader::END_ELEMENT) {
                if ($xml->name === 'cellXfs') {
                    $inCellXfs = false;
                }
            }
        }
        $xml->close();

        return $dateStyles;
    }

    /**
     * Les sharedStrings du classeur (les chaînes uniques).
     *
     * C'est le SEUL index gardé en mémoire : quelques mégaoctets pour
     * 50 000 produits, contre des centaines pour le classeur entier.
     *
     * @return array<int, string>
     */
    private function readSharedStringsNative(\ZipArchive $zip, string $filePath): array
    {
        $shared = [];
        $index = $zip->locateName('xl/sharedStrings.xml');
        if ($index === false) {
            return $shared;
        }

        $xml = new \XMLReader();
        $xml->open('zip://' . $filePath . '#xl/sharedStrings.xml');

        $current = '';
        $inSi = false;
        $inText = false;

        while (@$xml->read()) {
            if ($xml->nodeType === \XMLReader::ELEMENT) {
                if ($xml->name === 'si') {
                    $current = '';
                    $inSi = true;
                } elseif ($xml->name === 't' && $inSi) {
                    $inText = true;
                }
            } elseif ($xml->nodeType === \XMLReader::TEXT || $xml->nodeType === \XMLReader::CDATA) {
                if ($inText) {
                    $current .= $xml->readString();
                }
            } elseif ($xml->nodeType === \XMLReader::END_ELEMENT) {
                if ($xml->name === 't') {
                    $inText = false;
                } elseif ($xml->name === 'si') {
                    $shared[] = $current;
                    $inSi = false;
                }
            }
        }
        $xml->close();

        return $shared;
    }

    /**
     * Associe les en-têtes aux colonnes logiques (équivalent natif de
     * `mapHeaders()`, qui travaille sur des objets Cell).
     *
     * @param array<string, string> $cells  Lettre de colonne → valeur.
     * @return array<string, int>  Colonne logique → position 1-based.
     */
    private function mapHeadersNative(array $cells): array
    {
        $headerMap = [];
        $position = 0;

        /*
         * ⚠️ On itère les colonnes DANS L'ORDRE (A, B, C…), pas dans
         *    l'ordre du tableau PHP : les colonnes lues ne sont pas
         *    forcément contiguës, et `mapHeaders()` (le lecteur PhpSpreadsheet)
         *    compte la position sur les cellules RÉELLEMENT présentes.
         *    Sans ce tri, une colonne vide au milieu décalerait tous les
         *    index d'une position.
         */
        ksort($cells, SORT_NATURAL);

        foreach ($cells as $value) {
            $position++;
            $normalized = $this->normalizeHeader((string)$value);
            if ($normalized === '') {
                continue;
            }
            foreach (self::HEADER_ALIASES as $canonical => $aliases) {
                if (in_array($normalized, $aliases, true) && !isset($headerMap[$canonical])) {
                    $headerMap[$canonical] = $position;
                    break;
                }
            }
        }

        return $headerMap;
    }

    /**
     * Extrait les valeurs logiques d'une ligne (équivalent natif de
     * `extractCells()`).
     *
     * ⚠️ Le lecteur natif ne distingue pas une date d'un nombre : la
     *    colonne `date_expiration` est donc convertie par `buildProduct()`
     *    (qui sait lire « YYYY-MM-DD » ET « JJ/MM/AAAA »), et non par
     *    `SharedDate::isDateTime()`.
     *
     * @param array<string, string> $cells
     * @param array<string, int>    $map
     */
    private function extractCellsNative(array $cells, array $map, array $dateCells = []): array
    {
        $extracted = [];

        /*
         * ⚠️ `$map` associe une colonne logique à une POSITION 1-based
         *    (comme `mapHeaders()`), pas à une lettre. On reconstruit donc
         *    la lettre depuis la position — c'est le même contrat que
         *    `extractCells()`, qui fait l'inverse sur des objets Cell.
         */
        foreach ($map as $column => $position) {
            $letter = Coordinate::stringFromColumnIndex((int)$position);
            $raw = $cells[$letter] ?? null;

            $extracted[$column] = [
                'value'     => $raw,
                'formatted' => $raw === null ? '' : trim((string)$raw),
                // Une cellule dont le STYLE est une date : la valeur est un
                // sérial Excel, que `readDate()` sait convertir.
                'is_date'   => isset($dateCells[$letter]),
            ];
        }

        return $extracted;
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
        //
        // ⚠️ On lit la VALEUR BRUTE de la cellule, jamais sa valeur affichée :
        // Excel applique automatiquement un format « date » (m/d/yyyy) aux
        // codes-barres à 13 chiffres. La valeur affichée est alors
        // « 5/17/23070765319 » au lieu de « 8426423308202 ».
        $codeBarres = $this->readBarcode($cells, 'code_barres');
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

    /**
     * Collecte les code-barres présents dans le fichier, SANS toucher à la
     * base de données.
     *
     * ⚠️ Sert à la recherche batchée des produits existants (cf.
     * `importFile()`) : on prépare la liste des code-barres à interroger en
     * une seule passe sur les lignes, au lieu d'interroger la base ligne par
     * ligne pendant l'écriture.
     *
     * La lecture est IDENTIQUE à celle de `buildProduct()` (`readBarcode()`,
     * valeur brute et non valeur affichée). Les lignes dont le code-barres
     * est VIDE sont ignorées : `buildProduct()` leur en génère un, et ce
     * code-barres généré est par construction absent du catalogue.
     *
     * @param array $rows Lignes lues par `readRows()`
     * @return array<int,string> Liste des code-barres (doublons possibles)
     */
    private function collectBarcodes(array $rows): array
    {
        $barcodes = [];
        foreach ($rows as $row) {
            $cells = $row['cells'] ?? [];
            if (!is_array($cells)) {
                continue;
            }
            $barcode = $this->readBarcode($cells, 'code_barres');
            if ($barcode !== '') {
                $barcodes[] = $barcode;
            }
        }
        return $barcodes;
    }

    // MARK: Résolution des référentiels

    /** Charge les catégories visibles de la boutique (indexées par nom normalisé). */
    private function loadCategories($shopId): array
    {
        $categories = [];
        if (!$shopId) {
            // Sans boutique de destination, on ne charge AUCUNE catégorie :
            // sinon l'import pourrait rattacher un produit à la catégorie
            // d'une AUTRE boutique (ou à une catégorie orpheline).
            return $categories;
        }

        try {
            $rows = $this->categoryModel->all($shopId);
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

    /**
     * Lit une valeur texte.
     *
     * ⚠️ La valeur BRUTE prime sur la valeur AFFICHÉE. Excel applique le
     * format de la cellule aux valeurs textuelles numériques : une
     * catégorie « 04 » dans une cellule au format « m/d/yyyy » s'affiche
     * « 1/4/1900 ». On conserve donc le contenu réel de la cellule.
     */
    private function readString(array $cells, string $column): string
    {
        if (!isset($cells[$column])) {
            return '';
        }
        $cell = $cells[$column];
        if (!is_array($cell)) {
            return '';
        }

        $raw = $cell['value'] ?? null;

        if (is_string($raw)) {
            $raw = trim($raw);

            if ($raw === '') {
                return '';
            }

            // Formule Excel : la valeur brute est l'expression, pas le
            // résultat → on utilise la valeur affichée.
            if (str_starts_with($raw, '=')) {
                return trim((string)($cell['formatted'] ?? ''));
            }

            return $raw;
        }

        if (is_int($raw)) {
            return (string) $raw;
        }

        if (is_float($raw) && floor($raw) === $raw) {
            return (string) (int) $raw;
        }

        return trim((string)($cell['formatted'] ?? ''));
    }

    /**
     * Lit un code-barres depuis la VALEUR BRUTE de la cellule.
     *
     * ⚠️ Le code-barres ne doit JAMAIS passer par `getFormattedValue()` :
     * Excel formate automatiquement en date (m/d/yyyy) les codes-barres
     * numériques à 13 chiffres. Le code-barres « 8426423308202 » s'affiche
     * alors « 5/17/23070765319 » : l'import enregistrait cette chaîne
     * comme code-barres, rendant le produit invisible au scanner.
     *
     * On conserve donc le contenu réel de la cellule :
     *   - texte  → tel quel (les zéros initiaux sont préservés) ;
     *   - entier → sa valeur exacte ;
     *   - float  → sa valeur exacte, sans notation scientifique.
     *
     * @param string $column  Colonne logique (défaut : `code_barres`)
     */
    private function readBarcode(array $cells, string $column = 'code_barres'): string
    {
        if (!isset($cells[$column]) || !is_array($cells[$column])) {
            return '';
        }
        $cell = $cells[$column];

        $raw = $cell['value'] ?? null;

        if (is_string($raw)) {
            $raw = trim($raw);

            // Cellule vide : on ne retombe PAS sur la valeur affichée, qui
            // pourrait être une date (« 5/17/23070765319 »).
            if ($raw === '') {
                return '';
            }

            // Formule Excel : la valeur brute est l'expression, pas le
            // résultat → on utilise la valeur affichée.
            if (str_starts_with($raw, '=')) {
                return trim((string)($cell['formatted'] ?? ''));
            }

            return $raw;
        }

        if (is_int($raw)) {
            return (string) $raw;
        }

        if (is_float($raw)) {
            // `%.0f` évite la notation scientifique (« 8.426423308202E+12 »).
            return sprintf('%.0f', $raw);
        }

        // Aucune valeur brute exploitable (cellule vide).
        return '';
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
            // ⚠️ `findIdByBarcode()` et non `findByBarcode()` : ce dernier
            // ajoute 4 requêtes d'enrichissement (stock, lots…) qui ne
            // servent qu'à savoir SI le code-barres existe déjà.
            $existing = $this->productModel->findIdByBarcode($barcode);
        } while ($existing !== null);

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
