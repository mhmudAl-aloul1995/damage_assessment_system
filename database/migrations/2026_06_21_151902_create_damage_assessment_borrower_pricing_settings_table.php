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
        Schema::create('damage_assessment_borrower_pricing_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('exchange_rate', 10, 4)->default(3.2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('damage_assessment_borrower_pricing_settings');
    }
};
