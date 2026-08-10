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
        Schema::create('access_civil_registry_records', function (Blueprint $table) {
            $table->id();
            $table->string('id_card_no', 20)->nullable();
            $table->string('first_name')->nullable();
            $table->string('father_name')->nullable();
            $table->string('grand_name')->nullable();
            $table->string('family_name')->nullable();
            $table->string('full_name')->nullable();
            $table->string('full_name_normalized')->nullable();
            $table->string('mother_name')->nullable();
            $table->string('neighborhood')->nullable();
            $table->date('birth_date')->nullable();
            $table->timestamps();

            $table->unique('id_card_no', 'access_civil_registry_records_id_card_unique');
            $table->index('full_name_normalized', 'access_civil_registry_records_name_normalized_index');
            $table->index('full_name', 'access_civil_registry_records_full_name_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_civil_registry_records');
    }
};
