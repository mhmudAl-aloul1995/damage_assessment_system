<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'mhmudAloul',
            'email' => 'mhmudaloul@gmail.com',
            'password' => bcrypt('123456'),
        ]);

        $this->call([
            AssessmentsSqlSeeder::class,
            AssessmentStatusSeeder::class,
            FilterSeeder::class,
            PublicBuildingFilterSeeder::class,
            RolesAndPermissionsSeeder::class,
            BuildingSeeder::class,
            HousingSeeder::class,
        ]);
    }
}
