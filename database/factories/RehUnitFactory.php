<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Reh\Models\RehUnit>
 */
class RehUnitFactory extends Factory
{
    protected $model = \App\Modules\Reh\Models\RehUnit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $globalId = fake()->uuid();
        $objectId = fake()->unique()->numberBetween(1, 1000000);
        $source = ['globalid' => $globalId, 'objectid' => $objectId, 'owner_full_name' => 'مالك تجريبي', 'owner_identity_number' => '123456789', 'consent_given' => 'yes', 'unit_safe_to_enter' => 'yes', 'unit_uxo_present' => 'no', 'unit_dangerous_debris_present' => 'no', 'demolished_units_above' => 'no', 'unit_needs_intervention' => 'yes', 'ownership_proof_status' => 'yes', 'boq_1_1_water_tank_repair_qty' => 2];

        return ['building_id' => RehBuildingFactory::new(), 'global_id' => $globalId, 'object_id' => $objectId, 'code' => 'REH-'.$objectId, 'owner_name' => $source['owner_full_name'], 'owner_identity' => $source['owner_identity_number'], 'floor_number' => 0, 'apartment_number' => 1, 'source' => $source, 'source_hash' => hash('sha256', json_encode($source)), 'synced_at' => now()];
    }
}
