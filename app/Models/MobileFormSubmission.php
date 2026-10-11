<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MobileFormSubmission extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'submission_uuid', 'module', 'form_key', 'form_version', 'record_id', 'record_version', 'request_hash', 'changes', 'location'];

    protected $hidden = ['request_hash', 'changes'];

    protected function casts(): array
    {
        return ['changes' => 'encrypted:array', 'location' => 'encrypted:array', 'record_id' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MobileFormAttachment::class);
    }
}
