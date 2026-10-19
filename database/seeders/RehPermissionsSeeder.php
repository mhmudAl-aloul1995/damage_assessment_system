<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RehPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $scoresPermissionIsNew = ! Permission::query()->where('guard_name', 'web')->where('name', 'reh.scores.view')->exists();
        foreach (config('access.reh_permissions') as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        if ($scoresPermissionIsNew) {
            Role::query()->where('guard_name', 'web')->whereHas('permissions', fn ($query) => $query->where('name', 'reh.sensitive'))->each(fn (Role $role) => $role->givePermissionTo('reh.scores.view'));
            User::query()->whereHas('permissions', fn ($query) => $query->where('name', 'reh.sensitive')->where('guard_name', 'web'))->each(fn (User $user) => $user->givePermissionTo('reh.scores.view'));
        }
        foreach (config('access.role_profiles') as $name => $profile) {
            if ($profile['module'] !== 'reh') {
                continue;
            }
            $role = Role::findOrCreate($name, 'web');
            if ($role->wasRecentlyCreated) {
                $role->givePermissionTo($profile['default_permissions']);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
