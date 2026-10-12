<?php

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Builder;
use LogicException;

class AppendOnlyBuilder extends Builder
{
    public function update(array $values): never
    {
        throw new LogicException('HR ledger records cannot be updated.');
    }

    public function delete(): never
    {
        throw new LogicException('HR ledger records cannot be deleted.');
    }

    public function forceDelete(): never
    {
        throw new LogicException('HR ledger records cannot be deleted.');
    }

    public function upsert(array $values, $uniqueBy, $update = null): never
    {
        throw new LogicException('HR ledger records cannot be upserted.');
    }

    public function increment($column, $amount = 1, array $extra = []): never
    {
        throw new LogicException('HR ledger records cannot be updated.');
    }

    public function decrement($column, $amount = 1, array $extra = []): never
    {
        throw new LogicException('HR ledger records cannot be updated.');
    }
}
