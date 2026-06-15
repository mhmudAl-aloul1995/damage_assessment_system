<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FilterSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('filters')->truncate();

        $filters = collect(require database_path('seeders/data/damage_assessment_filters.php'))
            ->merge($this->buildingBackedFilters())
            ->unique(fn (array $row): string => Str::lower($row['list_name'].'|'.$row['name']))
            ->values();

        foreach ($filters->chunk(200) as $chunk) {
            DB::table('filters')->insert($chunk->all());
        }

        $this->command?->info("Inserted {$filters->count()} damage assessment filters from seed data and buildings table.");
    }

    private function buildingBackedFilters(): Collection
    {
        return collect([
            'governorate' => 'governorate',
            'municipalitie' => 'municipalitie',
            'locality' => 'municipalitie',
            'neighborhood' => 'neighborhood',
        ])->flatMap(function (string $column, string $listName): Collection {
            return DB::table('buildings')
                ->whereNotNull($column)
                ->where($column, '<>', '')
                ->distinct()
                ->orderBy($column)
                ->pluck($column)
                ->map(function (mixed $value) use ($listName): array {
                    $value = trim((string) $value);

                    return [
                        'list_name' => $listName,
                        'name' => $value,
                        'label' => $value,
                    ];
                });
        })->values();
    }
}
