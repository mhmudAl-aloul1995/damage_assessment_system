<?php

namespace App\Modules\Heks\Models;

use Illuminate\Database\Eloquent\Model;

class HeksKoboChoice extends Model
{
    protected $fillable = [
        'service_name',
        'question_key',
        'list_name',
        'choice_name',
        'choice_label',
        'language',
        'version',
        'sort_order',
        'is_active',
        'raw_data',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'raw_data' => 'array',
        ];
    }
}
