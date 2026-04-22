<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_integrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('type', 20);
            $table->string('status', 30)->default('pending');
            $table->string('telegram_chat_id')->nullable();
            $table->string('telegram_username')->nullable();
            $table->string('telegram_title')->nullable();
            $table->string('linked_by')->nullable();
            $table->timestamp('linked_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['telegram_chat_id', 'type']);
        });

        Schema::create('telegram_link_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('telegram_integration_id')->constrained('telegram_integrations')->cascadeOnDelete();
            $table->string('token')->unique();
            $table->string('status', 30)->default('pending');
            $table->json('telegram_payload')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['telegram_integration_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_link_sessions');
        Schema::dropIfExists('telegram_integrations');
    }
};
