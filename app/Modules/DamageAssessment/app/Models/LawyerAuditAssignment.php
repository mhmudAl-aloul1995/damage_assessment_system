<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LawyerAuditAssignment extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'excel_index',
        'source_row_number',
        'lawyer_name',
        'building_objectid',
        'housing_unit_objectid',
        'building_globalid',
        'housing_unit_globalid',
        'unit_owner',
        'owner_full_name',
        'id_number',
        'mobile_number',
        'unit_damage_status',
        'floor_number',
        'housing_unit_number',
        'governorate',
        'locality',
        'neighborhood',
        'street',
        'closest_facility',
    ];

    public function assessmentUrl(): string
    {
        return url('damage-assessment/showAssessmentAudit/'
            .rawurlencode($this->building_globalid)
            .'/'
            .rawurlencode($this->housing_unit_globalid));
    }
}
