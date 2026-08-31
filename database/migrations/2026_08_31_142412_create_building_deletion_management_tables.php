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
        Schema::create('building_deletion_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('building_id')->nullable()->constrained('buildings')->nullOnDelete();
            $table->text('building_globalid');
            $table->integer('building_objectid')->nullable();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->text('notes')->nullable();
            $table->string('status')->index();
            $table->foreignId('gis_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('gis_reviewed_at')->nullable();
            $table->text('gis_notes')->nullable();
            $table->timestamp('snapshot_verified_at')->nullable();
            $table->timestamp('execution_started_at')->nullable();
            $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('executed_at')->nullable();
            $table->string('failed_step')->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('last_successful_step')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->json('deletion_plan')->nullable();
            $table->json('execution_results')->nullable();
            $table->timestamps();

            $table->index('building_globalid', 'building_deletion_requests_globalid_index');
            $table->index('building_objectid');
            $table->index('requested_by');
            $table->index('gis_reviewed_by');
            $table->index('created_at');
        });

        Schema::create('building_deletion_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->constrained('building_deletion_requests')->cascadeOnDelete();
            $table->foreignId('building_id')->nullable()->constrained('buildings')->nullOnDelete();
            $table->text('building_globalid');
            $table->integer('building_objectid')->nullable();
            $table->string('snapshot_version')->default('1.0');
            $table->json('base_data');
            $table->json('audited_data');
            $table->json('target_data')->nullable();
            $table->json('related_data');
            $table->json('attachments_data');
            $table->json('metadata');
            $table->json('schema_data');
            $table->string('snapshot_hash', 64);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index('building_globalid', 'building_deletion_snapshots_globalid_index');
            $table->index('snapshot_hash');
        });

        Schema::create('building_deletion_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->constrained('building_deletion_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('step')->index();
            $table->string('status')->index();
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['request_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('building_deletion_audit_logs');
        Schema::dropIfExists('building_deletion_snapshots');
        Schema::dropIfExists('building_deletion_signatures');
        Schema::dropIfExists('building_deletion_requests');
    }
};
