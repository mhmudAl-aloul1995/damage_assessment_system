<?php

namespace App\Modules\HR\Models;

use Database\Factories\HrDepartmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends CatalogModel
{
    use HasFactory;

    protected $table = 'hr_departments';

    protected $fillable = ['code', 'name', 'name_en', 'active', 'parent_id'];

    protected static function newFactory(): HrDepartmentFactory
    {
        return HrDepartmentFactory::new();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
