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
        Schema::dropIfExists('building_deletion_signatures');

        Schema::table('building_deletion_requests', function (Blueprint $table): void {
            $table->boolean('requires_field_engineer_approvals')->default(false)->after('status');
            $table->foreignId('team_leader_reviewed_by')->nullable()->after('requires_field_engineer_approvals')->constrained('users')->nullOnDelete();
            $table->timestamp('team_leader_reviewed_at')->nullable()->after('team_leader_reviewed_by');
            $table->text('team_leader_notes')->nullable()->after('team_leader_reviewed_at');
            $table->foreignId('area_manager_reviewed_by')->nullable()->after('team_leader_notes')->constrained('users')->nullOnDelete();
            $table->timestamp('area_manager_reviewed_at')->nullable()->after('area_manager_reviewed_by');
            $table->text('area_manager_notes')->nullable()->after('area_manager_reviewed_at');

            $table->index('requires_field_engineer_approvals', 'building_deletion_requests_requires_fe_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('building_deletion_requests', function (Blueprint $table): void {
            $table->dropIndex('building_deletion_requests_requires_fe_index');
            $table->dropConstrainedForeignId('area_manager_reviewed_by');
            $table->dropColumn('area_manager_reviewed_at');
            $table->dropColumn('area_manager_notes');
            $table->dropConstrainedForeignId('team_leader_reviewed_by');
            $table->dropColumn('team_leader_reviewed_at');
            $table->dropColumn('team_leader_notes');
            $table->dropColumn('requires_field_engineer_approvals');
        });

        if (! Schema::hasTable('building_deletion_signatures')) {
            Schema::create('building_deletion_signatures', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('request_id')->constrained('building_deletion_requests')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
                $table->string('role');
                $table->string('action');
                $table->string('signature_path');
                $table->text('notes')->nullable();
                $table->timestamp('signed_at');
                $table->timestamps();

                $table->index(['request_id', 'action']);
                $table->unique(['request_id', 'user_id', 'action'], 'building_deletion_signature_unique_action');
            });
        }
    }
};
