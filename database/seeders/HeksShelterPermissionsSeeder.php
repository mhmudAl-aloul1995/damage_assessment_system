<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class HeksShelterPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $profiles = [
            'HEKS Field Engineer' => ['view', 'submit', 'followup'],
            'HEKS Reviewer' => ['view', 'view-all', 'review', 'export'],
            'HEKS Project Officer' => ['view', 'view-all', 'review', 'approve', 'pricing', 'followup', 'export'],
            'HEKS Finance Officer' => ['view', 'view-all', 'finance', 'export'],
        ];
        foreach (['view', 'view-all', 'submit', 'review', 'approve', 'pricing', 'finance', 'followup', 'export'] as $permission) {
            Permission::findOrCreate('heks3.'.$permission, 'web');
        }
        foreach ($profiles as $name => $permissions) {
            $role = Role::findOrCreate($name, 'web');
            if ($role->wasRecentlyCreated) {
                $role->givePermissionTo(array_map(fn (string $permission): string => 'heks3.'.$permission, $permissions));
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
