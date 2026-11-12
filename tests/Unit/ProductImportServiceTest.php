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
