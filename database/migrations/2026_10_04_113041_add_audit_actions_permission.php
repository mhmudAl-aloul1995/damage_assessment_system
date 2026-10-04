<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Permission::findOrCreate('audit.actions', 'web');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Permission::query()
            ->where('name', 'audit.actions')
            ->where('guard_name', 'web')
            ->first()
            ?->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
