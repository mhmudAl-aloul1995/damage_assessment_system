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
        Schema::create('heks_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('filename');
            $table->string('sheet_name')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('created_rows')->default(0);
            $table->unsignedInteger('updated_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->json('summary')->nullable();
            $table->timestamps();
        });

        Schema::create('heks_beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name')->nullable()->index();
            $table->string('identity_number')->nullable()->index();
            $table->string('phone')->nullable();
            $table->string('alternate_phone')->nullable();
            $table->string('field_engineer')->nullable()->index();
            $table->date('visit_date')->nullable();
            $table->string('governorate')->nullable()->index();
            $table->string('area')->nullable()->index();
            $table->text('address')->nullable();
            $table->string('household_head_gender')->nullable();
            $table->string('marital_status')->nullable();
            $table->string('displacement_status')->nullable();
            $table->string('occupancy_status')->nullable();
            $table->string('damage_status')->nullable();
            $table->decimal('grant_amount', 12, 2)->nullable();
            $table->decimal('payment_1', 12, 2)->nullable();
            $table->decimal('payment_2', 12, 2)->nullable();
            $table->decimal('payment_3', 12, 2)->nullable();
            $table->text('social_notes')->nullable();
            $table->text('engineer_notes')->nullable();
            $table->text('recommendations')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
        });

        Schema::create('heks_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('heks_beneficiary_id')->constrained('heks_beneficiaries')->cascadeOnDelete();
            $table->string('source')->nullable();
            $table->string('label_key');
            $table->text('label_value')->nullable();
            $table->string('version')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->index(['label_key', 'version']);
        });

        Schema::create('heks_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('heks_beneficiary_id')->constrained('heks_beneficiaries')->cascadeOnDelete();
            $table->string('code')->index();
            $table->string('visit_number')->nullable();
            $table->date('visit_date')->nullable();
            $table->string('engineer_name')->nullable()->index();
            $table->string('working_condition')->nullable()->index();
            $table->text('other_condition')->nullable();
            $table->decimal('completed_amount_ils', 12, 2)->nullable();
            $table->decimal('completion_percentage', 5, 2)->nullable();
            $table->text('engineer_recommendations')->nullable();
            $table->string('boq_filename')->nullable();
            $table->text('boq_url')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
        });

        Schema::create('heks_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('heks_beneficiary_id')->constrained('heks_beneficiaries')->cascadeOnDelete();
            $table->string('source')->nullable();
            $table->decimal('grant_amount', 12, 2)->nullable();
            $table->decimal('payment_1', 12, 2)->nullable();
            $table->decimal('payment_2', 12, 2)->nullable();
            $table->decimal('payment_3', 12, 2)->nullable();
            $table->decimal('social_score', 8, 2)->nullable();
            $table->decimal('technical_score', 8, 2)->nullable();
            $table->decimal('total_score', 8, 2)->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('heks_scores');
        Schema::dropIfExists('heks_follow_ups');
        Schema::dropIfExists('heks_labels');
        Schema::dropIfExists('heks_beneficiaries');
        Schema::dropIfExists('heks_imports');
    }
};
