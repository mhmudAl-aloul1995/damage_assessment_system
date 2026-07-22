<?php

namespace App\Modules\Heks\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeksMainKoboRecord extends Model
{
    protected $table = 'heks_main_kobo_records';

    protected $guarded = [];

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(HeksBeneficiary::class, 'heks_beneficiary_id');
    }

    protected function casts(): array
    {
        return [
            'heks_beneficiary_id' => 'integer',
            'heks_follow_up_id' => 'integer',
            'kobo_rest_submission_id' => 'integer',
            'received_at' => 'datetime',
            'synced_at' => 'datetime',
            'raw_data' => 'array',
        ];
    }
}
