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
        if (Schema::hasTable('building_status_history_backfills')) {
            return;
        }

        Schema::create('building_status_history_backfills', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('building_status_id');
            $table->unsignedBigInteger('building_status_history_id');
            $table->timestamp('rolled_back_at')->nullable();
            $table->timestamps();

            $table->foreign('building_status_id', 'bsh_backfills_status_fk')
                ->references('id')
                ->on('building_statuses')
                ->cascadeOnDelete();
            $table->unique('building_status_history_id', 'bsh_backfills_history_unique');
            $table->index(['building_status_id', 'rolled_back_at'], 'bsh_backfills_status_rollback_idx');
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
