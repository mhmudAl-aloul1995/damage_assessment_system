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
        foreach (['hr.attendance.capture', 'hr.devices.register', 'hr.devices.view', 'hr.devices.approve', 'hr.devices.revoke'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        foreach (['hr.employees.view', 'hr.employees.view-own', 'hr.employees.view-team', 'hr.employees.manage', 'hr.employees.sensitive',
            'hr.organization.manage', 'hr.documents.view', 'hr.documents.upload', 'hr.documents.sensitive', 'hr.documents.withdraw'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['hr.contracts.view', 'hr.contracts.view-own', 'hr.contracts.view-team', 'hr.contracts.manage', 'hr.contracts.approve', 'hr.contracts.sensitive', 'hr.contracts.configure', 'hr.contracts.export'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
