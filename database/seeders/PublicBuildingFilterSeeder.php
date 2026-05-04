<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PublicBuildingFilterSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('public_building_filters')->truncate();

        $path = database_path('seeders/public_building_choices.xlsx');

        if (! file_exists($path)) {
            $this->command?->warn("File not found: {$path}");
            return;
        }

        $spreadsheet = IOFactory::load($path);

        // ✅ قراءة من Sheet محدد
        $sheet = $spreadsheet->getSheetByName('choices');

        if (! $sheet) {
            $this->command?->error("Sheet 'choices' not found in Excel file.");
            return;
        }

        $rows = collect($sheet->toArray(null, true, true, true));

        $data = $rows
            ->skip(1) // تخطي الهيدر
            ->map(function (array $row): array {
                $listName = trim((string) ($row['A'] ?? ''));
                $name     = trim((string) ($row['B'] ?? ''));
                $label    = trim((string) ($row['C'] ?? ''));
                $gov      = trim((string) ($row['D'] ?? ''));
                $order    = trim((string) ($row['E'] ?? ''));
                $sector   = trim((string) ($row['F'] ?? ''));

                return [
                    'list_name'  => $listName,
                    'name'       => $name,
                    'label'      => $label !== '' ? $label : $name,
                    'gov'        => $gov !== '' ? $gov : null,
                    'sort_order' => $order !== '' ? $order : null,
                    'sector'     => $sector !== '' ? $sector : null,
                    'created_at' => now(),
                    'updated_at' => now(),
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
            DB::table('public_building_filters')->insert($chunk->all());
        }

        $this->command?->info("Inserted {$data->count()} records from 'choices' sheet.");
    }
}