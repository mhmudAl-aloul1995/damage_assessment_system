<?php

namespace App\Modules\HeksShelter\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShelterVisit extends Model
{
    protected $table = 'heks_shelter_visits';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['completion' => 'decimal:2', 'visited_on' => 'date'];
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
