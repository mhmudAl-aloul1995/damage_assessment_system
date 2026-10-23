<?php

namespace App\Modules\HeksShelter\Services;

use App\Modules\HeksShelter\Models\ShelterCase;
use App\Modules\HeksShelter\Models\SurveyQuestion;
use App\Modules\HeksShelter\Models\SurveyRevision;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SurveyAnswerStorage
{
    public static function column(string $key): string
    {
        if (! preg_match('/^[a-z][a-z0-9_]{0,47}$/D', $key)) {
            throw new \InvalidArgumentException('مفتاح السؤال غير صالح.');
        }

        return 'sf_'.$key;
    }

    public function ensureColumns(SurveyRevision $revision): void
    {
        $columns = Schema::getColumnListing('heks_shelter_cases');
        $questions = $revision->questions()->get();
        Schema::table('heks_shelter_cases', function (Blueprint $table) use ($questions, $columns): void {
            foreach ($questions as $question) {
                if ($question->parent_key || $question->type === 'multiselect' || in_array(self::column($question->key), $columns, true)) {
                    continue;
                }
                $this->addColumn($table, $question);
            }
        });
        foreach ($questions->where('type', 'repeat') as $group) {
            $name = 'heks_rows_'.$group->key;
            if (! Schema::hasTable($name)) {
                Schema::create($name, function (Blueprint $table) use ($name): void {
                    $table->id();
                    $table->foreignId('case_id')->constrained('heks_shelter_cases', indexName: 'hr_'.sha1($name))->cascadeOnDelete();
                    $table->unsignedInteger('row_position');
                });
            }
            $existing = Schema::getColumnListing($name);
            Schema::table($name, function (Blueprint $table) use ($questions, $group, $existing): void {
                foreach ($questions->where('parent_key', $group->key) as $child) {
                    if (! in_array(self::column($child->key), $existing, true)) {
                        $this->addColumn($table, $child);
                    }
                }
            });
        }
    }

    private function addColumn(Blueprint $table, SurveyQuestion $question): void
    {
        $name = self::column($question->key);
        $column = match ($question->type) {
            'integer' => $table->bigInteger($name), 'decimal' => $table->decimal($name, 24, 8),
            'boolean' => $table->boolean($name), 'date' => $table->date($name),
            'select' => $table->string($name, 100), 'repeat' => $table->unsignedInteger($name),
            default => $table->text($name),
        };
        $column->nullable();
    }

    public function write(int $caseId, array $answers): void
    {
        $questions = SurveyQuestion::query()->whereHas('revision', fn ($query) => $query->where('status', 'published'))->get()->unique(fn ($question): string => ($question->parent_key ?? '').'.'.$question->key);
        $connection = (new ShelterCase)->getConnection();
        if (array_diff(array_keys($answers), $questions->whereNull('parent_key')->pluck('key')->all()) !== []) {
            throw new \RuntimeException('توجد إجابات دون تعريف سؤال؛ أوقف النقل لحماية البيانات.');
        }
        $values = [];
        foreach ($questions->whereNull('parent_key') as $question) {
            if (! array_key_exists($question->key, $answers)) {
                continue;
            }
            $value = $answers[$question->key];
            if ($question->type === 'multiselect') {
                $connection->table('heks_survey_selections')->where('case_id', $caseId)->where('field_key', $question->key)->delete();
                foreach ($value ?? [] as $selected) {
                    $connection->table('heks_survey_selections')->insert(['case_id' => $caseId, 'field_key' => $question->key, 'value' => $selected]);
                }
            } elseif ($question->type === 'repeat') {
                $values[self::column($question->key)] = $value === null ? null : count($value);
                $connection->table('heks_rows_'.$question->key)->where('case_id', $caseId)->delete();
                foreach ($value ?? [] as $index => $row) {
                    $attributes = ['case_id' => $caseId, 'row_position' => $index];
                    foreach ($questions->where('parent_key', $question->key) as $child) {
                        $attributes[self::column($child->key)] = $row[$child->key] ?? null;
                    }
                    $connection->table('heks_rows_'.$question->key)->insert($attributes);
                }
            } else {
                $values[self::column($question->key)] = $value;
            }
        }
        if ($values !== []) {
            $connection->table('heks_shelter_cases')->where('id', $caseId)->update($values);
        }
    }

    public function read(ShelterCase $case): array
    {
        $questions = app(SurveyDesigner::class)->questionsFor($case->survey_revision_id);
        $answers = [];
        foreach ($questions->whereNull('parent_key') as $question) {
            if ($question->type === 'multiselect') {
                $answers[$question->key] = $case->surveySelections->where('field_key', $question->key)->sortBy('id')->pluck('value')->values()->all();
            } elseif ($question->type === 'repeat') {
                $children = $questions->where('parent_key', $question->key);
                $answers[$question->key] = $case->getConnection()->table('heks_rows_'.$question->key)->where('case_id', $case->id)->orderBy('row_position')->get()->map(function ($row) use ($children): array {
                    $values = [];
                    foreach ($children as $child) {
                        $values[$child->key] = $this->typed($child, $row->{self::column($child->key)} ?? null);
                    }

                    return $values;
                })->all();
            } else {
                $answers[$question->key] = $this->typed($question, $case->getAttribute(self::column($question->key)));
            }
        }

        return $answers;
    }

    private function typed(SurveyQuestion $question, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($question->type) {
            'integer' => (int) $value, 'decimal' => (float) $value, 'boolean' => (bool) $value, default => $value
        };
    }

    public function recordChanges(int $caseId, int $eventId, array $before, array $after): void
    {
        $old = \Illuminate\Support\Arr::dot($before);
        $new = \Illuminate\Support\Arr::dot($after);
        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $path) {
            $a = $old[$path] ?? null;
            $b = $new[$path] ?? null;
            if ($a === $b || is_array($a) || is_array($b)) {
                continue;
            }
            (new ShelterCase)->getConnection()->table('heks_survey_answer_changes')->insert(['case_id' => $caseId, 'event_id' => $eventId, 'field_path' => $path, 'old_value' => $a === null ? null : (string) $a, 'new_value' => $b === null ? null : (string) $b]);
        }
    }
}
