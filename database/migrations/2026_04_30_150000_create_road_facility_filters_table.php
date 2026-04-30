<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('road_facility_filters')) {

            Schema::create('road_facility_filters', function (Blueprint $table): void {
                $table->id();
                $table->string('list_name');
                $table->string('name');
                $table->string('label')->nullable();
                $table->string('group_value')->nullable();
                $table->unsignedInteger('sort_order')->nullable();

                $table->unique(['list_name', 'name'], 'road_facility_filters_list_name_name_unique');
                $table->index('list_name');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('road_facility_filters');
    }
};
