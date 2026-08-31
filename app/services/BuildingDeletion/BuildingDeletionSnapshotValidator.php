<?php

namespace App\Services\BuildingDeletion;

use RuntimeException;

class BuildingDeletionSnapshotValidator
{
    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function validate(array $snapshot): void
    {
        $schema = $snapshot['schema'] ?? [];

        $this->assertColumnsPresent('base.building.database', $schema['building_columns'] ?? [], data_get($snapshot, 'base.building.database'));
        $this->assertRowsContainColumns('base.housing_units', $schema['housing_unit_columns'] ?? [], data_get($snapshot, 'base.housing_units', []));

        if (($schema['audited_building_columns'] ?? []) !== []) {
            $this->assertColumnsPresent('audited.building.database', $schema['audited_building_columns'], data_get($snapshot, 'audited.building.database'));
        }

        if (($schema['audited_housing_unit_columns'] ?? []) !== []) {
            $this->assertRowsContainColumns('audited.housing_units', $schema['audited_housing_unit_columns'], data_get($snapshot, 'audited.housing_units', []));
        }
    }

    /**
     * @param  array<int, string>  $columns
     * @param  array<string, mixed>|null  $record
     */
    private function assertColumnsPresent(string $path, array $columns, ?array $record): void
    {
        if ($record === null) {
            return;
        }

        $missing = array_values(array_diff($columns, array_keys($record)));

        if ($missing !== []) {
            throw new RuntimeException($path.' is missing columns: '.implode(', ', $missing));
        }
    }

    /**
     * @param  array<int, string>  $columns
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function assertRowsContainColumns(string $path, array $columns, array $rows): void
    {
        foreach ($rows as $index => $row) {
            $record = $row['database'] ?? null;

            if (is_array($record)) {
                $this->assertColumnsPresent($path.'.'.$index.'.database', $columns, $record);
            }
        }
    }
}
