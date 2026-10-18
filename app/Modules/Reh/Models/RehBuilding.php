<?php

namespace App\Modules\Reh\Models;

use Illuminate\Database\Eloquent\Model;

class RehBuilding extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['source' => 'array', 'geometry' => 'array', 'source_missing' => 'boolean', 'synced_at' => 'datetime'];
    }

    protected static function newFactory(): \Database\Factories\RehBuildingFactory
    {
        return \Database\Factories\RehBuildingFactory::new();
    }

    public function units(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RehUnit::class, 'building_id');
    }
}
