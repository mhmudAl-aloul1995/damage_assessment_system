<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('heks_shelter_cases', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->unsignedTinyInteger('phase')->default(3)->index();
            $table->string('name');
            $table->string('identity_number', 20)->unique();
            $table->string('phone', 30)->nullable();
            $table->string('governorate')->nullable()->index();
            $table->string('area')->nullable();
            $table->foreignId('engineer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('visit_date')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('answers');
            $table->string('status')->default('received')->index();
            $table->string('engineering_status')->default('pending');
            $table->string('social_status')->default('pending');
            $table->unsignedInteger('version')->default(1);
            $table->decimal('grant_amount', 12, 2)->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('benefited_at')->nullable();
            $table->timestamps();
        });
        Schema::create('heks_shelter_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_id')->constrained('heks_shelter_cases')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->text('note');
            $table->json('details')->nullable();
            $table->timestamps();
        });
        Schema::create('heks_shelter_boq_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_id')->constrained('heks_shelter_cases')->cascadeOnDelete();
            $table->string('description');
            $table->string('unit', 30);
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 12, 2);
            $table->timestamps();
        });
        Schema::create('heks_shelter_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_id')->constrained('heks_shelter_cases')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('paid_on');
            $table->string('reference')->unique();
            $table->text('note');
            $table->timestamps();
        });
        Schema::create('heks_shelter_visits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_id')->constrained('heks_shelter_cases')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('visited_on');
            $table->decimal('completion', 5, 2);
            $table->text('note');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['visits', 'payments', 'boq_lines', 'events', 'cases'] as $table) {
            Schema::dropIfExists('heks_shelter_'.$table);
        }
    }
};
