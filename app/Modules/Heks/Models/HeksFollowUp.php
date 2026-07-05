<?php

namespace App\Modules\Heks\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HeksFollowUp extends Model
{
    protected $fillable = [
        'heks_beneficiary_id',
        'code',
        'visit_number',
        'visit_date',
        'engineer_name',
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

    protected function casts(): array
    {
        return [
            'heks_beneficiary_id' => 'integer',
            'visit_date' => 'date',
            'completed_amount_ils' => 'decimal:2',
            'completion_percentage' => 'decimal:2',
            'raw_data' => 'array',
        ];
    }
}
