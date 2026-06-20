<?php

namespace App\Modules\DamageAssessmentBorrowers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BorrowerBoqCatalogItem extends Model
{
    protected $table = 'damage_assessment_borrower_boq_catalog_items';

    protected $fillable = [
        'item_code',
        'source_column',
        'source_key',
        'description',
        'normalized_description',
        'unit',
        'unit_price',
        'unit_price_ils',
        'category',
        'source_sheet',
        'sort_order',
    ];

    public function borrowerItems(): HasMany
    {
        return $this->hasMany(BorrowerBoqItem::class, 'catalog_item_id');
    }

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'unit_price_ils' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }
}
