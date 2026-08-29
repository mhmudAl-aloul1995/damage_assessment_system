<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'default_phase_number')) {
                $table->unsignedSmallInteger('default_phase_number')->nullable()->after('preferred_locale');
            }

            if (! Schema::hasColumn('users', 'allowed_phase_numbers')) {
                $table->json('allowed_phase_numbers')->nullable()->after('default_phase_number');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'allowed_phase_numbers')) {
                $table->dropColumn('allowed_phase_numbers');
            }

            if (Schema::hasColumn('users', 'default_phase_number')) {
                $table->dropColumn('default_phase_number');
            }
        });
    }
};
