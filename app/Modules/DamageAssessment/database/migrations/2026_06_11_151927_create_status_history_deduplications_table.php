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
        if (Schema::hasTable('status_history_deduplications')) {
            return;
        }

        Schema::create('status_history_deduplications', function (Blueprint $table) {
            $table->id();
            $table->string('source_table');
            $table->unsignedBigInteger('history_id');
            $table->longText('payload');
            $table->timestamp('restored_at')->nullable();
            $table->timestamps();

            $table->unique(['source_table', 'history_id'], 'status_dedupe_source_history_unique');
            $table->index(['source_table', 'restored_at'], 'status_dedupe_source_restored_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('status_history_deduplications');
    }
};
