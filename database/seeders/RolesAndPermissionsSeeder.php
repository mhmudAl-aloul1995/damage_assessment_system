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

        $this->call([
            InfAuditRolesSeeder::class,
            InfAuditStatusesSeeder::class,
        ]);

        $permissions = [
            'view committee decisions',
            'create committee decisions',
            'edit committee decisions',
            'sign committee decisions',
            'manage committee members',
            'manage committee decision content',
            'sync committee decision arcgis',
            'system.maintenance',
            'system-logs.view',
            'login-logs.view',
            'user-activity-logs.view',
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',
            'permissions.view',
            'permissions.create',
            'permissions.update',
            'permissions.delete',
            'damage-assessments.view',
            'damage-assessments.create',
            'damage-assessments.update',
            'damage-assessments.delete',
            'buildings.view',
            'buildings.create',
            'buildings.update',
            'buildings.delete',
            'buildings.export',
            'housing-units.view',
            'housing-units.create',
            'housing-units.update',
            'housing-units.delete',
            'audit.view',
            'audit.assign',
            'audit.update-assessment',
            'audit.update-status',
            'audit.final-approve',
            'audit.undp-final-approve',
            'audit.import-final-approval',
            'audit.view-history',
            'audit.delete-history',
            'audit.export',
            'committee-decisions.view',
            'committee-decisions.create',
            'committee-decisions.update',
            'committee-decisions.sign',
            'committee-decisions.manage-content',
            'committee-decisions.retry-arcgis',
            'committee-members.view',
            'committee-members.create',
            'committee-members.update',
            'committee-members.delete',
            'inf-audit.public-buildings.view',
            'inf-audit.public-buildings.assign',
            'inf-audit.public-buildings.update-status',
            'inf-audit.public-buildings.update-fields',
            'inf-audit.public-buildings.create-child',
            'inf-audit.roads.view',
            'inf-audit.roads.assign',
            'inf-audit.roads.update-status',
            'inf-audit.roads.update-fields',
            'inf-audit.roads.create-child',
            'reports.view',
            'reports.export',
            'reports.damage-statistics.view',
            'reports.productivity.view',
            'reports.area-productivity.view',
            'reports.field-engineer.view',
            'reports.daily-achievement.view',
            'reports.hlp-audit.view',
            'reports.public-buildings.view',
            'reports.road-facilities.view',
            'attendance.view',
            'attendance.create',
            'attendance.import',
            'attendance.update-status',
            'attendance.reports.view',
            'attendance.reports.export',
            'arcgis.sync',
            'exports.view',
            'exports.create',
            'exports.cancel',
            'exports.import-objectids',
            'exports.reset-objectids',
            'team-leader-field-engineers.view',
            'team-leader-field-engineers.create',
            'team-leader-field-engineers.delete',
            'team-leader-field-engineers.export',
            'building-survey-return-requests.view',
            'building-survey-return-requests.create',
            'building-survey-return-requests.team-leader-approve',
            'building-survey-return-requests.area-manager-approve',
            'building-survey-return-requests.reject',
        ];

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
        $undpProjectManager = Role::firstOrCreate([
            'name' => 'undp-Project Manager',
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
        $infEngineer = Role::firstOrCreate([
            'name' => 'Inf - QC/QA Engineer',
            'guard_name' => 'web',
        ]);
        $infTeamLeader = Role::firstOrCreate([
            'name' => 'Team Leader -INF',
            'guard_name' => 'web',
        ]);

        $systemManager->givePermissionTo($permissions);
        $areaManager->givePermissionTo([
            'view committee decisions',
            'create committee decisions',
            'edit committee decisions',
            'manage committee members',
            'manage committee decision content',
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
        $undpProjectManager->givePermissionTo([
            'view committee decisions',
            'create committee decisions',
            'edit committee decisions',
            'manage committee members',
            'manage committee decision content',
            'sync committee decision arcgis',
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

        $areaManager->givePermissionTo([
            'reports.view',
            'reports.export',
            'audit.view',
            'audit.final-approve',
            'committee-decisions.view',
            'committee-decisions.create',
            'committee-decisions.update',
            'committee-decisions.manage-content',
            'committee-decisions.retry-arcgis',
            'committee-members.view',
            'committee-members.create',
            'committee-members.update',
            'committee-members.delete',
        ]);
        $teamLeader->givePermissionTo([
            'audit.view',
            'audit.assign',
            'audit.update-status',
            'audit.view-history',
            'reports.view',
            'committee-decisions.view',
            'committee-decisions.create',
            'committee-decisions.update',
            'committee-decisions.manage-content',
        ]);
        $auditing->givePermissionTo([
            'audit.view',
            'audit.update-assessment',
            'audit.update-status',
            'audit.view-history',
            'committee-decisions.view',
            'committee-decisions.sign',
        ]);
        $fieldEngineer->givePermissionTo([
            'damage-assessments.view',
            'damage-assessments.update',
            'buildings.view',
            'housing-units.view',
            'building-survey-return-requests.view',
            'building-survey-return-requests.create',
            'committee-decisions.view',
            'committee-decisions.sign',
        ]);
        $undpProjectManager->givePermissionTo([
            'reports.view',
            'reports.export',
            'audit.view',
            'audit.undp-final-approve',
            'committee-decisions.view',
            'committee-decisions.create',
            'committee-decisions.update',
            'committee-decisions.manage-content',
            'committee-decisions.retry-arcgis',
            'committee-members.view',
            'committee-members.create',
            'committee-members.update',
            'committee-members.delete',
        ]);
        $legalAuditor->givePermissionTo([
            'committee-decisions.view',
            'committee-decisions.sign',
            'reports.view',
        ]);
        $operationalLead->givePermissionTo([
            'team-leader-field-engineers.view',
            'team-leader-field-engineers.create',
            'team-leader-field-engineers.delete',
            'team-leader-field-engineers.export',
            'building-survey-return-requests.view',
            'building-survey-return-requests.team-leader-approve',
            'reports.field-engineer.view',
            'committee-decisions.view',
            'committee-members.view',
            'committee-members.create',
            'committee-members.update',
            'committee-members.delete',
            'committee-decisions.manage-content',
        ]);
        $infEngineer->givePermissionTo([
            'inf-audit.public-buildings.view',
            'inf-audit.public-buildings.update-status',
            'inf-audit.public-buildings.update-fields',
            'inf-audit.public-buildings.create-child',
            'inf-audit.roads.view',
            'inf-audit.roads.update-status',
            'inf-audit.roads.update-fields',
            'inf-audit.roads.create-child',
        ]);
        $infTeamLeader->givePermissionTo([
            'inf-audit.public-buildings.view',
            'inf-audit.public-buildings.assign',
            'inf-audit.roads.view',
            'inf-audit.roads.assign',
            'reports.public-buildings.view',
            'reports.road-facilities.view',
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
