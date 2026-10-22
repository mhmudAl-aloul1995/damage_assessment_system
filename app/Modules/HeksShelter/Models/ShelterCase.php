<?php

namespace App\Modules\HeksShelter\Models;

use App\Models\MobileFormSubmission;
use App\Models\User;
use Database\Factories\ShelterCaseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShelterCase extends Model
{
    use HasFactory;

    protected $table = 'heks_shelter_cases';

    protected $guarded = ['id'];

    protected static function newFactory(): ShelterCaseFactory
    {
        return ShelterCaseFactory::new();
    }

    protected function casts(): array
    {
        return ['answers' => 'array', 'phase' => 'integer', 'version' => 'integer', 'visit_date' => 'date', 'approved_at' => 'datetime', 'benefited_at' => 'datetime', 'grant_amount' => 'decimal:2'];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->when(! $user->can('heks3.view-all'), fn (Builder $query): Builder => $query->where('engineer_id', $user->id));
    }

    public function engineer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'engineer_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ShelterEvent::class, 'case_id');
    }

    public function boqLines(): HasMany
    {
        return $this->hasMany(ShelterBoqLine::class, 'case_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ShelterPayment::class, 'case_id');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(ShelterVisit::class, 'case_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(MobileFormSubmission::class, 'record_id')->where('module', 'heks-shelter');
    }
}
