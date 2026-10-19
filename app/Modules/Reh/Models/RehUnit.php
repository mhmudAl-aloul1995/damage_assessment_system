<?php

namespace App\Modules\Reh\Models;

use Illuminate\Database\Eloquent\Model;

class RehUnit extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $guarded = [];

    protected function oldObjectid(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::get(fn (): string => \App\Modules\Reh\Services\RehSurvey::reference($this->source ?? []));
    }

    protected function casts(): array
    {
        return ['source' => 'array', 'quantity_overrides' => 'array', 'version' => 'integer', 'source_missing' => 'boolean', 'synced_at' => 'datetime'];
    }

    protected static function newFactory(): \Database\Factories\RehUnitFactory
    {
        return \Database\Factories\RehUnitFactory::new();
    }

    public function building(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RehBuilding::class, 'building_id');
    }

    public function reviewer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'reviewer_id');
    }

    public function reviews(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RehReview::class, 'unit_id');
    }
}
