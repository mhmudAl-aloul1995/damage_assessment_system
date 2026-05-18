<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buildings', function (Blueprint $table) {
            if (! Schema::hasColumn('buildings', 'legal_challenge')) {
                $table->string('legal_challenge')->nullable()->after('doc_challenges_other');
            }
        });

        Schema::table('housing_units', function (Blueprint $table) {
            if (! Schema::hasColumn('housing_units', 'legal_challenge')) {
                $table->string('legal_challenge')->nullable()->after('house_unit_ownership');
            }
        });
    }

    public function down(): void
    {
        Schema::table('buildings', function (Blueprint $table) {
            if (Schema::hasColumn('buildings', 'legal_challenge')) {
                $table->dropColumn('legal_challenge');
            }
        });

        Schema::table('housing_units', function (Blueprint $table) {
            if (Schema::hasColumn('housing_units', 'legal_challenge')) {
                $table->dropColumn('legal_challenge');
            }
        });
    }
};
