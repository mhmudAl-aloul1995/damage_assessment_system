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
            $table->string('loan_number')->nullable()->index()->after('form_number');
            $table->string('loan_status')->nullable()->index()->after('loan_number');
            $table->decimal('loan_original_amount', 14, 2)->nullable()->after('loan_status');
            $table->decimal('loan_total_amount', 14, 2)->nullable()->after('loan_original_amount');
            $table->decimal('loan_balance', 14, 2)->nullable()->after('loan_total_amount');
            $table->decimal('loan_paid_amount', 14, 2)->nullable()->after('loan_balance');
            $table->unsignedSmallInteger('loan_installments_count')->nullable()->after('loan_paid_amount');
            $table->date('loan_started_at')->nullable()->after('loan_installments_count');
            $table->date('loan_last_installment_at')->nullable()->after('loan_started_at');
            $table->boolean('loan_clearance_delivered')->nullable()->after('loan_last_installment_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('damage_assessment_borrowers', function (Blueprint $table) {
            $table->dropColumn([
                'loan_number',
                'loan_status',
                'loan_original_amount',
                'loan_total_amount',
                'loan_balance',
                'loan_paid_amount',
                'loan_installments_count',
                'loan_started_at',
                'loan_last_installment_at',
                'loan_clearance_delivered',
            ]);
        });
    }
};
