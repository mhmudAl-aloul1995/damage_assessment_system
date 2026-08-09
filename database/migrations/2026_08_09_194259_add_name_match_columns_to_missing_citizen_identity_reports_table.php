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
        Schema::table('missing_citizen_identity_reports', function (Blueprint $table) {
            $table->string('normalized_owner_name')->nullable()->after('owner_name');
            $table->string('name_match_status', 30)->default('not_checked')->after('id_number');
            $table->unsignedBigInteger('matched_citizen_id')->nullable()->after('name_match_status');
            $table->string('matched_citizen_id_card_no')->nullable()->after('matched_citizen_id');
            $table->string('matched_citizen_full_name')->nullable()->after('matched_citizen_id_card_no');
            $table->unsignedInteger('matched_citizens_count')->default(0)->after('matched_citizen_full_name');
            $table->timestamp('approved_at')->nullable()->after('matched_citizens_count');
            $table->unsignedBigInteger('approved_by')->nullable()->after('approved_at');
            $table->string('arcgis_sync_status', 30)->nullable()->after('approved_by');
            $table->text('arcgis_sync_message')->nullable()->after('arcgis_sync_status');

            $table->index('normalized_owner_name', 'missing_citizen_identity_reports_normalized_owner_index');
            $table->index('name_match_status', 'missing_citizen_identity_reports_match_status_index');
            $table->index('matched_citizen_id_card_no', 'missing_citizen_identity_reports_matched_card_index');
            $table->index('approved_at', 'missing_citizen_identity_reports_approved_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('missing_citizen_identity_reports', function (Blueprint $table) {
            $table->dropIndex('missing_citizen_identity_reports_normalized_owner_index');
            $table->dropIndex('missing_citizen_identity_reports_match_status_index');
            $table->dropIndex('missing_citizen_identity_reports_matched_card_index');
            $table->dropIndex('missing_citizen_identity_reports_approved_at_index');

            $table->dropColumn([
                'normalized_owner_name',
                'name_match_status',
                'matched_citizen_id',
                'matched_citizen_id_card_no',
                'matched_citizen_full_name',
                'matched_citizens_count',
                'approved_at',
                'approved_by',
                'arcgis_sync_status',
                'arcgis_sync_message',
            ]);
        });
    }
};
