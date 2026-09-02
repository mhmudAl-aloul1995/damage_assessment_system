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
        if (! Schema::hasTable('dashboard_cards')) {
            Schema::create('dashboard_cards', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->string('title');
                $table->string('subtitle')->nullable();
                $table->string('source_bucket');
                $table->string('total_stat_key');
                $table->string('icon')->default('ki-category');
                $table->string('color', 20)->default('#315f72');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->json('options')->nullable();
                $table->timestamps();
            });
        }

        Schema::dropIfExists('dashboard_card_items');

        Schema::create('dashboard_card_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dashboard_card_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('title');
            $table->string('source_bucket');
            $table->string('stat_key');
            $table->string('icon')->default('ki-dot');
            $table->string('link_group')->nullable();
            $table->string('link_key')->nullable();
            $table->string('calculation_type')->default('stat_key');
            $table->string('source_model')->nullable();
            $table->string('filter_field')->nullable();
            $table->string('filter_operator', 20)->nullable();
            $table->string('filter_value')->nullable();
            $table->string('value_suffix', 20)->nullable();
            $table->unsignedSmallInteger('decimal_places')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('options')->nullable();
            $table->timestamps();

            $table->unique(['dashboard_card_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_card_items');
    }
};
