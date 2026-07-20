<?php

namespace App\Controllers;

use App\Models\CompanyInfo;
use App\controllers\Controller;

class CompanyInfoController extends Controller
{
    public function index()
    {
        if (!$this->requireSuperAdmin()) return;

        $companyInfoModel = new CompanyInfo();
        $this->json($companyInfoModel->get());
    }

    public function update()
    {
        if (!$this->requireSuperAdmin()) return;

        $input = json_decode(file_get_contents('php://input'), true);
        
        $companyInfoModel = new CompanyInfo();
        $result = $companyInfoModel->update([
            'name' => $input['name'] ?? null,
            'address' => $input['address'] ?? null,
            'email' => $input['email'] ?? null,
            'pdv' => $input['pdv'] ?? null,
            'phone' => $input['phone'] ?? null,
            'ice' => $input['ice'] ?? null,
            'rccm' => $input['rccm'] ?? null,
            'isf' => $input['isf'] ?? null,
            'nid' => $input['nid'] ?? null,
            'token' => $input['token'] ?? null,
            'port' => $input['port'] ?? null
        ]);

        if ($result) {
            $this->logAudit('update', 'company_info', 1, $input);
            $this->json(['success' => true, 'message' => 'Informations entreprise mises à jour']);
        } else {
            $this->status(500)->json(['error' => 'Erreur lors de la mise à jour']);
        }
    }
}
