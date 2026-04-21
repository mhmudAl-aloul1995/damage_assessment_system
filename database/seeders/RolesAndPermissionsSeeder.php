<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'view committee decisions',
            'create committee decisions',
            'edit committee decisions',
            'sign committee decisions',
            'manage committee members',
            'manage committee decision content',
            'send committee whatsapp',
            'sync committee decision arcgis',
        /* 'dashboard.view',

            'users.view',
            'users.create',
            'users.edit',
            'users.delete',

            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',

            'areas.view',
            'areas.manage',

            'buildings.view',
            'buildings.assign',

            'assessments.view',
            'assessments.create',
            'assessments.edit',
            'assessments.submit',
            'assessments.audit',
            'assessments.approve',
            'assessments.reject',

            'reports.view',
            'reports.export', */];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $systemManager = Role::firstOrCreate([
            'name' => 'Database Officer',
            'guard_name' => 'web',
        ]);

        $areaManager = Role::firstOrCreate([
            'name' => 'Project Officer',
            'guard_name' => 'web',
        ]);

        $teamLeader = Role::firstOrCreate([
            'name' => 'Auditing Supervisor',
            'guard_name' => 'web',
        ]);

        $auditing = Role::firstOrCreate([
            'name' => 'auditing',
            'guard_name' => 'web',
        ]);

        $fieldEngineer = Role::firstOrCreate([
            'name' => 'QC/QA Engineer',
            'guard_name' => 'web',
        ]);
        $legalAuditor = Role::firstOrCreate([
            'name' => 'Legal Auditor',
            'guard_name' => 'web',
        ]);
        $operationalLead = Role::firstOrCreate([
            'name' => 'Team Leader',
            'guard_name' => 'web',
        ]);

        $systemManager->givePermissionTo($permissions);
        $areaManager->givePermissionTo([
            'view committee decisions',
            'create committee decisions',
            'edit committee decisions',
            'manage committee members',
            'manage committee decision content',
            'send committee whatsapp',
            'sync committee decision arcgis',
        ]);
        $teamLeader->givePermissionTo([
            'view committee decisions',
            'create committee decisions',
            'edit committee decisions',
            'manage committee decision content',
        ]);
        $auditing->givePermissionTo([
            'view committee decisions',
            'sign committee decisions',
        ]);
        $fieldEngineer->givePermissionTo([
            'view committee decisions',
            'sign committee decisions',
        ]);
        $legalAuditor->givePermissionTo([
            'view committee decisions',
            'sign committee decisions',
        ]);
        $operationalLead->givePermissionTo([
            'view committee decisions',
            'manage committee members',
            'manage committee decision content',
        ]);
        /*
        $systemManager->syncPermissions($permissions);

        $areaManager->syncPermissions([
            'dashboard.view',
            'areas.view',
            'buildings.view',
            'buildings.assign',
            'assessments.view',
            'reports.view',
            'reports.export',
            'users.view',
        ]);

        $teamLeader->syncPermissions([
            'dashboard.view',
            'buildings.view',
            'assessments.view',
            'assessments.create',
            'assessments.edit',
            'assessments.submit',
            'reports.view',
        ]);

        $auditing->syncPermissions([
            'dashboard.view',
            'buildings.view',
            'assessments.view',
            'assessments.audit',
            'assessments.approve',
            'assessments.reject',
            'reports.view',
        ]);

        $fieldEngineer->syncPermissions([
            'dashboard.view',
            'buildings.view',
            'assessments.view',
            'assessments.create',
            'assessments.edit',
            'assessments.submit',
        ]); */
        User::find(1)?->assignRole($systemManager);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
