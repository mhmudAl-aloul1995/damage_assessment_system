<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('CREATE INDEX edit_assessments_audited_lookup_index ON edit_assessments (`type`, `global_id`(191), `id`)');
            DB::statement('CREATE INDEX edit_assessments_audited_changed_index ON edit_assessments (`type`, `updated_at`, `global_id`(191))');

            return;
        }

        Schema::table('edit_assessments', function ($table): void {
            $table->index(['type', 'id'], 'edit_assessments_audited_lookup_index');
            $table->index(['type', 'updated_at'], 'edit_assessments_audited_changed_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('edit_assessments', function ($table): void {
            $table->dropIndex('edit_assessments_audited_lookup_index');
            $table->dropIndex('edit_assessments_audited_changed_index');
        });
    }
};
