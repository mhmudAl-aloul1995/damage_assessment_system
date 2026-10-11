<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_form_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('submission_uuid');
            $table->string('module', 80);
            $table->string('form_key', 80);
            $table->string('form_version', 64);
            $table->unsignedBigInteger('record_id');
            $table->string('record_version', 64);
            $table->string('request_hash', 64);
            $table->longText('changes')->nullable();
            $table->longText('location')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'submission_uuid']);
            $table->index(['module', 'record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_form_submissions');
    }
};
