<?php

namespace Database\Seeders;

use App\Models\CsoSurveyFilter;
use Illuminate\Database\Seeder;

class CsoSurveyFilterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $filters = require __DIR__.'/data/cso_damage_assessment_filters.php';

        foreach ($filters as $filter) {
            CsoSurveyFilter::query()->updateOrCreate(
                [
                    'list_name' => $filter['list_name'],
                    'name' => $filter['name'],
                ],
                [
                    'label' => $filter['label'],
                    'sort_order' => $filter['sort_order'],
                ],
            );
        }
    }
}
