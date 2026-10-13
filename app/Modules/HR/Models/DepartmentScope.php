<?php

namespace App\Modules\HR\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DepartmentScope extends AppendOnlyModel
{
    protected $table = 'hr_department_scopes';

    protected $fillable = ['user_id', 'department_id', 'include_descendants', 'starts_on', 'ends_on', 'created_by'];

    protected function casts(): array
    {
        return ['include_descendants' => 'boolean', 'starts_on' => \App\Modules\HR\Models\Casts\DateOnly::class, 'ends_on' => \App\Modules\HR\Models\Casts\DateOnly::class];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function endings(): HasMany
    {
        return $this->hasMany(DepartmentScopeEnding::class, 'scope_id');
    }

    public function latestEnding(): HasOne
    {
        return $this->hasOne(DepartmentScopeEnding::class, 'scope_id')->latestOfMany();
    }

    public function scopeEffectiveOn(Builder $query, string $date): void
    {
        $query->where('starts_on', '<=', $date)->where(fn (Builder $query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', $date))
            ->whereDoesntHave('latestEnding', fn (Builder $query) => $query->where('ends_on', '<', $date));
    }
}
