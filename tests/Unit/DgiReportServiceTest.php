<?php

namespace Tests\Unit;

use App\Exceptions\DgiReportException;
use App\Services\DgiReportService;
use PHPUnit\Framework\TestCase;

/**
 * Tests purs du client des rapports DGI/OSAT.
 *
 * Aucun appel réseau n'est effectué : le transport HTTP est simulé.
 */
class DgiReportServiceTest extends TestCase
{
    private array $samplePayload;

    protected function setUp(): void
    {
        $path = dirname(__DIR__) . '/Fixtures/dgi_report_sample.json';
        $decoded = json_decode((string)file_get_contents($path), true);
        $this->assertIsArray($decoded, 'La fixture DGI doit contenir un JSON valide.');
        $this->samplePayload = $decoded;
    }

    public function testLUrlProviderUtiliseLeFormatAttendu(): void
    {
        $service = new DgiReportService();

        $url = $service->buildProviderUrl(
            'A1720894F',
            '2026-10-07 00:00:00',
            '2026-11-07 23:59:59'
        );

        $this->assertStringContainsString('nif=A1720894F', $url);
        // Les dates sont converties en UTC (Kinshasa = UTC+1) : la DGI
        // horodate ses factures en UTC.
        $this->assertStringContainsString('debut=06/10/2026%2023:00:00', $url);
        $this->assertStringContainsString('fin=07/11/2026%2022:59:59', $url);
    }

    public function testLesDatesSontConvertiesEnUtc(): void
    {
        $service = new DgiReportService();

        // Kinshasa = UTC+1 : 17:30:50 local = 16:30:50 UTC.
        [$utc, $offset] = $service->toProviderUtc('2026-11-10 17:30:50');
        $this->assertSame('10/11/2026 16:30:50', $utc);
        $this->assertSame(3600, $offset);

        // Une date à minuit local bascule sur la veille en UTC.
        [$utcMinuit] = $service->toProviderUtc('2026-11-10 00:00:00');
        $this->assertSame('09/11/2026 23:00:00', $utcMinuit);
    }

    public function testLeTokenEstAjouteALUrlProvider(): void
    {
        $service = new DgiReportService();

        $url = $service->buildProviderUrl(
            'A1720894F',
            '2026-10-07 00:00:00',
            '2026-11-07 23:59:59',
            'TOKEN-SECRET'
        );

        $this->assertStringContainsString('token=TOKEN-SECRET', $url);
    }

    public function testUneUrlSansTokenNAjoutePasLeParametre(): void
    {
        $service = new DgiReportService();

        $url = $service->buildProviderUrl(
            'A1720894F',
            '2026-10-07 00:00:00',
            '2026-11-07 23:59:59',
            ''
        );

        $this->assertStringNotContainsString('token=', $url);
    }

    /**
     * Le fuseau source de la période est convertible : le provider reçoit
     * toujours de l'UTC, mais les bornes sont calculées dans le fuseau
     * demandé (serveur +1 pour le tableau de bord).
     */
    public function testLeFuseauSourceEstAppliqueALaConversionUtc(): void
    {
        $service = new DgiReportService();
        $berlin = new \DateTimeZone('Europe/Berlin'); // UTC+1

        $url = $service->buildProviderUrl(
            'A1720894F',
            '2026-11-10 00:00:00',
            '2026-11-10 23:59:59',
            '',
            $berlin
        );

        // Minuit Berlin (UTC+1) = 23:00 UTC de la veille.
        $this->assertStringContainsString('debut=09/11/2026%2023:00:00', $url);
        // 23:59:59 Berlin (UTC+1) = 22:59:59 UTC.
        $this->assertStringContainsString('fin=10/11/2026%2022:59:59', $url);
    }

    public function testLeFuseauDeRequeteParDefautEstCeluiDuServeur(): void
    {
        $service = new DgiReportService();

        $this->assertSame(
            date_default_timezone_get(),
            $service->getRequestTimezone()->getName(),
            'Le tableau de bord doit suivre l\'horloge du serveur.'
        );
    }

    public function testLePayloadRealEstNormaliseSansPerteDeTotaux(): void
    {
        $service = new DgiReportService(
            fn (string $url) => [
                'status' => 200,
                'body' => json_encode($this->samplePayload),
            ]
        );

        $report = $service->getReport(
            'A1720894F',
            '2026-10-07 00:00:00',
            '2026-11-07 23:59:59'
        );

        $this->assertEqualsWithDelta(57090793.26, $report['totals']['ht'], 0.001);
        $this->assertEqualsWithDelta(9252760.95, $report['totals']['tva'], 0.001);
        $this->assertEqualsWithDelta(5264859.0, $report['totals']['ts_total'], 0.001);
        $this->assertEqualsWithDelta(128507893.21, $report['totals']['ttc'], 0.001);
        $this->assertSame(163, $report['totals']['invoice_count']);
        $this->assertEqualsWithDelta(72810.0, $report['totals']['discount_total'], 0.001);
        $this->assertSame([], $report['warnings']);
    }

    public function testLesTypesDeFactureEtLesValeursNegativesSontConserves(): void
    {
        $service = new DgiReportService();
        $report = $service->normalizePayload($this->samplePayload);

        $byType = [];
        foreach ($report['by_invoice_type'] as $invoiceType) {
            $byType[$invoiceType['type']] = $invoiceType;
        }

        $this->assertArrayHasKey('FA', $byType);
        $this->assertArrayHasKey('FT', $byType);
        $this->assertArrayHasKey('FV', $byType);
        $this->assertSame("Facture d'avoir", $byType['FA']['label']);
        $this->assertEqualsWithDelta(-510477.68, $byType['FA']['totals']['ht'], 0.001);
        $this->assertEqualsWithDelta(-592151.0, $byType['FA']['totals']['ttc'], 0.001);
        $this->assertSame(7, $byType['FA']['totals']['invoice_count']);
        $this->assertSame(155, $byType['FV']['totals']['invoice_count']);
    }

    public function testLesCategoriesFiscalesSontMappéesEtLesAbsentesVautentZero(): void
    {
        $service = new DgiReportService();
        $report = $service->normalizePayload($this->samplePayload);

        $groups = [];
        foreach ($report['tax_groups'] as $group) {
            $groups[$group['code']] = $group;
        }

        $this->assertCount(16, $groups);
        $this->assertSame('Taxable', $groups['B']['label']);
        $this->assertEqualsWithDelta(16.0, $groups['B']['rate'], 0.001);
        $this->assertEqualsWithDelta(57001241.18, $groups['B']['amount'], 0.001);
        $this->assertEqualsWithDelta(9241456.03, $groups['B']['tva'], 0.001);
        $this->assertEqualsWithDelta(757859.0, $groups['B']['ts'], 0.001);
        $this->assertEqualsWithDelta(0.0, $groups['D']['amount'], 0.001);
    }

    public function testLesCategoriesSontCoherentesAvecLesTotaux(): void
    {
        // Relations stables sur toutes les périodes observées :
        // somme(ha) = TTC et somme(ts) = TS_TOTAL.
        //
        // ⚠️ La somme des TVA par catégorie ne correspond PAS au total TVA
        // (écart de 6 800 sur le payload de référence) : ce n'est pas une
        // relation contrôlée.
        $service = new DgiReportService();
        $report = $service->normalizePayload($this->samplePayload);

        $sumHa = 0.0;
        $sumTs = 0.0;
        foreach ($report['tax_groups'] as $group) {
            $sumHa += (float)$group['amount'];
            $sumTs += (float)$group['ts'];
        }

        $this->assertEqualsWithDelta($report['totals']['ttc'], $sumHa, 0.01);
        $this->assertEqualsWithDelta($report['totals']['ts_total'], $sumTs, 0.01);
        $this->assertSame([], $report['warnings']);
    }

    public function testUneIncoherenceCategorieTtcProduitUnAvertissement(): void
    {
        $payload = $this->samplePayload;
        $payload['data_final']['TTC'] = 1.0;

        $service = new DgiReportService();
        $report = $service->normalizePayload($payload);

        $this->assertNotEmpty($report['warnings']);
        $this->assertStringContainsString(
            'diffère du TTC',
            implode(' ', $report['warnings'])
        );
    }

    public function testLesModesDePaiementSontNormalisesEtTries(): void
    {
        $service = new DgiReportService();
        $report = $service->normalizePayload($this->samplePayload);

        $this->assertCount(6, $report['payments']);

        // Trié par montant décroissant : les espèces en premier.
        $this->assertSame('ESPECES', $report['payments'][0]['code']);
        $this->assertSame('Espèces', $report['payments'][0]['label']);
        $this->assertEqualsWithDelta(152339676.26, $report['payments'][0]['total'], 0.001);

        $codes = array_column($report['payments'], 'code');
        $this->assertContains('CARTEBANCAIRE', $codes);
        $this->assertContains('MOBILEMONEY', $codes);
        $this->assertContains('VIREMENT', $codes);
    }

    public function testLesPaiementsSontPresentsParTypeDeFacture(): void
    {
        $service = new DgiReportService();
        $report = $service->normalizePayload($this->samplePayload);

        $byType = [];
        foreach ($report['by_invoice_type'] as $invoiceType) {
            $byType[$invoiceType['type']] = $invoiceType;
        }

        $this->assertCount(3, $byType['FA']['payments']);
        // Trié par montant décroissant : le virement (500 000) précède la carte.
        $this->assertSame('VIREMENT', $byType['FA']['payments'][0]['code']);
        $this->assertCount(1, $byType['FT']['payments']);
        $this->assertSame('CREDIT', $byType['FT']['payments'][0]['code']);
        $this->assertCount(6, $byType['FV']['payments']);
    }

    public function testLesPaiementsSontRecalculesQuandUnTypeEstFiltre(): void
    {
        $service = new DgiReportService();
        $report = $service->normalizePayload($this->samplePayload, ['FA']);

        $this->assertCount(3, $report['payments']);
        $this->assertSame('VIREMENT', $report['payments'][0]['code']);
        $this->assertEqualsWithDelta(500000.0, $report['payments'][0]['total'], 0.001);
    }

    public function testUneIncoherenceDePaiementProduitUnAvertissement(): void
    {
        $payload = $this->samplePayload;
        $payload['data_par_type']['FV']['paiements']['ESPECES'] = 100.0;

        $service = new DgiReportService();
        $report = $service->normalizePayload($payload);

        $this->assertNotEmpty($report['warnings']);
        $this->assertStringContainsString(
            'paiements Espèces',
            implode(' ', $report['warnings'])
        );
    }

    public function testLeFiltreParTypeNeConserveQueLesTypesDemandes(): void
    {
        $service = new DgiReportService();
        $report = $service->normalizePayload($this->samplePayload, ['FV']);

        $types = array_column($report['by_invoice_type'], 'type');
        $this->assertSame(['FV'], $types);
        $this->assertTrue($report['filter']['filtered']);
        $this->assertSame(['FV'], $report['filter']['types']);
    }

    public function testLeFiltreParTypeRecalculeLesTotauxGlobaux(): void
    {
        $service = new DgiReportService();
        $report = $service->normalizePayload($this->samplePayload, ['FV']);

        // Les totaux doivent correspondre au type FV seul, pas à l'ensemble.
        $this->assertEqualsWithDelta(57601265.99, $report['totals']['ht'], 0.001);
        $this->assertEqualsWithDelta(9334434.22, $report['totals']['tva'], 0.001);
        $this->assertEqualsWithDelta(129100039.21, $report['totals']['ttc'], 0.001);
        $this->assertSame(155, $report['totals']['invoice_count']);
        $this->assertEqualsWithDelta(72310.0, $report['totals']['discount_total'], 0.001);
        $this->assertSame([], $report['warnings']);
    }

    public function testLeFiltreParTypeIgnoreLesTypesInconnus(): void
    {
        $service = new DgiReportService();
        $report = $service->normalizePayload($this->samplePayload, ['FV', 'XX', 'fv']);

        $types = array_column($report['by_invoice_type'], 'type');
        $this->assertSame(['FV'], $types);
    }

    public function testAucunFiltreConserveTousLesTypes(): void
    {
        $service = new DgiReportService();
        $report = $service->normalizePayload($this->samplePayload);

        $this->assertCount(3, $report['by_invoice_type']);
        $this->assertFalse($report['filter']['filtered']);
    }

    public function testUneIncoherenceDeSommeProduitUnAvertissement(): void
    {
        $payload = $this->samplePayload;
        $payload['data_par_type']['FV']['nombre_factures_traitees'] = 154;

        $service = new DgiReportService();
        $report = $service->normalizePayload($payload);

        $this->assertNotEmpty($report['warnings']);
        $this->assertStringContainsString(
            'nombre de factures',
            implode(' ', $report['warnings'])
        );
    }

    public function testUneReponseHttpEnErreurEstRejetee(): void
    {
        $service = new DgiReportService(
            fn (string $url) => ['status' => 500, 'body' => 'server error']
        );

        try {
            $service->getReport('A1720894F', '2026-10-07 00:00:00', '2026-11-07 23:59:59');
            $this->fail('Une réponse HTTP en erreur doit être rejetée.');
        } catch (DgiReportException $e) {
            $this->assertSame('dgi_provider_http_error', $e->getErrorCode());
            $this->assertSame(502, $e->getHttpStatus());
            $this->assertStringNotContainsString('server error', $e->getMessage());
        }
    }

    public function testUnJsonInvalideEstRejete(): void
    {
        $service = new DgiReportService(
            fn (string $url) => ['status' => 200, 'body' => '<html>error</html>']
        );

        try {
            $service->getReport('A1720894F', '2026-10-07 00:00:00', '2026-11-07 23:59:59');
            $this->fail('Un JSON invalide doit être rejeté.');
        } catch (DgiReportException $e) {
            $this->assertSame('dgi_provider_invalid_json', $e->getErrorCode());
            $this->assertSame(502, $e->getHttpStatus());
        }
    }

    public function testUnSchemaInattenduEstRejete(): void
    {
        $service = new DgiReportService();

        $this->expectException(DgiReportException::class);
        $this->expectExceptionMessage('format du rapport DGI');
        $service->normalizePayload(['unexpected' => true]);
    }

    public function testUneDateInvalideEstRejetee(): void
    {
        $service = new DgiReportService();

        $this->expectException(DgiReportException::class);
        $this->expectExceptionMessage('période du rapport DGI');
        $service->formatProviderDate('2026-02-31 00:00:00');
    }

    public function testUnIsfVideEstRejeteAvantLAppelHttp(): void
    {
        $called = false;
        $service = new DgiReportService(function (string $url) use (&$called) {
            $called = true;
            return ['status' => 200, 'body' => '{}'];
        });

        try {
            $service->getReport('  ', '2026-10-07 00:00:00', '2026-11-07 23:59:59');
            $this->fail('Un ISF vide doit être rejeté.');
        } catch (DgiReportException $e) {
            $this->assertSame('dgi_report_missing_isf', $e->getErrorCode());
            $this->assertSame(422, $e->getHttpStatus());
            $this->assertFalse($called);
        }
    }
}
