<?php

use App\Modules\HeksShelter\Models\ShelterCase;
use App\Modules\HeksShelter\Models\SurveyRevision;
use App\Modules\HeksShelter\Services\SurveyAnswerStorage;
use App\Modules\HeksShelter\Services\SurveyDesigner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $create = static function (string $name, \Closure $definition): void {
            if (! Schema::hasTable($name)) {
                Schema::create($name, $definition);
            }
        };
        $create('heks_survey_revisions', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('status')->default('draft');
            $table->unsignedInteger('edit_version')->default(1);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
        $create('heks_survey_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('revision_id')->constrained('heks_survey_revisions')->cascadeOnDelete();
            $table->string('key', 48);
            $table->string('label');
            $table->string('type', 30);
            $table->string('section');
            $table->unsignedInteger('section_order')->default(0);
            $table->unsignedInteger('position')->default(0);
            $table->boolean('required')->default(false);
            $table->boolean('enabled')->default(true);
            $table->string('parent_key', 48)->nullable();
            $table->string('visible_field', 48)->nullable();
            $table->string('visible_value')->nullable();
            $table->decimal('minimum', 15, 3)->nullable();
            $table->decimal('maximum', 15, 3)->nullable();
            $table->unsignedInteger('max_length')->nullable();
            $table->string('help')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamps();
            $table->unique(['revision_id', 'key', 'parent_key'], 'hs_question_key_unique');
        });
        $create('heks_survey_choices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('question_id')->constrained('heks_survey_questions')->cascadeOnDelete();
            $table->string('value', 100);
            $table->string('label');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('exclusive')->default(false);
            $table->timestamps();
            $table->unique(['question_id', 'value']);
        });
        $create('heks_survey_selections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_id')->constrained('heks_shelter_cases')->cascadeOnDelete();
            $table->string('field_key', 48);
            $table->string('value', 100);
            $table->unique(['case_id', 'field_key', 'value'], 'hs_selection_unique');
            $table->index(['field_key', 'value']);
        });
        $create('heks_survey_answer_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_id')->constrained('heks_shelter_cases')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('heks_shelter_events')->cascadeOnDelete();
            $table->string('field_path', 180);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
        });
        if (! Schema::hasColumn('heks_shelter_cases', 'survey_revision_id')) {
            Schema::table('heks_shelter_cases', function (Blueprint $table): void {
                $table->foreignId('survey_revision_id')->nullable()->constrained('heks_survey_revisions');
            });
        }
        app(SurveyDesigner::class)->initialize();
        $revision = SurveyRevision::query()->where('status', 'published')->firstOrFail();
        app(SurveyAnswerStorage::class)->ensureColumns($revision);
        if (Schema::hasColumn('heks_shelter_cases', 'answers')) {
            ShelterCase::query()->orderBy('id')->chunkById(100, function ($cases) use ($revision): void {
                foreach ($cases as $case) {
                    $answers = json_decode($case->getRawOriginal('answers') ?? '{}', true, flags: JSON_THROW_ON_ERROR);
                    app(SurveyAnswerStorage::class)->write($case->id, $answers);
                    $case->getConnection()->table('heks_shelter_cases')->where('id', $case->id)->update(['survey_revision_id' => $revision->id]);
                }
            });
            Schema::table('heks_shelter_cases', function (Blueprint $table): void {
                $table->dropColumn('answers');
            });
        }
        \App\Modules\HeksShelter\Models\ShelterEvent::query()->where('action', 'survey')->chunkById(100, function ($events): void {
            foreach ($events as $event) {
                $details = $event->details ?? [];
                app(SurveyAnswerStorage::class)->recordChanges($event->case_id, $event->id, $details['before'] ?? [], $details['after'] ?? []);
                unset($details['before'],$details['after']);
                $event->update(['details' => $details]);
            }
        });
        if (Schema::hasTable('mobile_form_submissions')) {
            \App\Models\MobileFormSubmission::query()->where('form_key', 'heks-phase-three')->chunkById(100, function ($receipts) use ($revision): void {
                foreach ($receipts as $receipt) {
                    $receipt->update(['changes' => ['before' => [], 'after' => ['survey_revision_id' => $revision->id]]]);
                }
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('استعادة بنية التخزين السابقة تحتاج نسخة احتياطية؛ لا يُحذف تخزين إجابات الاستبيان تلقائياً.');
    }
};
