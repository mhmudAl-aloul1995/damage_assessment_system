<?php

declare(strict_types=1);

namespace App\services;

use App\Models\CsoAuditLog;
use App\Models\CsoSurvey;
use App\Models\CsoSurveyOrganization;
use App\Models\CsoSurveyUnit;
use App\Models\InfEditAssessment;
use App\Support\Forms\CsoSurveyLayout;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class CsoAuditService
{
    /** @var array<string, array<string, array<string, mixed>>> */
    private array $editableFieldCache = [];

    private const RECORDS = [
        'cso_survey_table' => [CsoSurvey::class, 'cso_survey', 0, 'cso_survey_layer_url'],
        'cso_survey_organization_table' => [CsoSurveyOrganization::class, 'cso_survey_organization', 1, 'cso_survey_organizations_layer_url'],
        'cso_survey_unit_table' => [CsoSurveyUnit::class, 'cso_survey_unit', 2, 'cso_survey_units_layer_url'],
    ];

    /** @return array<string, array<string, mixed>> */
    public function editableFields(string $tableType): array
    {
        if (isset($this->editableFieldCache[$tableType])) {
            return $this->editableFieldCache[$tableType];
        }

        $sections = match ($tableType) {
            'cso_survey_organization_table' => CsoSurveyLayout::repeatSections('CSO_Organizations'),
            'cso_survey_unit_table' => CsoSurveyLayout::repeatSections('Unit_Information'),
            'cso_survey_table' => collect(CsoSurveyLayout::sections())->except(array_merge(
                CsoSurveyLayout::repeatSectionNames('CSO_Organizations'),
                CsoSurveyLayout::repeatSectionNames('Unit_Information'),
                ['CSO_Organizations', 'Unit_Information'],
            ))->all(),
            default => [],
        };

        return $this->editableFieldCache[$tableType] = collect($sections)->flatMap(fn (array $section): array => $section['fields'] ?? [])
            ->filter(fn (array $field): bool => in_array($field['type'], ['text', 'integer', 'decimal', 'date', 'select_one', 'select_multiple'], true)
                && ! in_array(strtolower($field['name']), ['id', 'objectid', 'globalid', 'parentglobalid', 'creationdate', 'creator', 'editdate', 'editor', 'phase_number'], true))
            ->keyBy('name')->all();
    }

    public function recordType(string $tableType): string
    {
        return self::RECORDS[$tableType][1];
    }

    public function value(Model $record, string $fieldName): mixed
    {
        if (array_key_exists($fieldName, $record->getAttributes())) {
            if ($record->hasCast($fieldName, ['date', 'immutable_date'])) {
                return $record->getAttribute($fieldName)?->format('Y-m-d');
            }

            return $record->getRawOriginal($fieldName);
        }

        foreach ($record->raw_payload ?? [] as $key => $value) {
            if (strcasecmp((string) $key, $fieldName) === 0) {
                return $value;
            }
        }

        return null;
    }

    public function save(CsoSurvey $survey, Model $record, string $tableType, string $fieldName, ?string $newValue, int $userId, ?string $notes = null, ?string $expectedValue = null, bool $checkExpectedValue = false): void
    {
        if (! isset($this->editableFields($tableType)[$fieldName])) {
            throw ValidationException::withMessages(['field_name' => 'هذا الحقل غير قابل للتعديل.']);
        }

        if (! filled($record->objectid)) {
            throw ValidationException::withMessages(['auditable_id' => 'السجل لا يحتوي على معرّف ArcGIS صالح.']);
        }

        $remoteUpdated = false;
        $remote = null;

        try {
            $record->getConnection()->transaction(function () use ($survey, $record, $tableType, $fieldName, $newValue, $userId, $notes, $expectedValue, $checkExpectedValue, &$remoteUpdated, &$remote): void {
                $locked = $record->newQuery()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

                if (! $locked instanceof CsoSurvey && $locked->parentglobalid !== $survey->globalid) {
                    throw new HttpException(404, 'السجل غير تابع لهذا الاستبيان.');
                }

                $oldValue = $this->value($locked, $fieldName);

                if ($checkExpectedValue && $this->comparisonValue($oldValue) !== $this->comparisonValue($expectedValue)) {
                    throw new HttpException(409, 'تغيّرت القيمة منذ فتح الشاشة. حدّث الصفحة ثم أعد المحاولة.');
                }

                if ($this->comparisonValue($oldValue) === $this->comparisonValue($newValue)) {
                    return;
                }

                $remote = $this->remoteField($tableType, $locked, $fieldName, $newValue);
                $value = $remote['local_value'];

                if ($this->comparisonValue($oldValue) === $this->comparisonValue($value)) {
                    return;
                }

                $this->applyValue($locked, $fieldName, $value);

                CsoAuditLog::query()->create([
                    'cso_survey_id' => $survey->id,
                    'record_type' => $this->recordType($tableType),
                    'record_id' => $locked->id,
                    'record_globalid' => $locked->globalid,
                    'record_objectid' => $locked->objectid,
                    'field_name' => $fieldName,
                    'old_value' => $oldValue,
                    'new_value' => $value,
                    'user_id' => $userId,
                    'notes' => $notes,
                    'created_at' => now(),
                ]);

                $this->updateRemote($remote, $remote['new_value']);
                $remoteUpdated = true;
            });
        } catch (Throwable $exception) {
            if ($remoteUpdated && $remote !== null) {
                try {
                    $this->updateRemote($remote, $remote['old_value']);
                } catch (Throwable) {
                    Log::error('CSO ArcGIS compensation failed.', ['record_type' => $this->recordType($tableType), 'record_id' => $record->id, 'field_name' => $fieldName]);
                    throw new HttpException(502, 'تم التعديل في ArcGIS وتعذر إكمال الحفظ المحلي أو استعادة القيمة. يلزم التحقق من السجل.', $exception);
                }

                throw new HttpException(500, 'تعذر إكمال الحفظ المحلي، وتمت استعادة قيمة ArcGIS السابقة.', $exception);
            }

            throw $exception;
        }

        $record->refresh();
    }

    /** @return array<string, mixed> */
    private function remoteField(string $tableType, Model $record, string $fieldName, ?string $newValue): array
    {
        $url = $this->layerUrl($tableType);

        try {
            $token = app(ArcgisService::class)->getToken();
            $response = Http::asForm()->timeout(30)->connectTimeout(10)->get($url, ['f' => 'json', 'token' => $token]);

            if (! $response->successful() || $response->json('error') !== null) {
                throw new HttpException(502, 'تعذر قراءة تعريف حقول ArcGIS.');
            }

            $fields = collect($response->json('fields') ?? []);
            $field = $fields->first(fn (array $field): bool => strcasecmp($field['name'], $fieldName) === 0);
            $objectIdField = $response->json('objectIdField') ?: ($fields->firstWhere('type', 'esriFieldTypeOID')['name'] ?? null);

            if ($field === null || ($field['editable'] ?? true) === false || ! $objectIdField) {
                throw ValidationException::withMessages(['field_name' => 'الحقل غير موجود أو غير قابل للتعديل في ArcGIS.']);
            }

            [$localValue, $wireValue] = $this->normalizeValue($newValue, $field);
            $source = Http::timeout(30)->connectTimeout(10)->get($url.'/query', [
                'f' => 'json', 'token' => $token,
                'where' => $objectIdField.' = '.(int) $record->objectid,
                'outFields' => $objectIdField.','.$field['name'],
                'returnGeometry' => 'false',
            ]);
            $attributes = $source->json('features.0.attributes');

            if (! $source->successful() || $source->json('error') !== null || ! is_array($attributes) || ! array_key_exists($field['name'], $attributes)) {
                throw new HttpException(502, 'تعذر قراءة السجل الحالي من ArcGIS.');
            }

            return [
                'url' => $url, 'token' => $token, 'objectid' => (int) $record->objectid,
                'objectid_field' => $objectIdField, 'field_name' => $field['name'],
                'old_value' => $attributes[$field['name']], 'new_value' => $wireValue, 'local_value' => $localValue,
            ];
        } catch (ValidationException|HttpException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new HttpException(502, 'تعذر الاتصال بـ ArcGIS. لم يتم حفظ التعديل.', $exception);
        }
    }

    /** @param array<string, mixed> $remote */
    private function updateRemote(array $remote, mixed $value): void
    {
        try {
            $response = Http::asForm()->timeout(30)->connectTimeout(10)->post($remote['url'].'/updateFeatures', [
                'f' => 'json', 'token' => $remote['token'], 'rollbackOnFailure' => 'true',
                'features' => json_encode([['attributes' => [$remote['objectid_field'] => $remote['objectid'], $remote['field_name'] => $value]]], JSON_THROW_ON_ERROR),
            ]);

            if (! $response->successful() || $response->json('error') !== null || $response->json('updateResults.0.success') !== true) {
                throw new HttpException(502, 'رفض ArcGIS التعديل. لم يتم حفظ التعديل محلياً.');
            }
        } catch (HttpException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new HttpException(502, 'تعذر تأكيد حفظ التعديل في ArcGIS. لم يتم حفظ التعديل محلياً؛ تحقق من ArcGIS قبل إعادة المحاولة.', $exception);
        }
    }

    /** @param array<string, mixed> $field
     * @return array{0: mixed, 1: mixed}
     */
    private function normalizeValue(?string $value, array $field): array
    {
        $type = $field['type'];
        $rules = $value === null ? [($field['nullable'] ?? true) ? 'nullable' : 'required'] : match ($type) {
            'esriFieldTypeSmallInteger', 'esriFieldTypeInteger', 'esriFieldTypeBigInteger' => ['integer'],
            'esriFieldTypeSingle', 'esriFieldTypeDouble' => ['numeric'],
            'esriFieldTypeDate', 'esriFieldTypeDateOnly' => ['date'],
            'esriFieldTypeString' => ['string', 'max:'.($field['length'] ?? 10000)],
            default => ['prohibited'],
        };
        Validator::make(['field_value' => $value], ['field_value' => $rules], [
            'field_value.required' => 'هذا الحقل لا يقبل قيمة فارغة.',
            'field_value.integer' => 'يجب إدخال عدد صحيح.',
            'field_value.numeric' => 'يجب إدخال قيمة رقمية.',
            'field_value.date' => 'يجب إدخال تاريخ صالح.',
            'field_value.max' => 'القيمة تتجاوز طول الحقل المسموح.',
            'field_value.prohibited' => 'نوع هذا الحقل غير قابل للتعديل.',
        ])->validate();

        $localValue = $value === null ? null : match ($type) {
            'esriFieldTypeSmallInteger', 'esriFieldTypeInteger', 'esriFieldTypeBigInteger' => (int) $value,
            'esriFieldTypeSingle', 'esriFieldTypeDouble' => (float) $value,
            'esriFieldTypeDate', 'esriFieldTypeDateOnly' => CarbonImmutable::parse($value)->toDateString(),
            default => $value,
        };
        $domain = $field['domain'] ?? [];

        if ($localValue !== null && ($domain['type'] ?? null) === 'codedValue'
            && ! collect($domain['codedValues'])->contains(fn (array $choice): bool => (string) $choice['code'] === (string) $localValue)) {
            throw ValidationException::withMessages(['field_value' => 'القيمة غير موجودة ضمن خيارات ArcGIS.']);
        }

        if ($localValue !== null && ($domain['type'] ?? null) === 'range'
            && ($localValue < $domain['range'][0] || $localValue > $domain['range'][1])) {
            throw ValidationException::withMessages(['field_value' => 'القيمة خارج النطاق المسموح في ArcGIS.']);
        }

        return [$localValue, $localValue !== null && $type === 'esriFieldTypeDate' ? CarbonImmutable::parse($localValue, 'UTC')->getTimestampMs() : $localValue];
    }

    private function layerUrl(string $tableType): string
    {
        [, , $layer, $key] = self::RECORDS[$tableType];
        $url = (string) config('services.arcgis.'.$key);

        if ($url === '' && $layer !== 0) {
            $url = (string) config('services.arcgis.cso_survey_layer_url');
        }

        $url = preg_replace('#/query/?$#i', '', rtrim($url, '/')) ?? '';

        if ($url === '') {
            throw new HttpException(503, 'رابط طبقة CSO في ArcGIS غير مهيأ.');
        }

        if ($layer !== 0 && filled(config('services.arcgis.'.$key)) && preg_match('#/FeatureServer/\d+$#i', $url)) {
            return $url;
        }

        return preg_replace('#/FeatureServer(?:/\d+)?$#i', '/FeatureServer/'.$layer, $url) ?? $url;
    }

    public function applyValue(Model $record, string $fieldName, mixed $value): void
    {
        $payload = $record->raw_payload ?? [];

        foreach (array_keys($payload) as $key) {
            if (strcasecmp((string) $key, $fieldName) === 0) {
                $payload[$key] = $value;
            }
        }

        $payload[$fieldName] = $value;
        $record->raw_payload = $payload;

        if (array_key_exists($fieldName, $record->getAttributes())) {
            $record->setAttribute($fieldName, $value);
        }

        if ($record instanceof CsoSurvey && in_array($fieldName, ['organization_name_en', 'organization_name_ar', 'organization_acronym'], true)) {
            $record->organization_name = $payload['organization_name_en'] ?? $payload['organization_name_ar'] ?? $payload['organization_acronym'] ?? null;
        }

        $record->save();
    }

    private function comparisonValue(mixed $value): string
    {
        return is_array($value) ? implode(',', $value) : (string) ($value ?? '');
    }

    public function importLegacyEdits(): void
    {
        InfEditAssessment::query()->whereIn('table_type', array_keys(self::RECORDS))->orderBy('id')->chunkById(200, function ($edits): void {
            foreach ($edits as $edit) {
                $edit->getConnection()->transaction(function () use ($edit): void {
                    if (CsoAuditLog::query()->where('legacy_edit_id', $edit->id)->exists()) {
                        return;
                    }

                    [$model, $type] = self::RECORDS[$edit->table_type];
                    $record = $model::withoutGlobalScopes()->where(function ($query) use ($edit): void {
                        if (filled($edit->global_id)) {
                            $query->where('globalid', $edit->global_id);
                        } elseif (filled($edit->objectid)) {
                            $query->where('objectid', $edit->objectid);
                        } else {
                            $query->whereKey($edit->auditable_id);
                        }
                    })->lockForUpdate()->first();
                    $survey = $record instanceof CsoSurvey ? $record : ($record ? CsoSurvey::withoutGlobalScopes()->where('globalid', $record->parentglobalid)->first() : null);

                    CsoAuditLog::query()->create([
                        'cso_survey_id' => $survey?->id, 'record_type' => $type, 'record_id' => $record?->id ?? $edit->auditable_id,
                        'record_globalid' => $edit->global_id, 'record_objectid' => $edit->objectid,
                        'field_name' => $edit->field_name, 'old_value' => $record ? $this->value($record, $edit->field_name) : $edit->old_value, 'new_value' => $edit->field_value,
                        'user_id' => $edit->user_id, 'notes' => $edit->notes, 'legacy_edit_id' => $edit->id, 'created_at' => $edit->created_at ?? now(),
                    ]);

                    if ($record !== null && $edit->field_value !== null && isset($this->editableFields($edit->table_type)[$edit->field_name])) {
                        $this->applyValue($record, $edit->field_name, $edit->field_value);
                    }
                });
            }
        });
    }
}
