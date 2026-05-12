<?php

/**
 * BillPaymentController.php
 * API pour le paiement de factures (SNEL/REGIDESO)
 * Option A: Utilise table `ventes` modifiée
 */

require_once __DIR__ . '/../../core/Database.php';

class BillPaymentController
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    // ==========================================
    // API: Fetch Bill Inquiry (appelle OSAT-Energie pour éviter CORS)
    // ==========================================

    public function fetchBillInquiry()
    {
        try {
            $compteur = trim($_GET['compteur'] ?? '');
            $service = trim($_GET['service'] ?? '');

            if (empty($compteur) || empty($service)) {
                throw new Exception('Paramètres manquants: compteur et service requis');
            }

            // Appel API OSAT-Energie (côté serveur pour éviter CORS)
            $url = 'https://osat-energie.com/json.php?compteur=' . urlencode($compteur) . '&service=' . urlencode($service);

            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 30,
                    'ignore_errors' => true
                ]
            ]);

            $response = file_get_contents($url, false, $context);

            if ($response === false) {
                throw new Exception('Erreur de connexion à l\'API OSAT-Energie');
            }

            $data = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Réponse API invalide');
            }

            return [
                'success' => true,
                'data' => $data
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    // ==========================================
    // API: Traiter le paiement
    // ==========================================

    public function processPayment()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            // Validation
            if (empty($data['provider_id']) || empty($data['numero_compteur'])) {
                throw new Exception('Données incomplètes');
            }

            $providerId = intval($data['provider_id']);
            $numeroCompteur = trim($data['numero_compteur']);
            $clientNom = trim($data['client_nom'] ?? '');
            $clientReference = trim($data['client_reference'] ?? '');
            $months = $data['months'] ?? [];
            $totalMontant = floatval($data['total_montant'] ?? 0);
            $nombreMois = intval($data['nombre_mois'] ?? count($months));
            $methodePaiement = $data['methode_paiement'] ?? 'cash';
            $vendeurId = intval($data['vendeur_id'] ?? $_SESSION['user_id'] ?? 1);
            $clientId = intval($data['client_id'] ?? null);
            $apiResponse = json_encode($data['api_response'] ?? null);

            // Vérifier que table ventes a les colonnes
            $this->ensureVentesColumns();

            // Générer numéro facture
            $numeroFacture = $this->generateNumeroFacture();

            // Insert vente (type bill_payment)
            $stmt = $this->db->prepare("
                INSERT INTO ventes 
                (numero_facture, type_vente, provider_id, numero_compteur, client_reference, client_nom, 
                 client_id, vendeur_id, methode_paiement, sous_total_ht, tva, total, api_response)
                VALUES (?, 'bill_payment', ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)
            ");
            $stmt->execute([
                $numeroFacture,
                $providerId,
                $numeroCompteur,
                $clientReference,
                $clientNom,
                $clientId,
                $vendeurId,
                $methodePaiement,
                $totalMontant,
                $totalMontant,
                $apiResponse
            ]);
            $venteId = $this->db->lastInsertId();

            // Insert details_vente pour chaque mois
            $stmtDetail = $this->db->prepare("
                INSERT INTO details_vente (vente_id, produit_id, quantite, prix, comment)
                VALUES (?, NULL, 1, ?, ?)
            ");

            foreach ($months as $month) {
                $moisAnnee = $this->formatMoisAnnee($month['annee'], $month['mois']);
                $stmtDetail->execute([
                    $venteId,
                    floatval($month['montant']),
                    $moisAnnee
                ]);
            }

            // Optionnel: Envoyer à l'API du provider (REGIDESO/SNEL)
            $this->sendToProviderAPI($providerId, $numeroCompteur, $clientReference, $months);

            return [
                'success' => true,
                'data' => [
                    'vente_id' => $venteId,
                    'numero_transaction' => $numeroFacture,
                    'provider_id' => $providerId,
                    'numero_compteur' => $numeroCompteur,
                    'total_montant' => $totalMontant,
                    'nombre_mois' => $nombreMois,
                    'date' => date('Y-m-d H:i:s')
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    // ==========================================
    // API: Liste des paiements factures
    // ==========================================

    public function getBillPayments()
    {
        try {
            $stmt = $this->db->prepare("
                SELECT v.*, sp.nom as provider_nom, sp.code as provider_code
                FROM ventes v
                LEFT JOIN service_providers sp ON v.provider_id = sp.id
                WHERE v.type_vente = 'bill_payment'
                ORDER BY v.id DESC
                LIMIT 50
            ");
            $stmt->execute();
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $payments
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    // ==========================================
    // API: Détails d'un paiement
    // ==========================================

    public function getPaymentDetails($venteId)
    {
        try {
            // Récupérer la vente
            $stmt = $this->db->prepare("
                SELECT v.*, sp.nom as provider_nom, sp.code as provider_code
                FROM ventes v
                LEFT JOIN service_providers sp ON v.provider_id = sp.id
                WHERE v.id = ? AND v.type_vente = 'bill_payment'
            ");
            $stmt->execute([$venteId]);
            $vente = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$vente) {
                throw new Exception('Paiement non trouvé');
            }

            // Récupérer les détails (mois)
            $stmtDetails = $this->db->prepare("
                SELECT * FROM details_vente WHERE vente_id = ?
            ");
            $stmtDetails->execute([$venteId]);
            $vente['items'] = $stmtDetails->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $vente
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    // ==========================================
    // HELPERS
    // ==========================================

    private function columnExists($table, $column)
    {
        $stmt = $this->db->prepare("SHOW COLUMNS FROM $table LIKE ?");
        $stmt->execute([$column]);
        return $stmt->rowCount() > 0;
    }

    private function ensureVentesColumns()
    {
        $columns = [
            'type_vente' => "ADD COLUMN type_vente ENUM('product', 'bill_payment') DEFAULT 'product'",
            'provider_id' => "ADD COLUMN provider_id INT NULL",
            'numero_compteur' => "ADD COLUMN numero_compteur VARCHAR(50) NULL",
            'client_reference' => "ADD COLUMN client_reference VARCHAR(100) NULL",
            'api_response' => "ADD COLUMN api_response TEXT NULL"
        ];

        foreach ($columns as $col => $alter) {
            if (!$this->columnExists('ventes', $col)) {
                $this->db->exec("ALTER TABLE ventes $alter");
            }
        }
    }

    private function generateNumeroFacture()
    {
        $stmt = $this->db->query("SELECT MAX(CAST(SUBSTRING(numero_facture, 5) AS UNSIGNED)) as max_num FROM ventes");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $next = intval($row['max_num'] ?? 0) + 1;
        return 'FAC-' . str_pad($next, 6, '0', STR_PAD_LEFT);
    }

    private function formatMoisAnnee($annee, $mois)
    {
        $moisNoms = [
            '',
            'Janvier',
            'Février',
            'Mars',
            'Avril',
            'Mai',
            'Juin',
            'Juillet',
            'Août',
            'Septembre',
            'Octobre',
            'Novembre',
            'Décembre'
        ];
        return $moisNoms[$mois] . ' ' . $annee;
    }

    private function sendToProviderAPI($providerId, $numeroCompteur, $clientReference, $months)
    {
        // SIMULATION - Implémenter le vrai appel API
        // Cette fonction envoie les données au provider (SNEL/REGIDESO)

        // Exemple pour REGIDESO:
        // $stmt = $this->db->prepare("SELECT * FROM service_providers WHERE id = ?");
        // $stmt->execute([$providerId]);
        // $provider = $stmt->fetch(PDO::FETCH_ASSOC);
        // 
        // $payload = [
        //     'compteur' => $numeroCompteur,
        //     'reference_client' => $clientReference,
        //     'mois' => $months
        // ];
        // 
        // $response = file_get_contents($provider['api_endpoint'], ...);

        return true;
    }
}

// ==========================================
// ROUTING API
// ==========================================

if (basename($_SERVER['REQUEST_URI']) === 'bill-payment') {
    header('Content-Type: application/json');

    $controller = new BillPaymentController();
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    switch ($action) {
        case 'process':
            echo json_encode($controller->processPayment());
            break;

        case 'list':
            echo json_encode($controller->getBillPayments());
            break;

        case 'get':
            $id = intval($_GET['id'] ?? 0);
            echo json_encode($controller->getPaymentDetails($id));
            break;

        case 'fetch':
            echo json_encode($controller->fetchBillInquiry());
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
    }
}
