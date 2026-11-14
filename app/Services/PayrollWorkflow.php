<?php

namespace App\Services;

use App\Models\PayrollPayslip;
use App\Models\PayrollPeriod;

/**
 * Règles de cycle de vie du module Paie (statuts, gardes, clôture).
 *
 * Centralise les invariants qui étaient jusqu'ici implicites — et donc
 * violables — dans plusieurs contrôleurs :
 *
 *   draft → calculated → validated → paid
 *                      ↘ (période) closed
 *
 * Invariant 1 — IMMUABILITÉ APRÈS VALIDATION
 *   Un bulletin `validated` ou `paid` ne peut plus être recalculé ni modifié :
 *   c'est un document comptable validé. Idem pour une période `closed`.
 *
 * Invariant 2 — CLÔTURE AUTOMATIQUE
 *   Quand tous les bulletins d'une période sont `paid`, la période passe
 *   automatiquement à `closed` (et ne peut plus être modifiée).
 *
 * Invariant 3 — PAIEMENT D'UN BULLETIN NON VALIDÉ
 *   Seuls les bulletins `validated` peuvent être réglés : payer un bulletin
 *   encore en `calculated` reviendrait à valider implicitement une paie.
 */
class PayrollWorkflow
{
    /** Statuts qui interdisent toute modification du bulletin. */
    public const LOCKED_PAYSLIP_STATUSES = ['validated', 'paid'];

    /** Statuts qui interdisent toute modification de la période. */
    public const LOCKED_PERIOD_STATUSES = ['closed'];

    /**
     * Un bulletin est-il verrouillé (validé ou payé) ?
     */
    public static function isPayslipLocked(?array $payslip): bool
    {
        if (!$payslip) {
            return false;
        }
        return in_array($payslip['status'] ?? '', self::LOCKED_PAYSLIP_STATUSES, true);
    }

    /**
     * Une période est-elle verrouillée (clôturée) ?
     */
    public static function isPeriodLocked(?array $period): bool
    {
        if (!$period) {
            return false;
        }
        return in_array($period['status'] ?? '', self::LOCKED_PERIOD_STATUSES, true);
    }

    /**
     * Un bulletin peut-il être recalculé ?
     *
     * @return array{ok: bool, reason: string}
     */
    public static function canRecalculatePayslip(?array $payslip, ?array $period = null): array
    {
        if (self::isPayslipLocked($payslip)) {
            return [
                'ok'     => false,
                'reason' => 'Ce bulletin est ' . ($payslip['status'] ?? '')
                    . ' : il ne peut plus être recalculé.',
            ];
        }

        if (self::isPeriodLocked($period)) {
            return [
                'ok'     => false,
                'reason' => 'La période est clôturée : le calcul y est bloqué.',
            ];
        }

        return ['ok' => true, 'reason' => ''];
    }

    /**
     * Une période peut-elle être recalculée ?
     *
     * @return array{ok: bool, reason: string}
     */
    public static function canCalculatePeriod(?array $period): array
    {
        if (self::isPeriodLocked($period)) {
            return [
                'ok'     => false,
                'reason' => 'La période est clôturée : le calcul y est bloqué.',
            ];
        }

        // Une période validée ne doit plus être recalculée : les bulletins
        // portent des montants validés (voire payés).
        if (($period['status'] ?? '') === 'validated') {
            return [
                'ok'     => false,
                'reason' => 'La période est validée : elle ne peut plus être recalculée.',
            ];
        }

        return ['ok' => true, 'reason' => ''];
    }

    /**
     * Un bulletin peut-il être modifié (champs éditables) ?
     *
     * @return array{ok: bool, reason: string}
     */
    public static function canModifyPayslip(?array $payslip): array
    {
        if (self::isPayslipLocked($payslip)) {
            return [
                'ok'     => false,
                'reason' => 'Ce bulletin est ' . ($payslip['status'] ?? '')
                    . ' : il est verrouillé et ne peut plus être modifié.',
            ];
        }

        return ['ok' => true, 'reason' => ''];
    }

    /**
     * Un bulletin peut-il être réglé (paiement enregistré) ?
     *
     * @return array{ok: bool, reason: string}
     */
    public static function canPayPayslip(?array $payslip): array
    {
        $status = $payslip['status'] ?? '';

        if ($status === 'paid') {
            return ['ok' => false, 'reason' => 'Ce bulletin est déjà payé.'];
        }

        if ($status !== 'validated') {
            return [
                'ok'     => false,
                'reason' => 'Seul un bulletin validé peut être réglé (statut actuel : '
                    . ($status !== '' ? $status : 'inconnu') . ').',
            ];
        }

        return ['ok' => true, 'reason' => ''];
    }

    /**
     * Une fiche employé peut-elle être modifiée ?
     *
     * Un agent ayant un bulletin `validated` ou `paid` ne doit plus voir ses
     * données de rémunération modifiées.
     *
     * @return array{ok: bool, reason: string}
     */
    public static function canModifyEmployee(int $employeeId, $shopId = null): array
    {
        $locked = (new PayrollPayslip())->findLockedByEmployee($employeeId, $shopId);

        if ($locked) {
            return [
                'ok'     => false,
                'reason' => 'Cet agent a un bulletin verrouillé ('
                    . ($locked['status'] ?? '') . ') : sa fiche ne peut plus être modifiée.',
            ];
        }

        return ['ok' => true, 'reason' => ''];
    }

    /**
     * Clôture automatique : si tous les bulletins d'une période sont payés,
     * la période passe à `closed`.
     *
     * @return bool true si la période a été clôturée
     */
    public static function closePeriodIfAllPaid(int $periodId, $shopId = null): bool
    {
        $payslips = (new PayrollPayslip())->findByPeriod($periodId, $shopId);
        if (empty($payslips)) {
            // Rien à clôturer : une période sans bulletin reste ouverte.
            return false;
        }

        foreach ($payslips as $p) {
            if (($p['status'] ?? '') !== 'paid') {
                return false;
            }
        }

        $period = (new PayrollPeriod())->findById($periodId, $shopId);
        if (self::isPeriodLocked($period)) {
            return false;
        }

        return (bool)(new PayrollPeriod())->markClosed($periodId);
    }
}
