<?php

namespace App\Services;

use App\Models\PayrollAbsence;
use App\Models\PayrollAllowance;
use App\Models\PayrollAttendance;
use App\Models\PayrollContract;
use App\Models\PayrollContributionRate;
use App\Models\PayrollDeduction;
use App\Models\PayrollEmployee;
use App\Models\PayrollOvertime;
use App\Models\PayrollPayslip;
use App\Models\PayrollPayslipLine;
use App\Models\PayrollPeriod;
use App\Models\PayrollSeniorityBand;

class PayrollCalculator
{
    private $employee;
    private $contract;
    private $period;
    private $attendance;
    private $absence;
    private $overtime;
    private $allowance;
    private $deduction;
    private $contribution;
    private $seniority;
    private $payslip;
    private $payslipLine;

    public function __construct()
    {
        $this->employee    = new PayrollEmployee();
        $this->contract    = new PayrollContract();
        $this->period      = new PayrollPeriod();
        $this->attendance  = new PayrollAttendance();
        $this->absence     = new PayrollAbsence();
        $this->overtime    = new PayrollOvertime();
        $this->allowance   = new PayrollAllowance();
        $this->deduction   = new PayrollDeduction();
        $this->contribution = new PayrollContributionRate();
        $this->seniority   = new PayrollSeniorityBand();
        $this->payslip     = new PayrollPayslip();
        $this->payslipLine = new PayrollPayslipLine();
    }

    public function calculateForEmployee($employeeId, $periodId, $shopId = null)
    {
        $employee = $this->employee->findById($employeeId);
        if (!$employee) {
            throw new \Exception('Employé inconnu');
        }

        $contract = $this->contract->findActiveByEmployee($employeeId);
        if (!$contract) {
            throw new \Exception('Contrat actif non trouvé pour l\'employé');
        }

        $period = $this->period->findById($periodId);
        if (!$period) {
            throw new \Exception('Période de paie inconnue');
        }

        $shopId = $shopId ?? $employee['shop_id'] ?? $period['shop_id'];

        $attendance = $this->attendance->findByEmployeeAndPeriod($employeeId, $periodId);
        $unpaidAbsences = $this->absence->totalDaysByEmployeeAndPeriod($employeeId, $periodId);
        $workingDays = (float)$period['working_days'];

        $paidDays = $attendance['paid_days'] ?? max(0, $workingDays - (float)$unpaidAbsences);
        $workedHours = $attendance['worked_hours'] ?? ($paidDays * 8);

        list($baseAmount, $hourlyRate) = $this->computeBase(
            $contract,
            $workingDays,
            (float)$paidDays,
            (float)$workedHours
        );

        $sursalary = (float)$contract['sursalary'];

        $overtimeAmount = $this->computeOvertime($employeeId, $periodId, $hourlyRate);

        $seniorityAmount = $this->computeSeniority($employee, $baseAmount, $shopId);

        list($allowanceAmount, $allowanceLines) = $this->computeAllowances($baseAmount, $shopId);

        $gross = $baseAmount + $sursalary + $overtimeAmount + $seniorityAmount + $allowanceAmount;

        list($deductionAmount, $deductionLines) = $this->computeDeductions($gross, $shopId);

        list($employeeContrib, $employerContrib, $contributionLines) = $this->computeContributions($gross, $shopId);

        $ierRate = (float)$employee['ier_rate'];
        $ierAmount = $gross * ($ierRate / 100);

        $totalDeductions = $deductionAmount + $employeeContrib;
        $employerCharges = $employerContrib + $ierAmount;
        $employerCost    = $gross + $employerCharges;
        $net             = $gross - $totalDeductions;

        $payslipData = [
            'shop_id'           => $shopId,
            'employee_id'       => $employeeId,
            'payroll_period_id' => $periodId,
            'gross_amount'      => round($gross, 2),
            'taxable_amount'    => round($gross, 2),
            'cnss_base'         => round($gross, 2),
            'total_deductions'  => round($totalDeductions, 2),
            'employer_charges'  => round($employerCharges, 2),
            'employer_cost'     => round($employerCost, 2),
            'net_amount'        => round($net, 2),
            'status'            => 'calculated',
        ];

        $lines = $this->buildLines(
            $baseAmount,
            (float)$paidDays,
            $sursalary,
            $seniorityAmount,
            $this->yearsBetween($employee['hire_date'], date('Y-m-d')),
            $this->seniority->findBandForYears($this->yearsBetween($employee['hire_date'], date('Y-m-d')), $shopId)['percent'] ?? 0,
            $overtimeAmount,
            $allowanceLines,
            $deductionLines,
            $contributionLines,
            $ierRate,
            $ierAmount
        );

        return [
            'payslip' => $payslipData,
            'lines'   => $lines,
        ];
    }

    public function savePayslip($employeeId, $periodId, $shopId = null)
    {
        $calc = $this->calculateForEmployee($employeeId, $periodId, $shopId);
        $payslipId = $this->payslip->createOrUpdate($calc['payslip']);

        if (!$payslipId) {
            throw new \Exception('Impossible d\'enregistrer le bulletin');
        }

        $lines = array_map(function ($line) use ($payslipId) {
            $line['payslip_id'] = $payslipId;
            return $line;
        }, $calc['lines']);

        $this->payslipLine->bulkInsert($payslipId, $lines);
        return $payslipId;
    }

    public function calculateForPeriod($periodId, $shopId = null)
    {
        $period = $this->period->findById($periodId);
        if (!$period) {
            throw new \Exception('Période inconnue');
        }

        $shopId = $shopId ?? $period['shop_id'];
        $employees = $this->employee->all($shopId, false);

        $created = [];
        foreach ($employees as $emp) {
            if ($this->contract->findActiveByEmployee($emp['id'])) {
                $created[] = $this->savePayslip($emp['id'], $periodId, $shopId);
            }
        }

        $this->period->markCalculated($periodId);
        return $created;
    }

    private function computeBase($contract, $workingDays, $paidDays, $workedHours)
    {
        $base = (float)$contract['base_salary'];
        $type = $contract['pay_type'];

        if ($type === 'monthly') {
            $dailyRate = $base / max(1, $workingDays);
            $baseAmount = $dailyRate * max(0, $paidDays);
            $hourlyRate = $base / max(1, $workingDays * 8);
            return [$baseAmount, $hourlyRate];
        }

        if ($type === 'daily') {
            $baseAmount = $base * max(0, $paidDays);
            $hourlyRate = $base / 8;
            return [$baseAmount, $hourlyRate];
        }

        $baseAmount = $base * max(0, $workedHours);
        $hourlyRate = $base;
        return [$baseAmount, $hourlyRate];
    }

    private function computeOvertime($employeeId, $periodId, $hourlyRate)
    {
        $overtimeRows = $this->overtime->findByEmployeeAndPeriod($employeeId, $periodId);
        $total = 0;
        foreach ($overtimeRows as $ot) {
            $amount = (float)($ot['amount'] ?? 0);
            if (!$amount && (float)$ot['hours'] > 0) {
                $amount = (float)$ot['hours'] * $hourlyRate * (float)$ot['multiplier'];
            }
            $total += $amount;
        }
        return $total;
    }

    private function computeSeniority($employee, $baseAmount, $shopId)
    {
        $years = $this->yearsBetween($employee['hire_date'], date('Y-m-d'));
        $band = $this->seniority->findBandForYears($years, $shopId);
        if (!$band) {
            return 0;
        }
        return $baseAmount * ((float)$band['percent'] / 100);
    }

    private function computeAllowances($baseAmount, $shopId)
    {
        $allowances = $this->allowance->active($shopId);
        $total = 0;
        $lines = [];
        foreach ($allowances as $a) {
            $amt = ($a['calculation_type'] === 'percent_base')
                ? $baseAmount * ((float)$a['amount'] / 100)
                : (float)$a['amount'];
            $total += $amt;
            $lines[] = [
                'code'       => $a['code'],
                'label'      => $a['label'],
                'type'       => 'earning',
                'quantity'   => null,
                'rate'       => $a['amount'],
                'amount'     => round($amt, 2),
                'sort_order' => 20,
            ];
        }
        return [$total, $lines];
    }

    private function computeDeductions($gross, $shopId)
    {
        $deductions = $this->deduction->active($shopId);
        $total = 0;
        $lines = [];
        foreach ($deductions as $d) {
            $amt = ($d['calculation_type'] === 'percent_gross')
                ? $gross * ((float)$d['amount'] / 100)
                : (float)$d['amount'];
            $total += $amt;
            $lines[] = [
                'code'       => $d['code'],
                'label'      => $d['label'],
                'type'       => 'deduction',
                'quantity'   => null,
                'rate'       => $d['amount'],
                'amount'     => round($amt, 2),
                'sort_order' => 50,
            ];
        }
        return [$total, $lines];
    }

    private function computeContributions($gross, $shopId)
    {
        $contribs = $this->contribution->active($shopId);
        $employeeTotal = 0;
        $employerTotal = 0;
        $lines = [];
        foreach ($contribs as $c) {
            $emp   = $gross * ((float)$c['employee_rate'] / 100);
            $pat   = $gross * ((float)$c['employer_rate'] / 100);
            $employeeTotal += $emp;
            $employerTotal += $pat;
            $lines[] = [
                'code'       => $c['code'] . '_SAL',
                'label'      => $c['label'] . ' (salarié)',
                'type'       => 'deduction',
                'quantity'   => null,
                'rate'       => $c['employee_rate'],
                'amount'     => round($emp, 2),
                'sort_order' => 60,
            ];
            $lines[] = [
                'code'       => $c['code'] . '_PAT',
                'label'      => $c['label'] . ' (employeur)',
                'type'       => 'employer',
                'quantity'   => null,
                'rate'       => $c['employer_rate'],
                'amount'     => round($pat, 2),
                'sort_order' => 70,
            ];
        }
        return [$employeeTotal, $employerTotal, $lines];
    }

    private function buildLines($baseAmount, $paidDays, $sursalary, $seniorityAmount, $years, $seniorityRate, $overtimeAmount, $allowanceLines, $deductionLines, $contributionLines, $ierRate, $ierAmount)
    {
        $lines = [
            [
                'code'       => 'BASE',
                'label'      => 'Salaire de base',
                'type'       => 'earning',
                'quantity'   => $paidDays,
                'rate'       => round($baseAmount / max($paidDays, 1), 4),
                'amount'     => round($baseAmount, 2),
                'sort_order' => 0,
            ],
            [
                'code'       => 'SURSAL',
                'label'      => 'Sursalaire',
                'type'       => 'earning',
                'quantity'   => null,
                'rate'       => null,
                'amount'     => round($sursalary, 2),
                'sort_order' => 5,
            ],
        ];

        $lines = array_merge($lines, $allowanceLines);

        $lines[] = [
            'code'       => 'ANCIEN',
            'label'      => 'Ancienneté (' . $years . ' ans)',
            'type'       => 'earning',
            'quantity'   => round($years, 2),
            'rate'       => $seniorityRate,
            'amount'     => round($seniorityAmount, 2),
            'sort_order' => 30,
        ];

        $lines[] = [
            'code'       => 'HS',
            'label'      => 'Heures supplémentaires',
            'type'       => 'earning',
            'quantity'   => null,
            'rate'       => null,
            'amount'     => round($overtimeAmount, 2),
            'sort_order' => 40,
        ];

        $lines = array_merge($lines, $deductionLines, $contributionLines);

        $lines[] = [
            'code'       => 'IER',
            'label'      => 'IER employeur',
            'type'       => 'employer',
            'quantity'   => null,
            'rate'       => $ierRate,
            'amount'     => round($ierAmount, 2),
            'sort_order' => 90,
        ];

        foreach ($lines as $i => $line) {
            $lines[$i]['sort_order'] = ($line['sort_order'] ?? $i) * 10;
        }

        return $lines;
    }

    private function yearsBetween($from, $to)
    {
        $fromDate = new \DateTime($from);
        $toDate   = new \DateTime($to);
        return $fromDate->diff($toDate)->y + ($fromDate->diff($toDate)->m / 12) + ($fromDate->diff($toDate)->d / 365);
    }
}
