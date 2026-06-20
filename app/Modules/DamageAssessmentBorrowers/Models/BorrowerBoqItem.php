<?php

namespace App\Modules\DamageAssessmentBorrowers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BorrowerBoqItem extends Model
{
    protected $table = 'damage_assessment_borrower_boq_items';

    protected $fillable = [
        'damage_assessment_borrower_id',
        'catalog_item_id',
        'source_column',
        'source_key',
        'item_code',
        'description',
        'unit',
        'unit_price',
        'quantity',
        'total_price',
        'sort_order',
    ];

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(DamageAssessmentBorrower::class, 'damage_assessment_borrower_id');
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(BorrowerBoqCatalogItem::class, 'catalog_item_id');
    }

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'quantity' => 'decimal:2',
            'total_price' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }
}
