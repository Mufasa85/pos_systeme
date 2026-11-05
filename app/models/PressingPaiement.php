<?php

namespace App\Models;

/**
 * Encaissements pressing (`pressing_paiements`).
 *
 * Le pressing a SON PROPRE flux d'encaissement, distinct du paiement de la
 * vente POS (`ventes.payments`) :
 *   - le total d'un dossier = prix unitaire × coefficient, qui differe du
 *     montant de la vente POS et du total de la facture DGI ;
 *   - le solde d'un dossier (total − paiements pressing) conditionne la
 *     remise du linge au client : le pressing ne rend pas un vetement non
 *     paye.
 *
 * Verifie sur les donnees reelles : un dossier d'une facture DGI de 56 000
 * (prix avec coefficient : 70 000) a ete encaisse de 70 000 au pressing.
 */
class PressingPaiement
{
    /**
     * Modes de paiement acceptes, aux constantes du POS.
     *
     * L appli autonome utilisait des libelles accentues ('Especes',
     * 'Mobile Money', 'Virement') ; le POS utilise des constantes
     * majuscules, lues par ReportService et facture-ticket.php.
     */
    public const MODES = ['ESPECES', 'MOBILEMONEY', 'VIREMENT'];

    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    /**
     * Enregistre un encaissement sur un dossier.
     *
     * @param int   $commandeId Id du dossier
     * @param float $montant    Montant (> 0)
     * @param string $mode       Mode de paiement (ESPECES|MOBILEMONEY|VIREMENT)
     * @param int   $userId     Agent ayant encaisse
     *
     * @return array{success:bool,message?:string,paye?:float,total?:float,solde?:float}
     */
    public function create(int $commandeId, float $montant, string $mode, int $userId): array
    {
        if ($montant <= 0) {
            return ['success' => false, 'message' => 'Montant invalide'];
        }

        if (!in_array($mode, self::MODES, true)) {
            $mode = 'ESPECES';
        }

        $this->db->execute(
            'INSERT INTO pressing_paiements (commande_id, montant, mode, user_id, date_paiement)
             VALUES (:cmd, :montant, :mode, :uid, NOW())',
            [
                ':cmd'     => $commandeId,
                ':montant' => $montant,
                ':mode'    => $mode,
                // user_id = 0 violerait la FK vers `utilisateurs` : on le
                // laisse NULL (la colonne est nullable, ON DELETE SET NULL).
                ':uid'     => $userId > 0 ? $userId : null,
            ]
        );

        $paye = (float)$this->db->fetch(
            'SELECT COALESCE(SUM(montant), 0) AS m FROM pressing_paiements WHERE commande_id = ?',
            [$commandeId]
        )['m'];

        $total = (float)$this->db->fetch(
            'SELECT COALESCE(SUM(quantite * prix_unitaire), 0) AS m
             FROM pressing_articles WHERE commande_id = ?',
            [$commandeId]
        )['m'];

        return [
            'success' => true,
            'paye'    => $paye,
            'total'   => $total,
            'solde'   => $total - $paye,
        ];
    }

    /**
     * Total encaisse sur un dossier.
     *
     * @param int $commandeId
     *
     * @return float
     */
    public function totalEncaisse(int $commandeId): float
    {
        return (float)$this->db->fetch(
            'SELECT COALESCE(SUM(montant), 0) AS m FROM pressing_paiements WHERE commande_id = ?',
            [$commandeId]
        )['m'];
    }
}
