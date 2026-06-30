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
        Schema::create('heks_boq_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('heks_beneficiary_id')->constrained('heks_beneficiaries')->cascadeOnDelete();
            $table->string('source')->nullable();
            $table->string('section')->nullable()->index();
            $table->string('item_code')->nullable()->index();
            $table->text('description');
            $table->string('unit')->nullable();
            $table->decimal('quantity', 12, 3)->default(0);
            $table->decimal('unit_price_ils', 12, 2)->default(0);
            $table->decimal('total_price_ils', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('heks_boq_items');
    }
};
