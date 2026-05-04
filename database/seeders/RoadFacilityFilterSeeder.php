<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class RoadFacilityFilterSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('road_facility_filters')->truncate();

        $path = database_path('seeders/road_facility_choices.xlsx');

        if (! file_exists($path)) {
            $this->command?->warn("File not found: {$path}");
            return;
        }

        $spreadsheet = IOFactory::load($path);

        // ✅ اقرأ من choices
        $sheet = $spreadsheet->getSheetByName('choices');

        if (! $sheet) {
            $this->command?->error("Sheet 'choices' not found.");
            return;
        }

        $rows = collect($sheet->toArray(null, true, true, true));

        $data = $rows
            ->skip(1)
            ->map(function ($row) {

                $listName = trim((string) ($row['A'] ?? ''));
                $name     = trim((string) ($row['B'] ?? ''));
                $label    = trim((string) ($row['C'] ?? ''));
                $group    = trim((string) ($row['D'] ?? '')); // ← مهم
                $order    = trim((string) ($row['E'] ?? ''));

                return [
                    'list_name'   => $listName,
                    'name'        => $name,
                    'label'       => $label !== '' ? $label : $name,
                    'group_value' => $group !== '' ? $group : null,
                    'sort_order'  => is_numeric($order) ? (int) $order : null,
                ];
            })
            ->filter(fn ($row) =>
                $row['list_name'] !== ''
                && strtolower($row['list_name']) !== 'list_name'
                && $row['name'] !== ''
            )
            ->unique(fn ($row) =>
                Str::lower($row['list_name'].'|'.$row['name'])
            )
            ->values();

        foreach ($data->chunk(200) as $chunk) {
            DB::table('road_facility_filters')->insert($chunk->all());
        }

        $this->command?->info("Inserted {$data->count()} road facility filters.");
    }
}