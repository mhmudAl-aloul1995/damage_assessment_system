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
        Schema::create('building_status_history_backfills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_status_id')
                ->constrained('building_statuses')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('building_status_history_id')->unique();
            $table->timestamp('rolled_back_at')->nullable();
            $table->timestamps();

            $table->index(['building_status_id', 'rolled_back_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('building_status_history_backfills');
    }
};
