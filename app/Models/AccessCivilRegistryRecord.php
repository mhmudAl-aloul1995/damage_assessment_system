<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccessCivilRegistryRecord extends Model
{
    protected $fillable = [
        'id_card_no',
        'first_name',
        'father_name',
        'grand_name',
        'family_name',
        'full_name',
        'full_name_normalized',
        'mother_name',
        'neighborhood',
        'birth_date',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
        ];
    }
}
