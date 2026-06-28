<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KoboRestSubmission extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'damage_assessment_borrower_id' => 'integer',
            'payload' => 'array',
            'received_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }
}
