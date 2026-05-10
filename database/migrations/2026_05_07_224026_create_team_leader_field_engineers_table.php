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
        Schema::create('team_leader_field_engineers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_leader_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('field_engineer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['team_leader_id', 'field_engineer_id'], 'tl_fe_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_leader_field_engineers');
    }
};
