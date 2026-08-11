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
        Schema::create('lawyer_audit_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('excel_index')->unique();
            $table->unsignedInteger('source_row_number');
            $table->string('lawyer_name');
            $table->unsignedInteger('building_objectid')->nullable();
            $table->unsignedInteger('housing_unit_objectid')->nullable();
            $table->string('building_globalid');
            $table->string('housing_unit_globalid');
            $table->string('unit_owner')->nullable();
            $table->string('owner_full_name')->nullable();
            $table->string('id_number')->nullable();
            $table->string('mobile_number')->nullable();
            $table->string('unit_damage_status')->nullable();
            $table->string('floor_number')->nullable();
            $table->string('housing_unit_number')->nullable();
            $table->string('governorate')->nullable();
            $table->string('locality')->nullable();
            $table->string('neighborhood')->nullable();
            $table->string('street')->nullable();
            $table->string('closest_facility')->nullable();
            $table->timestamps();

            $table->index('lawyer_name');
            $table->index('building_globalid');
            $table->index('housing_unit_globalid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lawyer_audit_assignments');
    }
};
