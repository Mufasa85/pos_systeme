<?php

namespace Tests\Unit;

use App\Exceptions\DgiReportException;
use App\Services\DgiDashboardService;
use App\Services\DgiReportService;
use PHPUnit\Framework\TestCase;

/**
 * Tests purs du service d'agrégats DGI du tableau de bord.
 *
 * Aucun appel réseau n'est effectué : le transport HTTP est simulé.
 */
class DgiDashboardServiceTest extends TestCase
{
    private array $samplePayload;

    protected function setUp(): void
    {
        $path = dirname(__DIR__) . '/Fixtures/dgi_report_sample.json';
        $decoded = json_decode((string)file_get_contents($path), true);
        $this->assertIsArray($decoded, 'La fixture DGI doit contenir un JSON valide.');
        $this->samplePayload = $decoded;
    }

    /**
     * Service dont le transport renvoie la fixture pour toute requête.
     */
    private function serviceAvecTransport(?callable $onUrl = null): DgiDashboardService
    {
        $transport = function (string $url) use ($onUrl): array {
            if ($onUrl !== null) {
                $onUrl($url);
            }
            return [
                'status' => 200,
                'body' => json_encode($this->samplePayload),
            ];
        };

        return new DgiDashboardService(new DgiReportService($transport));
    }

    public function testLesTroisPeriodesSontInterrogees(): void
    {
        $urls = [];
        $service = $this->serviceAvecTransport(function (string $url) use (&$urls): void {
            $urls[] = $url;
        });

        $stats = $service->getStats(1, 'A1720894F');

        $this->assertCount(3, $urls, 'Trois periodes doivent etre demandees (jour, semaine, mois).');
        $this->assertArrayHasKey('day', $stats['periods']);
        $this->assertArrayHasKey('week', $stats['periods']);
        $this->assertArrayHasKey('month', $stats['periods']);
    }

    public function testLesPeriodesUtilisentLesBornesAttendues(): void
    {
        $service = $this->serviceAvecTransport();

        $stats = $service->getStats(1, 'A1720894F');
        $periods = $stats['meta']['periods'];

        // Le jour : de minuit à la fin de la journée.
        $this->assertSame('00:00:00', substr($periods['day']['start'], 11));
        $this->assertSame('23:59:59', substr($periods['day']['end'], 11));

        // La semaine : 7 jours glissants.
        $this->assertSame(
            date('Y-m-d', strtotime($periods['day']['start'] . ' -6 days')),
            substr($periods['week']['start'], 0, 10)
        );

        // Le mois : du 1er au dernier jour.
        $this->assertSame('01', substr($periods['month']['start'], 8, 2));
        $this->assertSame(
            date('t', strtotime($periods['month']['start'])),
            substr($periods['month']['end'], 8, 2)
        );
    }

    public function testLesTotauxSontExposesPourChaquePeriode(): void
    {
        $service = $this->serviceAvecTransport();

        $stats = $service->getStats(1, 'A1720894F');

        foreach (['day', 'week', 'month'] as $key) {
            $period = $stats['periods'][$key];
            $this->assertArrayHasKey('ht', $period);
            $this->assertArrayHasKey('ttc', $period);
            $this->assertArrayHasKey('invoice_count', $period);
            $this->assertNull($period['error'], 'Aucune erreur attendue sur une reponse valide.');
            $this->assertGreaterThan(0, $period['ttc']);
        }
    }

    public function testLesBoutiquesSontAdditionnees(): void
    {
        $service = $this->serviceAvecTransport();

        $uneBoutique = $service->getStats(1, 'A1720894F');
        $deuxBoutiques = $service->getStatsForShops([
            1 => 'A1720894F',
            2 => 'A900011T',
        ]);

        $ttcUne = $uneBoutique['periods']['month']['ttc'];
        $ttcDeux = $deuxBoutiques['periods']['month']['ttc'];

        $this->assertSame(
            $ttcUne * 2,
            $ttcDeux,
            'Les montants de deux boutiques doivent etre additionnes.'
        );
        $this->assertSame(
            $uneBoutique['periods']['month']['invoice_count'] * 2,
            $deuxBoutiques['periods']['month']['invoice_count']
        );
        $this->assertSame(2, $deuxBoutiques['meta']['shops_count']);
    }

    public function testUneErreurProviderNAnnulePasLesAutresPeriodes(): void
    {
        // Le premier appel echoue, les suivants reussissent.
        $calls = 0;
        $transport = function (string $url) use (&$calls): array {
            $calls++;
            if ($calls === 1) {
                return ['status' => 500, 'body' => 'ERREUR'];
            }
            return [
                'status' => 200,
                'body' => json_encode($this->samplePayload),
            ];
        };

        $service = new DgiDashboardService(new DgiReportService($transport));
        $stats = $service->getStats(1, 'A1720894F');

        $this->assertNotNull(
            $stats['periods']['day']['error'],
            'La periode en echec doit porter un message d\'erreur.'
        );
        $this->assertNull(
            $stats['periods']['week']['error'],
            'Les autres periodes doivent rester exploitables.'
        );
        $this->assertGreaterThan(0, $stats['periods']['week']['ttc']);
    }

    public function testUnIsfVideEstRefuse(): void
    {
        $this->expectException(DgiReportException::class);

        $service = $this->serviceAvecTransport();
        $service->getStats(1, '   ');
    }

    public function testUneListeDeBoutiquesVideEstRefusee(): void
    {
        $this->expectException(DgiReportException::class);

        $service = $this->serviceAvecTransport();
        $service->getStatsForShops([]);
    }
}
