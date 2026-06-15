<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArcgisAttachmentBackup extends Model
{
    protected $fillable = [
        'operation',
        'auditable_type',
        'building_globalid',
        'building_objectid',
        'attachment_id',
        'attachment_name',
        'content_type',
        'size',
        'disk',
        'path',
        'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
