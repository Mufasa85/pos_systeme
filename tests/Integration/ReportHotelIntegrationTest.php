<?php

namespace Tests\Integration;

use App\Core\Database;
use App\Models\Report;
use App\Models\Sale;
use App\Models\SaleDetail;
use PHPUnit\Framework\TestCase;

/**
 * Vérifie l'INTÉGRATION DE L'HÔTEL DANS LES RAPPORTS FISCAUX.
 *
 * 3 exigences :
 *
 *   1. Les NUITÉES d'hôtel apparaissent dans le rapport « Par groupe de
 *      taxation ». Avant, elles étaient totalement invisibles (produit_id
 *      NULL → `t.taux` NULL → ligne absente du rapport) alors qu'elles
 *      pèsent le CA principal de l'hôtel.
 *
 *   2. Le CA hôtelier est ventilé par CHAMBRE (bloc dédié).
 *
 *   3. PAS DE DOUBLE COMPTE : les extras hôtel (`invoice_type = 'EX'`) sont
 *      déjà inclus dans le total des factures hôtel. Ils doivent donc être
 *      EXCLUS des totaux consolidés (types de facture, groupes de taxation,
 *      paiements) — mais rester VISIBLES dans le bloc dédié, avec un libellé
 *      explicite.
 */
class ReportHotelIntegrationTest extends TestCase
{
    private static $pdo;
    private $ids = [];

    public static function setUpBeforeClass(): void
    {
        self::$pdo = Database::getInstance()->getConnection();
    }

    protected function setUp(): void
    {
        // Guest
        self::$pdo->prepare("INSERT INTO hotel_guests (nom, prenom) VALUES ('__rt__', 'R')")->execute();
        $this->ids['guest'] = (int)self::$pdo->lastInsertId();

        // Chambre rattachée à une taxe (Groupe B = 16%) → la ligne de nuitée
        // doit récupérer CE groupe de taxation dans le rapport.
        self::$pdo->prepare(
            "INSERT INTO hotel_chambres (numero, type, prix_base, etat, taxe_id)
             VALUES (?, 'simple', 1000, 'disponible', (SELECT id FROM taxes WHERE taux = 16 LIMIT 1))"
        )->execute(['RT' . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT)]);
        $this->ids['chambre'] = (int)self::$pdo->lastInsertId();

        self::$pdo->prepare(
            "INSERT INTO hotel_sejours (chambre_id, guest_id, date_arrivee, date_depart_prevue, statut, prix_par_nuit)
             VALUES (?, ?, DATE_SUB(CURDATE(), INTERVAL 1 DAY), DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'en_cours', 1000)"
        )->execute([$this->ids['chambre'], $this->ids['guest']]);
        $this->ids['sejour'] = (int)self::$pdo->lastInsertId();

        // Un vendeur (ventes.vendeur_id NOT NULL + FK)
        $stmt = self::$pdo->prepare("SELECT id FROM utilisateurs ORDER BY id LIMIT 1");
        $stmt->execute();
        $userId = $stmt->fetchColumn();
        if (!$userId) {
            $this->markTestSkipped('Aucun utilisateur en base : test non applicable.');
        }
        $this->ids['user'] = (int)$userId;
    }

    protected function tearDown(): void
    {
        $this->purgerVentes('__RTH_%');
        $this->purgerVentes('__RTX_%');
        self::$pdo->prepare("DELETE FROM hotel_factures WHERE sejour_id = ?")->execute([$this->ids['sejour']]);
        self::$pdo->prepare("DELETE FROM hotel_sejours WHERE id = ?")->execute([$this->ids['sejour']]);
        self::$pdo->prepare("DELETE FROM hotel_guests WHERE id = ?")->execute([$this->ids['guest']]);
        self::$pdo->prepare("DELETE FROM hotel_chambres WHERE id = ?")->execute([$this->ids['chambre']]);
    }

    private function purgerVentes(string $prefixe): void
    {
        $stmt = self::$pdo->prepare("SELECT id FROM ventes WHERE numero_facture LIKE ?");
        $stmt->execute([$prefixe . '%']);
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $venteId) {
            self::$pdo->prepare("DELETE FROM hotel_factures WHERE vente_id = ?")->execute([$venteId]);
            self::$pdo->prepare("DELETE FROM details_vente WHERE vente_id = ?")->execute([$venteId]);
            self::$pdo->prepare("DELETE FROM ventes WHERE id = ?")->execute([$venteId]);
        }
    }

    /**
     * Crée une facture hôtel : 2 nuitées de 1000 avec le snapshot de taxe
     * de la chambre (Groupe B, 16%).
     *
     * @param string|null $date Date de la vente (null = maintenant).
     *                        Permet d'ISOLER la facture dans une tranche
     *                        horaire pour que les agrégats du rapport ne
     *                        cumulent pas les fixtures des autres tests.
     */
    private function creerFactureHotel(?string $date = null): int
    {
        $numero = '__RTH_' . bin2hex(random_bytes(4));

        $saleId = (new Sale())->create([
            'numero_facture' => $numero,
            'invoice_type'   => 'FV',
            'est_hotel'      => 1,
            'client_id'      => null,
            'sejour_id'      => $this->ids['sejour'],
            'sous_total_ht'  => 2000,
            'tva'            => 0,
            'total'          => 2000,
            'payments'       => [['type' => 'ESPECES', 'amount' => 2000]],
            'vendeur_id'     => $this->ids['user'],
            'date'           => $date ?? date('Y-m-d H:i:s'),
        ]);

        $detailModel = new SaleDetail();
        for ($i = 0; $i < 2; $i++) {
            $detailModel->create([
                'vente_id'             => $saleId,
                'produit_id'           => null,
                'quantite'             => 1,
                'prix'                 => 1000,
                'remise_type'          => '%',
                'remise_value'         => 0,
                'taxe_specifique_type' => '%',
                'taxe_specifique_value' => 0,
                'sejour_id'            => $this->ids['sejour'],
                'chambre_id'           => $this->ids['chambre'],
                'ligne_type'           => 'nuit',
                'libelle'              => 'Nuit 01/01 - Chambre RT',
                // Snapshot du groupe de taxation (migration 2026_11_06)
                'tax_rate'             => 16,
                'tax_etiquette'        => 'B',
                'tax_groupe'           => 'Groupe B',
            ]);
        }

        self::$pdo->prepare(
            "INSERT INTO hotel_factures
                (sejour_id, vente_id, numero_facture, invoice_type,
                 total_nuits, total_extras, remise_pct, remise_montant,
                 tva_type, tva_montant, total_final, acompte_precedent, solde_du,
                 statut, user_id)
             VALUES (?, ?, ?, 'FV', 2000, 0, 0, 0, 'B', 0, 2000, 0, 2000, 'emise', ?)"
        )->execute([$this->ids['sejour'], $saleId, $numero, $this->ids['user']]);

        return $saleId;
    }

    /**
     * Crée une vente EXTRA hôtel (`invoice_type = 'EX'`), imputée au séjour.
     * Simule un extra encaissé à la caisse et déjà inclus dans la facture.
     */
    private function creerVenteExtra(int $montant = 500): int
    {
        $numero = '__RTX_' . bin2hex(random_bytes(4));
        $saleId = (new Sale())->create([
            'numero_facture' => $numero,
            'invoice_type'   => 'EX',
            'est_hotel'      => 0,
            'client_id'      => null,
            'sejour_id'      => $this->ids['sejour'],
            'sous_total_ht'  => $montant,
            'tva'            => 0,
            'total'          => $montant,
            'payments'       => [['type' => 'ESPECES', 'amount' => $montant]],
            'vendeur_id'     => $this->ids['user'],
            'date'           => date('Y-m-d H:i:s'),
        ]);

        (new SaleDetail())->create([
            'vente_id'             => $saleId,
            'produit_id'           => null,
            'quantite'             => 1,
            'prix'                 => $montant,
            'remise_type'          => '%',
            'remise_value'         => 0,
            'taxe_specifique_type' => '%',
            'taxe_specifique_value' => 0,
            'ligne_type'           => 'extra_caisse',
            'libelle'              => 'Extra test',
            'tax_rate'             => 16,
            'tax_etiquette'        => 'B',
            'tax_groupe'           => 'Groupe B',
        ]);

        return $saleId;
    }

    /**
     * Ajoute les lignes d'EXTRA à la facture hôtel — comme le fait désormais
     * HotelFactureController::store() (ligne `ligne_type = 'extra'`).
     */
    private function ajouterLignesExtraALaFacture(int $saleId, int $montant = 500): void
    {
        (new SaleDetail())->create([
            'vente_id'             => $saleId,
            'produit_id'           => null,
            'quantite'             => 1,
            'prix'                 => $montant,
            'remise_type'          => '%',
            'remise_value'         => 0,
            'taxe_specifique_type' => '%',
            'taxe_specifique_value' => 0,
            'sejour_id'            => $this->ids['sejour'],
            'chambre_id'           => $this->ids['chambre'],
            'ligne_type'           => 'extra',
            'libelle'              => 'Extra test',
            'tax_rate'             => 16,
            'tax_etiquette'        => 'B',
            'tax_groupe'           => 'Groupe B',
        ]);
    }

    private function periode(): array
    {
        return [date('Y-m-d 00:00:00'), date('Y-m-d 23:59:59')];
    }

    /**
     * Tranche horaire ISOLÉE pour les tests qui assertent un TOTAL exact.
     *
     * ⚠️ `periode()` couvre TOUTE la journée : les fixtures des autres classes
     * de tests (factures hôtel créées via le contrôleur, avec leur TS de
     * chambre) s'y cumulaient, et les assertions de TS totale échouaient
     * (500 200 au lieu de 200).
     *
     * On place donc la vente du test dans une tranche d'une minute, choisie
     * aléatoirement entre 04:00 et 04:59 (les fixtures des autres tests sont
     * créées à l'heure réelle, 07:xx).
     */
    private function periodeIsolee(): array
    {
        if (!isset($this->ids['minute'])) {
            $this->ids['minute'] = random_int(0, 59);
        }
        $minute = str_pad((string)$this->ids['minute'], 2, '0', STR_PAD_LEFT);

        return [
            date('Y-m-d') . ' 04:' . $minute . ':00',
            date('Y-m-d') . ' 04:' . $minute . ':59',
        ];
    }

    /** Date de vente placée dans la tranche isolée (cf. periodeIsolee()). */
    private function dateIsolee(): string
    {
        [$start] = $this->periodeIsolee();
        return $start;
    }

    public function testLesNuiteesApparaissentDansLeGroupeDeTaxation(): void
    {
        [$start, $end] = $this->periode();
        $report = new Report();

        $htAvant = $this->sommeGroupeB($report, $start, $end);
        $this->creerFactureHotel();
        $htApres = $this->sommeGroupeB($report, $start, $end);

        // On cherche la ligne du groupe B (16%) : elle doit contenir les
        // 2 nuitées de 1000 → 2000 HT.
        $groupeB = null;
        foreach ($report->getSalesByTaxGroup(null, $start, $end) as $g) {
            if ((float)$g['taux'] === 16.0) {
                $groupeB = $g;
                break;
            }
        }

        $this->assertNotNull(
            $groupeB,
            'Les nuitées d\'hôtel doivent apparaître dans un groupe de taxation.'
        );
        $this->assertEquals(
            2000.0,
            $htApres - $htAvant,
            'Le groupe B doit inclure le HT des nuitées (2000).'
        );
        $this->assertGreaterThanOrEqual(
            320.0,
            (float)$groupeB['total_tax'],
            'Le groupe B doit inclure la TVA des nuitées (2000 × 16% = 320).'
        );
    }

    public function testLeCaHotelEstVentileParChambre(): void
    {
        $this->creerFactureHotel();
        [$start, $end] = $this->periode();

        $parChambre = (new Report())->getHotelReportByRoom(null, $start, $end);

        $this->assertNotEmpty($parChambre, 'Le bloc hôtel par chambre ne doit pas être vide.');

        $chambre = null;
        foreach ($parChambre as $r) {
            if ((int)$r['chambre_id'] === $this->ids['chambre']) {
                $chambre = $r;
                break;
            }
        }

        $this->assertNotNull($chambre, 'La chambre facturée doit apparaître.');
        $this->assertEquals(2.0, (float)$chambre['nb_nuitees'], '2 nuitées vendues.');
        $this->assertEquals(2000.0, (float)$chambre['total_ht'], 'HT des nuitées = 2000.');
    }

    public function testLesExtrasHotelNeSontPasComptesDeuxFois(): void
    {
        [$start, $end] = $this->periode();
        $report = new Report();

        // État AVANT : d'autres factures hôtel peuvent exister en base
        // (autres tests, exécutions précédentes). On mémorise les montants
        // pour comparer des DELTAS et non des valeurs absolues.
        $extrasAvant = (float)($report->getHotelBreakdown(null, $start, $end)['extras']['total_ht'] ?? 0);
        $htAvant     = $this->sommeGroupeB($report, $start, $end);

        $saleId = $this->creerFactureHotel();
        $this->creerVenteExtra(500);          // vente EX (source de référence)
        $this->ajouterLignesExtraALaFacture($saleId, 500); // ligne dans la facture

        // 1. Le CA par groupe de taxation ne doit pas compter l'extra DEUX fois :
        //    une seule fois via la ligne de la facture hôtel (2000 + 500).
        //    On compare à un DELTA : d'autres ventes peuvent coexister en
        //    base (autres tests), on isole donc le calcul sur NOS lignes.
        $groupes = $report->getSalesByTaxGroup(null, $start, $end);
        $htGroupeB = $this->sommeGroupeB($report, $start, $end);
        $this->assertEquals(
            2500.0,
            $htGroupeB - $htAvant,
            'Le CA consolidé doit compter l\'extra UNE SEULE FOIS (2000 nuits + 500 extra).'
        );

        // Contrôle anti-double-compte STRICT : le montant des lignes
        // d'extras de NOTRE facture (500) ne doit pas être doublé par les
        // ventes 'EX' (qui sont exclues du rapport).
        $notreExtra = $report->getHotelBreakdown(null, $start, $end);
        $this->assertEquals(
            500.0,
            (float)$notreExtra['extras']['total_ht'] - $extrasAvant,
            'L\'extra ne doit pas être compté deux fois.',
        );

        // 2. Le total des factures hôtel contient notre facture (1).
        //    ⚠️ On ne compare pas à 1 en absolu : d'autres factures hôtel
        //    peuvent exister en base (autres tests, exécutions précédentes).
        //    On vérifie donc que NOTRE facture est bien comptée et qu'aucune
        //    facture n'est comptée deux fois.
        $breakdown = $report->getHotelBreakdown(null, $start, $end);
        $this->assertGreaterThanOrEqual(
            1,
            $breakdown['factures']['nb_factures'],
            'La facture hôtel doit être comptée dans la ventilation.'
        );

        // 3. L'extra est visible dans la ventilation hôtel (source : lignes
        //    de la facture).
        $this->assertGreaterThanOrEqual(
            1,
            $breakdown['extras']['nb_lignes'],
            'L\'extra doit être visible dans la ventilation hôtel.'
        );
        $this->assertGreaterThanOrEqual(
            500.0,
            (float)$breakdown['extras']['total_ht'],
            'Montant de l\'extra affiché pour information.'
        );
    }

    public function testLesExtrasHotelApparaissentDansLeGroupeDeTaxation(): void
    {
        [$start, $end] = $this->periode();
        $report = new Report();

        // Montant du groupe B AVANT nos données (d'autres factures hôtel
        // peuvent exister en base : autres tests, exécutions précédentes).
        $htAvant = $this->sommeGroupeB($report, $start, $end);

        $saleId = $this->creerFactureHotel();
        $this->ajouterLignesExtraALaFacture($saleId, 500);

        // APRÈS : on compare un DELTA (2000 nuits + 500 extra).
        $htApres = $this->sommeGroupeB($report, $start, $end);

        $this->assertEquals(
            2500.0,
            $htApres - $htAvant,
            'Les extras doivent être inclus dans le groupe de taxation (2000 nuits + 500 extra).'
        );
    }

    /**
     * Somme du HT du groupe de taxation à 16% (Groupe B) sur la période.
     */
    private function sommeGroupeB(Report $report, string $start, string $end): float
    {
        $total = 0.0;
        foreach ($report->getSalesByTaxGroup(null, $start, $end) as $g) {
            if ((float)$g['taux'] === 16.0) {
                $total += (float)$g['total_ht'];
            }
        }
        return $total;
    }

    public function testLesExtrasHotelApparaissentDansLArticleReport(): void
    {
        $saleId = $this->creerFactureHotel();
        $this->ajouterLignesExtraALaFacture($saleId, 500);
        [$start, $end] = $this->periode();

        $articles = (new Report())->getArticlesReport(null, $start, $end);

        $trouve = null;
        foreach ($articles as $a) {
            if ((string)($a['category_name'] ?? '') === 'Hôtel - Extras') {
                $trouve = $a;
                break;
            }
        }

        $this->assertNotNull(
            $trouve,
            'Les extras d\'hôtel doivent apparaître dans l\'A-rapport.'
        );
        $this->assertEquals(500.0, (float)$trouve['montant_collecte'], 'Montant collecté de l\'extra.');
    }

    public function testLaTsTotaleEstCalculeeSurLesNuitees(): void
    {
        // 2 nuitées à 1000 avec une TS de 10% → 100 de TS par nuitée
        // (1000 × 10% = 100), soit 200 pour les 2 nuitées.
        //
        // La vente est placée dans une tranche horaire ISOLÉE : le rapport
        // n'agrège que cette tranche, donc les fixtures des autres tests ne
        // s'ajoutent pas au total.
        $saleId = $this->creerFactureHotel($this->dateIsolee());
        self::$pdo->prepare(
            "UPDATE details_vente SET taxe_specifique_type = '%', taxe_specifique_value = 10
             WHERE vente_id = ? AND ligne_type = 'nuit'"
        )->execute([$saleId]);

        [$start, $end] = $this->periodeIsolee();
        $groupes = (new Report())->getSalesByTaxGroup(null, $start, $end);

        $ts = 0.0;
        foreach ($groupes as $g) {
            $ts += (float)($g['total_ts'] ?? 0);
        }

        $this->assertEquals(
            200.0,
            $ts,
            'La TS totale doit être calculée : 2 nuitées × (1000 × 10%) = 200.'
        );
    }

    public function testLaTsTotaleEstDansLesTotauxGlobaux(): void
    {
        $saleId = $this->creerFactureHotel($this->dateIsolee());
        self::$pdo->prepare(
            "UPDATE details_vente SET taxe_specifique_type = 'CDF', taxe_specifique_value = 250
             WHERE vente_id = ? AND ligne_type = 'nuit'"
        )->execute([$saleId]);

        [$start, $end] = $this->periodeIsolee();
        $report = new \App\Services\ReportService();
        $data = $report->generate(null, 'X', $start, $end);

        $this->assertEquals(
            500.0,
            (float)$data['totals']['total_ts'],
            'La TS totale du CA doit être exposée dans les totaux globaux (2 × 250 CDF).'
        );
    }

    public function testLesExtrasHotelSontExclusDuParTypeDeFacture(): void
    {
        $this->creerFactureHotel();
        $this->creerVenteExtra(500);
        [$start, $end] = $this->periode();

        $parType = (new Report())->getSalesByInvoiceType(null, $start, $end);

        foreach ($parType as $t) {
            $this->assertNotSame(
                'EX',
                $t['invoice_type'],
                'Les extras hôtel ne doivent pas apparaître comme un type de facture.'
            );
        }
    }

    public function testLesExtrasHotelSontExclusDesPaiementsConsolides(): void
    {
        $this->creerFactureHotel();   // paiement de 2000
        $this->creerVenteExtra(500);  // paiement de 500 (exclu)
        [$start, $end] = $this->periode();

        $paiements = (new Report())->getPaymentsParsed(null, $start, $end);

        $montants = [];
        foreach ($paiements as $row) {
            $decoded = json_decode($row['payments'] ?? '[]', true);
            foreach ((array)$decoded as $p) {
                $montants[] = (float)($p['amount'] ?? 0);
            }
        }

        $this->assertNotEmpty($montants, 'Les paiements consolidés ne doivent pas être vides.');

        // Le paiement de l'extra (500) ne doit PAS être présent.
        $this->assertNotContains(
            500.0,
            $montants,
            'Le paiement de l\'extra ne doit pas être compté dans les paiements consolidés.'
        );

        // Le paiement de la facture hôtel (2000) doit être présent.
        $this->assertContains(
            2000.0,
            $montants,
            'Le paiement de la facture hôtel doit être compté dans les paiements consolidés.'
        );
    }
}
