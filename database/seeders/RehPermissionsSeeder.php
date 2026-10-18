<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RehPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['reh.view', 'reh.review', 'reh.review.engineering', 'reh.review.social', 'reh.decide', 'reh.sensitive', 'reh.sync', 'reh.export'] as $permission) {
            \Spatie\Permission\Models\Permission::findOrCreate($permission, 'web');
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
