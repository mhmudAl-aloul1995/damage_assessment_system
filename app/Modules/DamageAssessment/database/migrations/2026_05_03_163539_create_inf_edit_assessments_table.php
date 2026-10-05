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
        Schema::create('inf_edit_assessments', function (Blueprint $table) {
            $table->id();
            $table->string('auditable_type')->nullable()->index();
            $table->unsignedBigInteger('auditable_id')->nullable()->index();
            $table->string('global_id')->nullable()->index();
            $table->unsignedBigInteger('objectid')->nullable()->index();
            $table->string('table_type')->index();
            $table->string('field_name')->index();
            $table->longText('field_value')->nullable();
            $table->longText('old_value')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['table_type', 'objectid', 'field_name'], 'inf_edit_table_object_field_idx');
            $table->index(['table_type', 'global_id', 'field_name'], 'inf_edit_table_global_field_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inf_edit_assessments');
    }
};
