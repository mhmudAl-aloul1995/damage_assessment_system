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
        if (! Schema::hasColumn('heks_scores', 'classification')) {
            Schema::table('heks_scores', function (Blueprint $table) {
                $table->string('classification')->nullable()->after('total_score')->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('heks_scores', 'classification')) {
            Schema::table('heks_scores', function (Blueprint $table) {
                $table->dropColumn('classification');
            });
        }
    }
};
