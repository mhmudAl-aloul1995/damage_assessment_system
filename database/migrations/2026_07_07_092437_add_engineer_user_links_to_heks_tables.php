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
        if (Schema::hasTable('heks_beneficiaries') && ! Schema::hasColumn('heks_beneficiaries', 'field_engineer_user_id')) {
            Schema::table('heks_beneficiaries', function (Blueprint $table): void {
                $table->foreignId('field_engineer_user_id')
                    ->nullable()
                    ->after('field_engineer')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('heks_follow_ups') && ! Schema::hasColumn('heks_follow_ups', 'engineer_user_id')) {
            Schema::table('heks_follow_ups', function (Blueprint $table): void {
                $table->foreignId('engineer_user_id')
                    ->nullable()
                    ->after('engineer_name')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('heks_work_assignments') && ! Schema::hasColumn('heks_work_assignments', 'engineer_user_id')) {
            Schema::table('heks_work_assignments', function (Blueprint $table): void {
                $table->foreignId('engineer_user_id')
                    ->nullable()
                    ->after('engineer_name')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('heks_work_assignments') && Schema::hasColumn('heks_work_assignments', 'engineer_user_id')) {
            Schema::table('heks_work_assignments', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('engineer_user_id');
            });
        }

        if (Schema::hasTable('heks_follow_ups') && Schema::hasColumn('heks_follow_ups', 'engineer_user_id')) {
            Schema::table('heks_follow_ups', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('engineer_user_id');
            });
        }

        if (Schema::hasTable('heks_beneficiaries') && Schema::hasColumn('heks_beneficiaries', 'field_engineer_user_id')) {
            Schema::table('heks_beneficiaries', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('field_engineer_user_id');
            });
        }
    }
};
