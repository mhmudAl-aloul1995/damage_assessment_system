<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PublicBuildingFilterSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('public_building_filters')->truncate();

        $path = database_path('seeders/public_building_choices.txt');

        if (! file_exists($path)) {
            return;
        }

        $rows = collect(file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))
            ->map(static fn (string $line): array => array_map('trim', explode('|', $line)))
            ->filter(function (array $parts): bool {
                return count($parts) >= 3
                    && $parts[0] !== ''
                    && $parts[0] !== 'list_name'
                    && $parts[1] !== '';
            })
            ->map(function (array $parts): array {
                return [
                    'list_name' => $parts[0],
                    'name' => $parts[1],
                    'label' => $parts[2] !== '' ? $parts[2] : $parts[1],
                    'gov' => $parts[3] !== '' ? $parts[3] : null,
                    'sort_order' => $parts[4] !== '' ? $parts[4] : null,
                    'sector' => $parts[5] !== '' ? $parts[5] : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })
            ->unique(static fn (array $row): string => Str::lower($row['list_name'].'|'.$row['name']))
            ->values();

        foreach ($rows->chunk(200) as $chunk) {
            DB::table('public_building_filters')->insert($chunk->all());
        }
    }
}
