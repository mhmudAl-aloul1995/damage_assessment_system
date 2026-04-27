<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemOperationLog extends Model
{
    protected $fillable = [
        'operation_type',
        'status',
        'connection_name',
        'layer_name',
        'layer_id',
        'started_at',
        'finished_at',
        'file_path',
        'total_records',
        'message',
    ];
}