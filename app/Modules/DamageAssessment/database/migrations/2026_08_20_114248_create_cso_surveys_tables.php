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
        Schema::create('cso_surveys', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('objectid')->nullable()->unique();
            $table->string('globalid')->nullable()->index();
            $table->longText('location')->nullable();
            $table->string('field_status')->nullable()->index();
            $table->string('assignedto')->nullable()->index();
            $table->string('governorate')->nullable()->index();
            $table->string('municipalitie')->nullable()->index();
            $table->string('neighborhood')->nullable()->index();
            $table->decimal('longitude', 12, 8)->nullable();
            $table->decimal('latitude', 12, 8)->nullable();
            $table->string('building_name')->nullable()->index();
            $table->string('organization_name')->nullable()->index();
            $table->string('building_damage_status')->nullable()->index();
            $table->string('operational_status')->nullable()->index();
            $table->date('damage_date')->nullable()->index();
            $table->timestamp('creationdate')->nullable()->index();
            $table->string('creator')->nullable();
            $table->timestamp('editdate')->nullable();
            $table->string('editor')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('cso_survey_filters', function (Blueprint $table): void {
            $table->id();
            $table->string('list_name')->index();
            $table->string('name');
            $table->string('label')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['list_name', 'name'], 'cso_survey_filters_list_name_name_unique');
        });

        Schema::create('cso_survey_audit_statuses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cso_survey_id')->nullable()->constrained('cso_surveys')->nullOnDelete();
            $table->unsignedBigInteger('objectid')->nullable()->index();
            $table->string('globalid')->nullable()->index();
            $table->foreignId('status_id')->constrained('inf_audit_statuses');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('cso_survey_id', 'cso_audit_status_survey_id_index');
        });

        Schema::create('cso_survey_audit_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cso_survey_id')->nullable()->constrained('cso_surveys')->nullOnDelete();
            $table->unsignedBigInteger('objectid')->nullable()->index();
            $table->string('globalid')->nullable()->index();
            $table->foreignId('status_id')->constrained('inf_audit_statuses');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cso_survey_audit_histories');
        Schema::dropIfExists('cso_survey_audit_statuses');
        Schema::dropIfExists('cso_survey_filters');
        Schema::dropIfExists('cso_surveys');
    }
};
