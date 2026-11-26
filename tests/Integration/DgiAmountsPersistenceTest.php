<?php

namespace Tests\Integration;

use App\Core\Database;
use App\Models\Sale;
use App\Services\DgiAmountsService;
use PHPUnit\Framework\TestCase;

/**
 * NON-RÉGRESSION : les MONTANTS d'une vente doivent provenir de la DGI.
 *
 * Problème observé (2026-11-17) : la table `ventes` stockait les totaux
 * CALCULÉS PAR LE FRONT (prix × quantité + TS + TVA recalculés dans le
 * navigateur), qui divergeaient des montants réellement validés par la DGI.
 *
 * Règle appliquée (cf. DgiAmountsService) :
 *     sous_total_ht = data.total - data.vtotal
 *     tva           = data.vtotal
 *     total         = data.total
 *
 * Ce test verrouille :
 *   1. une vente enregistrée avec les montants DGI ;
 *   2. le repli sur les montants du front quand la DGI n'a rien renvoyé
 *      (fallback « non assujettie ») ;
 *   3. le réalignement des montants lors de la relève DGI différée
 *      (DgiSyncService::applyDgiData).
 */
class DgiAmountsPersistenceTest extends TestCase
{
    private static $pdo;
    private $ids = [];

    public static function setUpBeforeClass(): void
    {
        self::$pdo = Database::getInstance()->getConnection();
    }

    protected function setUp(): void
    {
        $stmt = self::$pdo->prepare('SELECT id FROM utilisateurs ORDER BY id LIMIT 1');
        $stmt->execute();
        $userId = $stmt->fetchColumn();
        if (!$userId) {
            $this->markTestSkipped('Aucun utilisateur en base : test non applicable.');
        }
        $this->ids['user'] = (int)$userId;
    }

    protected function tearDown(): void
    {
        $stmt = self::$pdo->prepare("SELECT id FROM ventes WHERE numero_facture LIKE '__DGIAMT_%'");
        $stmt->execute();
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $venteId) {
            self::$pdo->prepare('DELETE FROM ventes WHERE id = ?')->execute([$venteId]);
        }
    }

    /**
     * Crée une vente en appliquant la même règle que SaleController::create.
     *
     * @param array $dgiData  La réponse DGI transmise par le front
     * @param array $montants Les totaux calculés par le front
     */
    private function creerVente(array $dgiData, array $montants): int
    {
        $amounts = (new DgiAmountsService())->apply(
            $montants,
            $dgiData,
            'FV'
        );

        $numero = '__DGIAMT_' . bin2hex(random_bytes(4));

        return (new Sale())->create([
            'numero_facture' => $numero,
            'invoice_type'   => 'FV',
            'client_id'      => null,
            'sejour_id'      => null,
            'sous_total_ht'  => $amounts['sous_total_ht'],
            'tva'            => $amounts['tva'],
            'total'          => $amounts['total'],
            'vendeur_id'     => $this->ids['user'],
            'date'           => date('Y-m-d H:i:s'),
            'codeDEFDGI'     => $dgiData['codeDEFDGI'] ?? null,
            'counters'       => $dgiData['counters'] ?? null,
            'nim'            => $dgiData['nim'] ?? null,
            'dgi_status'     => 'synced',
        ]);
    }

    private function relire(int $saleId): array
    {
        $stmt = self::$pdo->prepare('SELECT * FROM ventes WHERE id = ?');
        $stmt->execute([$saleId]);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    // ── Cas nominal ─────────────────────────────────────────────────

    public function testLesMontantsEnregistresViennentDeLaDgi(): void
    {
        // Réponse DGI réelle : total = 3200, vtotal = 441.38
        // → HT = 2758.62
        $saleId = $this->creerVente(
            [
                'total'      => 3200,
                'vtotal'     => 441.38,
                'codeDEFDGI' => 'TEST-FACT-URES-AIFC-CIO4-QSZB',
                'counters'   => '1689/1807 FV',
                'nim'        => 'CD01004990-1',
            ],
            // Totaux « du front » (approximation) : ils ne doivent PAS être
            // conservés, la DGI fait foi.
            [
                'sous_total_ht' => 2800.00,
                'tva'           => 400.00,
                'total'         => 3200.00,
            ]
        );

        $vente = $this->relire($saleId);

        $this->assertEqualsWithDelta(3200.00, (float)$vente['total'], 0.005);
        $this->assertEqualsWithDelta(441.38, (float)$vente['tva'], 0.005);
        $this->assertEqualsWithDelta(2758.62, (float)$vente['sous_total_ht'], 0.005);

        // Auto-contrôle : HT + TVA = TTC
        $this->assertEqualsWithDelta(
            (float)$vente['total'],
            (float)$vente['sous_total_ht'] + (float)$vente['tva'],
            0.005
        );
    }

    // ── Repli : la DGI n'a rien renvoyé d'exploitable ───────────────

    public function testLesMontantsDuFrontSontConservesSurUnFallbackDgi(): void
    {
        // Fallback « non assujettie » : la DGI était hors service. On ne doit
        // PAS écraser la comptabilité avec un total fantôme.
        $saleId = $this->creerVente(
            [
                'total'              => 3200,
                'vtotal'             => 441.38,
                'store_homologation' => 2,
            ],
            [
                'sous_total_ht' => 2800.00,
                'tva'           => 400.00,
                'total'         => 3200.00,
            ]
        );

        $vente = $this->relire($saleId);

        $this->assertEqualsWithDelta(3200.00, (float)$vente['total'], 0.005);
        $this->assertEqualsWithDelta(400.00, (float)$vente['tva'], 0.005);
        $this->assertEqualsWithDelta(2800.00, (float)$vente['sous_total_ht'], 0.005);
    }

    // ── Relève DGI différée ────────────────────────────────────────

    public function testLaReleveDgiRealigneLesMontantsEnBase(): void
    {
        // Vente créée pendant une panne DGI : les montants sont ceux du front.
        $saleId = $this->creerVente(
            [
                'store_homologation' => 2,
                'total'              => 3200,
                'vtotal'             => 441.38,
            ],
            [
                'sous_total_ht' => 2800.00,
                'tva'           => 400.00,
                'total'         => 3200.00,
            ]
        );

        // La DGI revient : OSAT pousse la résolution.
        $service = new \App\Services\DgiSyncService();
        $sale = $service->findSaleByDgiKey((string)$this->relire($saleId)['numero_facture']);
        $this->assertNotNull($sale);

        $apply = $service->applyDgiData($sale, [
            'data' => [
                'total'      => 3200,
                'vtotal'     => 441.38,
                'codeDEFDGI' => 'TEST-FACT-URES-AIFC-CIO4-QSZB',
                'counters'   => '1689/1807 FV',
                'nim'        => 'CD01004990-1',
                'qrCode'     => 'RDCF01;CD01004990-1;TEST;A1720894F;20261117160503',
            ],
        ]);

        $this->assertTrue($apply['done']);

        $vente = $this->relire($saleId);

        $this->assertEqualsWithDelta(3200.00, (float)$vente['total'], 0.005);
        $this->assertEqualsWithDelta(441.38, (float)$vente['tva'], 0.005);
        $this->assertEqualsWithDelta(2758.62, (float)$vente['sous_total_ht'], 0.005);
        $this->assertSame('synced', $vente['dgi_status']);
    }
}
