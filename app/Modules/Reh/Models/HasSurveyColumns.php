<?php

namespace App\Modules\Reh\Models;

use App\Modules\Reh\Services\RehSurveyColumns;
use Carbon\CarbonImmutable;

trait HasSurveyColumns
{
    public function setSourceAttribute(array|string $value): void
    {
        $source = is_array($value) ? $value : json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        $this->attributes['source'] = json_encode($source, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    protected static function bootHasSurveyColumns(): void
    {
        static::saving(function (self $record): void {
            if (! $record->isDirty('source')) {
                return;
            }
            foreach (RehSurveyColumns::values($record->source ?? [], $record->surveyScope()) as $column => $answer) {
                if ($answer === null && in_array($column, ['floor_number', 'apartment_number', 'governorate', 'neighborhood'], true) && array_key_exists($column, $record->attributes)) {
                    continue;
                }
                $record->attributes[$column] = $answer;
            }
        });
    }

    public function surveyScope(): string
    {
        return $this instanceof RehBuilding ? 'building' : 'unit';
    }

    protected function surveyCasts(): array
    {
        $casts = ['survey_columns_ready' => 'boolean'];
        foreach (RehSurveyColumns::FIELDS[$this->surveyScope()] as [$column, $type]) {
            $cast = match ($type) {
                'integer' => 'integer',
                'decimal' => 'decimal:6',
                'date' => 'immutable_date',
                'datetime' => 'immutable_datetime',
                default => null,
            };
            if ($cast !== null) {
                $casts[$column] = $cast;
            }
        }

        return $casts;
    }

    public function surveyData(): array
    {
        $snapshot = $this->source ?? [];
        if (! ($this->attributes['survey_columns_ready'] ?? false) || $this->isDirty('source')) {
            return $snapshot;
        }
        $data = $snapshot;
        foreach (RehSurveyColumns::FIELDS[$this->surveyScope()] as $field => [$column, $type]) {
            $value = $this->attributes[$column] ?? null;
            if (! array_key_exists($field, $snapshot) && $value === null) {
                unset($data[$field]);

                continue;
            }
            if ($value !== null && in_array($type, ['integer', 'decimal'], true)) {
                $value = $type === 'integer' ? (int) $value : (float) $value;
            }
            if ($value !== null && in_array($type, ['date', 'datetime'], true) && is_numeric($snapshot[$field] ?? null)) {
                $value = CarbonImmutable::parse($value, config('app.timezone'))->getTimestampMs();
            }
            $data[$field] = $value;
        }
        foreach (['old_objectID', 'old_objectid'] as $legacy) {
            unset($data[$legacy]);
        }

        return $data;
    }
}
