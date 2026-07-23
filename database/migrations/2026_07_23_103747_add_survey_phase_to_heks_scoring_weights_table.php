<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('heks_scoring_weights', function (Blueprint $table) {
            if (! Schema::hasColumn('heks_scoring_weights', 'survey_phase')) {
                $table->string('survey_phase')->default('phase_1')->after('source')->index();
            }
        });

        DB::table('heks_scoring_weights')
            ->whereNull('survey_phase')
            ->update(['survey_phase' => 'phase_1']);

        $now = now();
        DB::table('heks_scoring_weights')
            ->where('survey_phase', 'phase_1')
            ->orderBy('id')
            ->get()
            ->each(function (object $weight) use ($now): void {
                $exists = DB::table('heks_scoring_weights')
                    ->where('survey_phase', 'phase_2')
                    ->where('source', $weight->source)
                    ->where('question_key', $weight->question_key)
                    ->where('option_value', $weight->option_value)
                    ->exists();

                if ($exists) {
                    return;
                }

                DB::table('heks_scoring_weights')->insert([
                    'source' => $weight->source,
                    'survey_phase' => 'phase_2',
                    'category' => $weight->category,
                    'indicator' => $weight->indicator,
                    'weight' => $weight->weight,
                    'question_key' => $weight->question_key,
                    'option_value' => $weight->option_value,
                    'option_score' => $weight->option_score,
                    'raw_data' => $weight->raw_data,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('heks_scoring_weights', function (Blueprint $table) {
            if (Schema::hasColumn('heks_scoring_weights', 'survey_phase')) {
                $table->dropColumn('survey_phase');
            }
        });
    }
};
