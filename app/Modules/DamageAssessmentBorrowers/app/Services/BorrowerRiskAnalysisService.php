<?php

namespace App\Modules\DamageAssessmentBorrowers\Services;

use App\Modules\DamageAssessmentBorrowers\Models\DamageAssessmentBorrower;

class BorrowerRiskAnalysisService
{
    /**
     * @param  array<string, mixed>|DamageAssessmentBorrower  $borrower
     * @return array{risk_level: string, risk_score: int, risk_reasons: array<int, string>}
     */
    public function analyze(array|DamageAssessmentBorrower $borrower): array
    {
        $data = $borrower instanceof DamageAssessmentBorrower ? $borrower->toArray() : $borrower;
        $score = 0;
        $reasons = [];

        $add = function (int $points, string $reason) use (&$score, &$reasons): void {
            $score += $points;
            $reasons[] = $reason;
        };

        if (! (bool) ($data['is_borrower_alive'] ?? true)) {
            $add(40, 'المقترض متوفى ويحتاج معالجة خاصة لملف القرض.');
        }

        if (($data['employment_status'] ?? null) === 'not_working') {
            $add(18, 'المقترض لا يعمل حاليًا.');
        } elseif (($data['employment_status'] ?? null) === 'retired') {
            $add(8, 'المقترض متقاعد.');
        }

        $vulnerabilityTypes = $data['vulnerability_types'] ?? [];
        if (is_array($vulnerabilityTypes) && count($vulnerabilityTypes) > 0 && ! in_array('none', $vulnerabilityTypes, true)) {
            $add(min(20, count($vulnerabilityTypes) * 6), 'يوجد مؤشرات هشاشة اجتماعية داخل الأسرة.');
        }

        if (($data['guarantors_alive_status'] ?? null) === 'no') {
            $add(20, 'يوجد كفيل أو أكثر متوفى.');
        } elseif (($data['guarantors_alive_status'] ?? null) === 'none') {
            $add(24, 'لا يوجد كفلاء فعالون مسجلون.');
        }

        $guarantorStatuses = $data['guarantors_employment_statuses'] ?? [];
        if (is_array($guarantorStatuses)) {
            if (in_array('lost_job', $guarantorStatuses, true)) {
                $add(16, 'يوجد كفيل فقد عمله.');
            }

            if (in_array('retired', $guarantorStatuses, true)) {
                $add(8, 'يوجد كفيل متقاعد.');
            }
        }

        if (($data['displacement_status'] ?? null) === 'displaced') {
            $add(14, 'المقترض نازح حاليًا.');
        }

        $damageStatus = $data['loan_unit_damage_status'] ?? null;
        if ($damageStatus === 'destroyed') {
            $add(30, 'الوحدة السكنية هدم كلي.');
        } elseif ($damageStatus === 'severe_uninhabitable') {
            $add(24, 'الوحدة السكنية متضررة بليغ وغير صالحة للسكن.');
        } elseif ($damageStatus === 'severe_habitable') {
            $add(12, 'الوحدة السكنية متضررة بليغ لكنها صالحة للسكن.');
        } elseif ($damageStatus === 'minor') {
            $add(4, 'الوحدة السكنية بها أضرار طفيفة.');
        }

        if (($data['loan_unit_occupancy_status'] ?? null) === 'none_due_damage') {
            $add(12, 'لا يوجد سكان داخل الوحدة بسبب الضرر.');
        } elseif (in_array(($data['loan_unit_occupancy_status'] ?? null), ['displaced_hosted', 'tenants', 'buyers', 'heirs'], true)) {
            $add(6, 'الوحدة يسكنها طرف غير أسرة المقترض.');
        }

        $level = match (true) {
            $score >= 70 => 'critical',
            $score >= 45 => 'high',
            $score >= 22 => 'medium',
            default => 'low',
        };

        return [
            'risk_level' => $level,
            'risk_score' => min($score, 100),
            'risk_reasons' => array_values(array_unique($reasons)),
        ];
    }
}
