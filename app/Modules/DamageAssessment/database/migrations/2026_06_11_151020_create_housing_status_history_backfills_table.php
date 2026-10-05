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
        if (Schema::hasTable('housing_status_history_backfills')) {
            return;
        }

        Schema::create('housing_status_history_backfills', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('housing_status_id');
            $table->unsignedBigInteger('housing_status_history_id');
            $table->timestamp('rolled_back_at')->nullable();
            $table->timestamps();

            $table->foreign('housing_status_id', 'hsh_backfills_status_fk')
                ->references('id')
                ->on('housing_statuses')
                ->cascadeOnDelete();
            $table->unique('housing_status_history_id', 'hsh_backfills_history_unique');
            $table->index(['housing_status_id', 'rolled_back_at'], 'hsh_backfills_status_rollback_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('housing_status_history_backfills');
    }
};
