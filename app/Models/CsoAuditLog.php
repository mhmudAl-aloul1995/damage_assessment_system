<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CsoAuditLog extends Model
{
    /** @use HasFactory<\Database\Factories\CsoAuditLogFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'old_value' => 'json',
            'new_value' => 'json',
            'created_at' => 'datetime',
        ];
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(CsoSurvey::class, 'cso_survey_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
