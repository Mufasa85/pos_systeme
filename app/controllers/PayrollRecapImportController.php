<?php

namespace App\Controllers;

use App\Services\PayrollRecapImportService;

/**
 * Import du récapitulatif de paie Excel (cf. `Recap paie.xlsx`).
 *
 * Le classeur du comptable est la source de vérité historique de la paie :
 * il contient, pour chaque agent, son salaire, son sursalaire, sa date
 * d'embauche, sa charge fiscale, etc.
 *
 * Deux modes :
 *   - `dry_run` (par défaut) : SIMULATION. Le fichier est lu et apparié aux
 *     agents, mais rien n'est écrit. L'admin voit ce qui serait importé.
 *   - `apply` : l'import est réellement écrit (fiches, contrats, primes,
 *     présences).
 *
 * Le fichier est TOUJOURS analysé avant écriture : si une ligne est
 * inexploitable, elle est ignorée et signalée, sans bloquer les autres.
 */
class PayrollRecapImportController extends Controller
{
    /**
     * Analyse le fichier et propose l'import (simulation).
     */
    public function preview()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $file = $this->uploadedFile();
        if ($file === null) {
            return;
        }

        $tmp = $file['tmp_name'];

        try {
            $service = new PayrollRecapImportService();
            $read = $service->read($tmp);

            // Simulation : on apparie sans rien écrire.
            $result = $service->import($read['rows'], (int)$this->getShopId(), true);

            $this->json([
                'success'      => true,
                'dry_run'      => true,
                'file'         => $file['name'],
                'rows_found'   => count($read['rows']),
                'headers'      => array_keys($read['headers']),
                'imported'     => $result['imported'],
                'skipped'      => $result['skipped'],
                'errors'       => array_merge($read['errors'], $result['errors']),
                'details'      => $result['details'],
            ]);
        } catch (\Throwable $e) {
            $this->status(500)->json(['error' => $e->getMessage()]);
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Importe réellement le récap.
     */
    public function apply()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $file = $this->uploadedFile();
        if ($file === null) {
            return;
        }

        $tmp = $file['tmp_name'];

        try {
            $service = new PayrollRecapImportService();
            $read = $service->read($tmp);
            $result = $service->import($read['rows'], (int)$this->getShopId(), false);

            $this->json([
                'success'    => true,
                'dry_run'    => false,
                'file'       => $file['name'],
                'imported'   => $result['imported'],
                'skipped'    => $result['skipped'],
                'errors'     => array_merge($read['errors'], $result['errors']),
                'details'    => $result['details'],
                'message'    => $result['imported'] . ' agent(s) importé(s), '
                    . $result['skipped'] . ' ignoré(s).',
            ]);
        } catch (\Throwable $e) {
            $this->status(500)->json(['error' => $e->getMessage()]);
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Récupère et valide le fichier téléversé.
     *
     * @return array|null ['name' => …, 'tmp_name' => …] ou null (erreur déjà renvoyée)
     */
    private function uploadedFile()
    {
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $this->status(400)->json([
                'error' => 'Aucun fichier reçu (champ « file » attendu).',
            ]);
            return null;
        }

        $file = $_FILES['file'];

        // Sécurité : on n'accepte que les .xlsx (le classeur du comptable).
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'xlsm'], true)) {
            $this->status(400)->json([
                'error' => 'Format non supporté : ' . $extension . ' (un fichier .xlsx est attendu).',
            ]);
            return null;
        }

        return $file;
    }
}
