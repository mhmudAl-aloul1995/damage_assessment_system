<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\Phase\PhaseContext;
use Illuminate\Database\Eloquent\Builder;

trait HasPhaseScope
{
    protected static function bootHasPhaseScope(): void
    {
        static::addGlobalScope('selected_phase', function (Builder $builder): void {
            app(PhaseContext::class)->applyToEloquent($builder);
        });
    }
}
