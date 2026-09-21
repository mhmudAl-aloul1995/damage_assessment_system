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
        Schema::create('arcgis_webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('source')->default('arcgis_cso');
            $table->string('webhook_name')->nullable();
            $table->json('event_names')->nullable();
            $table->string('status', 32)->default('received');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->boolean('signature_present')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->text('changes_url')->nullable();
            $table->json('payload')->nullable();
            $table->json('summary')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['source', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arcgis_webhook_deliveries');
    }
};
