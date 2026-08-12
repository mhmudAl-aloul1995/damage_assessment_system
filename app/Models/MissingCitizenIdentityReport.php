<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MissingCitizenIdentityReport extends Model
{
    protected $fillable = [
        'housing_unit_id',
        'owner_name',
        'normalized_owner_name',
        'id_number',
        'issue_type',
        'name_match_status',
        'matched_citizen_id',
        'matched_citizen_id_card_no',
        'matched_citizen_full_name',
        'matched_citizens_count',
        'approved_at',
        'approved_by',
        'arcgis_sync_status',
        'arcgis_sync_message',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }
}
