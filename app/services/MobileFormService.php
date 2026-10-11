<?php

namespace App\services;

use App\Models\MobileFormAttachment;
use App\Models\MobileFormSubmission;
use App\Models\User;
use App\Modules\DamageAssessmentBorrowers\Http\Requests\StoreBorrowerSurveyRequest;
use App\Modules\DamageAssessmentBorrowers\Models\BorrowerPricingSetting;
use App\Modules\DamageAssessmentBorrowers\Models\DamageAssessmentBorrower;
use App\Modules\DamageAssessmentBorrowers\Services\BorrowerDamageValuationService;
use App\Modules\DamageAssessmentBorrowers\Services\BorrowerRiskAnalysisService;
use App\Modules\Heks\Models\HeksFollowUp;
use App\Modules\Heks\Services\HeksEngineerUserResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MobileFormService
{
    private ?array $cachedDefinitions = null;

    public function __construct(
        private MobilePortalService $portal,
        private MobileFormDefinitionService $definitions,
        private BorrowerRiskAnalysisService $risk,
        private BorrowerDamageValuationService $valuation,
        private HeksEngineerUserResolver $engineers,
        private MobileDamageFormService $damage,
    ) {}

    public function canWriteAny(User $user): bool
    {
        return $this->catalog($user) !== [];
    }

    /** @return array<int, array<string, mixed>> */
    public function catalog(User $user): array
    {
        $rows = [];
        foreach ($this->allDefinitions() as $key => $definition) {
            if ($this->allowed($user, $key, $definition['module'])) {
                $rows[] = ['key' => $key, 'module' => $definition['module'], 'title' => $definition['title'],
                    'version' => $definition['version'], 'requires_record' => $definition['requires_record'],
                    'sector' => $definition['sector'] ?? null, 'target_kind' => $definition['target_kind'] ?? 'record',
                    'can_submit' => $user->tokenCan('mobile:submit')];
            }
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    public function definition(User $user, string $form): array
    {
        $definition = $this->allDefinitions()[$form] ?? null;
        abort_unless($definition !== null, 404);
        abort_unless($this->allowed($user, $form, $definition['module']), 403);

        return $definition;
    }

    /** @return array<string, mixed> */
    public function schema(User $user, string $form, ?int $record): array
    {
        $definition = $this->definition($user, $form);
        abort_if($definition['requires_record'] && $record === null, 422, 'اختر السجل المطلوب أولًا.');
        $row = $record !== null ? $this->target($user, $form, $definition, $record) : null;
        $keys = array_keys(array_filter($definition['rules'], fn (string $key): bool => ! str_contains($key, '.'), ARRAY_FILTER_USE_KEY));
        $answers = $row ? ($definition['module'] === 'damage_assessment' ? $this->damage->answers($form, $row, $definition) : array_intersect_key($row->attributesToArray(), array_flip($keys))) : [];
        unset($definition['rules']);

        return [...$definition, 'key' => $form, 'record_id' => $record,
            'record_version' => $row ? $this->version($form, $row) : null, 'answers' => (object) $answers];
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function records(User $user, string $form, array $filters): array
    {
        $definition = $this->definition($user, $form);
        if ($definition['module'] === 'damage_assessment') {
            return $this->damage->records($user, $form, $filters);
        }
        if ($form !== 'heks-followup') {
            return $this->portal->records($user, $definition['module'], $filters);
        }
        $query = HeksFollowUp::query()->with('beneficiary:id,name,code');
        if (filled($filters['search'] ?? null)) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($filters['search'])).'%';
            $query->whereHas('beneficiary', fn (Builder $query): Builder => $query->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("code LIKE ? ESCAPE '!'", [$pattern]));
        }
        $page = $query->orderByDesc('id')->paginate(20, ['*'], 'page', $filters['page'] ?? 1);

        return ['data' => $page->getCollection()->map(fn (HeksFollowUp $row): array => [
            'id' => $row->id, 'module' => 'heks', 'title' => $row->beneficiary?->name ?? 'زيارة متابعة',
            'subtitle' => 'زيارة '.($row->visit_number ?? $row->id), 'status' => $row->working_condition,
        ])->all(), 'total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()];
    }

    /** @param array<string, mixed> $input
     * @return array{data: array<string, mixed>, replayed: bool}
     */
    public function submit(User $user, string $form, array $input, Request $request): array
    {
        $definition = $this->definition($user, $form);
        abort_unless($user->tokenCan('mobile:submit'), 403);
        $input = ['record_id' => null, 'record_version' => null, 'attachments' => [], 'location' => null, ...$input];
        $hash = hash('sha256', json_encode($this->canonical($input), JSON_THROW_ON_ERROR));

        return $user->getConnection()->transaction(function () use ($user, $form, $input, $request, $definition, $hash): array {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = MobileFormSubmission::query()->where('user_id', $user->id)->where('submission_uuid', $input['submission_uuid'])->first();
            if ($existing) {
                abort_unless($existing->form_key === $form && hash_equals($existing->request_hash, $hash), 409, 'استُخدم معرّف الإرسال لبيانات مختلفة.');

                return ['data' => $this->receipt($existing), 'replayed' => true];
            }
            abort_unless(hash_equals($definition['version'], $input['form_version']), 409, 'تغيّر إصدار النموذج. حدّث النموذج قبل الإرسال.');
            $id = $input['record_id'] ?? null;
            abort_if($definition['requires_record'] && $id === null, 422, 'هذا النموذج يحتاج سجلًا موجودًا.');
            $row = $id !== null ? $this->target($user, $form, $definition, $id, true) : null;
            if ($row) {
                abort_unless(filled($input['record_version'] ?? null) && hash_equals($this->version($form, $row), $input['record_version']), 409, 'تغيّر السجل على السيرفر. راجع آخر نسخة قبل الإرسال.');
            }
            $currentAnswers = $row ? ($definition['module'] === 'damage_assessment' ? $this->damage->answers($form, $row, $definition) : $row->attributesToArray()) : [];
            $answers = $this->validateAnswers($definition, $input['answers'], $currentAnswers);
            $attachments = MobileFormAttachment::query()->where('user_id', $user->id)->where('submission_uuid', $input['submission_uuid'])
                ->where('form_key', $form)->whereNull('mobile_form_submission_id')->whereIn('id', $input['attachments'] ?? [])->lockForUpdate()->get();
            if ($attachments->count() !== count($input['attachments'] ?? [])) {
                throw ValidationException::withMessages(['attachments' => 'المرفق غير متاح لهذا الحساب أو لهذا الإرسال.']);
            }
            $before = array_intersect_key($currentAnswers, $answers);
            if ($definition['module'] === 'damage_assessment') {
                $this->damage->save($form, $row, $answers, $request);
            } elseif ($form === 'borrower-survey') {
                $identity = $answers['borrower_id_number'] ?? null;
                if (filled($identity) && DamageAssessmentBorrower::query()->where('borrower_id_number', $identity)->when($row !== null, fn (Builder $query): Builder => $query->whereKeyNot($row->getKey()))->exists()) {
                    throw ValidationException::withMessages(['answers.borrower_id_number' => 'يوجد سجل بهذه الهوية. افتح السجل الموجود لتحديثه.']);
                }
                $row ??= new DamageAssessmentBorrower;
                $merged = [...$row->attributesToArray(), ...$answers];
                $attributes = [...$answers, ...$this->risk->analyze($merged)];
                if (! $row->exists) {
                    $attributes['submitted_by'] = $user->id;
                    $attributes['submitted_by_name'] = $user->name;
                }
                $value = $this->valuation->fullDemolitionValueUsd(isset($merged['loan_unit_area']) ? (float) $merged['loan_unit_area'] : null, $merged['loan_unit_floor_type'] ?? null, $merged['loan_unit_damage_status'] ?? null);
                if ($value !== null) {
                    $rate = (float) (BorrowerPricingSetting::query()->value('exchange_rate') ?? DamageAssessmentBorrower::query()->latest('updated_at')->value('exchange_rate') ?? 3.2);
                    $attributes = [...$attributes, 'boq_total_usd' => $value, 'exchange_rate' => $rate, 'boq_total_ils' => round($value * $rate, 2)];
                }
                if (isset($input['location'])) {
                    $attributes = [...$attributes, 'location_latitude' => $input['location']['latitude'], 'location_longitude' => $input['location']['longitude'], 'location_precision' => $input['location']['accuracy'] ?? null];
                }
                $row->fill($attributes)->save();
            } else {
                if ($form === 'heks-beneficiary' && array_key_exists('field_engineer', $answers)) {
                    $answers['field_engineer_user_id'] = $this->engineers->resolve($answers['field_engineer']);
                }
                if ($form === 'heks-followup' && array_key_exists('engineer_name', $answers)) {
                    $answers['engineer_user_id'] = $this->engineers->resolve($answers['engineer_name']);
                }
                $row->fill($answers)->save();
            }
            $row->refresh();
            $receipt = MobileFormSubmission::query()->create([
                'user_id' => $user->id, 'submission_uuid' => $input['submission_uuid'], 'module' => $definition['module'], 'form_key' => $form,
                'form_version' => $definition['version'], 'record_id' => $definition['module'] === 'damage_assessment' ? $input['record_id'] : $row->getKey(), 'record_version' => $this->version($form, $row),
                'request_hash' => $hash, 'changes' => ['before' => $before, 'after' => array_intersect_key($definition['module'] === 'damage_assessment' ? $this->damage->answers($form, $row, $definition) : $row->attributesToArray(), $answers)],
                'location' => $input['location'] ?? null,
            ]);
            foreach ($attachments as $attachment) {
                $attachment->update(['mobile_form_submission_id' => $receipt->id]);
            }

            return ['data' => $this->receipt($receipt), 'replayed' => false];
        }, 3);
    }

    public function upload(User $user, string $form, string $uuid, UploadedFile $file): MobileFormAttachment
    {
        $this->definition($user, $form);
        abort_unless($user->tokenCan('mobile:submit'), 403);

        return $user->getConnection()->transaction(function () use ($user, $form, $uuid, $file): MobileFormAttachment {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_if(MobileFormSubmission::query()->where('user_id', $user->id)->where('submission_uuid', $uuid)->exists(), 409, 'تم استلام هذا الإرسال بالفعل.');
            $pending = MobileFormAttachment::query()->where('user_id', $user->id)->where('submission_uuid', $uuid);
            $hash = hash_file('sha256', $file->getRealPath());
            $existing = (clone $pending)->where('content_hash', $hash)->where('form_key', $form)->first();
            if ($existing) {
                return $existing;
            }
            abort_if($pending->count() >= 5 || (clone $pending)->sum('size') + $file->getSize() > 30 * 1024 * 1024, 422, 'الحد خمسة مرفقات ومجموع 30 ميغابايت لكل استبيان.');
            abort_if(MobileFormAttachment::query()->where('user_id', $user->id)->whereNull('mobile_form_submission_id')->count() >= 100, 422, 'أكمل إرسال مسودات المرفقات قبل رفع المزيد.');
            $path = $file->store('mobile-form-attachments/'.$user->id, 'local');
            abort_unless($path !== false, 503, 'تعذّر حفظ المرفق.');
            try {
                $name = preg_replace('/[\x00-\x1F\x7F]/u', '_', basename(str_replace('\\', '/', $file->getClientOriginalName()))) ?: 'attachment.'.$file->extension();

                return MobileFormAttachment::query()->create([
                    'user_id' => $user->id, 'submission_uuid' => $uuid, 'form_key' => $form, 'path' => $path,
                    'name' => mb_substr($name, 0, 180),
                    'mime' => $file->getMimeType(), 'size' => $file->getSize(), 'content_hash' => $hash,
                ]);
            } catch (\Throwable $error) {
                Storage::disk('local')->delete($path);
                throw $error;
            }
        }, 3);
    }

    /** @return array<string, mixed> */
    public function receipt(MobileFormSubmission $receipt): array
    {
        return ['id' => $receipt->id, 'submission_uuid' => $receipt->submission_uuid, 'form_key' => $receipt->form_key,
            'module' => $receipt->module, 'record_id' => $receipt->record_id, 'record_version' => $receipt->record_version,
            'status' => 'received', 'received_at' => $receipt->created_at->toIso8601String(),
            'attachments' => $receipt->attachments()->get(['id', 'name', 'mime', 'size'])->toArray()];
    }

    public function authorizeReceipt(User $user, MobileFormSubmission $receipt): void
    {
        abort_unless((int) $receipt->user_id === $user->id, 404);
        $definition = $this->definition($user, $receipt->form_key);
        $this->target($user, $receipt->form_key, $definition, $receipt->record_id);
    }

    public function discard(User $user, string $form, string $uuid, int $attachment): void
    {
        $this->definition($user, $form);
        abort_unless($user->tokenCan('mobile:submit'), 403);
        $user->getConnection()->transaction(function () use ($user, $form, $uuid, $attachment): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $row = MobileFormAttachment::query()->where('user_id', $user->id)->where('form_key', $form)->where('submission_uuid', $uuid)->find($attachment);
            if ($row === null) {
                return;
            }
            abort_if($row->mobile_form_submission_id !== null, 409, 'المرفق جزء من إرسال مستلم ولا يُحذف من هنا.');
            if (! Storage::disk('local')->delete($row->path)) {
                abort(503, 'تعذّر حذف المرفق المؤقت.');
            }
            $row->delete();
        });
    }

    /** @param array<string, mixed> $definition */
    private function target(User $user, string $form, array $definition, int $id, bool $lock = false): Model
    {
        if ($definition['module'] === 'damage_assessment') {
            return $this->damage->target($user, $form, $id, $lock);
        }
        if ($form === 'heks-followup') {
            $row = HeksFollowUp::query()->when($lock, fn (Builder $query): Builder => $query->lockForUpdate())->findOrFail($id);
            $this->portal->record($user, 'heks', $row->heks_beneficiary_id);

            return $row;
        }

        return $this->portal->record($user, $definition['module'], $id, $lock);
    }

    private function allowed(User $user, string $form, string $module): bool
    {
        try {
            $this->portal->authorize($user, $module);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException) {
            return false;
        }
        if ($module === 'damage_assessment') {
            return $this->damage->allowed($user, $form);
        }
        if ($form === 'borrower-survey') {
            $request = new StoreBorrowerSurveyRequest;
            $request->setUserResolver(fn (): User => $user);

            return $request->authorize();
        }

        return $user->hasRole('Database Officer');
    }

    /** @return array<string, array<string, mixed>> */
    private function allDefinitions(): array
    {
        return $this->cachedDefinitions ??= [...$this->definitions->definitions(), ...$this->damage->definitions()];
    }

    private function version(string $form, Model $row): string
    {
        return ($this->allDefinitions()[$form]['module'] ?? null) === 'damage_assessment' ? $this->damage->version($form, $row) : $this->portal->version($row);
    }

    /** @param array<string, mixed> $definition
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    private function validateAnswers(array $definition, array $answers, array $current = []): array
    {
        $keys = array_keys(array_filter($definition['rules'], fn (string $key): bool => ! str_contains($key, '.'), ARRAY_FILTER_USE_KEY));
        if (array_diff(array_keys($answers), $keys) !== []) {
            throw ValidationException::withMessages(['answers' => 'يوجد حقل غير مسموح في النموذج.']);
        }
        $rules = $definition['rules'];
        foreach ($definition['sections'] as $section) {
            foreach ($section['fields'] as $field) {
                $this->fieldRules($rules, $field);
                $condition = $field['visible_when'];
                if ($condition !== null && (array_key_exists($field['key'], $answers) || array_key_exists($condition['field'], $answers)) && ($answers[$condition['field']] ?? $current[$condition['field']] ?? null) !== $condition['equals']) {
                    $answers[$field['key']] = null;
                }
            }
        }
        $validator = Validator::make($answers, $rules, [
            'required' => 'هذا الحقل مطلوب.', 'in' => 'اختر قيمة من الخيارات المسموحة.', 'numeric' => 'أدخل رقمًا صحيحًا.',
            'integer' => 'أدخل عددًا صحيحًا.', 'date' => 'أدخل تاريخًا صالحًا مثل 2026-10-11.',
            'max' => 'القيمة تتجاوز الحد المسموح.', 'min' => 'القيمة أقل من الحد المسموح.',
            'array' => 'بيانات المجموعة غير صالحة.', 'boolean' => 'اختر نعم أو لا.',
        ]);
        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())->mapWithKeys(fn (array $messages, string $key): array => ['answers.'.$key => $messages])->all());
        }

        return $validator->validated();
    }

    /** @param array<string, array<mixed>> $rules
     * @param  array<string, mixed>  $field
     */
    private function fieldRules(array &$rules, array $field, string $prefix = ''): void
    {
        $key = $prefix.$field['key'];
        if ($field['choices'] !== []) {
            $choiceKey = $field['type'] === 'multiselect' ? $key.'.*' : $key;
            $rules[$choiceKey][] = Rule::in(array_column($field['choices'], 'value'));
        }
        if ($field['type'] === 'repeat') {
            $rules[$key][] = 'max:'.$field['max_items'];
            $rules[$key.'.*'] = ['array:'.implode(',', array_column($field['children'], 'key'))];
            foreach ($field['children'] as $child) {
                $this->fieldRules($rules, $child, $key.'.*.');
            }
        } elseif ($field['type'] === 'multiselect') {
            $rules[$key][] = 'max:20';
            $rules[$key.'.*'][] = 'distinct';
            $rules[$key][] = function (string $attribute, mixed $value, \Closure $fail) use ($field): void {
                if (is_array($value) && count($value) > 1 && array_intersect($value, $field['exclusive_choices'] ?? []) !== []) {
                    $fail('خيار لا يوجد يُختار وحده ولا يُجمع مع بقية الخيارات.');
                }
            };
        } elseif ($field['max_length'] !== null) {
            $rules[$key][] = 'max:'.$field['max_length'];
        }
    }

    private function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $child): mixed => $this->canonical($child), $value);
    }
}
