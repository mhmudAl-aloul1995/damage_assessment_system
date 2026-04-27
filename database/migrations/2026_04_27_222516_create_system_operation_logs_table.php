<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('system_operation_logs', function (Blueprint $table) {
            $table->id();

            $table->string('operation_type');
            // backup_database, sync_layer

            $table->string('status')->default('success');
            // success, failed

            $table->string('connection_name')->nullable();
            $table->string('layer_name')->nullable();
            $table->unsignedInteger('layer_id')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->string('file_path')->nullable();
            $table->unsignedInteger('total_records')->nullable();
            $table->text('message')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_operation_logs');
    }
};
