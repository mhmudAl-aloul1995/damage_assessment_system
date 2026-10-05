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
        Schema::create('audit_edit_deletion_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('audit_edit_deletion_batches')->cascadeOnDelete();
            $table->unsignedBigInteger('edit_assessment_id')->index();
            $table->text('global_id');
            $table->enum('type', ['building_table', 'housing_table'])->index();
            $table->string('field_name')->index();
            $table->longText('field_value')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->timestamp('edit_created_at')->nullable();
            $table->timestamp('edit_updated_at')->nullable();
            $table->timestamp('restored_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['batch_id', 'edit_assessment_id'], 'audit_edit_deletion_items_batch_edit_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_edit_deletion_items');
    }
};
