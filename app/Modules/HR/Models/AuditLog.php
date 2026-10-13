<?php

namespace App\Modules\HR\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends AppendOnlyModel
{
    public $timestamps = false;

    protected $table = 'hr_audit_logs';

    protected $fillable = ['actor_id', 'action', 'subject_type', 'subject_id', 'subject_key', 'metadata', 'created_at'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
