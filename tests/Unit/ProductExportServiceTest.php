<?php

namespace Tests\Unit;

use App\Services\ProductExportService;
use App\Services\ProductImportService;
use PHPUnit\Framework\TestCase;

/**
 * Tests du service d'export produits (ProductExportService).
 *
 * Point central : les en-têtes produits par l'export doivent être
 * EXACTEMENT ceux acceptés par l'import. C'est ce qui permet le
 * round-trip « export → modification → ré-import ».
 */
class ProductExportServiceTest extends TestCase
{
    /**
     * Appelle une méthode privée du service.
     */
    private function callPrivate($service, string $method, array $args = [])
    {
        $reflection = new \ReflectionMethod($service, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($service, $args);
    }

    /**
     * Les en-têtes de l'export doivent être reconnus tels quels par l'import.
     */
    public function testLesEntetesExportesSontReconnusParLImport(): void
    {
        $export = new ProductExportService();
        $import = new ProductImportService();

        $headers = $this->callPrivate($export, 'buildHeaders');

        $this->assertNotEmpty($headers, 'L\'export doit définir des en-têtes.');

        foreach ($headers as $header) {
            $map = $this->callPrivate($import, 'mapHeaders', [[$header]]);

            $this->assertNotEmpty(
                $map,
                sprintf('La colonne exportée « %s » doit être reconnue par l\'import.', $header)
            );
        }
    }

    /**
     * Les en-têtes exportés doivent être EXACTEMENT les mêmes que ceux du
     * modèle d'import (ordre compris) : c'est le contrat du round-trip.
     */
    public function testLesEntetesExportesSontIdentiquesAuModeleDImport(): void
    {
        $export = new ProductExportService();
        $import = new ProductImportService();

        $exportHeaders = $this->callPrivate($export, 'buildHeaders');

        $importReflection = new \ReflectionClass($import);
        $importAliases = $importReflection->getConstant('HEADER_ALIASES');
        $importHeaders = array_keys($importAliases);

        $this->assertSame(
            $importHeaders,
            $exportHeaders,
            'L\'export doit produire exactement les colonnes du modèle d\'import.'
        );
    }

    /**
     * Le nom de fichier reflète les filtres appliqués.
     */
    public function testLeNomDeFichierRefleteLesFiltres(): void
    {
        $service = new ProductExportService();

        $noFilter = $this->callPrivate($service, 'buildFilename', [[]]);
        $this->assertStringStartsWith('produits_', $noFilter);
        $this->assertStringEndsWith('.xlsx', $noFilter);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}/', $noFilter);

        $withCategory = $this->callPrivate($service, 'buildFilename', [[
            'category' => 'Boissons',
        ]]);
        $this->assertStringContainsString('boissons', $withCategory);

        $withSearch = $this->callPrivate($service, 'buildFilename', [[
            'search' => 'Lait Frais',
        ]]);
        $this->assertStringContainsString('lait_frais', $withSearch);

        // Un filtre « all » ne doit pas polluer le nom.
        $allCategory = $this->callPrivate($service, 'buildFilename', [[
            'category' => 'all',
        ]]);
        $this->assertSame($noFilter, $allCategory);
    }

    /**
     * Les caractères spéciaux sont nettoyés dans le nom de fichier.
     */
    public function testLeNomDeFichierEstNettoye(): void
    {
        $service = new ProductExportService();

        $name = $this->callPrivate($service, 'buildFilename', [[
            'search' => 'Café / Crème & Co ???',
        ]]);

        // Ni accent, ni espace, ni caractère special (le tiret des dates est OK).
        $this->assertStringContainsString('cafe_creme_co', $name);
        $this->assertDoesNotMatchRegularExpression('/[^a-z0-9_.-]/', $name);
        $this->assertStringNotContainsString(' ', $name);
    }
}
