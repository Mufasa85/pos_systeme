<?php
/**
 * MESURE : quelle stratégie de lecture du .xlsx tient à 50 000 lignes ?
 *
 * On compare, sur le VRAI fichier de 50 000 produits :
 *
 *   A. IOFactory::load()          → charge TOUT le classeur en RAM
 *   B. readDataOnly + readFilter  → ignore les styles, ne lit que la 1re feuille
 *   C. Lecteur NATIF en streaming → ZipArchive + XMLReader, flux constant
 *
 * Pour chacune : le TEMPS et le PIC de mémoire.
 *
 * ⚠️ `memory_get_peak_usage()` est mesuré dans un SOUS-PROCESSE pour
 *    chaque variante : le pic est cumulé sinon, et la variante suivante
 *    hériterait de la précédente.
 */

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Shared\Date as SharedDate;

$file = $argv[1] ?? (dirname(__DIR__) . '/docs/produits_50k_import.xlsx');
$mode = $argv[2] ?? 'all';

if (!is_file($file)) {
    fwrite(STDERR, "Fichier introuvable : {$file}\n");
    exit(1);
}
printf("Fichier : %s (%.2f Mo)\n\n", basename($file), filesize($file) / 1048576);

$workDir = sys_get_temp_dir() . '/r3_readers';
@mkdir($workDir, 0777, true);

// ── Les variantes sont exécutées dans des SOUS-PROCESSEURS. ─────────────
$variantes = [
    'A_load_classique' => <<<'PHP'
<?php
require $argv[1] . '/vendor/autoload.php';
$t0 = microtime(true);
$s = PhpOffice\PhpSpreadsheet\IOFactory::load($argv[2]);
$sh = $s->getActiveSheet();
$n = 0;
foreach ($sh->getRowIterator() as $row) {
    $it = $row->getCellIterator();
    $it->setIterateOnlyExistingCells(true);
    foreach ($it as $c) { $v = $c->getValue(); }
    $n++;
}
$s->disconnectWorksheets();
printf("{\"ms\":%d,\"mem\":%d,\"rows\":%d}", (microtime(true)-$t0)*1000, memory_get_peak_usage(true), $n);
PHP,
    'B_dataOnly_filter' => <<<'PHP'
<?php
require $argv[1] . '/vendor/autoload.php';
$filter = new class implements PhpOffice\PhpSpreadsheet\Reader\IReadFilter {
    public function readCell($columnAddress, $row, $worksheetName = ''): bool { return true; }
    public function readSheet($worksheetName = ''): bool { return $worksheetName === 'Produits'; }
};
$t0 = microtime(true);
$r = PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($argv[2]);
$r->setReadDataOnly(true);
$r->setReadFilter($filter);
$s = $r->load($argv[2]);
$sh = $s->getActiveSheet();
$n = 0;
foreach ($sh->getRowIterator() as $row) {
    $it = $row->getCellIterator();
    $it->setIterateOnlyExistingCells(true);
    foreach ($it as $c) { $v = $c->getValue(); }
    $n++;
}
$s->disconnectWorksheets();
printf("{\"ms\":%d,\"mem\":%d,\"rows\":%d}", (microtime(true)-$t0)*1000, memory_get_peak_usage(true), $n);
PHP,
    'C_natif_streaming' => <<<'PHP'
<?php
require $argv[1] . '/vendor/autoload.php';
$t0 = microtime(true);

// 1) Les shared strings (les noms uniques) : on les garde en memoire
//    (2-3 Mo pour 50 000 produits), c'est le seul index necessaire.
$zip = new ZipArchive();
$zip->open($argv[2]);
$shared = [];
$idx = $zip->locateName('xl/sharedStrings.xml');
if ($idx !== false) {
    $xml = new XMLReader();
    $xml->open('zip://' . $argv[2] . '#xl/sharedStrings.xml');
    $cur = null;
    $inSi = false;
    while (@$xml->read()) {
        if ($xml->nodeType === XMLReader::ELEMENT) {
            if ($xml->name === 'si') { $cur = ''; $inSi = true; }
            elseif ($xml->name === 't' && $inSi) { $cur .= $xml->readString(); }
        } elseif ($xml->nodeType === XMLReader::END_ELEMENT && $xml->name === 'si') {
            $shared[] = $cur;
            $inSi = false;
        }
    }
    $xml->close();
}

// 2) La 1re feuille : on la lit en FLUX (XMLReader), pas en DOM.
$sheetName = null;
for ($i = 0; $i < $zip->numFiles; $i++) {
    $nm = $zip->getNameIndex($i);
    if (preg_match('#^xl/worksheets/sheet(\d+)\.xml$#', $nm, $m)) {
        $sheetName = $nm;
        break;
    }
}
$rows = 0;
$cells = 0;
if ($sheetName) {
    $xml = new XMLReader();
    $xml->open('zip://' . $argv[2] . '#' . $sheetName);
    $col = '';
    $rowIdx = 0;
    $type = '';
    while (@$xml->read()) {
        if ($xml->nodeType === XMLReader::ELEMENT) {
            if ($xml->name === 'c') {
                $col = (string)$xml->getAttribute('r');
                $type = (string)$xml->getAttribute('t');
                $col = preg_replace('/\d+/', '', $col);
            } elseif ($xml->name === 'v') {
                $val = $xml->readString();
                if ($type === 's') { $val = $shared[(int)$val] ?? ''; }
                $cells++;
            }
        } elseif ($xml->nodeType === XMLReader::END_ELEMENT && $xml->name === 'row') {
            $rows++;
        }
    }
    $xml->close();
}
$zip->close();
printf("{\"ms\":%d,\"mem\":%d,\"rows\":%d,\"cells\":%d}", (microtime(true)-$t0)*1000, memory_get_peak_usage(true), $rows, $cells);
PHP,
];

foreach ($variantes as $nom => $code) {
    if ($mode !== 'all' && $mode !== $nom) {
        continue;
    }

    $script = $workDir . '/' . $nom . '.php';
    file_put_contents($script, $code);

    // Chaque variante tourne dans son propre processus : le pic de mémoire
    // est donc isolé.
    $cmd = sprintf(
        '%s -d memory_limit=2G %s %s %s',
        escapeshellarg(PHP_BINARY),
        escapeshellarg($script),
        escapeshellarg(dirname(__DIR__)),
        escapeshellarg($file)
    );

    $t0 = microtime(true);
    $out = shell_exec($cmd . ' 2>&1');
    $wall = (microtime(true) - $t0) * 1000;

    $res = json_decode(trim((string)$out), true);
    if (!is_array($res)) {
        printf("  %-20s ÉCHEC\n", $nom);
        printf("      commande : %s\n", $cmd);
        printf("      sortie   : %s\n", substr(trim((string)$out), 0, 200));
        continue;
    }

    printf(
        "  %-20s %8.0f ms   pic %6.0f Mo   %d lignes   %d cellules%s\n",
        $nom,
        $res['ms'],
        $res['mem'] / 1048576,
        $res['rows'],
        $res['cells'] ?? 0,
        ($res['mem'] > 512 * 1048576) ? '   ⚠️ DÉPASSE 512 Mo' : ''
    );
}

echo "\n";
echo "  → Pour un import de 50 000 produits via le web, la variante utilisée\n";
echo "    doit tenir sous 512 Mo (le memory_limit par défaut de XAMPP).\n";
