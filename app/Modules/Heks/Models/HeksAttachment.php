<?php

namespace App\Modules\Heks\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeksAttachment extends Model
{
    protected $fillable = [
        'heks_beneficiary_id',
        'source',
        'filename',
        'url',
        'source_index',
        'parent_index',
        'parent_table',
        'attachment_type',
        'raw_data',
    ];

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(HeksBeneficiary::class, 'heks_beneficiary_id');
    }

    protected function casts(): array
    {
        return [
            'heks_beneficiary_id' => 'integer',
            'source_index' => 'integer',
            'parent_index' => 'integer',
            'raw_data' => 'array',
        ];
    }
}
