<?php

namespace App\Modules\Heks\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeksSurveyValueHistory extends Model
{
    protected $fillable = [
        'heks_beneficiary_id',
        'user_id',
        'source',
        'field_key',
        'old_value',
        'new_value',
    ];

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(HeksBeneficiary::class, 'heks_beneficiary_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'heks_beneficiary_id' => 'integer',
            'user_id' => 'integer',
        ];
    }
}
