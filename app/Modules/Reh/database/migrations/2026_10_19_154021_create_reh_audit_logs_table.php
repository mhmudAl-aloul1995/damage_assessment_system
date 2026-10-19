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
        Schema::create('reh_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('record_kind', 16);
            $table->unsignedBigInteger('record_id');
            $table->unsignedBigInteger('object_id');
            $table->uuid('global_id');
            $table->unsignedInteger('layer_id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('field');
            $table->boolean('sensitive')->default(false);
            $table->json('values');
            $table->text('reason');
            $table->string('status', 24)->default('pending');
            $table->text('message')->nullable();
            $table->timestamps();
            $table->index(['record_kind', 'record_id', 'id']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reh_audit_logs');
    }
};
