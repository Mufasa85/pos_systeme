<?php

namespace Tests\Integration;

use App\Core\Database;
use App\Models\User;
use PHPUnit\Framework\TestCase;

/**
 * Suppression d'un utilisateur référencé par le module Hôtel et la paie.
 *
 * Régression du bug « Integrity constraint violation: 1451 » :
 * `hotel_extras_user_fk` (et ses soeurs) étaient en `ON DELETE NO ACTION`,
 * ce qui rendait impossible la suppression d'un utilisateur lié à l'hôtel.
 *
 * La migration 2026_10_25_user_delete_fk_set_null.sql passe toutes ces clés
 * en `ON DELETE SET NULL` : l'utilisateur est supprimé, mais son HISTORIQUE
 * (séjours, extras, factures fiscales, bulletins de paie) est CONSERVÉ.
 */
class UserDeletionTest extends TestCase
{
    private static $pdo;
    private $userId;
    private $ids = [];

    public static function setUpBeforeClass(): void
    {
        self::$pdo = Database::getInstance()->getConnection();
    }

    protected function setUp(): void
    {
        // Shop de rattachement (FK `utilisateurs.shop_id`). La base de test
        // peut être vide : on crée le shop s'il n'existe pas.
        $shop = self::$pdo->query('SELECT id FROM shops ORDER BY id LIMIT 1')->fetch();
        if (!$shop) {
            self::$pdo->prepare(
                "INSERT INTO shops (nom, code, actif) VALUES ('Shop Test', 'TSTSHOP', 1)"
            )->execute();
            $shop = self::$pdo->query('SELECT id FROM shops ORDER BY id LIMIT 1')->fetch();
        }
        if (!$shop) {
            self::markTestSkipped('Impossible de créer un shop en base de test.');
        }
        $this->ids['shop'] = (int)$shop['id'];

        self::$pdo->prepare(
            "INSERT INTO utilisateurs (nom_utilisateur, mot_de_passe, nom_complet, role, hotel_role, shop_id, actif)
             VALUES ('__test_del__', 'x', 'Test Suppression', 'vendeur', 'receptionniste', ?, 1)"
        )->execute([$this->ids['shop']]);
        $this->userId = (int)self::$pdo->lastInsertId();

        // ── Données hôtel rattachées ──────────────────────────────────────
        self::$pdo->prepare("INSERT INTO hotel_guests (nom, prenom) VALUES ('__tg__', 'T')")->execute();
        $this->ids['guest'] = (int)self::$pdo->lastInsertId();

        self::$pdo->prepare(
            "INSERT INTO hotel_chambres (numero, type, prix_base, etat) VALUES ('TSTCH01', 'simple', 1000, 'disponible')"
        )->execute();
        $this->ids['chambre'] = (int)self::$pdo->lastInsertId();

        self::$pdo->prepare(
            "INSERT INTO hotel_sejours (chambre_id, guest_id, date_arrivee, date_depart_prevue, statut, prix_par_nuit, user_id)
             VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 1 DAY), 'en_cours', 1000, ?)"
        )->execute([$this->ids['chambre'], $this->ids['guest'], $this->userId]);
        $this->ids['sejour'] = (int)self::$pdo->lastInsertId();

        self::$pdo->prepare(
            "INSERT INTO hotel_extras (sejour_id, description, montant, user_id) VALUES (?, 'Extra test', 500, ?)"
        )->execute([$this->ids['sejour'], $this->userId]);
        $this->ids['extra'] = (int)self::$pdo->lastInsertId();

        self::$pdo->prepare(
            "INSERT INTO hotel_paiements (sejour_id, montant, mode_paiement, user_id) VALUES (?, 500, 'cash', ?)"
        )->execute([$this->ids['sejour'], $this->userId]);
        $this->ids['paiement'] = (int)self::$pdo->lastInsertId();

        // ── Une facture fiscale POS ────────────────────────────────────────
        self::$pdo->prepare(
            "INSERT INTO ventes (numero_facture, invoice_type, sous_total_ht, tva, total, vendeur_id, shop_id, date)
             VALUES ('__TEST_DEL_F__', 'FV', 1000, 0, 1000, ?, ?, NOW())"
        )->execute([$this->userId, $this->ids['shop']]);
        $this->ids['vente'] = (int)self::$pdo->lastInsertId();

        // ── Une fiche employé ──────────────────────────────────────────────
        self::$pdo->prepare(
            "INSERT INTO payroll_employees (user_id, shop_id, matricule, hire_date) VALUES (?, ?, 'TSTM001', CURDATE())"
        )->execute([$this->userId, $this->ids['shop']]);
        $this->ids['employe'] = (int)self::$pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        // Nettoyage (l'utilisateur a normalement déjà été supprimé par le test)
        foreach ([
            'payroll_employees' => 'employe',
            'ventes'            => 'vente',
            'hotel_paiements'   => 'paiement',
            'hotel_extras'      => 'extra',
            'hotel_sejours'     => 'sejour',
            'hotel_chambres'    => 'chambre',
            'hotel_guests'      => 'guest',
            'utilisateurs'      => 'userId',
        ] as $table => $key) {
            $id = $this->ids[$key] ?? $this->userId;
            self::$pdo->prepare("DELETE FROM `$table` WHERE id = ?")->execute([$id]);
        }
    }

    public function testSuppressionReussitMalgreLesReferencesHotel(): void
    {
        $result = (new User())->delete($this->userId);

        $this->assertTrue($result['success'], 'La suppression doit aboutir : ' . ($result['message'] ?? ''));
    }

    public function testLHistoriqueHotelEstConserve(): void
    {
        (new User())->delete($this->userId);

        foreach ([
            'hotel_sejours'   => $this->ids['sejour'],
            'hotel_extras'    => $this->ids['extra'],
            'hotel_paiements' => $this->ids['paiement'],
        ] as $table => $id) {
            $st = self::$pdo->prepare("SELECT COUNT(*) c FROM `$table` WHERE id = ?");
            $st->execute([$id]);
            $this->assertSame(1, (int)$st->fetch()['c'], "La ligne $table doit être conservée.");
        }
    }

    public function testLaFactureFiscaleEstConservee(): void
    {
        (new User())->delete($this->userId);

        $st = self::$pdo->prepare('SELECT COUNT(*) c FROM ventes WHERE id = ?');
        $st->execute([$this->ids['vente']]);
        $this->assertSame(1, (int)$st->fetch()['c'], 'La facture fiscale ne doit jamais être supprimée.');
    }

    public function testLHistoriqueDePaieEstConserve(): void
    {
        (new User())->delete($this->userId);

        $st = self::$pdo->prepare('SELECT COUNT(*) c FROM payroll_employees WHERE id = ?');
        $st->execute([$this->ids['employe']]);
        $this->assertSame(1, (int)$st->fetch()['c'], 'La fiche employé doit être conservée.');
    }

    public function testLAuteurEstDetacheNullable(): void
    {
        (new User())->delete($this->userId);

        foreach ([
            ['hotel_sejours', 'user_id', $this->ids['sejour']],
            ['hotel_extras', 'user_id', $this->ids['extra']],
            ['hotel_paiements', 'user_id', $this->ids['paiement']],
            ['ventes', 'vendeur_id', $this->ids['vente']],
            ['payroll_employees', 'user_id', $this->ids['employe']],
        ] as [$table, $col, $id]) {
            $st = self::$pdo->prepare("SELECT `$col` v FROM `$table` WHERE id = ?");
            $st->execute([$id]);
            $this->assertNull(
                $st->fetch()['v'],
                "$table.$col doit valoir NULL après suppression de l'utilisateur."
            );
        }
    }

    public function testUtilisateurSupprimeDeLaTable(): void
    {
        (new User())->delete($this->userId);

        $st = self::$pdo->prepare('SELECT COUNT(*) c FROM utilisateurs WHERE id = ?');
        $st->execute([$this->userId]);
        $this->assertSame(0, (int)$st->fetch()['c']);
    }

    public function testSupprimerUnUtilisateurInexistantEchoueSansException(): void
    {
        $result = (new User())->delete(999999999);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);
    }
}
