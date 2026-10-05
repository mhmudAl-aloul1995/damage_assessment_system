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
        Schema::create('audit_edit_deletion_batches', function (Blueprint $table) {
            $table->id();
            $table->string('target')->default('all')->index();
            $table->unsignedInteger('deleted_count')->default(0);
            $table->json('criteria')->nullable();
            $table->timestamp('restored_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_edit_deletion_batches');
    }
};
