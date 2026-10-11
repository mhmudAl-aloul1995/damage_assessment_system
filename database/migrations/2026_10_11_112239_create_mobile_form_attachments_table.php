<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_form_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mobile_form_submission_id')->nullable()->constrained()->cascadeOnDelete();
            $table->uuid('submission_uuid');
            $table->string('form_key', 80);
            $table->string('path');
            $table->string('name', 180);
            $table->string('mime', 80);
            $table->string('content_hash', 64);
            $table->unsignedInteger('size');
            $table->timestamps();
            $table->index(['user_id', 'submission_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_form_attachments');
    }
};
