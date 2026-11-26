<?php
/**
 * ÉQUIVALENCE : le lecteur NATIF (.xlsx) vs le lecteur PhpSpreadsheet.
 *
 * ⚠️ C'est le test qui prouve que le correctif de mémoire n'a RIEN
 *    changé au comportement de l'import.
 *
 * On compare, sur le MÊME fichier, pour les 500 premières lignes :
 *   - le code-barres lu ;
 *   - le nom lu (avec accents et apostrophes) ;
 *   - la catégorie résolue ;
 *   - le prix ;
 *   - le stock ;
 *   - la date de péremption (le cas le plus subtil : le lecteur natif
 *     distingue les cellules DATE par leur STYLE, pas par la valeur).
 *
 * Si les deux lecteurs divergent sur un champ, l'import produirait des
 * produits différents selon la version de PHP ou le format du fichier —
 * c'est ce qu'on veut éviter.
 */

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

$file = $argv[1] ?? (dirname(__DIR__) . '/docs/produits_50k_import.xlsx');
$maxLines = (int) ($argv[2] ?? 500);

if (!is_file($file)) {
    fwrite(STDERR, "Fichier introuvable : {$file}\n");
    exit(1);
}

/**
 * Le lecteur PhpSpreadsheet (l'ancien chemin) : charge tout, extrait
 * ligne par ligne.
 */
function lireAvecPhpSpreadsheet(string $file, int $maxLines): array
{
    $spreadsheet = IOFactory::load($file);
    $sheet = $spreadsheet->getActiveSheet();

    $headerMap = null;
    $rows = [];

    foreach ($sheet->getRowIterator() as $row) {
        $cells = [];
        $it = $row->getCellIterator();
        $it->setIterateOnlyExistingCells(true);
        foreach ($it as $cell) {
            $cells[$cell->getColumn()] = $cell;
        }

        if ($headerMap === null) {
            $headerMap = mapHeaders($cells);
            continue;
        }

        $extracted = [];
        foreach ($headerMap as $column => $position) {
            $letter = Coordinate::stringFromColumnIndex($position);
            $cell = $cells[$letter] ?? null;
            $extracted[$column] = $cell ? [
                'value'     => $cell->getValue(),
                'formatted' => (string)$cell->getFormattedValue(),
                'is_date'   => PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell),
            ] : ['value' => null, 'formatted' => '', 'is_date' => false];
        }

        $rows[] = ['line' => $row->getRowIndex(), 'cells' => $extracted];
        if (count($rows) >= $maxLines) {
            break;
        }
    }

    $spreadsheet->disconnectWorksheets();
    return $rows;
}

const HEADER_ALIASES = [
    'code_barres' => ['codebarres', 'codebarre', 'code', 'barcode', 'ean', 'ean13', 'reference', 'ref'],
    'nom' => ['nom', 'nomduproduit', 'produit', 'designation', 'libelle', 'name'],
    'categorie' => ['categorie', 'category', 'famille', 'rayon'],
    'prix' => ['prix', 'prixdevente', 'prixvente', 'prixunitaire', 'pv', 'price'],
    'stock' => ['stock', 'stockinitial', 'quantite', 'qte', 'quantiteinitiale'],
    'stock_minimum' => ['stockminimum', 'stockmin', 'seuil', 'seuildalerte'],
    'taxe' => ['taxe', 'groupedetaxe', 'grouptaxe', 'etiquette', 'taxgroup'],
    'product_type' => ['producttype', 'typedevente', 'typevente', 'type', 'unitetype'],
    'prod_service' => ['prodservice', 'typesdeservice', 'typedeservice', 'service'],
    'remise_type' => ['remisetype', 'typederemise'],
    'remise_value' => ['remisevalue', 'remise', 'valeurremise', 'pourcentageremise'],
    'taxe_specifique_type' => ['taxespecifiquetype', 'typespecificite'],
    'taxe_specifique_value' => ['taxespecifiquevalue', 'taxespecifique', 'valeurtaxespecifique'],
    'date_expiration' => ['dateexpiration', 'expiration', 'peremption', 'dateperemption'],
];

function normalizeHeader(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    $value = strtr($value, [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'è' => 'e', 'é' => 'e',
        'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'ú' => 'u',
        'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n',
    ]);
    $value = strtolower($value);
    return preg_replace('/[^a-z0-9]/', '', $value);
}

function mapHeaders(array $cells): array
{
    $map = [];
    $index = 0;
    foreach ($cells as $value) {
        $index++;
        $key = normalizeHeader((string)$value);
        if ($key === '') {
            continue;
        }
        foreach (HEADER_ALIASES as $column => $aliases) {
            if (in_array($key, $aliases, true) && !isset($map[$column])) {
                $map[$column] = $index;
                break;
            }
        }
    }
    return $map;
}

/**
 * Le lecteur NATIF (le nouveau chemin) : ZipArchive + XMLReader.
 */
function lireAvecLecteurNatif(string $file, int $maxLines): array
{
    $zip = new ZipArchive();
    $zip->open($file);

    // sharedStrings
    $shared = [];
    $xml = new XMLReader();
    $xml->open('zip://' . $file . '#xl/sharedStrings.xml');
    $cur = '';
    $inSi = false;
    $inT = false;
    while (@$xml->read()) {
        if ($xml->nodeType === XMLReader::ELEMENT) {
            if ($xml->name === 'si') { $cur = ''; $inSi = true; }
            elseif ($xml->name === 't' && $inSi) { $inT = true; }
        } elseif ($xml->nodeType === XMLReader::TEXT || $xml->nodeType === XMLReader::CDATA) {
            if ($inT) { $cur .= $xml->readString(); }
        } elseif ($xml->nodeType === XMLReader::END_ELEMENT) {
            if ($xml->name === 't') { $inT = false; }
            elseif ($xml->name === 'si') { $shared[] = $cur; $inSi = false; }
        }
    }
    $xml->close();

    // styles de date
    $dateStyles = [];
    $xml = new XMLReader();
    $xml->open('zip://' . $file . '#xl/styles.xml');
    $custom = [];
    $builtin = array_fill(0, 0, 48);
    foreach ([14, 15, 16, 17, 18, 19, 20, 21, 22, 45, 46, 47] as $id) { $builtin[$id] = 1; }
    $inXfs = false; $xfId = 0; $xfIdx = -1; $inNumFmts = false;
    while (@$xml->read()) {
        if ($xml->nodeType === XMLReader::ELEMENT) {
            if ($xml->name === 'numFmts') { $inNumFmts = true; }
            elseif ($xml->name === 'numFmt') { $custom[(int)$xml->getAttribute('numFmtId')] = (string)$xml->getAttribute('formatCode'); }
            elseif ($xml->name === 'cellXfs') { $inXfs = true; }
            elseif ($xml->name === 'xf' && $inXfs) { $xfIdx++; $xfId = (int)$xml->getAttribute('numFmtId'); }
        } elseif ($xml->nodeType === XMLReader::END_ELEMENT) {
            if ($xml->name === 'numFmts') { $inNumFmts = false; }
            elseif ($xml->name === 'cellXfs') { $inXfs = false; }
            elseif ($xml->name === 'xf' && $inXfs) {
                $isDate = 0;
                if (isset($builtin[$xfId]) && $builtin[$xfId]) { $isDate = 1; }
                elseif (isset($custom[$xfId])) {
                    $code = strtolower($custom[$xfId]);
                    if (preg_match('/[dmy]/', $code) && !preg_match('/^[^dmy]*$/', $code)) { $isDate = 1; }
                }
                if ($isDate) { $dateStyles[$xfIdx] = true; }
            }
        }
    }
    $xml->close();

    // La 1re feuille
    $sheetPath = null;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $nm = $zip->getNameIndex($i);
        if (preg_match('#^xl/worksheets/sheet(\d+)\.xml$#', (string)$nm)) { $sheetPath = (string)$nm; break; }
    }

    $rows = [];
    $headerMap = null;
    $xml = new XMLReader();
    $xml->open('zip://' . $file . '#' . $sheetPath);
    $rowIdx = 0; $cells = []; $dateCells = [];
    $col = ''; $type = ''; $isDate = false; $hasData = false;
    $inV = false; $vText = ''; $inT = false;

    while (@$xml->read()) {
        if ($xml->nodeType === XMLReader::ELEMENT) {
            switch ($xml->name) {
                case 'row':
                    $rowIdx = (int)$xml->getAttribute('r');
                    $cells = []; $dateCells = []; $hasData = false;
                    break;
                case 'c':
                    $ref = (string)$xml->getAttribute('r');
                    $type = (string)$xml->getAttribute('t');
                    $col = preg_replace('/\d+/', '', $ref);
                    $s = $xml->getAttribute('s');
                    $isDate = ($s !== null && $s !== '' && isset($dateStyles[(int)$s]));
                    break;
                case 'v': $inV = true; $vText = ''; break;
                case 't': $inT = true; $vText = ''; break;
            }
        } elseif ($xml->nodeType === XMLReader::TEXT || $xml->nodeType === XMLReader::CDATA) {
            if ($inV || $inT) { $vText .= $xml->readString(); }
        } elseif ($xml->nodeType === XMLReader::END_ELEMENT) {
            switch ($xml->name) {
                case 'v':
                    if ($inV) {
                        $val = $vText;
                        if ($type === 's') {
                            $val = $shared[(int)$val] ?? '';
                        } else {
                            // Meme normalisation que le service : le XML
                            // brut porte « 15939.0 » pour un entier.
                            $val = preg_replace('/\.0+$/', '', $val);
                        }
                        $cells[$col] = $val;
                        if ($isDate) { $dateCells[$col] = $val; }
                        $hasData = true;
                    }
                    $inV = false;
                    break;
                case 't':
                    if ($inT) { $cells[$col] = $vText; $hasData = true; }
                    $inT = false;
                    break;
                case 'row':
                    if ($headerMap === null) {
                        if ($hasData) {
                            ksort($cells, SORT_NATURAL);
                            $headerMap = [];
                            $pos = 0;
                            foreach ($cells as $v) {
                                $pos++;
                                $k = normalizeHeader((string)$v);
                                if ($k === '') { continue; }
                                foreach (HEADER_ALIASES as $c => $aliases) {
                                    if (in_array($k, $aliases, true) && !isset($headerMap[$c])) {
                                        $headerMap[$c] = $pos;
                                        break;
                                    }
                                }
                            }
                        }
                        break;
                    }
                    if (!$hasData) { break; }
                    $extracted = [];
                    foreach ($headerMap as $column => $position) {
                        $letter = Coordinate::stringFromColumnIndex((int)$position);
                        $raw = $cells[$letter] ?? null;
                        $extracted[$column] = [
                            'value'     => $raw,
                            'formatted' => $raw === null ? '' : trim((string)$raw),
                            'is_date'   => isset($dateCells[$letter]),
                        ];
                    }
                    $rows[] = ['line' => $rowIdx, 'cells' => $extracted];
                    if (count($rows) >= $maxLines) { break 2; }
                    break;
            }
        }
    }
    $xml->close();
    $zip->close();

    return $rows;
}

/*
 * ⚠️ Un fichier de test avec de VRAIES cellules DATE Excel.
 *
 * Dans notre fichier de 50 000 produits, la date est une CHAÎNE
 * (« 2026-12-29 ») : elle est donc écrite en `sharedStrings` et n'a
 * AUCUN style de date. Le cas « sérial Excel » (une vraie cellule date)
 * n'est donc PAS exercé par ce fichier — il faut le tester à part.
 *
 * Ce fichier-ci est généré avec PhpSpreadsheet en appliquant un format
 * de date à la colonne : les cellules portent alors un style de date, et
 * leur valeur est un sérial.
 */
$dateFile = sys_get_temp_dir() . '/test_dates.xlsx';
if (!is_file($dateFile) || (($argv[3] ?? '') === 'regen')) {
    $ss = new PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sh = $ss->getActiveSheet();
    $sh->setTitle('Produits');
    $sh->fromArray([
        'code_barres', 'nom', 'categorie', 'prix', 'stock', 'stock_minimum',
        'taxe', 'product_type', 'prod_service', 'remise_type', 'remise_value',
        'taxe_specifique_type', 'taxe_specifique_value', 'date_expiration',
    ], null, 'A1');
    // ⚠️ Pour qu'Excel stocke un SÉRIAL (et non une chaîne), la valeur
    //    doit être un timestamp PHP ET la cellule doit porter un format de
    //    date. Avec une chaîne « 2026-12-29 », PhpSpreadsheet écrit du
    //    texte — le cas du sérial n'est donc PAS exercé.
    $dates = [
        new DateTime('2026-12-29'),
        new DateTime('2027-01-15'),
        new DateTime('2027-02-20'),
    ];
    $sh->fromArray([
        ['3017620999001', 'Produit date 1', 'Comestible', 1500, 10, 2, 'A', 'unite', 'BIE', '%', 0, '%', 0, $dates[0]],
        ['3017620999002', 'Produit date 2', 'Boissons', 2500, 20, 3, 'B', 'unite', 'BIE', '%', 0, '%', 0, $dates[1]],
        ['3017620999003', 'Produit date 3', 'Service', 3500, 30, 4, 'C', 'unite', 'BIE', '%', 0, '%', 0, $dates[2]],
    ], null, 'A2');
    // ⚠️ Le format de DATE : c'est lui qui pose le style "date" sur la
    //    cellule, et qui fait qu'Excel stocke un SÉRIAL.
    $sh->getStyle('N2:N4')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
    $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss);
    $writer->save($dateFile);
    $ss->disconnectWorksheets();
    echo "  (fichier de dates généré : {$dateFile})\n";
}

// ── La comparaison. ───────────────────────────────────────────────────────
$champs = ['code_barres', 'nom', 'categorie', 'prix', 'stock', 'stock_minimum', 'taxe', 'date_expiration'];

echo "=== Lecture avec PhpSpreadsheet (l'ancien chemin) ===\n";
$t0 = microtime(true);
$a = lireAvecPhpSpreadsheet($file, $maxLines);
$tA = (microtime(true) - $t0) * 1000;
printf("    %d lignes en %.0f ms\n", count($a), $tA);

echo "\n=== Lecture avec le lecteur NATIF (le nouveau chemin) ===\n";
$t0 = microtime(true);
$b = lireAvecLecteurNatif($file, $maxLines);
$tB = (microtime(true) - $t0) * 1000;
printf("    %d lignes en %.0f ms\n", count($b), $tB);

echo "\n=== Comparaison champ par champ ===\n";
$ecarts = 0;
$total = 0;

if (count($a) !== count($b)) {
    echo "  [KO] nombre de lignes différent : " . count($a) . ' vs ' . count($b) . "\n";
    $ecarts++;
}

$n = min(count($a), count($b));
for ($i = 0; $i < $n; $i++) {
    foreach ($champs as $champ) {
        $va = $a[$i]['cells'][$champ]['value'] ?? null;
        $vb = $b[$i]['cells'][$champ]['value'] ?? null;
        $da = $a[$i]['cells'][$champ]['is_date'] ?? false;
        $db = $b[$i]['cells'][$champ]['is_date'] ?? false;

        $total++;

        // La valeur brute peut différer (sérial Excel vs chaîne) : on
        // compare la valeur FORMATTÉE, qui est ce que le service utilise.
        $fa = (string)($a[$i]['cells'][$champ]['formatted'] ?? '');
        $fb = (string)($b[$i]['cells'][$champ]['formatted'] ?? '');

        if ($fa !== $fb) {
            $ecarts++;
            if ($ecarts <= 10) {
                printf("  [KO] ligne %d, champ %s : « %s » vs « %s »\n",
                    $a[$i]['line'], $champ, $fa, $fb);
            }
        }
    }
}

printf("\n  %d champs comparés, %d écart(s)\n", $total, $ecarts);

// Un contrôle ciblé sur le champ le plus subtil : la date.
$datesA = 0; $datesB = 0; $valeursDateEgales = 0;
for ($i = 0; $i < $n; $i++) {
    $da = $a[$i]['cells']['date_expiration']['is_date'] ?? false;
    $db = $b[$i]['cells']['date_expiration']['is_date'] ?? false;
    if ($da) { $datesA++; }
    if ($db) { $datesB++; }
    $va = (string)($a[$i]['cells']['date_expiration']['value'] ?? '');
    $vb = (string)($b[$i]['cells']['date_expiration']['value'] ?? '');
    if ($va === $vb) { $valeursDateEgales++; }
}
printf("\n  Cellules DATE détectées : PhpSpreadsheet %d, natif %d (sur %d lignes)\n",
    $datesA, $datesB, $n);
printf("  Valeurs de date identiques : %d / %d\n", $valeursDateEgales, $n);

/*
 * ── Le cas des VRAIES cellules DATE (sérial Excel) ──────────────────────
 */
echo "\n=== Le cas des cellules DATE Excel (sérial) ===\n\n";
$aD = lireAvecPhpSpreadsheet($dateFile, 10);
$bD = lireAvecLecteurNatif($dateFile, 10);

printf("  %-4s %-14s %-14s %-8s %-8s\n", 'ligne', 'PhpSpreadsheet', 'natif', 'date?', 'date?');
$ecartsDate = 0;
for ($i = 0; $i < min(count($aD), count($bD)); $i++) {
    $va = $aD[$i]['cells']['date_expiration']['value'] ?? null;
    $vb = $bD[$i]['cells']['date_expiration']['value'] ?? null;
    $da = $aD[$i]['cells']['date_expiration']['is_date'] ?? false;
    $db = $bD[$i]['cells']['date_expiration']['is_date'] ?? false;

    printf("  %-4d %-14s %-14s %-8s %-8s\n", $aD[$i]['line'],
        var_export($va, true), var_export($vb, true),
        $da ? 'OUI' : 'non', $db ? 'OUI' : 'non');

    if ($va !== $vb) { $ecartsDate++; }
    if ($da !== $db) { $ecartsDate++; }
}

// La conversion en date lisible doit donner le MÊME résultat.
foreach ([[$aD, 'PhpSpreadsheet'], [$bD, 'natif']] as [$set, $label]) {
    foreach ($set as $r) {
        $cell = $r['cells']['date_expiration'];
        if (!empty($cell['is_date']) && is_numeric($cell['value'])) {
            $d = PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$cell['value'])->format('Y-m-d');
            printf("  %-14s ligne %d → %s\n", $label, $r['line'], $d);
        }
    }
}

$ecarts += $ecartsDate;

echo "\n" . ($ecarts === 0
    ? "=== LES DEUX LECTURES SONT IDENTIQUES (y compris les cellules DATE) ===\n"
    : "=== $ecarts ÉCART(S) — LE LECTEUR NATIF N'EST PAS ÉQUIVALENT ===\n");

exit($ecarts === 0 ? 0 : 1);
