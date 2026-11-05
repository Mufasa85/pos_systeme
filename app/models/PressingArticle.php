<?php

namespace App\Models;

/**
 * Vetements d'un dossier de pressing (`pressing_articles`).
 *
 * Chaque ligne represente un type de vetement depose (chemise, costume,
 * robe…) avec son prix, son delai de traitement et son etat d'avancement
 * (etape 0 → 6, cf. PressingCommande::ETAPES).
 *
 * Le prix unitaire stocke est le prix FINAL : le coefficient (majoration
 * express, traitement delicat) est deja applique a la creation. C'est
 * verifie sur les donnees reelles : une facture DGI de 56 000 avec un
 * coefficient de 1.25 donne un prix unitaire de 70 000.
 */
class PressingArticle
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    /**
     * Insere un vetement dans un dossier.
     *
     * @param int    $commandeId Id du dossier
     * @param array  $data       Donnees du vetement
     *
     * @return int Id du vetement cree
     */
    public function create(int $commandeId, array $data): int
    {
        $this->db->execute(
            'INSERT INTO pressing_articles
                (commande_id, type_vetement, couleur, quantite, prix_unitaire,
                 coefficient, observation, delai_type, delai_heures, date_dispo, etape)
             VALUES (:cmd, :type, :coul, :qte, :prix, :coef, :obs, :dt, :h, :dispo, :etape)',
            [
                ':cmd'   => $commandeId,
                ':type'  => (string)($data['type_vetement'] ?? ''),
                ':coul'  => (string)($data['couleur'] ?? 'Gris'),
                ':qte'   => max(1, (int)($data['quantite'] ?? 1)),
                ':prix'  => max(0, (float)($data['prix_unitaire'] ?? 0)),
                ':coef'  => max(0, (float)($data['coefficient'] ?? 1)),
                ':obs'   => substr(trim((string)($data['observation'] ?? '')), 0, 255),
                ':dt'    => in_array($data['delai_type'] ?? '', ['normal', 'express', 'perso'], true)
                                ? $data['delai_type'] : 'normal',
                ':h'     => max(1, (int)($data['delai_heures'] ?? PressingCommande::DELAIS['normal'])),
                ':dispo' => $data['date_dispo'] ?? null,
                ':etape' => (int)($data['etape'] ?? 0),
            ]
        );

        return (int)$this->db->lastInsertId();
    }

    /**
     * Recupere un vetement par son id.
     *
     * @param int $id
     *
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        $row = $this->db->fetch(
            'SELECT * FROM pressing_articles WHERE id = ?',
            [$id]
        );

        return $row ?: null;
    }

    /**
     * Met a jour l'etape d'un vetement.
     *
     * @param int $id
     * @param int $etape Nouvelle etape (0 → 6)
     *
     * @return bool
     */
    public function setEtape(int $id, int $etape): bool
    {
        return $this->db->execute(
            'UPDATE pressing_articles SET etape = ? WHERE id = ?',
            [$etape, $id]
        );
    }

    /**
     * Types de vetements deja utilises dans la boutique : alimente la liste
     * deroulante du formulaire de creation (choix rapide).
     *
     * @param int|null $shopId
     *
     * @return array Liste de noms distincts
     */
    public function typesConnus(?int $shopId): array
    {
        $sql = 'SELECT DISTINCT a.type_vetement
                FROM pressing_articles a
                INNER JOIN pressing_commandes c ON c.id = a.commande_id';
        $params = [];

        if ($shopId) {
            $sql .= ' WHERE c.shop_id = ?';
            $params[] = $shopId;
        }

        $sql .= ' ORDER BY a.type_vetement ASC';

        $rows = $this->db->fetchAll($sql, $params);

        return array_column($rows, 'type_vetement');
    }
}
