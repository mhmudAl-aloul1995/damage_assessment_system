<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class HrPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['hr.attendance.view', 'hr.attendance.view-own', 'hr.attendance.setup', 'hr.attendance.export'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
