<?php

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Builder;
use LogicException;

class ReportExportBuilder extends Builder
{
    public function update(array $values): int
    {
        $columns = array_map(fn (string $key): string => basename(str_replace('.', '/', $key)), array_keys($values));
        if (array_intersect($columns, ['id', 'actor_id', 'filters', 'employee_ids', 'expires_at', 'created_at']) !== []) {
            throw new LogicException('Attendance export identity, scope and expiry cannot be changed.');
        }

        return parent::update($values);
    }

    public function delete(): never
    {
        throw new LogicException('Attendance export request history cannot be deleted.');
    }

    public function forceDelete(): never
    {
        throw new LogicException('Attendance export request history cannot be deleted.');
    }

    public function truncate(): never
    {
        throw new LogicException('Attendance export request history cannot be deleted.');
    }

    public function upsert(array $values, $uniqueBy, $update = null): never
    {
        throw new LogicException('Attendance export requests cannot be upserted.');
    }

    public function increment($column, $amount = 1, array $extra = []): never
    {
        throw new LogicException('Attendance export identifiers cannot be incremented.');
    }

    public function decrement($column, $amount = 1, array $extra = []): never
    {
        throw new LogicException('Attendance export identifiers cannot be decremented.');
    }
}
