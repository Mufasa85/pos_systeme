<?php

namespace Tests\Unit;

use App\Services\DgiAmountsService;
use PHPUnit\Framework\TestCase;

/**
 * Tests de la source de vérité des MONTANTS DGI.
 *
 * Ces tests sont PURS (aucun accès BDD) : ils valident la décomposition
 * demandée par la DGI après validation d'une facture :
 *
 *     total         = data.total    (TTC)
 *     tva           = data.vtotal   (TVA)
 *     sous_total_ht = total - vtotal (HT)
 */
class DgiAmountsServiceTest extends TestCase
{
    /** @var DgiAmountsService */
    private $service;

    protected function setUp(): void
    {
        $this->service = new DgiAmountsService();
    }

    // ── Cas de référence (exemple réel fourni) ─────────────────────

    public function testDecompositionDeLaReponseDgiReelle(): void
    {
        // Exemple réel : total = 3200, vtotal = 441.38
        // → HT = 3200 - 441.38 = 2758.62
        $amounts = $this->service->fromDgiPayload([
            'data' => [
                'total'  => 3200,
                'vtotal' => 441.38,
            ],
        ]);

        $this->assertNotNull($amounts);
        $this->assertSame(2758.62, $amounts['sous_total_ht']);
        $this->assertSame(441.38, $amounts['tva']);
        $this->assertSame(3200.0, $amounts['total']);
    }

    public function testLaDecompositionEstAutocontrollanteHtPlusTvaEgalTtc(): void
    {
        $amounts = $this->service->fromDgiPayload([
            'data' => ['total' => 3200, 'vtotal' => 441.38],
        ]);

        $this->assertNotNull($amounts);
        $this->assertEqualsWithDelta(
            $amounts['total'],
            $amounts['sous_total_ht'] + $amounts['tva'],
            0.005
        );
    }

    // ── Formats d'entrée ───────────────────────────────────────────

    public function testLesMontantsACoteleRacineSontAcceptes(): void
    {
        $amounts = $this->service->fromDgiPayload([
            'total'  => 3200,
            'vtotal' => 441.38,
        ]);

        $this->assertNotNull($amounts);
        $this->assertSame(2758.62, $amounts['sous_total_ht']);
        $this->assertSame(441.38, $amounts['tva']);
        $this->assertSame(3200.0, $amounts['total']);
    }

    public function testLesMontantsEnChaineSontConvertis(): void
    {
        $amounts = $this->service->fromDgiPayload([
            'data' => ['total' => '3200', 'vtotal' => '441.38'],
        ]);

        $this->assertNotNull($amounts);
        $this->assertSame(2758.62, $amounts['sous_total_ht']);
        $this->assertSame(441.38, $amounts['tva']);
    }

    // ── Cas limites ────────────────────────────────────────────────

    public function testUneFactureExonereeALaTvaAZero(): void
    {
        // TVA = 0 est une valeur LÉGITIME (elle ne doit pas être confondue
        // avec une valeur absente).
        $amounts = $this->service->fromDgiPayload([
            'data' => ['total' => 1000, 'vtotal' => 0],
        ]);

        $this->assertNotNull($amounts);
        $this->assertSame(1000.0, $amounts['sous_total_ht']);
        $this->assertSame(0.0, $amounts['tva']);
        $this->assertSame(1000.0, $amounts['total']);
    }

    public function testLesMontantsArrondisADeuxDecimales(): void
    {
        $amounts = $this->service->fromDgiPayload([
            'data' => ['total' => 100.005, 'vtotal' => 16.005],
        ]);

        $this->assertNotNull($amounts);
        $this->assertSame(84.0, $amounts['sous_total_ht']);
        $this->assertSame(16.01, $amounts['tva']);
        $this->assertSame(100.01, $amounts['total']);
    }

    public function testUneReponseSansMontantEstRejetee(): void
    {
        $this->assertNull($this->service->fromDgiPayload(['data' => ['codeDEFDGI' => 'X']]));
        $this->assertNull($this->service->fromDgiPayload([]));
        $this->assertNull($this->service->fromDgiPayload(null));
    }

    public function testUneReponseAvecLeTotalSeulementEstRejetee(): void
    {
        // Sans `vtotal` (TVA), on ne peut pas décomposer : on ne devine pas.
        $this->assertNull($this->service->fromDgiPayload(['data' => ['total' => 3200]]));
    }

    // ── apply() : la source de vérité en contexte ──────────────────

    public function testApplyRemplaceLesMontantsDuFrontParCeuxDeLaDgi(): void
    {
        $result = $this->service->apply(
            [
                'sous_total_ht' => 2800.00,
                'tva'           => 400.00,
                'total'         => 3200.00,
            ],
            [
                'data' => [
                    'total'         => 3200,
                    'vtotal'        => 441.38,
                    'codeDEFDGI'    => 'TEST-FACT-URES-AIFC-CIO4-QSZB',
                    'counters'      => '1689/1807 FV',
                    'nim'           => 'CD01004990-1',
                    'qrCode'        => 'RDCF01;...',
                ],
            ],
            'FV'
        );

        $this->assertSame('dgi', $result['source']);
        $this->assertSame(2758.62, $result['sous_total_ht']);
        $this->assertSame(441.38, $result['tva']);
        $this->assertSame(3200.0, $result['total']);
    }

    public function testApplyConserveLesMontantsDuFrontSiLaDgiNARienRenoye(): void
    {
        $result = $this->service->apply(
            [
                'sous_total_ht' => 2800.00,
                'tva'           => 400.00,
                'total'         => 3200.00,
            ],
            [],
            'FV'
        );

        $this->assertSame('original', $result['source']);
        $this->assertSame(2800.00, $result['sous_total_ht']);
        $this->assertSame(400.00, $result['tva']);
        $this->assertSame(3200.00, $result['total']);
    }

    public function testApplyConserveLesMontantsDuFrontSurUnFallbackNonAssujettie(): void
    {
        // Fallback OSAT : les montants sont présents MAIS aucun élément de
        // sécurité n'est là (DGI hors service). On ne doit PAS appliquer un
        // total fantôme : le HT deviendrait 0 et la TVA serait inventée.
        $result = $this->service->apply(
            [
                'sous_total_ht' => 2800.00,
                'tva'           => 400.00,
                'total'         => 3200.00,
            ],
            [
                'data' => [
                    'total'              => 3200,
                    'vtotal'             => 441.38,
                    'store_homologation' => 2,
                ],
            ],
            'FV'
        );

        $this->assertSame('original', $result['source']);
        $this->assertSame(2800.00, $result['sous_total_ht']);
        $this->assertSame(400.00, $result['tva']);
        $this->assertSame(3200.00, $result['total']);
    }

    public function testUnTotalNulAnnonceParLaDgiNeRemplacePasUnMontantReel(): void
    {
        // Le contrat du callback OSAT mentionne `"total": 0, "vtotal": 0` :
        // appliquer ces zéros effacerait la comptabilité de la facture.
        $result = $this->service->apply(
            [
                'sous_total_ht' => 2800.00,
                'tva'           => 400.00,
                'total'         => 3200.00,
            ],
            [
                'data' => [
                    'total'      => 0,
                    'vtotal'     => 0,
                    'codeDEFDGI' => 'TEST-CB',
                ],
            ],
            'FV'
        );

        $this->assertSame('original', $result['source']);
        $this->assertSame(2800.00, $result['sous_total_ht']);
        $this->assertSame(400.00, $result['tva']);
        $this->assertSame(3200.00, $result['total']);
    }

    public function testUnTotalNulEstAppliqueSiLaVenteEstDejaAZero(): void
    {
        // Cas légitime : une facture réellement à zéro (exonération totale,
        // avoir d'un montant nul) doit pouvoir être enregistrée telle quelle.
        $result = $this->service->apply(
            ['sous_total_ht' => 0.0, 'tva' => 0.0, 'total' => 0.0],
            [
                'data' => [
                    'total'      => 0,
                    'vtotal'     => 0,
                    'codeDEFDGI' => 'TEST-0',
                ],
            ],
            'FV'
        );

        $this->assertSame('dgi', $result['source']);
        $this->assertSame(0.0, $result['total']);
    }

    // ── Avoirs (FA / EA) ────────────────────────────────────────────
    public function testUnAvoirConserveLeSigneNegatif(): void
    {
        $result = $this->service->apply(
            ['sous_total_ht' => -2800.00, 'tva' => -400.00, 'total' => -3200.00],
            [
                'data' => [
                    'total'      => -3200,
                    'vtotal'     => -441.38,
                    'codeDEFDGI' => 'TEST-AVOIR',
                ],
            ],
            'FA'
        );

        $this->assertSame('dgi', $result['source']);
        $this->assertSame(-2758.62, $result['sous_total_ht']);
        $this->assertSame(-441.38, $result['tva']);
        $this->assertSame(-3200.0, $result['total']);
    }

    public function testUnAvoirDontLaDgiRenvoieDesMontantsPositifsEstNegativise(): void
    {
        $result = $this->service->apply(
            ['sous_total_ht' => -2800.00, 'tva' => -400.00, 'total' => -3200.00],
            [
                'data' => [
                    'total'      => 3200,
                    'vtotal'     => 441.38,
                    'codeDEFDGI' => 'TEST-AVOIR',
                ],
            ],
            'FA'
        );

        $this->assertSame('dgi', $result['source']);
        $this->assertSame(-2758.62, $result['sous_total_ht']);
        $this->assertSame(-441.38, $result['tva']);
        $this->assertSame(-3200.0, $result['total']);
    }

    public function testUneFactureDeVenteNeChangePasDeSigne(): void
    {
        $result = $this->service->apply(
            ['sous_total_ht' => 2800.00, 'tva' => 400.00, 'total' => 3200.00],
            [
                'data' => [
                    'total'      => 3200,
                    'vtotal'     => 441.38,
                    'codeDEFDGI' => 'TEST-FV',
                ],
            ],
            'FV'
        );

        $this->assertSame(2758.62, $result['sous_total_ht']);
        $this->assertSame(441.38, $result['tva']);
        $this->assertSame(3200.0, $result['total']);
    }

    // ── hasSecurityFields() / hasAmounts() ─────────────────────────

    public function testLesElementsDeSecuriteSontDetectes(): void
    {
        $this->assertTrue(DgiAmountsService::hasSecurityFields([
            'codeDEFDGI' => 'DEF-123',
            'counters'   => 'A900011T',
            'nim'        => 'CD01004990',
        ]));

        $this->assertTrue(DgiAmountsService::hasSecurityFields([
            'qrCode' => 'RDCF01;...',
        ]));
    }

    public function testUnFallbackNEstPasUneFactureEnregistree(): void
    {
        $this->assertFalse(DgiAmountsService::hasSecurityFields([
            'store_homologation' => 2,
            'total'              => 100,
            'vtotal'             => 100,
        ]));

        $this->assertFalse(DgiAmountsService::hasSecurityFields([]));
        $this->assertFalse(DgiAmountsService::hasSecurityFields(null));
    }

    public function testHasAmountsExigeLesMontantsEtLesElementsDeSecurite(): void
    {
        $this->assertTrue($this->service->hasAmounts([
            'data' => ['total' => 3200, 'vtotal' => 441.38, 'codeDEFDGI' => 'X'],
        ]));

        // Montants présents mais pas d'élément de sécurité → fallback.
        $this->assertFalse($this->service->hasAmounts([
            'data' => ['total' => 3200, 'vtotal' => 441.38],
        ]));

        // Éléments de sécurité mais pas de montants.
        $this->assertFalse($this->service->hasAmounts([
            'data' => ['codeDEFDGI' => 'X'],
        ]));
    }
}
