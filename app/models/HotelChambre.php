<?php

namespace App\Models;

/**
 * Gestion des chambres hôtel (`hotel_chambres`).
 *
 * Garde :
 *  - numero UNIQUE (contrainte BDD) — on remonte l'erreur si doublon.
 *  - Le type est limité à ENUM('simple','double').
 *  - L'état (`etat`) est pilotable d'ici :
 *      disponible  : chambre prête à être louée
 *      a_nettoyer  : à nettoyer (gouvernante)
 *      hors_service: inutilisable (maintenance, panne)
 *    Note : l'état `occupée` n'existe pas dans l'enum actuel — c'est
 *    l'existence d'un séjour `en_cours` sur la chambre qui la marque
 *    occupée. Vu depuis la page Réception / Réservations.
 *
 * Taxe spécifique (Sprint 6 — 2026-10-18) :
 *  - `taxe_specifique_type` ∈ {'%','CDF'}  — calquée sur `produits`
 *  - `taxe_specifique_value`              — valeur de la TS (par nuitée)
 *  La validation des bornes (% ∈ [0,100] / CDF ∈ [0, prix]) est faite
 *  côté front (cf. hotel-chambres.js) pour rester cohérent avec le
 *  pattern produit (posCart.saveProduct).
 */
class HotelChambre
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function all(): array
    {
        return $this->db->fetchAll(
            'SELECT id, numero, type, prix_base, etat, notes,
                    taxe_specifique_type, taxe_specifique_value
             FROM hotel_chambres
             ORDER BY numero ASC'
        );
    }

    public function findById(int $id)
    {
        return $this->db->fetch('SELECT * FROM hotel_chambres WHERE id = :id', [':id' => $id]);
    }

    /**
     * Crée une chambre. Renvoie l'id.
     * Lève RuntimeException en cas de doublon de numero ou de type invalide.
     */
    public function create(string $numero, string $type, float $prixBase, ?string $notes, string $etat = 'disponible'): int
    {
        $this->validateType($type);
        $this->validateEtat($etat);
        $existing = $this->db->fetch('SELECT id FROM hotel_chambres WHERE numero = :n', [':n' => $numero]);
        if ($existing) {
            throw new \RuntimeException("Numéro de chambre déjà utilisé (existant #{$existing['id']}).");
        }
        $this->db->query(
            'INSERT INTO hotel_chambres (numero, type, prix_base, etat, notes)
             VALUES (:n, :t, :p, :e, :notes)',
            [':n' => $numero, ':t' => $type, ':p' => $prixBase, ':e' => $etat, ':notes' => $notes]
        );
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];
        foreach (['numero', 'type', 'prix_base', 'etat', 'notes', 'taxe_specifique_type', 'taxe_specifique_value'] as $k) {
            if (array_key_exists($k, $data)) {
                if ($k === 'type') $this->validateType((string)$data[$k]);
                if ($k === 'etat') $this->validateEtat((string)$data[$k]);
                if ($k === 'taxe_specifique_type') {
                    $v = (string)$data[$k];
                    if (!in_array($v, ['%', 'CDF'], true)) {
                        throw new \RuntimeException("Type de taxe spécifique invalide : $v (attendu : %, CDF)");
                    }
                    $data[$k] = $v;
                }
                if ($k === 'taxe_specifique_value') {
                    $v = (float)$data[$k];
                    if ($v < 0) {
                        throw new \RuntimeException("La valeur de taxe spécifique doit être >= 0.");
                    }
                    $data[$k] = $v;
                }
                $fields[] = "$k = :$k";
                $params[":$k"] = $data[$k];
            }
        }
        if (empty($fields)) return false;
        // Doublon de numero si changé
        if (array_key_exists('numero', $data)) {
            $existing = $this->db->fetch(
                'SELECT id FROM hotel_chambres WHERE numero = :n AND id != :id',
                [':n' => $data['numero'], ':id' => $id]
            );
            if ($existing) {
                throw new \RuntimeException("Numéro de chambre déjà utilisé (existant #{$existing['id']}).");
            }
        }
        $sql = 'UPDATE hotel_chambres SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $this->db->query($sql, $params);
        return true;
    }

    /**
     * Change uniquement l'état — utilisé par la page Chambres (manager).
     */
    public function setEtat(int $id, string $etat): bool
    {
        $this->validateEtat($etat);
        $this->db->query('UPDATE hotel_chambres SET etat = :e WHERE id = :id', [':e' => $etat, ':id' => $id]);
        return true;
    }

    private function validateType(string $type): void
    {
        if (!in_array($type, ['simple', 'double'], true)) {
            throw new \RuntimeException("Type invalide : $type (attendu : simple, double)");
        }
    }

    private function validateEtat(string $etat): void
    {
        if (!in_array($etat, ['disponible', 'a_nettoyer', 'hors_service'], true)) {
            throw new \RuntimeException("État invalide : $etat (attendu : disponible, a_nettoyer, hors_service)");
        }
    }
}
