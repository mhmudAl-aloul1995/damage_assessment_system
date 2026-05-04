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
        $sheet = $spreadsheet->getActiveSheet();

        $rows = collect($sheet->toArray(null, true, true, true));

        $data = $rows
            ->skip(1)
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
            ->filter(fn (array $row): bool =>
                $row['list_name'] !== ''
                && strtolower($row['list_name']) !== 'list_name'
                && $row['name'] !== ''
            )
            ->unique(fn (array $row): string =>
                Str::lower($row['list_name'].'|'.$row['name'])
            )
            ->values();

        foreach ($data->chunk(200) as $chunk) {
            DB::table('public_building_filters')->insert($chunk->all());
        }

        $this->command?->info("Inserted {$data->count()} public building filter records.");
    }
}