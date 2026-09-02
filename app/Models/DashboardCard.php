<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DashboardCard extends Model
{
    protected $fillable = [
        'key',
        'title',
        'subtitle',
        'source_bucket',
        'total_stat_key',
        'icon',
        'color',
        'sort_order',
        'is_active',
        'options',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'options' => 'array',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(DashboardCardItem::class)->orderBy('sort_order')->orderBy('id');
    }
}
