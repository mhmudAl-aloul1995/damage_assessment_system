<?php

namespace App\Modules\HR\Models;

use App\Modules\HR\Models\Casts\DateOnly;

class ContractAlert extends AppendOnlyModel
{
    public $timestamps = false;

    protected $table = 'hr_contract_alerts';

    protected $fillable = ['revision_id', 'recipient_id', 'threshold_days', 'expires_on', 'created_at'];

    protected function casts(): array
    {
        return ['expires_on' => DateOnly::class];
    }
}
