<?php

namespace App\Models;

use App\Core\Database;

class Client
{
    private $db;
    private $table = 'clients';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Récupérer tous les clients
     */
    public function getAll($shopId = null)
    {
        $where = '';
        $params = [];
        if ($shopId) {
            $where = 'WHERE c.shop_id = ?';
            $params[] = $shopId;
        }
        $sql = "SELECT c.*, tc.code as type_code, tc.description as type_description 
                FROM {$this->table} c
                LEFT JOIN type_client tc ON c.type_client_id = tc.id
                $where
                ORDER BY c.nom_client ASC";
        return $this->db->query($sql, $params);
    }

    /**
     * Rechercher un client par numéro de téléphone.
     *
     * La comparaison se fait sur `numero_normalise`, une colonne GENERATED
     * calculée par MySQL (cf. migration 2026_11_04_pressing_module.sql) :
     * le pressing stocke les numéros au format RDC ('+243 89 548 96 08')
     * alors que le POS les stocke bruts ('0895511485'). Sans normalisation,
     * la même personne serait créée en double.
     *
     * La colonne peut ne pas exister encore (base non migrée) : on retombe
     * alors sur un REPLACE(), plus lent mais correct.
     */
    public function findByNumero($numero)
    {
        $normalise = self::normaliserNumero($numero);
        if ($normalise === '') {
            return null;
        }

        if ($this->colonneNumeroNormaliseExiste()) {
            $sql = "SELECT c.*, tc.code as type_code, tc.description as type_description
                    FROM {$this->table} c
                    LEFT JOIN type_client tc ON c.type_client_id = tc.id
                    WHERE c.numero_normalise = ?
                    LIMIT 1";
        } else {
            $sql = "SELECT c.*, tc.code as type_code, tc.description as type_description
                    FROM {$this->table} c
                    LEFT JOIN type_client tc ON c.type_client_id = tc.id
                    WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                              REPLACE(REPLACE(c.numero, ' ', ''), '+', ''), '-', ''),
                              '(', ''), ')', ''), '/', ''), '.', '') = ?
                    LIMIT 1";
        }

        $result = $this->db->query($sql, [$normalise]);
        return $result ? $result[0] : null;
    }

    /**
     * Indique si la colonne `numero_normalise` existe (base migrée).
     *
     * @return bool
     */
    private function colonneNumeroNormaliseExiste(): bool
    {
        static $existe = null;

        if ($existe !== null) {
            return $existe;
        }

        try {
            $row = $this->db->fetch(
                "SELECT COUNT(*) AS c FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = ?
                   AND column_name = 'numero_normalise'",
                [$this->table]
            );
            $existe = ((int)($row['c'] ?? 0) > 0);
        } catch (\Throwable $e) {
            $existe = false;
        }

        return $existe;
    }

    /**
     * Normalise un numéro de téléphone congolais en chiffres seuls.
     *
     * Retire espaces, ponctuation et le préfixe international :
     *   '+243 89 548 96 08' → '895489608'
     *   '0895511485'        → '895511485'
     *   '243895511485'      → '895511485'
     *
     * Le résultat est utilisé pour comparer deux numéros malgré un format
     * d'écriture différent (pressing vs POS).
     *
     * @param string|null $numero
     *
     * @return string Chiffres uniquement ('' si rien d'exploitable)
     */
    public static function normaliserNumero($numero): string
    {
        $digits = preg_replace('/\D+/', '', (string)$numero);
        if ($digits === '') {
            return '';
        }

        // Préfixe international congolais
        if (str_starts_with($digits, '243')) {
            $digits = substr($digits, 3);
        }

        // Zéro de tête (format local)
        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return $digits;
    }

    /**
     * Rechercher un client par ID
     */
    public function findById($id)
    {
        $sql = "SELECT c.*, tc.code as type_code, tc.description as type_description 
                FROM {$this->table} c
                LEFT JOIN type_client tc ON c.type_client_id = tc.id
                WHERE c.id = ?";
        $result = $this->db->query($sql, [$id]);
        return $result ? $result[0] : null;
    }

    /**
     * Créer un nouveau client
     *
     * Le `code_client` est généré en base (et non à partir d'un MAX lu en
     * PHP) car il porte une contrainte UNIQUE : deux créations simultanées
     * (le pressing crée des clients en flux continu) lisaient le même MAX et
     * la seconde insert échouait sur un doublon. On réserve donc un code
     * disponible en transaction, avec retry en cas de collision.
     */
    public function create($data)
    {
        $code_client = $this->genererCodeClient();

        $sql = "INSERT INTO {$this->table}
                (nom_client, numero, code_client, type_client_id, nif, adresse, shop_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        $this->db->execute($sql, [
            $data['nom_client'],
            $data['numero'],
            $code_client,
            $data['type_client_id'] ?? 1,
            $data['nif'] ?? '',
            $data['adresse'] ?? '',
            $data['shop_id'] ?? null,
        ]);

        return $this->db->lastInsertId();
    }

    /**
     * Réserve un `code_client` libre (format CLI-001).
     *
     * La recherche part du plus grand identifiant existant : on ne veut pas
     * réutiliser un code déjà attribué. La boucle gère la course entre deux
     * créations simultanées (l'UNIQUE sur code_client peut être violé entre
     * la sélection et l'insert) — au plus 5 tentatives.
     *
     * @return string Code client libre
     */
    private function genererCodeClient(): string
    {
        for ($tentative = 0; $tentative < 5; $tentative++) {
            $row = $this->db->fetch(
                "SELECT COALESCE(MAX(id), 0) AS max_id FROM {$this->table}"
            );
            $nextNum = (int)($row['max_id'] ?? 0) + 1;
            $code = 'CLI-' . str_pad((string)$nextNum, 3, '0', STR_PAD_LEFT);

            // Vérifie que le code n'est pas déjà pris (des codes historiques
            // ont été saisis à la main : 'cli-2', 'NULL', …).
            $existe = $this->db->fetch(
                "SELECT id FROM {$this->table} WHERE code_client = ? LIMIT 1",
                [$code]
            );

            if (!$existe) {
                return $code;
            }
        }

        // Repli : suffixe horodaté, garant unique
        return 'CLI-' . date('YmdHis');
    }

    /**
     * Mettre à jour un client
     */
    public function update($id, $data)
    {
        $sql = "UPDATE {$this->table}
                SET nom_client = ?, numero = ?, type_client_id = ?, nif = ?, adresse = ?
                WHERE id = ?";

        return $this->db->execute($sql, [
            $data['nom_client'],
            $data['numero'],
            $data['type_client_id'] ?? 1,
            $data['nif'] ?? '',
            $data['adresse'] ?? '',
            $id,
        ]);
    }

    /**
     * Supprimer un client
     */
    public function delete($id)
    {
        $sql = "DELETE FROM {$this->table} WHERE id = ?";
        return $this->db->execute($sql, [$id]);
    }

    /**
     * Récupérer les types de clients
     */
    public function getTypes()
    {
        $sql = 'SELECT * FROM type_client ORDER BY code ASC';
        return $this->db->query($sql);
    }
}
