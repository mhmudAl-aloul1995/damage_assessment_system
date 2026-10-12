<?php

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

abstract class AppendOnlyModel extends Model
{
    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Create a new HR record instead of changing history.');
        });
        static::deleting(function (): never {
            throw new LogicException('HR history cannot be deleted.');
        });
    }

    public function newEloquentBuilder($query): AppendOnlyBuilder
    {
        return new AppendOnlyBuilder($query);
    }
}
