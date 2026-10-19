<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Reh\Models\RehBuilding>
 */
class RehBuildingFactory extends Factory
{
    protected $model = \App\Modules\Reh\Models\RehBuilding::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $globalId = fake()->uuid();
        $objectId = fake()->unique()->numberBetween(1, 1000000);
        $source = ['Field_status' => 'COMPLETED', 'globalid' => $globalId, 'objectid' => $objectId, 'building_name' => 'مبنى تجريبي', 'governorate' => 'khan_younis', 'neighborhood' => 'al_amal', 'engineer_name' => 'eng1', 'building_structurally_safe' => 'yes', 'building_uxo_present' => 'no', 'building_dangerous_debris' => 'no'];

        return ['global_id' => $globalId, 'object_id' => $objectId, 'name' => $source['building_name'], 'governorate' => 'khan_younis', 'neighborhood' => 'al_amal', 'engineer' => 'eng1', 'source' => $source, 'source_hash' => hash('sha256', json_encode($source)), 'synced_at' => now()];
    }
}
