<?php

namespace App\Modules\Heks\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeksImport extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'status',
        'filename',
        'sheet_name',
        'total_rows',
        'created_rows',
        'updated_rows',
        'skipped_rows',
        'summary',
        'error_report',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'total_rows' => 'integer',
            'created_rows' => 'integer',
            'updated_rows' => 'integer',
            'skipped_rows' => 'integer',
            'summary' => 'array',
            'error_report' => 'array',
        ];
    }
}
