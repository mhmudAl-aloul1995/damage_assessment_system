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
        Schema::create('inf_audit_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label_en')->nullable();
            $table->string('label_ar')->nullable();
            $table->unsignedInteger('order_step')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inf_audit_statuses');
    }
};
