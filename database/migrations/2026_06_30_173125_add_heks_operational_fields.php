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
        if (Schema::hasTable('heks_beneficiaries')) {
            Schema::table('heks_beneficiaries', function (Blueprint $table) {
                if (! Schema::hasColumn('heks_beneficiaries', 'is_selected')) {
                    $table->boolean('is_selected')->default(false)->index()->after('recommendations');
                }

                if (! Schema::hasColumn('heks_beneficiaries', 'selection_source')) {
                    $table->string('selection_source')->nullable()->index()->after('is_selected');
                }

                if (! Schema::hasColumn('heks_beneficiaries', 'selection_status')) {
                    $table->string('selection_status')->nullable()->index()->after('selection_source');
                }

                if (! Schema::hasColumn('heks_beneficiaries', 'payment_status')) {
                    $table->string('payment_status')->nullable()->index()->after('selection_status');
                }

                if (! Schema::hasColumn('heks_beneficiaries', 'work_group_source')) {
                    $table->string('work_group_source')->nullable()->index()->after('payment_status');
                }
            });
        }

        if (! Schema::hasTable('heks_payments')) {
            Schema::create('heks_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('heks_beneficiary_id')->constrained('heks_beneficiaries')->cascadeOnDelete();
                $table->string('source')->nullable();
                $table->decimal('grant_amount', 12, 2)->nullable();
                $table->decimal('payment_1_amount', 12, 2)->nullable();
                $table->decimal('payment_2_amount', 12, 2)->nullable();
                $table->decimal('payment_3_amount', 12, 2)->nullable();
                $table->date('payment_1_date')->nullable();
                $table->date('payment_2_date')->nullable();
                $table->date('payment_3_date')->nullable();
                $table->string('payment_1_words')->nullable();
                $table->string('payment_2_words')->nullable();
                $table->string('payment_3_words')->nullable();
                $table->string('grant_words')->nullable();
                $table->json('raw_data')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('heks_work_assignments')) {
            Schema::create('heks_work_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('heks_beneficiary_id')->constrained('heks_beneficiaries')->cascadeOnDelete();
                $table->string('source')->nullable();
                $table->string('engineer_name')->nullable()->index();
                $table->decimal('contract_amount_ils', 12, 2)->nullable();
                $table->decimal('first_payment_ils', 12, 2)->nullable();
                $table->string('phone')->nullable();
                $table->json('raw_data')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('heks_scoring_weights')) {
            Schema::create('heks_scoring_weights', function (Blueprint $table) {
                $table->id();
                $table->string('source');
                $table->string('category')->nullable()->index();
                $table->text('indicator')->nullable();
                $table->decimal('weight', 8, 2)->nullable();
                $table->string('question_key')->nullable()->index();
                $table->string('option_value')->nullable();
                $table->decimal('option_score', 8, 2)->nullable();
                $table->json('raw_data')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('heks_attachments')) {
            Schema::create('heks_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('heks_beneficiary_id')->nullable()->constrained('heks_beneficiaries')->nullOnDelete();
                $table->string('source')->nullable()->index();
                $table->string('filename')->nullable();
                $table->text('url')->nullable();
                $table->unsignedInteger('source_index')->nullable();
                $table->unsignedInteger('parent_index')->nullable();
                $table->string('parent_table')->nullable();
                $table->string('attachment_type')->nullable()->index();
                $table->json('raw_data')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('heks_attachments');
        Schema::dropIfExists('heks_scoring_weights');
        Schema::dropIfExists('heks_work_assignments');
        Schema::dropIfExists('heks_payments');

        if (Schema::hasTable('heks_beneficiaries')) {
            Schema::table('heks_beneficiaries', function (Blueprint $table) {
                foreach (['work_group_source', 'payment_status', 'selection_status', 'selection_source', 'is_selected'] as $column) {
                    if (Schema::hasColumn('heks_beneficiaries', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
