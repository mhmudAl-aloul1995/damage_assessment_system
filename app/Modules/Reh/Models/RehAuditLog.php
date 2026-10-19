<?php

namespace App\Modules\Reh\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RehAuditLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['values' => 'array', 'sensitive' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
