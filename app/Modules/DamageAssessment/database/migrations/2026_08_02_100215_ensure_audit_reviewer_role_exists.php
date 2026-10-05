<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Role::findOrCreate('Audit Reviewer', 'web');
    }

    public function down(): void
    {
        Role::query()
            ->where('name', 'Audit Reviewer')
            ->where('guard_name', 'web')
            ->delete();
    }
};
