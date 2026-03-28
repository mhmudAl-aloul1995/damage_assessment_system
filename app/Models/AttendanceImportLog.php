<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceImportLog extends Model
{
    protected $fillable = [
        'file_hash',
        'file_name',
        'imported_by',
        'total_rows',
        'processed_rows',
        'imported_records',
        'created_users',
        'status',
        'message',
    ];
}