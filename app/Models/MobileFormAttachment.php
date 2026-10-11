<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileFormAttachment extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'mobile_form_submission_id', 'submission_uuid', 'form_key', 'path', 'name', 'mime', 'size', 'content_hash'];

    protected $hidden = ['path'];

    protected function casts(): array
    {
        return ['user_id' => 'integer', 'mobile_form_submission_id' => 'integer', 'size' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(MobileFormSubmission::class, 'mobile_form_submission_id');
    }
}
