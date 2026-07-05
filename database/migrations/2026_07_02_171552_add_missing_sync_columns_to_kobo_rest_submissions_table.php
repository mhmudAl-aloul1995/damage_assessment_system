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
        Schema::table('kobo_rest_submissions', function (Blueprint $table) {
            if (! Schema::hasColumn('kobo_rest_submissions', 'damage_assessment_borrower_id')) {
                $table->foreignId('damage_assessment_borrower_id')
                    ->nullable()
                    ->after('payload')
                    ->constrained('damage_assessment_borrowers')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('kobo_rest_submissions', 'sync_status')) {
                $table->string('sync_status')->default('pending')->index()->after('damage_assessment_borrower_id');
            }

            if (! Schema::hasColumn('kobo_rest_submissions', 'sync_error')) {
                $table->text('sync_error')->nullable()->after('sync_status');
            }

            if (! Schema::hasColumn('kobo_rest_submissions', 'synced_at')) {
                $table->timestamp('synced_at')->nullable()->index()->after('sync_error');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kobo_rest_submissions', function (Blueprint $table) {
            if (Schema::hasColumn('kobo_rest_submissions', 'damage_assessment_borrower_id')) {
                $table->dropConstrainedForeignId('damage_assessment_borrower_id');
            }

            foreach (['synced_at', 'sync_error', 'sync_status'] as $column) {
                if (Schema::hasColumn('kobo_rest_submissions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
