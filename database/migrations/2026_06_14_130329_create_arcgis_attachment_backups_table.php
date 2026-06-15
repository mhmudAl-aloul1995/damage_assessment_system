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
        if (Schema::hasTable('arcgis_attachment_backups')) {
            return;
        }

        Schema::create('arcgis_attachment_backups', function (Blueprint $table) {
            $table->id();
            $table->string('operation');
            $table->string('auditable_type')->default('building');
            $table->string('building_globalid')->nullable()->index();
            $table->unsignedBigInteger('building_objectid')->nullable()->index();
            $table->unsignedBigInteger('attachment_id');
            $table->string('attachment_name')->nullable();
            $table->string('content_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['building_globalid', 'attachment_id'], 'arcgis_backup_building_attachment_idx');
            $table->index(['operation', 'created_at'], 'arcgis_backup_operation_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arcgis_attachment_backups');
    }
};
