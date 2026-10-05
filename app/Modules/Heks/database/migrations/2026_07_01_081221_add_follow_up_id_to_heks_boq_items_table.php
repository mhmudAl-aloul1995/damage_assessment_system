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
        Schema::table('heks_boq_items', function (Blueprint $table) {
            $table->foreignId('heks_follow_up_id')
                ->nullable()
                ->after('heks_beneficiary_id')
                ->constrained('heks_follow_ups')
                ->nullOnDelete();

            $table->index(['heks_beneficiary_id', 'heks_follow_up_id'], 'heks_boq_items_beneficiary_follow_up_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('heks_boq_items', function (Blueprint $table) {
            $table->dropIndex('heks_boq_items_beneficiary_follow_up_idx');
            $table->dropConstrainedForeignId('heks_follow_up_id');
        });
    }
};
