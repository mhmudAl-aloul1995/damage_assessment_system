<?php

namespace App\Modules\HeksShelter\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShelterBoqLine extends Model
{
    protected $table = 'heks_shelter_boq_lines';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'unit_price' => 'decimal:2'];
    }

    public function shelterCase(): BelongsTo
    {
        return $this->belongsTo(ShelterCase::class, 'case_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
