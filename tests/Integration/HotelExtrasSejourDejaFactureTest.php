<?php

namespace Tests\Integration;

use App\Core\Database;
use App\Models\HotelReservation;
use PHPUnit\Framework\TestCase;

/**
 * Interdit l'imputation d'un extra sur un séjour DÉJÀ FACTURÉ.
 *
 * Contexte métier : la facture hôtel peut être émise SANS check-out. Le
 * séjour reste donc `statut='en_cours'` mais sa note est close. Sans garde,
 * le caissier voit toujours ce séjour dans le picker « Extras » de /caisse
 * et peut continuer à lui imputer des produits — ce qui fausse le total
 * de la facture déjà émise.
 *
 * Ce test verrouille les 3 niveaux de défense :
 *   1. HotelReservation::sejoursImputablesAuxExtras() → le séjour facturé
 *      n'est PAS dans la liste du picker.
 *   2. HotelReservation::factureActive() → détecte la facture active.
 *   3. Une facture ANNULÉE ne bloque pas l'imputation (elle n'est plus active).
 */
class HotelExtrasSejourDejaFactureTest extends TestCase
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
        self::$pdo->prepare("INSERT INTO hotel_guests (nom, prenom) VALUES ('__tf__', 'F')")->execute();
        $this->ids['guest'] = (int)self::$pdo->lastInsertId();

        // Chambre
        self::$pdo->prepare(
            "INSERT INTO hotel_chambres (numero, type, prix_base, etat)
             VALUES (?, 'simple', 1000, 'disponible')"
        )->execute(['TF' . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT)]);
        $this->ids['chambre'] = (int)self::$pdo->lastInsertId();

        // Séjour EN COURS (donc visible par le picker historiquement)
        self::$pdo->prepare(
            "INSERT INTO hotel_sejours (chambre_id, guest_id, date_arrivee, date_depart_prevue, statut, prix_par_nuit)
             VALUES (?, ?, NOW(), DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'en_cours', 1000)"
        )->execute([$this->ids['chambre'], $this->ids['guest']]);
        $this->ids['sejour'] = (int)self::$pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        // Nettoyage : les extras et factures d'abord (FK sur le séjour).
        //
        // ⚠️ Les ventes `__TF_` créées par `creerFactureActive()` doivent
        // aussi être supprimées : `hotel_factures.vente_id` est une FK vers
        // `ventes`, donc on supprime la facture hôtel AVANT la vente.
        //
        // Sans ce nettoyage, chaque exécution de la suite laissait 4 ventes
        // `__TF_` (type 'HB') dans la base de test : les rapports de
        // `ReportHotelIntegrationTest`, qui agrègent la journée entière,
        // cumulaient ces fixtures et leurs assertions de TS échouaient
        // (400 200 au lieu de 200).
        $stmt = self::$pdo->prepare("SELECT id FROM ventes WHERE numero_facture LIKE '__TF_%'");
        $stmt->execute();
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $venteId) {
            self::$pdo->prepare("DELETE FROM hotel_factures WHERE vente_id = ?")->execute([$venteId]);
            self::$pdo->prepare("DELETE FROM details_vente WHERE vente_id = ?")->execute([$venteId]);
            self::$pdo->prepare("DELETE FROM ventes WHERE id = ?")->execute([$venteId]);
        }

        self::$pdo->prepare("DELETE FROM hotel_extras WHERE sejour_id = ?")->execute([$this->ids['sejour']]);
        self::$pdo->prepare("DELETE FROM hotel_factures WHERE sejour_id = ?")->execute([$this->ids['sejour']]);
        self::$pdo->prepare("DELETE FROM hotel_sejours WHERE id = ?")->execute([$this->ids['sejour']]);
        self::$pdo->prepare("DELETE FROM hotel_guests WHERE id = ?")->execute([$this->ids['guest']]);
        self::$pdo->prepare("DELETE FROM hotel_chambres WHERE id = ?")->execute([$this->ids['chambre']]);
    }

    /**
     * Crée une facture hôtel active ('emise') pour le séjour courant.
     * `hotel_factures.vente_id` est NOT NULL : on crée donc une vente
     * dédiée qui sert de support à la facture.
     */
    private function creerFactureActive(string $statut = 'emise'): int
    {
        self::$pdo->prepare(
            "INSERT INTO ventes (numero_facture, invoice_type, sous_total_ht, tva, total, date)
             VALUES (?, 'HB', 1000, 0, 1000, NOW())"
        )->execute(['__TF_' . bin2hex(random_bytes(4))]);
        $venteId = (int)self::$pdo->lastInsertId();

        self::$pdo->prepare(
            "INSERT INTO hotel_factures
                (sejour_id, vente_id, numero_facture, invoice_type,
                 total_nuits, total_extras, remise_pct, remise_montant,
                 tva_type, tva_montant, total_final, acompte_precedent, solde_du,
                 statut)
             VALUES (?, ?, ?, 'HB', 1000, 0, 0, 0, 'B', 0, 1000, 0, 1000, ?)"
        )->execute([
            $this->ids['sejour'],
            $venteId,
            '__TFF_' . bin2hex(random_bytes(4)),
            $statut,
        ]);

        return $venteId;
    }

    public function testUnSejourNonFactureResteImputable(): void
    {
        $sejours = (new HotelReservation())->sejoursImputablesAuxExtras();

        $ids = array_column($sejours, 'id');
        $this->assertContains(
            $this->ids['sejour'],
            $ids,
            'Un séjour en cours SANS facture doit rester imputable aux extras.'
        );
    }

    public function testUnSejourDejaFactureNestPlusDansLePicker(): void
    {
        $this->creerFactureActive('emise');

        $sejours = (new HotelReservation())->sejoursImputablesAuxExtras();

        $ids = array_column($sejours, 'id');
        $this->assertNotContains(
            $this->ids['sejour'],
            $ids,
            'Un séjour déjà facturé ne doit PLUS apparaître dans le picker des extras, '
            . 'même s\'il est toujours en cours.'
        );
    }

    public function testLeSejourResteEnCoursMaisEstExcluDuPicker(): void
    {
        // Facture émise SANS check-out → le séjour reste 'en_cours'.
        $this->creerFactureActive('emise');

        $sejour = (new HotelReservation())->findById($this->ids['sejour']);
        $this->assertSame('en_cours', $sejour['statut'], 'Le séjour doit rester en cours.');
        $this->assertNotNull($sejour['facture_id'], 'La facture doit être visible sur le séjour.');

        // …et pour autant il n'est plus imputable.
        $ids = array_column((new HotelReservation())->sejoursImputablesAuxExtras(), 'id');
        $this->assertNotContains($this->ids['sejour'], $ids);
    }

    public function testFactureActiveEstDetectee(): void
    {
        $this->creerFactureActive('emise');

        $facture = (new HotelReservation())->factureActive($this->ids['sejour']);

        $this->assertNotNull($facture, 'La facture active doit être détectée.');
        $this->assertSame('emise', $facture['statut']);
    }

    public function testUneFactureAnnuleeNeBloquePasLImputation(): void
    {
        // Une facture annulée n'est PLUS active : le séjour redevient imputable.
        $this->creerFactureActive('annulee');

        $this->assertNull(
            (new HotelReservation())->factureActive($this->ids['sejour']),
            'Une facture annulée ne doit pas être considérée comme active.'
        );

        $ids = array_column((new HotelReservation())->sejoursImputablesAuxExtras(), 'id');
        $this->assertContains(
            $this->ids['sejour'],
            $ids,
            'Un séjour dont la facture a été annulée doit redevenir imputable.'
        );
    }

    public function testAucuneFactureNeBloquePasLImputation(): void
    {
        $this->assertNull((new HotelReservation())->factureActive($this->ids['sejour']));
    }
}
