<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardCardItem extends Model
{
    protected $fillable = [
        'dashboard_card_id',
        'key',
        'title',
        'source_bucket',
        'stat_key',
        'icon',
        'link_group',
        'link_key',
        'calculation_type',
        'source_model',
        'filter_field',
        'filter_operator',
        'filter_value',
        'value_suffix',
        'decimal_places',
        'sort_order',
        'is_active',
        'options',
    ];

    protected function casts(): array
    {
        return [
            'decimal_places' => 'integer',
            'is_active' => 'boolean',
            'options' => 'array',
        ];
    }

    public function dashboardCard(): BelongsTo
    {
        return $this->belongsTo(DashboardCard::class);
    }
}
