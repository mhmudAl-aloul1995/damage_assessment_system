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
            $table->string('issue_type', 40)
                ->default('missing_civil_registry_identity')
                ->after('id_number')
                ->index('missing_citizen_identity_reports_issue_type_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('missing_citizen_identity_reports', function (Blueprint $table) {
            $table->dropIndex('missing_citizen_identity_reports_issue_type_index');
            $table->dropColumn('issue_type');
        });
    }
};
