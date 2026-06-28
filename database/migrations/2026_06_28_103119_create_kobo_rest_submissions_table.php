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
        Schema::create('kobo_rest_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('service_name')->index();
            $table->string('submission_uuid')->nullable()->unique();
            $table->json('payload');
            $table->foreignId('damage_assessment_borrower_id')->nullable()->constrained('damage_assessment_borrowers')->nullOnDelete();
            $table->string('sync_status')->default('pending')->index();
            $table->text('sync_error')->nullable();
            $table->timestamp('synced_at')->nullable()->index();
            $table->string('source_ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('received_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kobo_rest_submissions');
    }
};
