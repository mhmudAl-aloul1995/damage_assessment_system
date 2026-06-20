<?php

namespace App\Modules\DamageAssessmentBorrowers\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'location_latitude',
        'location_longitude',
        'location_altitude',
        'location_precision',
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
        'boq_total_usd',
        'exchange_rate',
        'boq_total_ils',
        'attachments_count',
    ];

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function boqItems(): HasMany
    {
        return $this->hasMany(BorrowerBoqItem::class, 'damage_assessment_borrower_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(BorrowerAttachment::class, 'damage_assessment_borrower_id');
    }

    public function residentHouseholds(): HasMany
    {
        return $this->hasMany(BorrowerResidentHousehold::class, 'damage_assessment_borrower_id');
    }

    protected function casts(): array
    {
        return [
            'submitted_by' => 'integer',
            'source_submission_id' => 'integer',
            'surveyed_at' => 'datetime',
            'location_latitude' => 'decimal:7',
            'location_longitude' => 'decimal:7',
            'location_altitude' => 'decimal:2',
            'location_precision' => 'decimal:2',
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
            'boq_total_usd' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'boq_total_ils' => 'decimal:2',
            'attachments_count' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
