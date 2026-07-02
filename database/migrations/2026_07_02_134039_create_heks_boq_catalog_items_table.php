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
        Schema::create('heks_boq_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('section')->nullable()->index();
            $table->string('item_code')->nullable()->index();
            $table->text('description');
            $table->string('unit')->nullable();
            $table->decimal('unit_price_ils', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('heks_boq_catalog_items');
    }
};
