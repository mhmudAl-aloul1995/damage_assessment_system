<?php

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;

abstract class CatalogModel extends Model
{
    protected $fillable = ['code', 'name', 'name_en', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function newEloquentBuilder($query): CatalogBuilder
    {
        return new CatalogBuilder($query);
    }

    protected static function booted(): void
    {
        static::deleting(function (): never {
            throw new \LogicException('Archive an HR catalog record instead of deleting history.');
        });
    }
}
