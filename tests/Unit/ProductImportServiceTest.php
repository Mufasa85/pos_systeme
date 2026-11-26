<?php

namespace Tests\Unit;

use App\Services\ProductImportService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;

/**
 * Tests du service d'import de produits (ProductImportService).
 *
 * Ces tests se concentrent sur la partie « pure » du service : la lecture
 * des en-têtes et la conversion des valeurs. Ils ne touchent pas la base
 * de données (pas de création de produit).
 */
class ProductImportServiceTest extends TestCase
{
    private $tmpFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        $this->tmpFiles = [];
    }

    /**
     * Crée un .xlsx temporaire avec les en-têtes et les lignes fournies.
     */
    private function makeXlsx(array $headers, array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headers, null, 'A1');
        if (!empty($rows)) {
            $sheet->fromArray($rows, null, 'A2');
        }

        $path = tempnam(sys_get_temp_dir(), 'import_test_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();

        $this->tmpFiles[] = $path;

        return $path;
    }

    /**
     * Crée un .xlsx temporaire dont la colonne « code_barres » (A) porte un
     * format de date « m/d/yyyy » — le cas réel rencontré sur les fichiers
     * fournisseurs : Excel convertit automatiquement un code-barres à 13
     * chiffres en date.
     *
     * @param array<int, array<int, mixed>> $rows
     */
    private function makeXlsxWithDateFormattedBarcode(array $headers, array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headers, null, 'A1');
        if (!empty($rows)) {
            $sheet->fromArray($rows, null, 'A2');
        }
        $sheet->getStyle('A:A')->getNumberFormat()->setFormatCode('m/d/yyyy');

        $path = tempnam(sys_get_temp_dir(), 'import_test_') . '.xlsx';
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
        $path = tempnam(sys_get_temp_dir(), 'import_test_') . '.csv';
        file_put_contents($path, $content);
        $this->tmpFiles[] = $path;

        return $path;
    }

    /**
     * Construit une cellule brute (structure utilisée par le service).
     */
    private function cell($value): array
    {
        return [
            'value' => $value,
            'formatted' => $value === null ? '' : trim((string)$value),
            'is_date' => false,
        ];
    }

    /**
     * Appelle une méthode privée du service (pour tester le « pur »).
     */
    private function callPrivate(ProductImportService $service, string $method, array $args = [])
    {
        $reflection = new \ReflectionMethod($service, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($service, $args);
    }

    // MARK: Normalisation des en-têtes

    public function testLesEnTetesSontNormalisesInsensiblementAuxAccentsEtALaCasse(): void
    {
        $service = new ProductImportService();

        $this->assertSame('codebarres', $this->callPrivate($service, 'normalizeHeader', ['Code-Barres']));
        $this->assertSame('codebarres', $this->callPrivate($service, 'normalizeHeader', ['CODE BARRES']));
        $this->assertSame('stockminimum', $this->callPrivate($service, 'normalizeHeader', ['Stock Minimum']));
        $this->assertSame('categorie', $this->callPrivate($service, 'normalizeHeader', ['Catégorié']));
        $this->assertSame('nomduproduit', $this->callPrivate($service, 'normalizeHeader', ['Nom du Produit']));
        $this->assertSame('', $this->callPrivate($service, 'normalizeHeader', ['   ']));
    }

    public function testLesAliasDEntetesSontReconus(): void
    {
        $service = new ProductImportService();

        $map = $this->callPrivate($service, 'mapHeaders', [[
            'Code-barres', 'Designation', 'Famille', 'Prix de vente',
            'Quantite', 'Seuil', 'Groupe de taxe', 'Type de vente',
        ]]);

        $this->assertSame(1, $map['code_barres']);
        $this->assertSame(2, $map['nom']);
        $this->assertSame(3, $map['categorie']);
        $this->assertSame(4, $map['prix']);
        $this->assertSame(5, $map['stock']);
        $this->assertSame(6, $map['stock_minimum']);
        $this->assertSame(7, $map['taxe']);
        $this->assertSame(8, $map['product_type']);
    }

    public function testUneColonneInconnueEstIgnoree(): void
    {
        $service = new ProductImportService();

        $map = $this->callPrivate($service, 'mapHeaders', [['Nom', 'Colonne inconnue', 'Prix']]);

        $this->assertArrayNotHasKey('inconnu', $map);
        $this->assertSame(1, $map['nom']);
        $this->assertSame(3, $map['prix']);
    }

    /**
     * Régression : chaque nom de colonne canonique (celui du modèle Excel)
     * doit figurer dans sa propre liste d'alias. Sinon la valeur est
     * silencieusement ignorée et le défaut est appliqué à la place.
     */
    public function testChaqueNomDeColonneCanoniqueEstReconnu(): void
    {
        $service = new ProductImportService();

        $reflection = new \ReflectionClass($service);
        $aliases = $reflection->getConstant('HEADER_ALIASES');

        foreach (array_keys($aliases) as $column) {
            $map = $this->callPrivate($service, 'mapHeaders', [[$column]]);

            $this->assertArrayHasKey(
                $column,
                $map,
                sprintf('La colonne « %s » doit être reconnue par son propre nom.', $column)
            );
        }
    }

    // MARK: Fusion du map code-barres → id (régression « Duplicate entry »)

    /**
     * RÉGRESSION — « Duplicate entry '3017620000036' for key
     * 'produits.code_barres' » sur un import en mode « mise à jour ».
     *
     * ⚠️ La cause : le map code-barres → id est fusionné avec
     *    `array_merge()`. Or PHP convertit une clé « chaîne numérique »
     *    en entier, et `array_merge()` RÉINDEXE les clés numériques : le
     *    map perdait TOUTES ses clés.
     *
     *    Le lookup rendait donc `null` pour des produits PRÉSENTS en base,
     *    et l'import tentait un INSERT → violation de l'UNIQUE.
     *
     *    Ce test passe avec des code-barres ALPHANUMÉRIQUES (les anciens
     *    tests) et échouait avec des code-barres NUMÉRIQUES (les vrais
     *    EAN-13) : c'est exactement l'écart qui a masqué le bug.
     */
    public function testLaFusionDuMapConserveLesClesNumeriquesDesCodeBarres(): void
    {
        // Un code-barres numérique : PHP le caste en clé INT.
        $codeBarres = '3017620000036';

        // Ce que `findIdsByBarcodes()` renvoie : code-barres → id.
        $lookup = [$codeBarres => 358];

        // ⚠️ `array_merge()` détruirait la clé (il réindexe) → le lookup
        //    rendrait `null` → INSERT → « Duplicate entry ».
        $avecMerge = array_merge([], $lookup);
        $avecReplace = array_replace([], $lookup);

        $this->assertArrayHasKey(
            $codeBarres,
            $avecReplace,
            'La fusion doit préserver la clé du code-barres (array_replace).'
        );
        $this->assertSame(358, $avecReplace[$codeBarres]);

        // On documente l'écart : c'est exactement ce qui causait le bug.
        $this->assertArrayNotHasKey(
            $codeBarres,
            $avecMerge,
            'array_merge() réindexe : c\'est la cause du bug « Duplicate entry ».'
        );
    }

    /**
     * La fusion successive de plusieurs blocs de 500 doit conserver
     * l'ENSEMBLE des code-barres, pas seulement ceux du dernier bloc.
     *
     * ⚠️ C'est le cas réel : l'import par blocs fusionne le lookup de
     *    chaque bloc. Un `array_merge()` perdait les clés numériques des
     *    blocs précédents.
     */
    public function testLaFusionSuccessiveDeBlocsConserveTousLesCodeBarres(): void
    {
        $bloc1 = ['3017620000010' => 356, '3017620000028' => 357];
        $bloc2 = ['3017620000036' => 358, '3017620000044' => 359];

        $fusion = array_replace($bloc1, $bloc2);

        $this->assertCount(4, $fusion);
        foreach ($bloc1 + $bloc2 as $codeBarres => $id) {
            $this->assertArrayHasKey($codeBarres, $fusion, 'Code-barres perdu à la fusion.');
            $this->assertSame($id, $fusion[$codeBarres], 'Id incohérent après fusion.');
        }
    }

    // MARK: Conversion des valeurs

    public function testLesNombresAcceptentLesFormatsFrancaisEtAnglais(): void
    {
        $service = new ProductImportService();

        $read = function ($raw) use ($service) {
            return $this->callPrivate($service, 'readNumber', [['prix' => $this->cell($raw)], 'prix']);
        };

        $this->assertSame(1500.5, $read('1500,50'));
        $this->assertSame(1500.5, $read('1500.50'));
        $this->assertSame(1500.5, $read('1 500,50'));
        $this->assertSame(1500.5, $read('1,500.50'));
        $this->assertSame(800.0, $read('800'));
        $this->assertSame(800.0, $read(800));
        $this->assertSame(800.0, $read('800 Fc'));
        $this->assertSame(12.5, $read('12,5%'));
        $this->assertNull($read(''));
        $this->assertNull($read('abc'));
    }

    public function testLesDatesAcceptentPlusieursFormats(): void
    {
        $service = new ProductImportService();

        $this->assertSame('2026-07-30', $this->callPrivate($service, 'normalizeDateString', ['2026-07-30']));
        $this->assertSame('2026-07-30', $this->callPrivate($service, 'normalizeDateString', ['30/07/2026']));
        $this->assertSame('2026-07-30', $this->callPrivate($service, 'normalizeDateString', ['30-07-2026']));
        $this->assertNull($this->callPrivate($service, 'normalizeDateString', ['']));
    }

    public function testUneLigneVideEstDetectee(): void
    {
        $service = new ProductImportService();

        $this->assertFalse($this->callPrivate($service, 'rowHasData', [['', '   ', null]]));
        $this->assertTrue($this->callPrivate($service, 'rowHasData', [['', 'Lait', null]]));
    }

    // MARK: Code-barres (régression : format « date » appliqué par Excel)

    /**
     * RÉGRESSION : un code-barres à 13 chiffres est automatiquement formaté en
     * date par Excel (m/d/yyyy). L'import lisait la valeur AFFICHÉE
     * (« 5/17/23070765319 ») au lieu de la valeur RÉELLE
     * (« 8426423308202 »).
     */
    public function testUnCodeBarresFormatEnDateParExcelEstLuDansSaValeurReelle(): void
    {
        $path = $this->makeXlsxWithDateFormattedBarcode(
            ['code_barres', 'nom', 'categorie', 'prix'],
            [[8426423308202, 'MOLTEX ADVANCED MEDIA PEMPERS 80`S', 'Comestible', '800']]
        );

        $service = new ProductImportService();
        $rows = $this->callPrivate($service, 'readRows', [$path]);

        $this->assertCount(1, $rows);

        $barcode = $this->callPrivate($service, 'readBarcode', [$rows[0]['cells'], 'code_barres']);

        $this->assertSame('8426423308202', $barcode);
        $this->assertStringNotContainsString('/', $barcode);
    }

    /**
     * Le code-barres brut doit être conservé tel quel, quel que soit son
     * type de cellule (texte, entier, flottant).
     */
    public function testLeCodeBarresBrutEstConserveQuelQueSoitLeTypeDeCellule(): void
    {
        $service = new ProductImportService();

        $read = function ($value) use ($service) {
            $cell = ['value' => $value, 'formatted' => 'FORMATE', 'is_date' => false];

            return $this->callPrivate($service, 'readBarcode', [['code_barres' => $cell], 'code_barres']);
        };

        // Texte : conservé tel quel (zéros initiaux préservés).
        $this->assertSame('051111407592', $read('051111407592'));
        $this->assertSame('ALMARAL1', $read('ALMARAL1'));

        // Entier : valeur exacte, sans séparateur de milliers.
        $this->assertSame('8426423308202', $read(8426423308202));

        // Flottant : valeur exacte, sans notation scientifique.
        $this->assertSame('8426423308202', $read(8426423308202.0));

        // Cellule vide : chaîne vide (un code-barres sera alors généré).
        $this->assertSame('', $read(null));
    }

    /**
     * Un code-barres en texte (avec zéros initiaux) ne doit jamais être
     * converti en nombre par l'import.
     */
    public function testUnCodeBarresTexteConserveSesZerosInitiaux(): void
    {
        $path = $this->makeXlsx(
            ['code_barres', 'nom', 'categorie', 'prix'],
            [['051111407592', 'Produit', 'Comestible', '800']]
        );

        $service = new ProductImportService();
        $rows = $this->callPrivate($service, 'readRows', [$path]);

        $barcode = $this->callPrivate($service, 'readBarcode', [$rows[0]['cells'], 'code_barres']);

        $this->assertSame('051111407592', $barcode);
    }

    /**
     * RÉGRESSION : une catégorie « 04 » dans une cellule au format date
     * s'affiche « 1/4/1900 ». La valeur BRUTE doit être utilisée, sinon
     * l'import échoue avec « Catégorie "1/4/1900" introuvable ».
     */
    public function testUneCategorieAuFormatDateEstLueDansSaValeurReelle(): void
    {
        $path = $this->makeXlsxWithDateFormattedBarcode(
            ['code_barres', 'nom', 'categorie', 'prix'],
            [['ALMARAL1', 'CUP SAUCE', '04', '800']]
        );

        $service = new ProductImportService();
        $rows = $this->callPrivate($service, 'readRows', [$path]);

        $categorie = $this->callPrivate($service, 'readString', [$rows[0]['cells'], 'categorie']);

        $this->assertSame('04', $categorie);
        $this->assertStringNotContainsString('/', $categorie);
    }

    /**
     * La valeur brute d'une cellule texte prime sur sa valeur affichée.
     */
    public function testLaValeurBrutePrimeSurLaValeurAffichee(): void
    {
        $service = new ProductImportService();

        $read = function ($value, $formatted) use ($service) {
            $cell = ['value' => $value, 'formatted' => $formatted, 'is_date' => true];

            return $this->callPrivate($service, 'readString', [['categorie' => $cell], 'categorie']);
        };

        // Texte : la valeur brute (« 04 ») prime sur l'affichage (« 1/4/1900 »).
        $this->assertSame('04', $read('04', '1/4/1900'));

        // Entier : valeur exacte.
        $this->assertSame('42', $read(42, '1/2/1900'));

        // Flottant entier : valeur exacte, sans « .00 ».
        $this->assertSame('42', $read(42.0, '1/2/1900'));

        // Formule : la valeur affichée est utilisée (le brut est l'expression).
        $this->assertSame('1/4/1900', $read('=A1', '1/4/1900'));

        // Cellule vide : chaîne vide.
        $this->assertSame('', $read(null, ''));
    }

    /**
     * Le modèle Excel doit imposer le format TEXTE à la colonne
     * « code_barres » (sinon Excel reformate les codes-barres en dates).
     */
    public function testLeModeleImposeLeFormatTexteALaColonneCodeBarres(): void
    {
        $service = new ProductImportService();

        ob_start();
        $service->sendTemplate();
        $binary = ob_get_clean();

        $path = tempnam(sys_get_temp_dir(), 'modele_test_') . '.xlsx';
        file_put_contents($path, $binary);
        $this->tmpFiles[] = $path;

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();

        $this->assertSame(
            '@',
            $sheet->getStyle('A:A')->getNumberFormat()->getFormatCode(),
            'La colonne « code_barres » doit être en format TEXTE.'
        );

        // L'exemple doit rester un texte (et non un nombre).
        $cell = $sheet->getCell('A2');
        $this->assertSame('5400141352573', (string)$cell->getValue());
        $this->assertSame('s', $cell->getDataType());

        $spreadsheet->disconnectWorksheets();
    }

    // MARK: Lecture des fichiers

    public function testUnFichierXlsxEstLuEtLesEnTetesSontDetectes(): void
    {
        $path = $this->makeXlsx(
            ['code_barres', 'nom', 'categorie', 'prix'],
            [
                ['5400141352573', 'Lait Frais 1L', 'Comestible', '800'],
                ['5400141352574', 'Pain', 'Comestible', '200'],
            ]
        );

        $service = new ProductImportService();
        $rows = $this->callPrivate($service, 'readRows', [$path]);

        $this->assertCount(2, $rows);
        $this->assertSame(2, $rows[0]['line']);
        $this->assertSame(3, $rows[1]['line']);
        $this->assertArrayHasKey('nom', $rows[0]['cells']);
        $this->assertArrayHasKey('prix', $rows[0]['cells']);
    }

    public function testUnFichierCsvEstLu(): void
    {
        $path = $this->makeCsv("code_barres;nom;categorie;prix\n123;Savon;Comestible;500\n");

        $service = new ProductImportService();
        $rows = $this->callPrivate($service, 'readRows', [$path]);

        $this->assertCount(1, $rows);
        $this->assertSame(2, $rows[0]['line']);
    }

    public function testUnFichierVideEstRefuse(): void
    {
        $path = $this->makeCsv('');

        $service = new ProductImportService();
        $result = $service->importFile(null, $path);

        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['error']);
    }

    public function testUnFichierInexistantEstRefuse(): void
    {
        $service = new ProductImportService();
        $result = $service->importFile(null, '/chemin/qui/nexiste/pas.xlsx');

        $this->assertFalse($result['success']);
        $this->assertSame('Fichier illisible', $result['error']);
    }

    /**
     * Le modèle .xlsx doit être un vrai fichier Excel (entête « PK » du ZIP)
     * et contenir les en-têtes attendus.
     */
    public function testLeModeleExcelEstUnFichierValide(): void
    {
        $service = new ProductImportService();

        ob_start();
        $service->sendTemplate();
        $binary = ob_get_clean();

        $this->assertNotEmpty($binary, 'Le modèle ne doit pas être vide.');
        $this->assertSame('PK', substr($binary, 0, 2), 'Le modèle doit être un fichier ZIP/OOXML valide.');

        // Vérifie que le fichier s'ouvre et contient les bons en-têtes.
        $path = tempnam(sys_get_temp_dir(), 'modele_test_') . '.xlsx';
        file_put_contents($path, $binary);
        $this->tmpFiles[] = $path;

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();

        $headers = $sheet->rangeToArray('A1:N1')[0];
        $this->assertSame('code_barres', $headers[0]);
        $this->assertSame('nom', $headers[1]);
        $this->assertSame('categorie', $headers[2]);
        $this->assertSame('prix', $headers[3]);

        // Un onglet « Aide » doit documenter les colonnes.
        $this->assertSame('Aide', $spreadsheet->getSheet(1)->getTitle());

        $spreadsheet->disconnectWorksheets();
    }
}
