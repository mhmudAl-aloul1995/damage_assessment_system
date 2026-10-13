<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class HrPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['hr.attendance.view', 'hr.attendance.view-own', 'hr.attendance.view-team', 'hr.attendance.setup', 'hr.attendance.export', 'hr.attendance.correct', 'hr.attendance.correct-own', 'hr.attendance.approve', 'hr.attendance.exceptions.manage', 'hr.attendance.reconcile', 'hr.attendance.audit'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
