<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArcgisWebhookDelivery extends Model
{
    protected $fillable = [
        'source',
        'webhook_name',
        'event_names',
        'status',
        'http_status',
        'signature_present',
        'ip_address',
        'user_agent',
        'changes_url',
        'payload',
        'summary',
        'error_message',
        'started_at',
        'finished_at',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'event_names' => 'array',
            'signature_present' => 'boolean',
            'payload' => 'array',
            'summary' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
