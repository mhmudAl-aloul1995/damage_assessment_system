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
        Schema::create('missing_citizen_name_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('housing_unit_id');
            $table->string('owner_name')->nullable();
            $table->string('normalized_owner_name')->nullable();
            $table->timestamps();

            $table->unique('housing_unit_id', 'missing_citizen_name_reports_unit_id_unique');
            $table->index('owner_name', 'missing_citizen_name_reports_owner_name_index');
            $table->index('normalized_owner_name', 'missing_citizen_name_reports_normalized_name_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('missing_citizen_name_reports');
    }
};
