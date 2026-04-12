<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Export extends Model
{
    protected $fillable = [
        'file_name',
        'status',
        'filters',
        'user_id'
    ];
}
