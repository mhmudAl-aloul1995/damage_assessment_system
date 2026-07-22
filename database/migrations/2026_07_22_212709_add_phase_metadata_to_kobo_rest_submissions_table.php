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
            if (! Schema::hasColumn('kobo_rest_submissions', 'source_project')) {
                $table->string('source_project')->nullable()->after('service_name')->index();
            }

            if (! Schema::hasColumn('kobo_rest_submissions', 'survey_phase')) {
                $table->string('survey_phase')->nullable()->after('source_project')->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kobo_rest_submissions', function (Blueprint $table) {
            if (Schema::hasColumn('kobo_rest_submissions', 'survey_phase')) {
                $table->dropColumn('survey_phase');
            }

            if (Schema::hasColumn('kobo_rest_submissions', 'source_project')) {
                $table->dropColumn('source_project');
            }
        });
    }
};
