<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const ROLE_NAME = 'Audit Reviewer';

    private const LEGACY_AUDIT_REVIEWER_ID_NUMBERS = [
        '800409062',
        '400940623',
        '400591194',
        '404581993',
        '456901503',
        '400662938',
        '803275288',
        '800900607',
        '801773987',
        '405790619',
        '403697311',
        '803307669',
        '404030421',
        '403746530',
        '406966812',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate(self::ROLE_NAME, 'web');

        User::query()
            ->whereIn('id_no', self::LEGACY_AUDIT_REVIEWER_ID_NUMBERS)
            ->get()
            ->each(fn (User $user) => $user->assignRole(self::ROLE_NAME));
    }

    public function down(): void
    {
        User::query()
            ->whereIn('id_no', self::LEGACY_AUDIT_REVIEWER_ID_NUMBERS)
            ->get()
            ->each(fn (User $user) => $user->removeRole(self::ROLE_NAME));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
