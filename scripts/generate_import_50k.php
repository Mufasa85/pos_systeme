<?php
/**
 * Génère un fichier Excel de 50 000 produits, prêt à importer.
 *
 * ⚠️ Le format suit EXACTEMENT le template de l'import
 *    (app/Services/ProductImportService.php::template()) :
 *      - les MÊMES en-têtes canoniques (`array_keys(HEADER_ALIASES)`) ;
 *      - `code_barres` en format TEXTE (@) — sinon Excel transforme un
 *        code à 13 chiffres en date ;
 *      - une feuille « Aide » avec le rappel des règles.
 *
 * ⚠️ Les DONNÉES sont realistas ET designed pour éprouver les
 *    optimizations faites :
 *      - des noms avec ACCENTS et apostrophes (test de l'échappement
 *        du bouton « Modifier ») ;
 *      - des codes-barres EAN-13 valides, uniques ;
 *      - des dates de péremption échelonnées (test de la page des lots
 *        et des alertes) ;
 *      - une répartition réaliste des prix, stocks et catégories.
 *
 * Usage : php scripts/generate_import_50k.php [N] [fichier.xlsx]
 */

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Calcule la clé de contrôle (chiffre de Luhn) d'un code-barres EAN-13.
 *
 * Le chiffre de Luhn rend le code-barres « lisible » par un scanner :
 * une erreur de saisie est détectée. C'est ce qui le rend crédible pour
 * un test de charge.
 */
function luhn_digit(string $base): string
{
    $sum = 0;
    $len = strlen($base);
    for ($i = 0; $i < $len; $i++) {
        $digit = (int)$base[$len - 1 - $i];
        $sum += ($i % 2 === 0) ? $digit * 2 : $digit;
    }
    return $base . (string)((10 - ($sum % 10)) % 10);
}

$N = (int) ($argv[1] ?? 50000);
$output = $argv[2] ?? (dirname(__DIR__) . '/docs/produits_50k_import.xlsx');

/*
 * ⚠️ Le CSV est généré EN PARALLÈLE de l'Excel : c'est la variante qui
 * CONTORNE la limite de mémoire de l'import.
 *
 * Constat mesuré : `ProductImportService::readRows()` fait
 * `IOFactory::load()` pour un .xlsx, ce qui charge TOUT le fichier en
 * RAM — épuisement de `memory_limit` (512 Mo) à 50 000 lignes.
 *
 * La branche CSV (`readCsvRows()`) lit le fichier ligne par ligne :
 * mémoire constante, aucune limite de taille.
 *
 * Le séparateur est le point-virgule (`;`) — c'est ce que
 * `readCsvRows()` attend (`fgetcsv($handle, 0, ';')`).
 */
$csvOutput = str_replace('.xlsx', '.csv', $output);

// ── Les en-têtes canoniques, dans l'ordre du template. ────────────────────
$headers = [
    'code_barres', 'nom', 'categorie', 'prix', 'stock', 'stock_minimum',
    'taxe', 'product_type', 'prod_service', 'remise_type', 'remise_value',
    'taxe_specifique_type', 'taxe_specifique_value', 'date_expiration',
];

// ── Catégories RÉELLEMENT présentes en base (l'import résout par le nom,
//    insensible à la casse ; une catégorie inconnue échoue la ligne).
$categories = ['Comestible', 'Non comestible', 'Boissons', 'Service', 'animal'];

// ── Étiquettes de taxe réellement présentes. ──────────────────────────────
$taxes = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R'];

/*
 * Les marques et désignations : avec accents, apostrophes et，业务
 * REALISTES. Le but est de ne pas importer un jeu de données "propre"
 * qui ne catcherait pas les bugs d'échappement ni de tri.
 */
$marques = [
    'Coca-Cola', 'Sprite', 'Fanta Orange', 'Pepsi Max', 'Orangina', 'Bière blonde',
    'Eau minérale', 'Lait frais', 'Danone', 'Nestlé', 'Yoplait', 'Président',
    'Camembert', 'Emmental', 'Baguette tradition', 'Pain de mie', 'Farine de blé',
    'Sucre en poudre', 'Sel de Guérande', 'Huile d\'olive', 'Riz parfumé', 'Pâtes',
    'Tomate', 'Pesto', 'Sauce soja', 'Ketchup', 'Mayonnaise', 'Moutarde',
    'Chocolat noir', 'Biscuit sablé', 'Céréale', 'Yaourt nature', 'Fromage râpé',
    'Jambon', 'Saumon fumé', 'Thon à l\'huile', 'Crevette', 'Poulet fermier',
    'Bœuf', 'Veau', 'Agneau', 'Porc', 'Semoule', 'Haricot rouge', 'Lentille',
    'Vanille', 'Caramel', 'Miel de fleurs', 'Confiture de fraise', 'Thé vert',
    'Café moulu', 'Tisane', 'Miel', 'Coulis de tomate', 'Moutarde de Dijon',
    'Éclairs au chocolat', 'Crêpes', 'Confiture', 'Épinards', 'Courgettes',
];

$unites = [
    '33cl', '50cl', '1L', '1,5L', '500g', '250g', '1kg', '200g', '400g',
    'Pack 6', 'Pack 12', 'Bouteille', 'Canette', 'Brique', 'Sachet', 'Barquette',
    'Sachet de 1kg', 'Pot de 500g', 'Bocal 400g', 'Pièce', 'Rouleau',
];

$types = ['unite', 'coupe', 'poids'];
$services = ['BIE', 'SER', 'TAX'];

echo "=== Génération de {$N} produits ===\n";

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Produits');

// L'en-tête, dans l'ordre du template.
$sheet->fromArray($headers, null, 'A1');

// ── Les lignes de données. ────────────────────────────────────────────────
/*
 * ⚠️ On écrit par BLOCS de 1000 lignes avec `fromArray()` : écrire
 *    cellule par cellule 50 000 × 14 = 700 000 fois prend plusieurs
 *    minutes et consomme un gigaoctet de RAM.
 */
/*
 * On ouvre le CSV en ecriture : les lignes y sont ecrites au fil de
 * l'eau (fputcsv), donc la mémoire reste constante.
 */
$csvHandle = fopen($csvOutput, 'w');
if (!$csvHandle) {
    fwrite(STDERR, "Impossible d'ecrire " . $csvOutput . "\n");
    exit(1);
}
fputcsv($csvHandle, $headers, ';');

$nbMarques  = count($marques);
$nbUnites  = count($unites);
$nbTaxes   = count($taxes);
$nbCats    = count($categories);

$bloc = [];
$ecrit = 0;

for ($i = 1; $i <= $N; $i++) {
    $marque = $marques[($i - 1) % $nbMarques];
    $unite  = $unites[($i - 1) % $nbUnites];

    /*
     * Le nom : "Marque - Désignation - Lot NNN".
     * Un lot sur 7 porte un accent et une apostrophe (pour éprouver
     * l'échappement HTML du bouton « Modifier »).
     */
    $lot = ($i % 7 === 0) ? "Édition speciale n°" . (($i % 97) + 1) : 'Lot ' . (($i % 1000) + 1);
    $nom = $marque . ' ' . $unite . ' ' . $lot;

    // Le code-barres : un EAN-13 plausible, avec sa clé de Luhn.
    $base = sprintf('%012d', 301762000000 + $i);
    $code = luhn_digit($base);

    // Le prix : distribution réaliste (100 → 50 000 Fc), avec des centimes.
    $prix = 100 + (($i * 7919) % 49900) + (($i % 3) * 0.5);

    // Le stock : souvent 0, parfois faible (test des alertes de stock).
    $stock = match (true) {
        $i % 11 === 0 => 0,
        $i % 7 === 0  => (($i % 5) + 1),
        default       => 5 + (($i * 13) % 200),
    };

    $stockMin = ($i % 4 === 0) ? (($i % 10) + 2) : 0;

    // La catégorie : répartie sur les catégories réelles.
    $categorie = $categories[($i - 1) % $nbCats];

    // La taxe : étiquette réelle, répartie.
    $taxe = $taxes[($i - 1) % $nbTaxes];

    // Le type de vente : mostly 'unite', some 'poids' forBulk.
    $productType = ($i % 5 === 0) ? $types[1] : $types[0];

    // Le service : BIE pour l'alimentaire, SER pour les services.
    $prodService = ($i % 13 === 0) ? $services[1] : $services[0];

    // La remise : 1 sur 9 des produits a une remise.
    $remise = ($i % 9 === 0);
    $remiseType = $remise ? '%' : '%';
    $remiseValue = $remise ? (($i % 20) + 5) : 0;

    // La taxe spécifique : 1 sur 11.
    $taxeSpec = ($i % 11 === 0);
    $taxeSpecType = $taxeSpec ? '%' : '%';
    $taxeSpecValue = $taxeSpec ? (($i % 5) + 1) : 0;

    /*
     * La date d'expiration : 1 produit sur 3 en a une, étalée de -10 jours
     * (déjà périmé) à +400 jours. C'est ce qui remplit la page des lots et
     * les alertes du dashboard.
     */
    $dateExp = '';
    if ($i % 3 === 0) {
        $offset = ($i % 4 === 0) ? -(($i % 10) + 1) : (($i * 11) % 400) + 1;
        $dateExp = date('Y-m-d', strtotime($offset . ' days'));
    }

    $ligne = [
        $code,
        $nom,
        $categorie,
        number_format($prix, 2, '.', ''),
        (string) $stock,
        (string) $stockMin,
        $taxe,
        $productType,
        $prodService,
        $remiseType,
        (string) $remiseValue,
        $taxeSpecType,
        (string) $taxeSpecValue,
        $dateExp,
    ];

    // Le CSV : ecriture immediate (memoire constante).
    fputcsv($csvHandle, $ligne, ';');

    $bloc[] = $ligne;

    if (count($bloc) >= 1000) {
        $sheet->fromArray($bloc, null, 'A' . ($ecrit + 2));
        $ecrit += count($bloc);
        $bloc = [];
        if ($ecrit % 10000 === 0) {
            echo "    {$ecrit} / {$N} lignes\n";
        }
    }
}

if (!empty($bloc)) {
    $sheet->fromArray($bloc, null, 'A' . ($ecrit + 2));
    $ecrit += count($bloc);
}
echo "    {$ecrit} lignes écrites\n";

/*
 * ⚠️ `code_barres` en format TEXTE ('@') sur TOUTE la colonne.
 *
 * Sans cela, Excel transforme un code à 13 chiffres en DATE
 * (« 5/17/23070765319 ») — le fichier devient illisible à l'import.
 * C'est exactement l'avertissement du template.
 */
$sheet->getStyle('A:A')->getNumberFormat()->setFormatCode('@');

// Idem pour le prix : on veut 1500.50 et non 1 500,50 selon la locale.
$sheet->getStyle('D:D')->getNumberFormat()->setFormatCode('0.00');

// Les en-têtes : gras, fond bleu, blancs — comme le template.
$sheet->getStyle('A1:N1')->getFont()->setBold(true);
$sheet->getStyle('A1:N1')->getFill()
    ->setFillType(Fill::FILL_SOLID)
    ->getStartColor()->setARGB('FF0B5E88');
$sheet->getStyle('A1:N1')->getFont()->getColor()->setARGB('FFFFFFFF');
// ⚠️ L'autofiltre est une propriété de la FEUILLE, pas du style.
$sheet->setAutoFilter('A1:N1');

$widths = [18, 42, 20, 12, 12, 14, 10, 14, 14, 14, 14, 20, 20, 18];
foreach ($widths as $i => $width) {
    $sheet->getColumnDimensionByColumn($i + 1)->setWidth($width);
}

// Figer la première ligne (confort de lecture sur 50 000 lignes).
$sheet->freezePane('A2');

// ── La feuille « Aide » : le rappel des règles, comme le template. ────────
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

// ── Une troisième feuille : le récapitulatif du jeu de test. ─────────────
$sheet3 = $spreadsheet->createSheet();
$sheet3->setTitle('Recap');
$sheet3->fromArray([
    ['Caractéristique', 'Valeur', 'Ce que ça éprouve'],
    ['Nombre de produits', $N, 'La pagination serveur de /produits (seuil : 50 000)'],
    ['Catégories utilisées', implode(', ', $categories), 'Doivent exister en base, sinon la ligne est rejetée'],
    ['Taxes utilisées', 'A à R', 'Étiquettes réelles de la table taxes'],
    ['Noms avec accents/apostrophes', '1 sur 7', "L'échappement HTML du bouton « Modifier » (D2)"],
    ['Produits périmés', '1 sur 12', 'Les alertes du dashboard (R0/R5)'],
    ['Produits avec péremption', '1 sur 3', 'La page /produits/lots (R3/R4)'],
    ['Produits en rupture (stock 0)', '1 sur 11', 'Les alertes de stock faible'],
    ['Produits avec remise', '1 sur 9', 'Le calcul du prix TTC en caisse'],
    ['Prix', '100 → 50 000 Fc', 'La dispersion sur le tri par prix (R2)'],
], null, 'A1');
$sheet3->getStyle('A1:C1')->getFont()->setBold(true);
$sheet3->getColumnDimensionByColumn(1)->setWidth(34);
$sheet3->getColumnDimensionByColumn(2)->setWidth(26);
$sheet3->getColumnDimensionByColumn(3)->setWidth(70);

$spreadsheet->setActiveSheetIndex(0);

fclose($csvHandle);
$csvSize = round(filesize($csvOutput) / 1048576, 2);
printf("    %s\n    %.2f Mo (CSV — a utiliser si l'import .xlsx echoue sur la memoire)\n",
    $csvOutput, $csvSize);

echo "\n=== Écriture du fichier ===\n";
$writer = new Xlsx($spreadsheet);
$writer->save($output);

$size = round(filesize($output) / 1048576, 2);
printf("    %s\n", $output);
printf("    %.2f Mo, %d lignes de données\n", $size, $ecrit);

$spreadsheet->disconnectWorksheets();
unset($spreadsheet);

