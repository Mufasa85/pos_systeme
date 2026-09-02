<?php

namespace App\Services;

use App\Models\PayrollEmployee;
use App\Models\PayrollTimeClock;

class PayrollImportService
{
    private $employee;
    private $timeClock;

    public function __construct()
    {
        $this->employee  = new PayrollEmployee();
        $this->timeClock = new PayrollTimeClock();
    }

    public function importCsv($shopId, $filePath, $importedBy = null)
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new \Exception('Fichier illisible');
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \Exception('Impossible d\'ouvrir le fichier');
        }

        $batch = [
            'shop_id'      => $shopId,
            'filename'     => basename($filePath),
            'imported_by'  => $importedBy,
            'rows_total'   => 0,
            'rows_ok'      => 0,
            'rows_skipped' => 0,
            'rows_error'   => 0,
        ];

        $db = \App\Core\Database::getInstance();
        $db->query('INSERT INTO payroll_time_clock_imports
                    (shop_id, filename, imported_by, rows_total, rows_ok, rows_skipped, rows_error)
                    VALUES
                    (:shop_id, :filename, :imported_by, 0, 0, 0, 0)', [
            ':shop_id'     => $shopId,
            ':filename'    => $batch['filename'],
            ':imported_by' => $importedBy,
        ]);
        $batchId = $db->lastInsertId();

        $total = $ok = $skipped = $error = 0;
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $total++;
            if (count($row) < 3) {
                $error++;
                continue;
            }

            $deviceUserId = trim($row[0]);
            $rawDateTime  = trim($row[1]);
            $eventType    = strtoupper(trim($row[2]));

            if (!in_array($eventType, ['IN', 'OUT', 'BREAK_START', 'BREAK_END'])) {
                $eventType = 'UNKNOWN';
            }

            $eventAt = date('Y-m-d H:i:s', strtotime($rawDateTime));
            if (!$eventAt || $eventAt < '2000-01-01 00:00:00') {
                $error++;
                continue;
            }

            $emp = $db->fetch(
                'SELECT id FROM payroll_employees WHERE device_user_id = ? AND shop_id = ? LIMIT 1',
                [$deviceUserId, $shopId]
            );
            if (!$emp) {
                $skipped++;
                continue;
            }

            $existing = $db->fetch(
                'SELECT id FROM payroll_time_clock_events
                 WHERE employee_id = ? AND event_at = ? AND event_type = ?',
                [$emp['id'], $eventAt, $eventType]
            );
            if ($existing) {
                $skipped++;
                continue;
            }

            $ok++;
            $this->timeClock->create([
                'shop_id'       => $shopId,
                'employee_id'   => $emp['id'],
                'event_type'    => $eventType,
                'event_at'      => $eventAt,
                'source'        => 'import_csv',
                'import_batch_id' => $batchId,
            ]);
        }
        fclose($handle);

        $db->execute(
            'UPDATE payroll_time_clock_imports
             SET rows_total = ?, rows_ok = ?, rows_skipped = ?, rows_error = ?
             WHERE id = ?',
            [$total, $ok, $skipped, $error, $batchId]
        );

        return [
            'batch_id'     => $batchId,
            'rows_total'   => $total,
            'rows_ok'      => $ok,
            'rows_skipped' => $skipped,
            'rows_error'   => $error,
        ];
    }

    public function importUsb($shopId, $filePath, $importedBy = null)
    {
        return $this->importCsv($shopId, $filePath, $importedBy);
    }
}
