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
        'loan_number',
        'loan_status',
        'loan_original_amount',
        'loan_total_amount',
        'loan_portfolio_amount',
        'loan_net_amount',
        'loan_balance',
        'loan_paid_amount',
        'loan_installments_count',
        'loan_started_at',
        'loan_last_installment_at',
        'loan_clearance_delivered',
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
        'loan_unit_floor_type',
        'parcel_number',
        'plot_number',
        'loan_unit_occupancy_status',
        'resident_households',
        'loan_unit_damage_status',
        'is_inside_yellow_line',
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

    public function koboAnswers(): HasMany
    {
        return $this->hasMany(BorrowerKoboAnswer::class, 'damage_assessment_borrower_id');
    }

    protected function casts(): array
    {
        return [
            'submitted_by' => 'integer',
            'source_submission_id' => 'integer',
            'loan_original_amount' => 'decimal:2',
            'loan_total_amount' => 'decimal:2',
            'loan_portfolio_amount' => 'decimal:2',
            'loan_net_amount' => 'decimal:2',
            'loan_balance' => 'decimal:2',
            'loan_paid_amount' => 'decimal:2',
            'loan_installments_count' => 'integer',
            'loan_started_at' => 'date',
            'loan_last_installment_at' => 'date',
            'loan_clearance_delivered' => 'boolean',
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
            'is_inside_yellow_line' => 'boolean',
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
