<?php
/**
 * Test du cas SÉRIAL EXCEL — le seul que le lecteur natif doit gérer.
 *
 * ⚠️ PhpSpreadsheet n'écrit JAMAIS un sérial Excel spontanément : il
 *    convertit les DateTime en texte (« 2026-12-29 00:00:00 »).
 *    Un fichier produit par Excel (ou par un vrai tableur) porte, lui,
 *    une cellule `<c r="N2" s="1"><v>46365</v></c>` — le style `s="1"`
 *    pointant vers un format de date, et la valeur étant un SÉRIAL.
 *
 *    Ce cas ne peut donc pas être produit par PhpSpreadsheet : on
 *    construit le .xlsx À LA MAIN (c'est un ZIP de XML), ce qui garantit
 *    qu'on teste exactement ce que Excel écrit.
 *
 * Le test compare :
 *   - la valeur brute lue (le sérial) ;
 *   - `is_date` (la détection par le STYLE) ;
 *   - la dateconvertie (identique des deux côtés).
 */

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as SharedDate;

$dir = sys_get_temp_dir() . '/serial_test';
@mkdir($dir, 0777, true);
$file = $dir . '/serial.xlsx';

echo "=== Construction d'un .xlsx avec un VRAI sérial Excel ===\n";

// ── Les sharedStrings : 2 chaînes (l'en-tête et un nom). ──────────────────
$sharedStrings = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="2" uniqueCount="2">
<si><t>nom</t></si>
<si><t>date_expiration</t></si>
</sst>
XML;

// ── Les styles : numFmtId 14 (date) pour le style 1. ────────────────────
$styles = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<numFmts count="0"/>
<fonts count="1"><font/></fonts>
<fills count="1"><fill/></fills>
<borders count="1"><border/></borders>
<cellStyleXfs count="1"><xf numFmtId="0"/></cellStyleXfs>
<cellXfs count="2">
<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
<xf numFmtId="14" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>
</cellXfs>
</styleSheet>
XML;

// 46365 = le 29/12/2026 en sérial Excel (base 1899-12-30).
$serial = SharedDate::PHPToExcel(new DateTime('2026-12-29'));
printf("    sérial Excel du 29/12/2026 : %d\n", $serial);

$sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
    . '<sheetData>'
    // Ligne 1 : l'en-tête (chaîne partagée).
    // Ligne 1 : les en-têtes. « date_expiration » est en colonne B —
    // c'est la colonne qui PORTERA les sérial en ligne 2.
    . '<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c></row>'
    // Ligne 2 : le nom (chaîne partagée) + la date (SÉRIAL, style 1).
    . '<row r="2">'
    . '<c r="A2" t="inlineStr"><is><t>Produit avec sérial</t></is></c>'
    . '<c r="B2" s="1"><v>' . $serial . '</v></c>'
    . '</row>'
    . '</sheetData></worksheet>';

$contentTypes = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>
XML;

$rootRels = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML;

$workbook = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets><sheet name="Produits" sheetId="1" r:id="rId1"/></sheets>
</workbook>
XML;

$workbookRels = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML;

@unlink($file);
$zip = new ZipArchive();
$zip->open($file, ZipArchive::CREATE);
$zip->addFromString('[Content_Types].xml', $contentTypes);
$zip->addFromString('_rels/.rels', $rootRels);
$zip->addFromString('xl/workbook.xml', $workbook);
$zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
$zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
$zip->addFromString('xl/sharedStrings.xml', $sharedStrings);
$zip->addFromString('xl/styles.xml', $styles);
$zip->close();
printf("    fichier construit : %s (%d octets)\n", $file, filesize($file));

// ── Lecture par PhpSpreadsheet. ───────────────────────────────────────────
echo "\n=== 1. PhpSpreadsheet (l'ancien chemin) ===\n";
$ss = IOFactory::load($file);
$sheet = $ss->getActiveSheet();
foreach ($sheet->getRowIterator() as $row) {
    $it = $row->getCellIterator();
    $it->setIterateOnlyExistingCells(true);
    foreach ($it as $cell) {
        printf("  %s : value=%s  formatted=%s  isDate=%s\n",
            $cell->getCoordinate(),
            var_export($cell->getValue(), true),
            var_export($cell->getFormattedValue(), true),
            SharedDate::isDateTime($cell) ? 'OUI' : 'non'
        );
    }
}
$ss->disconnectWorksheets();

// ── Lecture par le lecteur NATIF (le nouveau chemin). ────────────────────
echo "\n=== 2. Le lecteur NATIF DU SERVICE (le vrai code testé) ===\n";

// On appelle la méthode RÉELLE du service : c'est elle qu'on veut
// éprouver (et non une copie qui pourrait diverger).
$service0 = new \App\Services\ProductImportService();
$ref0 = new ReflectionClass($service0);
$m0 = $ref0->getMethod('readXlsxRowsNative');
$m0->setAccessible(true);

$natif = [];
foreach ($m0->invoke($service0, $file) as $r) {
    $natif[] = $r;
    printf("  ligne %d : %s\n", $r['line'], json_encode($r['cells'], JSON_UNESCAPED_UNICODE));
}

// ── Ce que le SERVICE en fait. ───────────────────────────────────────────
echo "\n=== 3. Ce que le service d'import en fait ===\n";
$service = new \App\Services\ProductImportService();
$ref = new ReflectionClass($service);
$m = $ref->getMethod('readDate');
$m->setAccessible(true);

// La cellule DATE lue par PhpSpreadsheet (is_date = OUI).
$cellPhp = ['value' => $serial, 'formatted' => '12/29/2026', 'is_date' => true];

// La cellule lue par le lecteur NATIF DU SERVICE.
$cellNat = null;
foreach ($natif as $r) {
    if (isset($r['cells']['date_expiration'])) {
        $cellNat = $r['cells']['date_expiration'];
        break;
    }
}

$dPhp = $m->invoke($service, ['date_expiration' => $cellPhp], 'date_expiration');
$dNat = $cellNat !== null
    ? $m->invoke($service, ['date_expiration' => $cellNat], 'date_expiration')
    : null;

printf("  PhpSpreadsheet (value=%s, is_date=%s) → %s\n",
    var_export($cellPhp['value'], true), $cellPhp['is_date'] ? 'OUI' : 'non', var_export($dPhp, true));
printf("  natif          (value=%s, is_date=%s) → %s\n",
    var_export($cellNat['value'] ?? null, true),
    !empty($cellNat['is_date']) ? 'OUI' : 'non',
    var_export($dNat, true));

$ok = ($dPhp === $dNat && $dPhp === '2026-12-29');

echo "\n" . ($dPhp === $dNat && $dPhp === '2026-12-29'
    ? "=== LE SÉRIAL EXCEL EST GÉRÉ IDENTIQUEMENT ===\n"
    : "=== DIVERGENCE SUR LE SÉRIAL EXCEL ===\n");

// ⚠️ On NE supprime PAS le fichier si KEEP_FILE=1 : le script de
//    diagnostic (scripts/dbg_styles.php) en a besoin.
if (getenv('KEEP_FILE') !== '1') {
    @unlink($file);
    @rmdir($dir);
} else {
    echo "\n  (fichier conservé : {$file})\n";
}

exit($dPhp === $dNat && $dPhp === '2026-12-29' ? 0 : 1);
