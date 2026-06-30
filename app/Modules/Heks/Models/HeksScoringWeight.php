<?php

namespace App\Modules\Heks\Models;

use Illuminate\Database\Eloquent\Model;

class HeksScoringWeight extends Model
{
    protected $fillable = [
        'source',
        'category',
        'indicator',
        'weight',
        'question_key',
        'option_value',
        'option_score',
        'raw_data',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'option_score' => 'decimal:2',
            'raw_data' => 'array',
        ];
    }
}
