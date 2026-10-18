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
        Schema::create('reh_buildings', function (Blueprint $table) {
            $table->id();
            $table->uuid('global_id')->unique();
            $table->unsignedBigInteger('object_id')->unique();
            $table->string('name')->nullable();
            $table->string('governorate')->nullable()->index();
            $table->string('neighborhood')->nullable()->index();
            $table->string('engineer')->nullable()->index();
            $table->string('social_worker')->nullable();
            $table->json('source');
            $table->json('geometry')->nullable();
            $table->string('source_hash', 64);
            $table->boolean('source_missing')->default(false);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reh_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained('reh_buildings')->restrictOnDelete();
            $table->uuid('global_id')->unique();
            $table->unsignedBigInteger('object_id')->unique();
            $table->string('code')->nullable()->index();
            $table->string('owner_name')->nullable()->index();
            $table->string('owner_identity')->nullable()->index();
            $table->integer('floor_number')->nullable();
            $table->integer('apartment_number')->nullable();
            $table->json('source');
            $table->string('source_hash', 64);
            $table->boolean('source_missing')->default(false);
            $table->string('workflow_status')->default('new')->index();
            $table->string('engineering_status')->default('pending');
            $table->string('social_status')->default('pending');
            $table->string('decision')->default('pending')->index();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('quantity_overrides')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reh_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('reh_units')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->text('note');
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('reh_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('queued')->index();
            $table->unsignedInteger('buildings_count')->default(0);
            $table->unsignedInteger('units_count')->default(0);
            $table->text('message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reh_sync_runs');
        Schema::dropIfExists('reh_reviews');
        Schema::dropIfExists('reh_units');
        Schema::dropIfExists('reh_buildings');
    }
};
