<?php

declare(strict_types=1);

namespace App\Support\Exports;

use Illuminate\Support\Facades\Schema;

class ExportDataColumns
{
    public const BUILDINGS_TABLE = 'buildings';

    public const HOUSING_UNITS_TABLE = 'housing_units';

    public const BUILDING_UNITS_COUNT_COLUMN = 'housing_units_count';

    private const HIDDEN_COLUMNS = [
        self::BUILDINGS_TABLE => [
            'id',
            'arcgis_hash',
            'arcgis_synced_at',
        ],
        self::HOUSING_UNITS_TABLE => [
            'id',
            'arcgis_hash',
            'arcgis_synced_at',
        ],
    ];

    /**
     * @return array<int, string>
     */
    public static function visibleBuildingColumns(): array
    {
        $columns = self::visibleTableColumns(self::BUILDINGS_TABLE);

        if (! in_array(self::BUILDING_UNITS_COUNT_COLUMN, $columns, true)) {
            $columns[] = self::BUILDING_UNITS_COUNT_COLUMN;
        }

        return $columns;
    }

    /**
     * @return array<int, string>
     */
    public static function visibleHousingColumns(): array
    {
        return self::visibleTableColumns(self::HOUSING_UNITS_TABLE);
    }

    /**
     * @param  array<int, mixed>  $columns
     * @param  array<int, string>  $extraColumns
     * @return array<int, string>
     */
    public static function sanitizeRequestedColumns(string $table, array $columns, array $extraColumns = []): array
    {
        $allowed = array_flip(array_merge(self::visibleTableColumns($table), $extraColumns));

        return collect($columns)
            ->map(fn ($column) => trim((string) $column))
            ->filter(fn ($column) => $column !== '' && isset($allowed[$column]))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function visibleTableColumns(string $table): array
    {
        $columns = Schema::hasTable($table)
            ? Schema::getColumnListing($table)
            : self::informationSchemaColumns($table);

        if (empty($columns)) {
            return [];
        }

        $hidden = array_flip(self::HIDDEN_COLUMNS[$table] ?? []);

        return collect($columns)
            ->map(fn ($column) => trim((string) $column))
            ->filter(fn ($column) => $column !== '' && ! isset($hidden[$column]))
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function informationSchemaColumns(string $table): array
    {
        if (config('database.default') !== 'mysql') {
            return [];
        }

        $database = config('database.connections.mysql.database');

        return \DB::table('information_schema.columns')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->orderBy('ordinal_position')
            ->pluck('column_name')
            ->values()
            ->all();
    }
}
