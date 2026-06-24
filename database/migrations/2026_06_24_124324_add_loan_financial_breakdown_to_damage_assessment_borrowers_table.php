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
            $table->decimal('loan_portfolio_amount', 14, 2)->nullable()->after('loan_total_amount');
            $table->decimal('loan_net_amount', 14, 2)->nullable()->after('loan_portfolio_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('damage_assessment_borrowers', function (Blueprint $table) {
            $table->dropColumn(['loan_portfolio_amount', 'loan_net_amount']);
        });
    }
};
