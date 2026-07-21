<?php

namespace App\Modules\Heks\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HeksFollowUp extends Model
{
    protected $fillable = [
        'heks_beneficiary_id',
        'code',
        'submission_uuid',
        'source_record_key',
        'visit_number',
        'visit_date',
        'engineer_name',
        'engineer_user_id',
        'working_condition',
        'other_condition',
        'completed_amount_ils',
        'completion_percentage',
        'engineer_recommendations',
        'boq_filename',
        'boq_url',
        'raw_data',
    ];

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(HeksBeneficiary::class, 'heks_beneficiary_id');
    }

    public function engineerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'engineer_user_id');
    }

    public function boqItems(): HasMany
    {
        return $this->hasMany(HeksBoqItem::class, 'heks_follow_up_id');
    }

    public function workingConditionLabel(): string
    {
        return match ($this->working_condition) {
            'the_work_have_not_been_started' => 'لم يتم البدء في العمل',
            'work_in_progress__but_not_due_for_second' => 'العمل قيد التنفيذ ولا يستحق الدفعة الثانية بعد',
            'work_in_progress__and_due_for_next_payme' => 'العمل قيد التنفيذ ويستحق الدفعة التالية',
            'work_has_been_finished_and_due_for_the_f' => 'تم الانتهاء من العمل ويستحق الدفعة النهائية',
            'other_condition' => filled($this->other_condition) ? (string) $this->other_condition : 'حالة أخرى',
            null, '' => '-',
            default => (string) $this->working_condition,
        };
    }

    public function hasBoqLink(): bool
    {
        return filled($this->boq_url) || filled($this->boq_filename);
    }

    public function completionPercentageForDisplay(): ?float
    {
        if ($this->completion_percentage === null) {
            return null;
        }

        $percentage = (float) $this->completion_percentage;

        return $percentage >= 0.0 && $percentage <= 100.0
            ? $percentage
            : null;
    }

    public static function normalizeVisitNumber(?string $visitNumber): ?string
    {
        $visitNumber = trim((string) $visitNumber);

        if ($visitNumber === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $visitNumber) === 1) {
            return null;
        }

        $normalized = preg_replace('/[^\d.\-]/', '', str_replace(',', '', $visitNumber)) ?? '';

        if ($normalized !== '' && is_numeric($normalized)) {
            $number = (float) $normalized;

            return floor($number) === $number ? (string) (int) $number : rtrim(rtrim((string) $number, '0'), '.');
        }

        return $visitNumber;
    }

    protected function casts(): array
    {
        return [
            'heks_beneficiary_id' => 'integer',
            'engineer_user_id' => 'integer',
            'visit_date' => 'date',
            'completed_amount_ils' => 'decimal:2',
            'completion_percentage' => 'decimal:2',
            'raw_data' => 'array',
        ];
    }
}
