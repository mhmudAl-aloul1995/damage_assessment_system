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
        Schema::create('cso_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cso_survey_id')->nullable()->constrained('cso_surveys')->nullOnDelete();
            $table->string('record_type');
            $table->unsignedBigInteger('record_id')->nullable();
            $table->string('record_globalid')->nullable()->index();
            $table->unsignedBigInteger('record_objectid')->nullable();
            $table->string('field_name');
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('legacy_edit_id')->nullable()->unique();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['record_type', 'record_id', 'field_name'], 'cso_audit_logs_record_field_index');
            $table->index(['cso_survey_id', 'id'], 'cso_audit_logs_survey_index');
        });

        app(\App\services\CsoAuditService::class)->importLegacyEdits();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cso_audit_logs');
    }
};
