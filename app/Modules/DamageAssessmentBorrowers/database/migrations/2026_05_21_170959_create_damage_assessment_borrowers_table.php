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
        Schema::create('damage_assessment_borrowers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('submitted_by_name')->nullable();
            $table->dateTime('surveyed_at')->nullable();
            $table->string('form_number')->nullable()->index();
            $table->string('borrower_name')->index();
            $table->string('borrower_id_number')->nullable()->index();
            $table->unsignedSmallInteger('family_members_count')->nullable();
            $table->string('marital_status')->nullable();
            $table->string('spouse_name')->nullable();
            $table->string('spouse_id_number')->nullable();
            $table->string('employment_status')->nullable()->index();
            $table->boolean('is_borrower_alive')->default(true)->index();
            $table->json('vulnerability_types')->nullable();
            $table->unsignedSmallInteger('guarantors_count')->nullable();
            $table->string('guarantors_alive_status')->nullable();
            $table->json('deceased_guarantors')->nullable();
            $table->json('guarantors_employment_statuses')->nullable();
            $table->json('affected_guarantors')->nullable();
            $table->string('displacement_status')->nullable()->index();
            $table->string('displaced_to_governorate')->nullable()->index();
            $table->text('current_residence_address')->nullable();
            $table->string('phone_primary')->nullable();
            $table->string('phone_secondary')->nullable();
            $table->text('loan_unit_address')->nullable();
            $table->decimal('loan_unit_area', 10, 2)->nullable();
            $table->string('parcel_number')->nullable();
            $table->string('plot_number')->nullable();
            $table->string('loan_unit_occupancy_status')->nullable()->index();
            $table->json('resident_households')->nullable();
            $table->string('loan_unit_damage_status')->nullable()->index();
            $table->text('notes')->nullable();
            $table->string('risk_level')->default('low')->index();
            $table->unsignedSmallInteger('risk_score')->default(0);
            $table->json('risk_reasons')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('damage_assessment_borrowers');
    }
};
