<?php

namespace App\Modules\HR\Models;

use Database\Factories\HrContractTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ContractType extends CatalogModel
{
    use HasFactory;

    protected $table = 'hr_contract_types';

    protected $fillable = ['code', 'name', 'name_en', 'active', 'requires_end', 'reminder_days'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'requires_end' => 'boolean', 'reminder_days' => 'array'];
    }

    protected static function newFactory(): HrContractTypeFactory
    {
        return HrContractTypeFactory::new();
    }
}
