<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reh_price_books', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('status', 20)->default('draft')->index();
            $t->decimal('exchange_rate', 12, 6);
            $t->date('effective_date');
            $t->string('source_name')->nullable();
            $t->string('source_hash', 64)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->unsignedInteger('revision')->default(1);
            $t->timestamps();
        });
        Schema::create('reh_price_items', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('book_id')->constrained('reh_price_books')->cascadeOnDelete();
            $t->string('code', 16);
            $t->string('field');
            $t->string('category');
            $t->text('label');
            $t->text('description');
            $t->string('unit', 32);
            $t->string('availability', 20)->default('available');
            $t->decimal('rate', 16, 6)->nullable();
            $t->text('issue')->nullable();
            $t->string('source_cell')->nullable();
            $t->unique(['book_id', 'code']);
            $t->unique(['book_id', 'field']);
            $t->timestamps();
        });
        Schema::create('reh_price_components', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('item_id')->constrained('reh_price_items')->cascadeOnDelete();
            $t->string('category', 80)->nullable();
            $t->text('label');
            $t->string('unit', 32)->nullable();
            $t->decimal('quantity', 16, 6)->nullable();
            $t->decimal('rate', 16, 6)->nullable();
            $t->string('source_cell')->nullable();
            $t->timestamps();
        });
        Schema::create('reh_cost_estimates', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('unit_id')->constrained('reh_units')->restrictOnDelete();
            $t->foreignId('book_id')->constrained('reh_price_books')->restrictOnDelete();
            $t->string('fingerprint', 64);
            $t->decimal('total', 18, 2);
            $t->decimal('exchange_rate', 12, 6);
            $t->decimal('total_usd', 18, 2);
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('reason');
            $t->timestamps();
            $t->unique(['unit_id', 'book_id', 'fingerprint'], 'reh_cost_estimate_unique');
        });
        Schema::create('reh_cost_lines', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('estimate_id')->constrained('reh_cost_estimates')->cascadeOnDelete();
            $t->string('code', 16);
            $t->string('field');
            $t->text('label');
            $t->string('unit', 32);
            $t->decimal('quantity', 16, 6);
            $t->decimal('rate', 16, 6);
            $t->decimal('amount', 18, 2);
        });
        Schema::create('reh_price_logs', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('book_id')->constrained('reh_price_books')->restrictOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action', 32);
            $t->text('reason');
            $t->json('changes');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['reh_price_logs', 'reh_cost_lines', 'reh_cost_estimates', 'reh_price_components', 'reh_price_items', 'reh_price_books'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
