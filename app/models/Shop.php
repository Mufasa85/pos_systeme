<?php

namespace App\Models;

class Shop
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function getAll()
    {
        return $this->db->fetchAll('SELECT * FROM shops ORDER BY nom ASC');
    }

    public function getActive()
    {
        return $this->db->fetchAll('SELECT * FROM shops WHERE actif = 1 ORDER BY nom ASC');
    }

    public function findById($id)
    {
        return $this->db->fetch('SELECT * FROM shops WHERE id = ?', [$id]);
    }

    public function findByCode($code)
    {
        return $this->db->fetch('SELECT * FROM shops WHERE code = ?', [$code]);
    }

    public function findByIsf($isf)
    {
        return $this->db->fetch('SELECT * FROM shops WHERE isf = ?', [$isf]);
    }

    /**
     * Indique si la boutique dispose de l'accès à la fonctionnalité « Extras »
     * (imputation d'une vente sur un séjour hôtelier depuis la caisse).
     *
     * La configuration vit dans `shops.acces_extras` et est cochée par le
     * super_admin dans /shops. Elle remplace le feature flag API « hotel »
     * pour cette seule fonctionnalité.
     *
     * Règles :
     *   - Boutique introuvable / sans identifiant → false (fail-closed).
     *   - super_admin sans boutique rattachée → false : le contrôle d'accès
     *     s'applique aux boutiques, pas au rôle global du super_admin.
     *   - Erreur SQL → false (fail-closed), le caller peut renvoyer un 403.
     */
    public function hasExtrasAccess($shopId): bool
    {
        if (!$shopId) {
            return false;
        }

        try {
            $shop = $this->db->fetch(
                'SELECT acces_extras FROM shops WHERE id = ? LIMIT 1',
                [(int)$shopId]
            );
        } catch (\Throwable $e) {
            error_log('Shop::hasExtrasAccess error: ' . $e->getMessage());
            return false;
        }

        return $shop && !empty($shop['acces_extras']);
    }

    public function create($data)
    {
        $sql = "INSERT INTO shops (nom, code, adresse, telephone, email, ice, rccm, isf, homologation, pdv, nid, token, port, service_type_id, actif, acces_extras)
                VALUES (:nom, :code, :adresse, :telephone, :email, :ice, :rccm, :isf, :homologation, :pdv, :nid, :token, :port, :service_type_id, :actif, :acces_extras)";
        $this->db->query($sql, [
            ':nom'             => $data['nom'],
            ':code'            => $data['code'],
            ':adresse'         => $data['adresse'] ?? null,
            ':telephone'       => $data['telephone'] ?? null,
            ':email'           => $data['email'] ?? null,
            ':ice'             => $data['ice'] ?? null,
            ':rccm'            => $data['rccm'] ?? null,
            ':isf'             => $data['isf'] ?? null,
            ':homologation'    => !empty($data['homologation']) ? 1 : 0,
            ':pdv'             => $data['pdv'] ?? null,
            ':nid'             => $data['nid'] ?? null,
            ':token'           => $data['token'] ?? null,
            ':port'            => $data['port'] ?? null,
            ':service_type_id' => $data['service_type_id'] ?? null,
            ':actif'           => $data['actif'] ?? 1,
            ':acces_extras'    => !empty($data['acces_extras']) ? 1 : 0,
        ]);
        return $this->db->lastInsertId();
    }

    public function update($id, $data)
    {
        $fields = [];
        $params = [':id' => $id];

        $allowed = ['nom', 'code', 'adresse', 'telephone', 'email', 'ice', 'rccm', 'isf', 'homologation', 'pdv', 'nid', 'logo', 'token', 'port', 'service_type_id', 'actif', 'acces_extras'];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = 'UPDATE shops SET ' . implode(', ', $fields) . ' WHERE id = :id';
        return $this->db->execute($sql, $params);
    }

    public function delete($id)
    {
        return $this->db->execute('DELETE FROM shops WHERE id = ?', [$id]);
    }

    public function getStats($shopId)
    {
        $params = $shopId ? [$shopId] : [];
        $where = $shopId ? 'WHERE shop_id = ?' : '';

        $products = $this->db->fetch("SELECT COUNT(*) as total FROM produits $where", $params);
        $users = $this->db->fetch("SELECT COUNT(*) as total FROM utilisateurs $where", $params);
        $sales = $this->db->fetch("SELECT COUNT(*) as total, COALESCE(SUM(total),0) as revenue FROM ventes $where", $params);

        return [
            'products' => $products['total'] ?? 0,
            'users'    => $users['total'] ?? 0,
            'sales'    => $sales['total'] ?? 0,
            'revenue'  => $sales['revenue'] ?? 0,
        ];
    }
}
