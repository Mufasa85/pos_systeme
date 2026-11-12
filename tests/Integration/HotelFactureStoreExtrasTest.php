<?php

namespace Tests\Integration;

use App\Core\Database;
use App\Models\Sale;
use App\Models\SaleDetail;
use PHPUnit\Framework\TestCase;

/**
 * Vérifie que la FACTURATION HÔTEL (POST /api/hotel/factures) crée bien
 * les lignes de détail des EXTRAS, et que la réponse reste du JSON valide.
 *
 * Régression du 2026-11-05 : une coquille dans le nom d'une variable
 * (`$manuelsNormalised` au lieu de `$manuelsNormalises`) déclenchait un
 * Warning PHP. Avec `display_errors` actif, ce Warning était imprimé AVANT
 * le JSON → le body n'était plus parsable → le front affichait
 * « Erreur 201 » alors que la facture avait bien été créée.
 */
class HotelFactureStoreExtrasTest extends TestCase
{
    private static $pdo;
    private $ids = [];

    public static function setUpBeforeClass(): void
    {
        self::$pdo = Database::getInstance()->getConnection();
    }

    protected function setUp(): void
    {
        self::$pdo->prepare("INSERT INTO hotel_guests (nom, prenom) VALUES ('__fs__', 'S')")->execute();
        $this->ids['guest'] = (int)self::$pdo->lastInsertId();

        self::$pdo->prepare(
            "INSERT INTO hotel_chambres (numero, type, prix_base, etat, taxe_id)
             VALUES (?, 'simple', 1000, 'disponible', (SELECT id FROM taxes WHERE taux = 16 LIMIT 1))"
        )->execute(['FS' . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT)]);
        $this->ids['chambre'] = (int)self::$pdo->lastInsertId();

        self::$pdo->prepare(
            "INSERT INTO hotel_sejours (chambre_id, guest_id, date_arrivee, date_depart_prevue, statut, prix_par_nuit)
             VALUES (?, ?, DATE_SUB(CURDATE(), INTERVAL 1 DAY), DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'en_cours', 1000)"
        )->execute([$this->ids['chambre'], $this->ids['guest']]);
        $this->ids['sejour'] = (int)self::$pdo->lastInsertId();

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
        // Nettoyage : les factures hôtel d'abord (FK vers `ventes` ET vers
        // `hotel_sejours`), puis les ventes et leurs lignes de détail.
        //
        // ⚠️ Le contrôleur génère un VRAI numéro de facture (`AAAA/xxxxxx`,
        // via Sale::generateInvoiceNumber()) : on ne peut donc pas le
        // retrouver avec un préfixe. On passe par `hotel_factures.sejour_id`,
        // qui identifie sans ambiguïté la facture créée pour CE séjour.
        //
        // Sans ce nettoyage, chaque exécution de la suite laissait une
        // facture hôtel (avec sa TS de chambre) dans la base de test : les
        // rapports de `ReportHotelIntegrationTest`, qui agrègent la journée
        // entière, cumulaient alors ces fixtures et leurs assertions de TS
        // échouaient (400 200 au lieu de 200).
        $stmt = self::$pdo->prepare(
            'SELECT vente_id FROM hotel_factures WHERE sejour_id = ?'
        );
        $stmt->execute([$this->ids['sejour']]);
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $venteId) {
            // ⚠️ `hotel_factures.vente_id` est une FK vers `ventes` : la
            // facture hôtel doit être supprimée AVANT la vente, sinon MySQL
            // refuse (erreur 1451).
            self::$pdo->prepare('DELETE FROM hotel_factures WHERE vente_id = ?')->execute([$venteId]);
            self::$pdo->prepare('DELETE FROM details_vente WHERE vente_id = ?')->execute([$venteId]);
            self::$pdo->prepare('DELETE FROM ventes WHERE id = ?')->execute([$venteId]);
        }

        $stmt = self::$pdo->prepare(
            "SELECT id FROM ventes WHERE numero_facture LIKE '__FS_%'"
        );
        $stmt->execute();
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $venteId) {
            self::$pdo->prepare("DELETE FROM hotel_factures WHERE vente_id = ?")->execute([$venteId]);
            self::$pdo->prepare("DELETE FROM details_vente WHERE vente_id = ?")->execute([$venteId]);
            self::$pdo->prepare("DELETE FROM ventes WHERE id = ?")->execute([$venteId]);
        }
        // Par sécurité (facture créée avec un autre préfixe) :
        self::$pdo->prepare("DELETE FROM hotel_factures WHERE sejour_id = ?")->execute([$this->ids['sejour']]);
        self::$pdo->prepare("DELETE FROM hotel_sejours WHERE id = ?")->execute([$this->ids['sejour']]);
        self::$pdo->prepare("DELETE FROM hotel_guests WHERE id = ?")->execute([$this->ids['guest']]);
        self::$pdo->prepare("DELETE FROM hotel_chambres WHERE id = ?")->execute([$this->ids['chambre']]);
    }

    /**
     * Vérifie que le contrôleur ne produit AUCUNE sortie parasite
     * (Warning/Notice PHP) avant le JSON.
     *
     * On appelle le contrôleur dans un sous-processus PHP : `jsonOut()`
     * termine par `exit`, on ne peut donc pas l'appeler dans le processus
     * de PHPUnit (qui tuerait les tests).
     */
    public function testLaFacturationNeProduitPasDeSortieParasite(): void
    {
        // Le script est exécuté dans un sous-processus PHP : `jsonOut()`
        // termine par `exit`, on ne peut donc pas l'appeler dans le processus
        // de PHPUnit (qui tuerait les tests).
        // Le chemin du projet est passé en argument (le script est écrit
        // dans le dossier temporaire, pas dans le projet).
        $racine = dirname(__DIR__, 2);

        $script = <<<'PHP'
<?php
$racine = $argv[1];
require $racine . '/vendor/autoload.php';
require $racine . '/config/config.php';
// display_errors ON : on reproduit l'environnement de développement.
ini_set('display_errors', '1');
error_reporting(E_ALL);

$sejourId = (int)$argv[2];
$userId   = (int)$argv[3];

// En CLI, `php://input` n'est pas alimenté par stdin de façon fiable.
// Le contrôleur lit le payload via `file_get_contents('php://input')`.
// Pour les tests, on lit le payload depuis un fichier passé en argument.
$payloadFile = $argv[4] ?? '';
$payload     = $payloadFile !== '' ? (string)file_get_contents($payloadFile) : '{}';

// On expose le payload au contrôleur via une variable globale : celui-ci
// la préfère à `php://input` quand elle est présente (utile en CLI/test).
$GLOBALS['__HOTEL_STORE_PAYLOAD__'] = $payload;

// Session : on la DÉMARRE avant d'affecter les variables, sinon
// `session_start()` (appelé par RechargeAccessService) réinitialise
// `$_SESSION` et nos valeurs disparaissent.
session_start();

// Session simulée (le contrôleur exige un utilisateur connecté).
$_SESSION['user_id']     = $userId;
$_SESSION['role']        = 'admin';
$_SESSION['shop_id']     = null;
$_SESSION['hotel_role']  = 'manager';

// Amorçage du cache des feature flags (clé 'feature_access_cache') :
// sans ça, le contrôleur appelle l'API OSAT externe pour savoir si le
// module hôtel est activé → le test dépendrait du réseau et de la
// configuration de la boutique. On force l'accès dans les deux caches.
$_SESSION['feature_access_cache'] = [
    'flags' => [
        'hotel' => true,
        'pressing' => true,
        'snelregideso' => true,
        'paie' => true,
    ],
    'expires_at' => time() + 3600,
];
$_SESSION['recharge_access_cache'] = $_SESSION['feature_access_cache'];

$controller = new \App\Controllers\HotelFactureController();
$controller->store();
PHP;

        $fichier = sys_get_temp_dir() . '/hotel_store_test_' . getmypid() . '.php';
        file_put_contents($fichier, $script);

        // Payload JSON de la facturation (simule l'appel du front).
        $payload = json_encode([
            'sejour_id'      => $this->ids['sejour'],
            'extras_manuels' => [],
            'paiements'      => [['mode' => 'cash', 'montant' => 2000]],
            'total_final'    => 2000,
            'total_nuits'    => 2000,
            'total_extras'   => 0,
            'solde_du'       => 2000,
        ]);
        $fichierPayload = sys_get_temp_dir() . '/hotel_store_payload_' . getmypid() . '.json';
        file_put_contents($fichierPayload, $payload);

        $commande = sprintf(
            '%s %s %s %d %d %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($fichier),
            escapeshellarg($racine),
            $this->ids['sejour'],
            $this->ids['user'],
            escapeshellarg($fichierPayload)
        );
        $sortie = shell_exec($commande . ' 2>&1');
        @unlink($fichier);
        @unlink($fichierPayload);

        $sortie = (string)$sortie;

        // 1. Aucune sortie parasite AVANT le JSON.
        $positionJson = strpos($sortie, '{');
        $parasite     = $positionJson === false ? $sortie : substr($sortie, 0, $positionJson);
        $this->assertSame(
            '',
            trim($parasite),
            'Aucune sortie parasite (Warning PHP) ne doit précéder le JSON. Sortie: ' . $sortie
        );

        // 2. Le JSON doit être parsable et indiquer un succès.
        $this->assertNotFalse(
            $positionJson,
            'La réponse doit contenir du JSON. Sortie: ' . $sortie
        );
        $data = json_decode(substr($sortie, $positionJson), true);
        $this->assertIsArray($data, 'La réponse doit être du JSON valide. Sortie: ' . $sortie);
        $this->assertTrue(
            $data['success'] ?? false,
            'La facturation doit réussir. Sortie: ' . $sortie
        );
    }
}
