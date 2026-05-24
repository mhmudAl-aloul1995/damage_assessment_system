<?php

namespace App\Modules\DamageAssessmentBorrowers\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DamageAssessmentBorrower extends Model
{
    use HasFactory;

    protected $table = 'damage_assessment_borrowers';

    protected $fillable = [
        'submitted_by',
        'submitted_by_name',
        'source_uuid',
        'source_submission_id',
        'surveyed_at',
        'form_number',
        'borrower_name',
        'borrower_id_number',
        'family_members_count',
        'marital_status',
        'spouse_name',
        'spouse_id_number',
        'employment_status',
        'is_borrower_alive',
        'vulnerability_types',
        'guarantors_count',
        'guarantors_alive_status',
        'deceased_guarantors',
        'guarantors_employment_statuses',
        'affected_guarantors',
        'displacement_status',
        'displaced_to_governorate',
        'current_residence_address',
        'phone_primary',
        'phone_secondary',
        'loan_unit_address',
        'loan_unit_area',
        'parcel_number',
        'plot_number',
        'loan_unit_occupancy_status',
        'resident_households',
        'loan_unit_damage_status',
        'notes',
        'risk_level',
        'risk_score',
        'risk_reasons',
    ];

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    protected function casts(): array
    {
        return [
            'submitted_by' => 'integer',
            'source_submission_id' => 'integer',
            'surveyed_at' => 'datetime',
            'family_members_count' => 'integer',
            'is_borrower_alive' => 'boolean',
            'vulnerability_types' => 'array',
            'guarantors_count' => 'integer',
            'deceased_guarantors' => 'array',
            'guarantors_employment_statuses' => 'array',
            'affected_guarantors' => 'array',
            'resident_households' => 'array',
            'loan_unit_area' => 'decimal:2',
            'risk_score' => 'integer',
            'risk_reasons' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
