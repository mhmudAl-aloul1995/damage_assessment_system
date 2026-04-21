<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('committee_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('title')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('signature_path')->nullable();
            $table->timestamps();
        });

        Schema::create('committee_decisions', function (Blueprint $table): void {
            $table->id();
            $table->nullableMorphs('decisionable');
            $table->string('decision_type')->nullable();
            $table->longText('decision_text')->nullable();
            $table->text('action_text')->nullable();
            $table->text('notes')->nullable();
            $table->date('decision_date')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('committee_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->string('whatsapp_status')->nullable();
            $table->timestamp('whatsapp_sent_at')->nullable();
            $table->timestamp('whatsapp_last_attempt_at')->nullable();
            $table->text('whatsapp_last_error')->nullable();
            $table->timestamp('arcgis_synced_at')->nullable();
            $table->timestamp('arcgis_last_attempt_at')->nullable();
            $table->string('arcgis_sync_status')->nullable();
            $table->text('arcgis_last_error')->nullable();
            $table->longText('arcgis_last_response')->nullable();
            $table->timestamps();

            $table->index(['status', 'decision_type']);
        });

        Schema::create('committee_decision_signatures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('committee_decision_id')->constrained('committee_decisions')->cascadeOnDelete();
            $table->foreignId('committee_member_id')->constrained('committee_members')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->foreignId('signed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['committee_decision_id', 'committee_member_id'], 'committee_decision_member_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('committee_decision_signatures');
        Schema::dropIfExists('committee_decisions');
        Schema::dropIfExists('committee_members');
    }
};
