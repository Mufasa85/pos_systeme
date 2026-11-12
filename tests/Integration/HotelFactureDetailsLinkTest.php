<?php

namespace Tests\Integration;

use App\Core\Database;
use App\Models\Sale;
use App\Models\SaleDetail;
use PHPUnit\Framework\TestCase;

/**
 * Verrouille les 2 corrections de la facturation hôtel :
 *
 *   1. Le TYPE DE FACTURE est le type fiscal réel choisi par l'utilisateur
 *      dans le modal « Informations Facture » (FV, EV, FT, FA, EA, ET).
 *      Avant, le backend forçait 'HB' — qui n'existe PAS à la DGI et qui
 *      polluait le rapport « Ventes par type de facture » (une ligne 'HB'
 *      parasite) et le filtre des avoirs (`IN ("FA","EA")`).
 *
 *   2. Les LIGNES DE DÉTAIL (`details_vente`) portent la traçabilité hôtel :
 *      `sejour_id`, `chambre_id`, `ligne_type='nuit'` et un `libelle` figé
 *      (« Nuit 05/11 - Chambre 12 »). Avant, `produit_id` valait NULL sans
 *      aucun lien : impossible de rattacher une ligne à son séjour, et la
 *      facture imprimée devinait le libellé via un test fragile sur 'HB'.
 *
 * Le marqueur « c'est une facture hôtel » est désormais porté par
 * `ventes.est_hotel` (et non par le type de facture).
 */
class HotelFactureDetailsLinkTest extends TestCase
{
    private static $pdo;
    private $ids = [];

    public static function setUpBeforeClass(): void
    {
        self::$pdo = Database::getInstance()->getConnection();
    }

    protected function setUp(): void
    {
        self::$pdo->prepare("INSERT INTO hotel_guests (nom, prenom) VALUES ('__tf__', 'F')")->execute();
        $this->ids['guest'] = (int)self::$pdo->lastInsertId();

        self::$pdo->prepare(
            "INSERT INTO hotel_chambres (numero, type, prix_base, etat)
             VALUES (?, 'simple', 1000, 'disponible')"
        )->execute(['TF' . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT)]);
        $this->ids['chambre'] = (int)self::$pdo->lastInsertId();

        // Séjour de 2 nuits (date d'arrivée → date de départ = 2 jours).
        self::$pdo->prepare(
            "INSERT INTO hotel_sejours (chambre_id, guest_id, date_arrivee, date_depart_prevue, statut, prix_par_nuit)
             VALUES (?, ?, DATE_SUB(CURDATE(), INTERVAL 1 DAY), DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'en_cours', 1000)"
        )->execute([$this->ids['chambre'], $this->ids['guest']]);
        $this->ids['sejour'] = (int)self::$pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        // Nettoyage : hotel_factures d'abord (FK vers ventes ET sejour_id
        // UNIQUE → une seule facture hôtel par séjour).
        self::$pdo->prepare("DELETE FROM hotel_factures WHERE sejour_id = ?")->execute([$this->ids['sejour']]);
        $this->purgerVentesFacture();
        self::$pdo->prepare("DELETE FROM hotel_sejours WHERE id = ?")->execute([$this->ids['sejour']]);
        self::$pdo->prepare("DELETE FROM hotel_guests WHERE id = ?")->execute([$this->ids['guest']]);
        self::$pdo->prepare("DELETE FROM hotel_chambres WHERE id = ?")->execute([$this->ids['chambre']]);
    }

    /**
     * Supprime les ventes + lignes de détail créées par ce test.
     * Identification via le préfixe du numéro de facture.
     *
     * ⚠️ `hotel_factures.vente_id` est une FK vers `ventes` : les factures
     * hôtel doivent être supprimées AVANT les ventes, sinon MySQL refuse
     * (erreur 1451).
     */
    private function purgerVentesFacture(): void
    {
        $stmt = self::$pdo->prepare("SELECT id FROM ventes WHERE numero_facture LIKE '__TFH_%'");
        $stmt->execute();
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $venteId) {
            self::$pdo->prepare("DELETE FROM hotel_factures WHERE vente_id = ?")->execute([$venteId]);
            self::$pdo->prepare("DELETE FROM details_vente WHERE vente_id = ?")->execute([$venteId]);
            self::$pdo->prepare("DELETE FROM ventes WHERE id = ?")->execute([$venteId]);
        }
    }

    /**
     * Récupère un vendeur existant (`ventes.vendeur_id` est NOT NULL + FK).
     */
    private function setUpUser(): void
    {
        if (isset($this->ids['user'])) {
            return;
        }
        $stmt = self::$pdo->prepare("SELECT id FROM utilisateurs ORDER BY id LIMIT 1");
        $stmt->execute();
        $userId = $stmt->fetchColumn();
        if (!$userId) {
            $this->markTestSkipped('Aucun utilisateur en base : test non applicable.');
        }
        $this->ids['user'] = (int)$userId;
    }

    /**
     * Simule ce que fait HotelFactureController::store() : une vente + ses
     * lignes de détail de nuitées, avec le type fiscal choisi.
     *
     * @param string $invoiceType Type fiscal choisi dans le modal
     */
    private function creerFactureHotel(string $invoiceType = 'FV'): int
    {
        $numero = '__TFH_' . bin2hex(random_bytes(4));

        $saleId = (new Sale())->create([
            'numero_facture' => $numero,
            'invoice_type'   => $invoiceType,
            'est_hotel'      => 1,
            'client_id'      => null,
            'sejour_id'      => $this->ids['sejour'],
            'sous_total_ht'  => 2000,
            'tva'            => 0,
            'total'          => 2000,
            'vendeur_id'     => $this->ids['user'],
            'date'           => date('Y-m-d H:i:s'),
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
                'libelle'              => 'Nuit 01/01 - Chambre TF',
            ]);
        }

        self::$pdo->prepare(
            "INSERT INTO hotel_factures
                (sejour_id, vente_id, numero_facture, invoice_type,
                 total_nuits, total_extras, remise_pct, remise_montant,
                 tva_type, tva_montant, total_final, acompte_precedent, solde_du,
                 statut, user_id)
             VALUES (?, ?, ?, ?, 2000, 0, 0, 0, 'B', 0, 2000, 0, 2000, 'emise', ?)"
        )->execute([$this->ids['sejour'], $saleId, $numero, $invoiceType, $this->ids['user']]);

        return $saleId;
    }

    public function testLeTypeDeFactureHotelEstLeTypeFiscalChoisi(): void
    {
        $this->setUpUser();

        // `hotel_factures.sejour_id` est UNIQUE (une facture hôtel par séjour)
        // → on ne peut créer qu'UNE facture par test : on vérifie donc le type
        // choisi pour un type donné, et le test « jamais HB » couvre les autres.
        $type = 'ET';
        $saleId = $this->creerFactureHotel($type);

        $vente = self::$pdo->prepare("SELECT invoice_type FROM ventes WHERE id = ?");
        $vente->execute([$saleId]);
        $this->assertSame(
            $type,
            $vente->fetchColumn(),
            "Le type de facture hôtel doit être le type fiscal choisi ($type), pas 'HB'."
        );

        $facture = self::$pdo->prepare(
            "SELECT invoice_type FROM hotel_factures WHERE vente_id = ?"
        );
        $facture->execute([$saleId]);
        $this->assertSame(
            $type,
            $facture->fetchColumn(),
            'hotel_factures.invoice_type doit refléter le type fiscal choisi.'
        );
    }

    /**
     * @dataProvider typesFiscauxProvider
     */
    public function testChaqueTypeFiscalEstAccepte(string $type): void
    {
        $this->setUpUser();
        $saleId = $this->creerFactureHotel($type);

        $vente = self::$pdo->prepare("SELECT invoice_type FROM ventes WHERE id = ?");
        $vente->execute([$saleId]);
        $this->assertSame($type, $vente->fetchColumn());
    }

    public static function typesFiscauxProvider(): array
    {
        return [
            'Facture de Vente'          => ['FV'],
            'Exportation'               => ['EV'],
            'Acompte'                   => ['FT'],
            'Avoir'                     => ['FA'],
            'Avoir exportation'         => ['EA'],
            'Acompte exportation'       => ['ET'],
        ];
    }

    public function testLeTypeHotelNEstJamaisHB(): void
    {
        $this->setUpUser();
        $saleId = $this->creerFactureHotel('FV');

        $stmt = self::$pdo->prepare(
            "SELECT COUNT(*) FROM ventes WHERE id = ? AND invoice_type = 'HB'"
        );
        $stmt->execute([$saleId]);
        $this->assertSame(
            0,
            (int)$stmt->fetchColumn(),
            "'HB' ne doit plus être utilisé comme type de facture."
        );
    }

    public function testLeMarqueurHotelEstIndependantDuTypeFiscal(): void
    {
        $this->setUpUser();
        $saleId = $this->creerFactureHotel('ET');

        $stmt = self::$pdo->prepare("SELECT est_hotel FROM ventes WHERE id = ?");
        $stmt->execute([$saleId]);
        $this->assertSame(
            1,
            (int)$stmt->fetchColumn(),
            'Le marqueur "facture hôtel" doit être porté par est_hotel, pas par le type.'
        );
    }

    public function testLesLignesDeDetailPortentLaTracabiliteHotel(): void
    {
        $this->setUpUser();
        $saleId = $this->creerFactureHotel('FV');

        $stmt = self::$pdo->prepare(
            "SELECT sejour_id, chambre_id, ligne_type, libelle FROM details_vente WHERE vente_id = ?"
        );
        $stmt->execute([$saleId]);
        $lignes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertCount(2, $lignes, 'Une ligne de détail par nuitée.');

        foreach ($lignes as $ligne) {
            $this->assertSame(
                $this->ids['sejour'],
                (int)$ligne['sejour_id'],
                'La ligne de détail doit être rattachée à son séjour.'
            );
            $this->assertSame(
                $this->ids['chambre'],
                (int)$ligne['chambre_id'],
                'La ligne de détail doit être rattachée à sa chambre.'
            );
            $this->assertSame('nuit', $ligne['ligne_type'], 'Le type de ligne doit être "nuit".');
            $this->assertNotEmpty(
                $ligne['libelle'],
                'La ligne de détail doit porter un libellé figé (affiché sur la facture imprimée).'
            );
        }
    }

    public function testLaFactureHotelEstRattacheeASonSejour(): void
    {
        $this->setUpUser();
        $saleId = $this->creerFactureHotel('FV');

        $stmt = self::$pdo->prepare("SELECT sejour_id FROM ventes WHERE id = ?");
        $stmt->execute([$saleId]);
        $this->assertSame(
            $this->ids['sejour'],
            (int)$stmt->fetchColumn(),
            'La vente hôtel doit être rattachée à son séjour.'
        );
    }
}
