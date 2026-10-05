<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MissingCitizenIdentityApproval extends Model
{
    protected $fillable = [
        'missing_citizen_identity_report_id',
        'housing_unit_id',
        'housing_unit_objectid',
        'old_id_number',
        'new_id_number',
        'owner_name',
        'citizen_id',
        'citizen_full_name',
        'approved_by',
        'arcgis_sync_status',
        'arcgis_sync_message',
        'arcgis_sync_response',
    ];

    protected function casts(): array
    {
        return [
            'arcgis_sync_response' => 'array',
        ];
    }
}
