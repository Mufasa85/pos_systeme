<?php

namespace Tests\Unit;

use App\Services\DgiSyncService;
use PHPUnit\Framework\TestCase;

/**
 * Tests de la logique de détection du fallback DGI.
 *
 * Ces tests sont PURS (aucun accès BDD) : ils valident le cœur métier qui
 * décide si une vente doit être mise en attente de résolution DGI, ou si
 * elle est hors périmètre.
 */
class DgiSyncServiceTest extends TestCase
{
    /** @var DgiSyncService */
    private $service;

    protected function setUp(): void
    {
        $this->service = new DgiSyncService();
    }

    // ── hasSecurityFields ───────────────────────────────────────────

    public function testElementsDeSecuritePresentsEstDetecte(): void
    {
        $this->assertTrue($this->service->hasSecurityFields([
            'codeDEFDGI' => 'DEF-123',
            'counters'   => 'A900011T',
            'nim'        => 'CD01004990',
        ]));
    }

    public function testQrCodeSeulSuffitASignalerUneFactureEnregistree(): void
    {
        $this->assertTrue($this->service->hasSecurityFields(['qrCode' => 'data:text/plain;base64,xxx']));
    }

    public function testFallbackSansElementDeSecuriteEstDetecte(): void
    {
        // Cas réel du fallback OSAT : la réponse a la forme d'une facture
        // "non assujettie", sans compteur, sans code DEF/DGI, sans NID.
        $this->assertFalse($this->service->hasSecurityFields([
            'store_homologation' => 2,
            'total'              => 100,
            'vtotal'             => 100,
        ]));
    }

    public function testTableauVideOuNulEstUnFallback(): void
    {
        $this->assertFalse($this->service->hasSecurityFields([]));
        $this->assertFalse($this->service->hasSecurityFields(null));
    }

    // ── isUpdateCase ───────────────────────────────────────────────

    public function testValeur2SignaleUnCasUpdate(): void
    {
        $this->assertTrue($this->service->isUpdateCase(2));
        $this->assertTrue($this->service->isUpdateCase('2'));
    }

    public function testValeurs0Et1NeSontPasDesCasUpdate(): void
    {
        $this->assertFalse($this->service->isUpdateCase(0));
        $this->assertFalse($this->service->isUpdateCase('0'));
        $this->assertFalse($this->service->isUpdateCase(1));
        $this->assertFalse($this->service->isUpdateCase('1'));
        $this->assertFalse($this->service->isUpdateCase(true));
        $this->assertFalse($this->service->isUpdateCase(false));
    }

    public function testValeurAbsenteNEstPasUnCasUpdate(): void
    {
        $this->assertFalse($this->service->isUpdateCase(null));
        $this->assertFalse($this->service->isUpdateCase(''));
    }

    // ── resolveInitialStatus ────────────────────────────────────────

    public function testFactureAvecElementsDeSecuriteEstSynced(): void
    {
        $status = $this->service->resolveInitialStatus(
            ['counters' => 'A900011T', 'codeDEFDGI' => 'DEF-1'],
            'FV',
            true
        );

        $this->assertSame(DgiSyncService::STATUS_SYNCED, $status);
    }

    public function testCasUpdateSurBoutiqueHomologueeEstPending(): void
    {
        // C'est LE cas cible : la DGI était indisponible, OSAT a mis la
        // demande en attente (store_homologation = 2).
        $status = $this->service->resolveInitialStatus(
            ['store_homologation' => 2],
            'FV',
            true
        );

        $this->assertSame(DgiSyncService::STATUS_PENDING, $status);
    }

    public function testBoutiqueNonHomologueeEstNotApplicable(): void
    {
        // Cas structurel : aucune donnée DGI ne viendra jamais. On ne doit
        // donc pas mettre la vente en attente indéfiniment.
        $status = $this->service->resolveInitialStatus(
            ['store_homologation' => 0],
            'FV',
            false
        );

        $this->assertSame(DgiSyncService::STATUS_NOT_APPLICABLE, $status);
    }

    public function testExtraHotelEstNotApplicable(): void
    {
        // Un extra d'hôtel ne passe pas par la DGI (pas d'encaissement).
        $status = $this->service->resolveInitialStatus(
            [],
            'EX',
            true
        );

        $this->assertSame(DgiSyncService::STATUS_NOT_APPLICABLE, $status);
    }

    public function testBoutiqueHomologueeSansElementsNiDrapeauEstPending(): void
    {
        // Filet de sécurité : si OSAT n'a rien signalé mais que la boutique
        // est homologuée, on suppose que la DGI était hors service.
        $status = $this->service->resolveInitialStatus(
            [],
            'FV',
            true
        );

        $this->assertSame(DgiSyncService::STATUS_PENDING, $status);
    }

    public function testCasUpdateEstPrioritaireSurLaNonHomologation(): void
    {
        // Si OSAT signale explicitement un cas d'update, on suit son
        // signalement : la boutique est de toute façon censée être
        // homologuée, et c'est l'information la plus fiable.
        $status = $this->service->resolveInitialStatus(
            ['store_homologation' => 2],
            'FV',
            false
        );

        $this->assertSame(DgiSyncService::STATUS_PENDING, $status);
    }

    // ── Cas du module Hôtel ─────────────────────────────────────────

    public function testFactureHotelHBEstPendingSurCasUpdate(): void
    {
        // Une facture hôtel (type 'HB', régime B) EST une facture fiscale :
        // elle doit donc pouvoir être synchronisée comme une facture POS.
        $status = $this->service->resolveInitialStatus(
            ['store_homologation' => 2],
            'HB',
            true
        );

        $this->assertSame(DgiSyncService::STATUS_PENDING, $status);
    }

    public function testFactureHotelHBAvecElementsDeSecuriteEstSynced(): void
    {
        $status = $this->service->resolveInitialStatus(
            ['counters' => '1549/1667 FV', 'codeDEFDGI' => 'DEF-1'],
            'HB',
            true
        );

        $this->assertSame(DgiSyncService::STATUS_SYNCED, $status);
    }

    public function testFactureHotelHBNonHomologueeEstNotApplicable(): void
    {
        $status = $this->service->resolveInitialStatus(
            ['store_homologation' => 0],
            'HB',
            false
        );

        $this->assertSame(DgiSyncService::STATUS_NOT_APPLICABLE, $status);
    }

    public function testExtraHotelEXNEstJamaisSynchronise(): void
    {
        // Un extra d'hôtel ne passe pas par la DGI (pas d'encaissement) :
        // même en cas d'update signalé par OSAT, il n'y a rien à récupérer.
        $status = $this->service->resolveInitialStatus(
            ['store_homologation' => 2],
            'EX',
            true
        );

        $this->assertSame(DgiSyncService::STATUS_NOT_APPLICABLE, $status);
    }
}
