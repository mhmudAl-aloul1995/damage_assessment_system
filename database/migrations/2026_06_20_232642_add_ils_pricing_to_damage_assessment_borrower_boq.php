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
        Schema::table('damage_assessment_borrowers', function (Blueprint $table) {
            $table->decimal('exchange_rate', 10, 4)->default(3.2)->after('boq_total_usd');
            $table->decimal('boq_total_ils', 14, 2)->default(0)->after('exchange_rate');
        });

        Schema::table('damage_assessment_borrower_boq_catalog_items', function (Blueprint $table) {
            $table->decimal('unit_price_ils', 14, 2)->default(0)->after('unit_price');
        });

        Schema::table('damage_assessment_borrower_boq_items', function (Blueprint $table) {
            $table->decimal('exchange_rate', 10, 4)->default(3.2)->after('unit_price');
            $table->decimal('unit_price_ils', 14, 2)->default(0)->after('exchange_rate');
            $table->decimal('total_price_ils', 14, 2)->default(0)->after('total_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('damage_assessment_borrower_boq_items', function (Blueprint $table) {
            $table->dropColumn(['exchange_rate', 'unit_price_ils', 'total_price_ils']);
        });

        Schema::table('damage_assessment_borrower_boq_catalog_items', function (Blueprint $table) {
            $table->dropColumn('unit_price_ils');
        });

        Schema::table('damage_assessment_borrowers', function (Blueprint $table) {
            $table->dropColumn(['exchange_rate', 'boq_total_ils']);
        });
    }
};
