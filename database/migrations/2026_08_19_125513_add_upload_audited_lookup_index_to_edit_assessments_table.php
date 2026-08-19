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
        Schema::table('edit_assessments', function (Blueprint $table): void {
            $table->index(['type', 'global_id', 'id'], 'edit_assessments_audited_lookup_index');
            $table->index(['type', 'updated_at', 'global_id'], 'edit_assessments_audited_changed_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('edit_assessments', function (Blueprint $table): void {
            $table->dropIndex('edit_assessments_audited_lookup_index');
            $table->dropIndex('edit_assessments_audited_changed_index');
        });
    }
};
