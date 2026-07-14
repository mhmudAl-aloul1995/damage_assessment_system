<?php

namespace App\Modules\Heks\Models;

use Illuminate\Database\Eloquent\Model;

class HeksKoboFieldMapping extends Model
{
    protected $fillable = [
        'service_name',
        'table_name',
        'kobo_field',
        'column_name',
        'display_label',
        'data_type',
        'field_type',
        'list_name',
        'language',
        'mapping_status',
        'confidence',
        'notes',
    ];
}
