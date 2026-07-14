<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('heks_kobo_choices', function (Blueprint $table): void {
            $table->id();
            $table->string('service_name')->index();
            $table->text('question_key');
            $table->string('list_name')->nullable()->index();
            $table->string('choice_name')->index();
            $table->text('choice_label')->nullable();
            $table->string('language')->nullable();
            $table->string('version')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->unique(['service_name', 'question_key', 'choice_name', 'language'], 'heks_kobo_choices_question_unique');
            $table->index(['service_name', 'question_key'], 'heks_kobo_choices_question_idx');
        });

        Schema::table('heks_kobo_field_mappings', function (Blueprint $table): void {
            if (! Schema::hasColumn('heks_kobo_field_mappings', 'field_type')) {
                $table->string('field_type')->nullable()->after('data_type')->index('heks_map_field_type_idx');
            }

            if (! Schema::hasColumn('heks_kobo_field_mappings', 'list_name')) {
                $table->string('list_name')->nullable()->after('field_type')->index('heks_map_list_name_idx');
            }

            if (! Schema::hasColumn('heks_kobo_field_mappings', 'language')) {
                $table->string('language')->nullable()->after('list_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('heks_kobo_field_mappings', function (Blueprint $table): void {
            foreach (['language', 'list_name', 'field_type'] as $column) {
                if (Schema::hasColumn('heks_kobo_field_mappings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('heks_kobo_choices');
    }
};
