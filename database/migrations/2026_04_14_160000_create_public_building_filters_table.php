<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('public_building_filters')) {

            Schema::create('public_building_filters', function (Blueprint $table): void {
                $table->id();
                $table->string('list_name');
                $table->string('name');
                $table->text('label')->nullable();
                $table->string('gov')->nullable();
                $table->string('sort_order')->nullable();
                $table->string('sector')->nullable();
                $table->timestamps();

                $table->unique(['list_name', 'name'], 'public_building_filters_list_name_name_unique');
                $table->index('list_name');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('public_building_filters');
    }
};
