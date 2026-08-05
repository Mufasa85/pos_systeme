<?php

namespace App\Models;

// app/models/Sale.php

class Sale
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function create($data)
    {
        $sql = "INSERT INTO ventes (numero_facture, client_id, sous_total_ht, tva, total, payments, vendeur_id, shop_id, store_isf, date, dateDGI, qrCode, codeDEFDGI, counters, nim, comment, service) 
                VALUES (:numero_facture, :client_id, :sous_total_ht, :tva, :total, :payments, :vendeur_id, :shop_id, :store_isf, :date, :dateDGI, :qrCode, :codeDEFDGI, :counters, :nim, :comment, :service)";
        $this->db->query($sql, [
            ':numero_facture' => $data['numero_facture'],
            ':client_id'      => $data['client_id'] ?? null,
            ':sous_total_ht'  => $data['sous_total_ht'],
            ':tva'            => $data['tva'],
            ':total'          => $data['total'],
            ':payments'       => isset($data['payments']) ? json_encode($data['payments']) : null,
            ':vendeur_id'     => $data['vendeur_id'],
            ':shop_id'        => $data['shop_id'] ?? null,
            ':store_isf'      => $data['store_isf'] ?? null,
            ':date'           => $data['date'],
            ':dateDGI'        => $data['dateDGI'] ?? null,
            ':qrCode'         => $data['qrCode'] ?? null,
            ':codeDEFDGI'     => $data['codeDEFDGI'] ?? null,
            ':counters'       => $data['counters'] ?? null,
            ':nim'            => $data['nim'] ?? null,
            ':comment'        => $data['comment'] ?? null,
            ':service'        => $data['service'] ?? null
        ]);
        return $this->db->getConnection()->lastInsertId();
    }

    public function getAllSales($shopId = null)
    {
        $where = '';
        $params = [];
        if ($shopId) {
            $where = 'WHERE v.shop_id = ?';
            $params[] = $shopId;
        }
        $sql = "SELECT v.*, u.nom_complet as nom_vendeur, 
                       c.nom_client, c.code_client, c.numero as client_numero, c.nif as client_nif,
                       tc.code as client_type_code
                 FROM ventes v 
                 LEFT JOIN utilisateurs u ON v.vendeur_id = u.id 
                 LEFT JOIN clients c ON v.client_id = c.id
                 LEFT JOIN type_client tc ON c.type_client_id = tc.id
                 $where
                 ORDER BY v.date DESC";
        return $this->db->fetchAll($sql, $params);
    }

    public function generateInvoiceNumber()
    {
        // Format: AAAA/xxxxxx (ex: 2026/000001) - Compteur global annuel
        $year = date('Y');

        // Récupérer le dernier numéro de l'année en cours
        $sql = "SELECT numero_facture FROM ventes 
                WHERE YEAR(date) = YEAR(CURDATE())
                ORDER BY id DESC LIMIT 1";
        $last = $this->db->fetch($sql);

        if ($last && preg_match('/^(\d{4})\/(\d+)$/', $last['numero_facture'], $m)) {
            $seq = intval($m[2]) + 1;
        } else {
            $seq = 1;
        }

        return $year . '/' . str_pad($seq, 6, '0', STR_PAD_LEFT);
    }

    public function exist($id)
    {
        $sql = "SELECT v.*, u.nom_complet as nom_vendeur, 
                       c.nom_client, c.code_client, c.numero as client_numero, c.nif as client_nif, c.adresse as client_adresse,
                       tc.code as client_type_code
                FROM ventes v 
                LEFT JOIN utilisateurs u ON v.vendeur_id = u.id 
                LEFT JOIN clients c ON v.client_id = c.id
                LEFT JOIN type_client tc ON c.type_client_id = tc.id
                WHERE v.id = ?";
        return $this->db->fetch($sql, [$id]);
    }

    /**
     * Find a sale by invoice number (numero_facture)
     */
    public function findByInvoiceNumber($invoiceNumber)
    {
        $sql = "SELECT v.*, u.nom_complet as nom_vendeur 
                FROM ventes v 
                LEFT JOIN utilisateurs u ON v.vendeur_id = u.id 
                WHERE v.numero_facture = ?";
        return $this->db->fetch($sql, [$invoiceNumber]);
    }

    public function searchArchive($shopId = null, $limit = 100, $offset = 0)
    {
        $where = '';
        $params = [];
        if ($shopId) {
            $where = 'WHERE va.shop_id = ?';
            $params[] = $shopId;
        }
        $params[] = (int)$limit;
        $params[] = (int)$offset;

        $sql = "SELECT va.*, u.nom_complet as nom_vendeur,
                       c.nom_client, c.code_client
                FROM ventes_archive va
                LEFT JOIN utilisateurs u ON va.vendeur_id = u.id
                LEFT JOIN clients c ON va.client_id = c.id
                $where
                ORDER BY va.date DESC
                LIMIT ? OFFSET ?";
        return $this->db->fetchAll($sql, $params);
    }

    public function delete($id)
    {
        return $this->db->execute("DELETE FROM ventes WHERE id = ?", [$id]);
    }

    public function getDetailsBySaleId($saleId)
    {
        $sql = "SELECT dv.*, p.nom as produit_nom, p.code_barres, p.stock
                FROM details_vente dv
                LEFT JOIN produits p ON dv.produit_id = p.id
                WHERE dv.vente_id = ?";
        return $this->db->fetchAll($sql, [$saleId]);
    }
}
