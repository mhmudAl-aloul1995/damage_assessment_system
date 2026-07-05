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
    ];
}
