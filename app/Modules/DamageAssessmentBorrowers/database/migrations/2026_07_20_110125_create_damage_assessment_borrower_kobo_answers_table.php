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
        Schema::create('damage_assessment_borrower_kobo_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('damage_assessment_borrower_id');
            $table->foreignId('kobo_rest_submission_id')->nullable();
            $table->string('field_hash', 40);
            $table->text('field_key');
            $table->text('field_label')->nullable();
            $table->longText('value')->nullable();
            $table->json('raw_value')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['damage_assessment_borrower_id', 'field_hash'], 'brw_kobo_answers_borrower_field_unique');
            $table->index('field_hash', 'brw_kobo_answers_field_hash_idx');
            $table->foreign('damage_assessment_borrower_id', 'brw_kobo_answers_borrower_fk')
                ->references('id')
                ->on('damage_assessment_borrowers')
                ->cascadeOnDelete();
            $table->foreign('kobo_rest_submission_id', 'brw_kobo_answers_submission_fk')
                ->references('id')
                ->on('kobo_rest_submissions')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('damage_assessment_borrower_kobo_answers');
    }
};
