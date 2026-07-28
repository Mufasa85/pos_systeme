<?php

namespace App\Controllers;

use App\Models\Shop;
use App\controllers\Controller;

class ShopController extends Controller
{
    public function index()
    {
        if (!$this->requireSuperAdmin()) return;

        $shopModel = new Shop();
        $this->json($shopModel->getAll());
    }

    public function create()
    {
        if (!$this->requireSuperAdmin()) return;

        $input = json_decode(file_get_contents('php://input'), true);

        $nom  = $this->sanitaze($input['nom'] ?? '');
        $code = $this->sanitaze($input['code'] ?? '');

        if (!$nom || !$code) {
            $this->status(400)->json(['error' => 'Nom et code requis']);
            return;
        }

        $shopModel = new Shop();

        // Vérifier unicité du code
        if ($shopModel->findByCode($code)) {
            $this->status(409)->json(['error' => 'Ce code boutique existe déjà']);
            return;
        }

        $id = $shopModel->create([
            'nom'             => $nom,
            'code'            => strtoupper($code),
            'adresse'         => $this->sanitaze($input['adresse'] ?? ''),
            'telephone'       => $this->sanitaze($input['telephone'] ?? ''),
            'email'           => $this->sanitaze($input['email'] ?? ''),
            'ice'             => $this->sanitaze($input['ice'] ?? ''),
            'rccm'            => $this->sanitaze($input['rccm'] ?? ''),
            'isf'             => $this->sanitaze($input['isf'] ?? ''),
            'pdv'             => $this->sanitaze($input['pdv'] ?? ''),
            'nid'             => $this->sanitaze($input['nid'] ?? ''),
            'token'           => $this->sanitaze($input['token'] ?? ''),
            'port'            => $this->sanitaze($input['port'] ?? ''),
            'service_type_id' => isset($input['service_type_id']) ? (int)$input['service_type_id'] : null,
            'actif'           => (int)($input['actif'] ?? 1)
        ]);

        $this->logAudit('create', 'shop', $id, ['nom' => $nom, 'code' => $code]);
        $this->json(['success' => true, 'id' => $id]);
    }

    public function update($id)
    {
        if (!$this->requireSuperAdmin()) return;

        if (is_array($id)) $id = $id['id'] ?? null;
        $id = (int)$id;

        if (!$id) {
            $this->status(400)->json(['error' => 'ID boutique manquant']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $shopModel = new Shop();

        $shop = $shopModel->findById($id);
        if (!$shop) {
            $this->status(404)->json(['error' => 'Boutique introuvable']);
            return;
        }

        $data = [];
        $allowed = ['nom', 'code', 'adresse', 'telephone', 'email', 'ice', 'rccm', 'isf', 'pdv', 'nid', 'token', 'port', 'service_type_id', 'actif'];
        foreach ($allowed as $field) {
            if (isset($input[$field])) {
                $data[$field] = $field === 'service_type_id' ? (int)$input[$field] : $this->sanitaze($input[$field]);
            }
        }

        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
            $existing = $shopModel->findByCode($data['code']);
            if ($existing && $existing['id'] != $id) {
                $this->status(409)->json(['error' => 'Ce code boutique existe déjà']);
                return;
            }
        }

        $shopModel->update($id, $data);
        $this->logAudit('update', 'shop', $id, $data);
        $this->json(['success' => true, 'message' => 'Boutique mise à jour']);
    }

    public function delete($id)
    {
        if (!$this->requireSuperAdmin()) return;

        if (is_array($id)) $id = $id['id'] ?? null;
        $id = (int)$id;

        if (!$id) {
            $this->status(400)->json(['error' => 'ID boutique manquant']);
            return;
        }

        $shopModel = new Shop();
        $shop = $shopModel->findById($id);
        if (!$shop) {
            $this->status(404)->json(['error' => 'Boutique introuvable']);
            return;
        }

        $shopModel->delete($id);
        $this->logAudit('delete', 'shop', $id, ['nom' => $shop['nom']]);
        $this->json(['success' => true, 'message' => 'Boutique supprimée']);
    }

    public function stats($id)
    {
        if (!$this->requireSuperAdmin()) return;

        if (is_array($id)) $id = $id['id'] ?? null;
        $id = (int)$id;

        $shopModel = new Shop();
        $stats = $shopModel->getStats($id ?: null);
        $this->json($stats);
    }
}
