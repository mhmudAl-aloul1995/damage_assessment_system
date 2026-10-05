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
        if (! Schema::hasColumn('housing_units', 'family_members_count')) {
            Schema::table('housing_units', function (Blueprint $table) {
                $table->unsignedInteger('family_members_count')->nullable()->after('lactating');
            });
        }

        if (Schema::hasTable('audited_housing_units') && ! Schema::hasColumn('audited_housing_units', 'family_members_count')) {
            Schema::table('audited_housing_units', function (Blueprint $table) {
                $table->unsignedInteger('family_members_count')->nullable()->after('lactating');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('housing_units', 'family_members_count')) {
            Schema::table('housing_units', function (Blueprint $table) {
                $table->dropColumn('family_members_count');
            });
        }

        if (Schema::hasTable('audited_housing_units') && Schema::hasColumn('audited_housing_units', 'family_members_count')) {
            Schema::table('audited_housing_units', function (Blueprint $table) {
                $table->dropColumn('family_members_count');
            });
        }
    }
};
