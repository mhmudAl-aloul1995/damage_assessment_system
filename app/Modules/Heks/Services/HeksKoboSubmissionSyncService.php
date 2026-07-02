<?php

namespace App\Modules\Heks\Services;

use App\Models\KoboRestSubmission;
use App\Modules\Heks\Models\HeksAttachment;
use App\Modules\Heks\Models\HeksBeneficiary;
use App\Modules\Heks\Models\HeksBoqCatalogItem;
use App\Modules\Heks\Models\HeksBoqItem;
use App\Modules\Heks\Models\HeksFollowUp;
use App\Modules\Heks\Models\HeksLabel;
use App\Modules\Heks\Models\HeksScore;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class HeksKoboSubmissionSyncService
{
    /**
     * @return array{status: string, error: ?string, beneficiary: ?HeksBeneficiary, follow_up: ?HeksFollowUp, boq_items: int}|null
     */
    public function sync(KoboRestSubmission $submission): ?array
    {
        if (! Str::startsWith($submission->service_name, 'heks-')) {
            return null;
        }

        $payload = $submission->payload ?? [];
        $flatPayload = $this->flatten($payload);
        $service = $submission->service_name;
        $beneficiary = $this->beneficiary($flatPayload, $service);

        if (! $beneficiary instanceof HeksBeneficiary) {
            return [
                'status' => 'skipped',
                'error' => 'HEKS Kobo submission does not include a beneficiary code or a matching beneficiary name.',
                'beneficiary' => null,
                'follow_up' => null,
                'boq_items' => 0,
            ];
        }

        $this->syncBeneficiary($beneficiary, $flatPayload, $service);
        $this->syncScores($beneficiary, $flatPayload, $service);
        $this->syncLabels($beneficiary, $flatPayload, $service);
        $this->syncAllSurveyAnswers($beneficiary, $flatPayload, $service);
        $this->syncAttachments($beneficiary, $payload, $flatPayload, $service);

        $followUp = null;
        $boqItems = 0;

        if (in_array($service, ['heks-followups', 'heks-followup-boq'], true)) {
            $followUp = $this->syncFollowUp($beneficiary, $flatPayload, $service);
        }

        if (in_array($service, ['heks-boq', 'heks-followup-boq'], true)) {
            $boqItems = $this->syncBoqItems($beneficiary, $payload, $flatPayload, $service, $followUp);
        }

        return [
            'status' => 'synced',
            'error' => null,
            'beneficiary' => $beneficiary,
            'follow_up' => $followUp,
            'boq_items' => $boqItems,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function beneficiary(array $payload, string $service): ?HeksBeneficiary
    {
        $code = $this->first($payload, [
            'code',
            'Code',
            'beneficiary_code',
            'case_code',
            'request_code',
            'رقم الطلب/الكود',
            'رقم الطلب',
            'الكود',
            'كود',
        ]);

        if ($code === '') {
            $code = $this->findByKeyPart($payload, ['beneficiary_code', 'case_code', 'request_code', 'code', 'الكود', 'كود']);
        }

        if ($code !== '') {
            return HeksBeneficiary::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => $this->beneficiaryName($payload) ?: null,
                    'raw_data' => [$service => $payload],
                ]
            );
        }

        $name = $this->beneficiaryName($payload);

        if ($name === '') {
            return null;
        }

        return HeksBeneficiary::query()
            ->where('name', $name)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncBeneficiary(HeksBeneficiary $beneficiary, array $payload, string $service): void
    {
        $data = array_filter([
            'name' => $this->beneficiaryName($payload),
            'identity_number' => $this->first($payload, ['identity_number', 'id_number', 'beneficiary_id_number', 'رقم هوية رب الأسرة', 'رقم الهوية', 'الهوية']),
            'phone' => $this->first($payload, ['phone', 'phone_number', 'mobile', 'رقم التواصل', 'رقم الجوال']),
            'alternate_phone' => $this->first($payload, ['alternate_phone', 'رقم تواصل بديل', 'رقم التواصل2']),
            'field_engineer' => $this->first($payload, ['engineer_name', 'field_engineer', 'Engineer Name', 'اسم المهندس', 'المهندس المتابع']),
            'visit_date' => $this->date($this->first($payload, ['visit_date', 'Visit Date', 'تاريخ الزيارة', '_submission_time'])),
            'governorate' => $this->first($payload, ['governorate', 'المحافظة']),
            'area' => $this->first($payload, ['area', 'community', 'المنطقة', 'التجمع']),
            'address' => $this->first($payload, ['address', 'العنوان', 'العنوان بالتفصيل']),
            'household_head_gender' => $this->first($payload, ['household_head_gender', 'gender', 'جنس رب الأسرة']),
            'marital_status' => $this->first($payload, ['marital_status', 'الحالة الاجتماعية']),
            'displacement_status' => $this->first($payload, ['displacement_status', 'حالة النزوح']),
            'occupancy_status' => $this->first($payload, ['occupancy_status', 'حالة الإشغال الحالي للوحدة السكنية', 'نوع الإشغال الحالي:', 'حالة الإشغال']),
            'damage_status' => $this->first($payload, ['damage_status', 'Damage assessment', 'تقييم حالة ضرر المأوى']),
            'grant_amount' => $this->decimal($this->first($payload, ['grant_amount', 'grant', 'GRANT', 'Intervention (ILS)', "Intervention \n(ILS)", 'المنحة', 'قيمة العقد ILS'])),
            'payment_1' => $this->decimal($this->first($payload, ['payment_1', 'Payment_1', '30%'])),
            'payment_2' => $this->decimal($this->first($payload, ['payment_2', 'Payment_2', '50%'])),
            'payment_3' => $this->decimal($this->first($payload, ['payment_3', 'Payment_3', '20%'])),
            'recommendations' => $this->first($payload, ['recommendations', 'final_recommendation', 'توصيات']),
            'social_notes' => $this->first($payload, ['social_notes', 'ملاحظات إجتماعية']),
            'engineer_notes' => $this->first($payload, ['engineer_notes', 'ملاحظات المهندسين']),
            'raw_data' => array_merge($beneficiary->raw_data ?? [], [$service => $payload]),
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        $beneficiary->fill($data)->save();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncFollowUp(HeksBeneficiary $beneficiary, array $payload, string $service): HeksFollowUp
    {
        $visitNumber = $this->first($payload, ['visit_number', 'visit #', 'visit_no', 'رقم الزيارة', 'الزيارة']);

        return HeksFollowUp::query()->updateOrCreate(
            [
                'heks_beneficiary_id' => $beneficiary->id,
                'code' => $beneficiary->code,
                'visit_number' => $visitNumber !== '' ? $visitNumber : null,
            ],
            [
                'visit_date' => $this->date($this->first($payload, ['visit_date', 'Visit Date', 'تاريخ الزيارة', '_submission_time'])),
                'engineer_name' => $this->first($payload, ['engineer_name', 'Engineer Name', 'اسم المهندس', 'المهندس المتابع']),
                'working_condition' => $this->first($payload, ['working_condition', 'Working condition', 'حالة العمل']),
                'other_condition' => $this->first($payload, ['other_condition', 'Other condition:', 'حالة أخرى']),
                'completed_amount_ils' => $this->decimal($this->first($payload, ['completed_amount_ils', 'completed_amount', 'إجمالي ما تم انجازة حتى الآن ILS'])),
                'completion_percentage' => $this->decimal($this->first($payload, ['completion_percentage', 'completion_percent', 'نسبة الإنجاز بالأعمال %'])),
                'engineer_recommendations' => $this->first($payload, ['engineer_recommendations', 'recommendations', 'توصيات المهندس للزيارة']),
                'boq_filename' => $this->first($payload, ['boq_filename', 'Insert BOQ', 'BOQ']),
                'boq_url' => $this->first($payload, ['boq_url', 'Insert BOQ_URL', 'BOQ_URL']),
                'raw_data' => $payload,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncScores(HeksBeneficiary $beneficiary, array $payload, string $service): void
    {
        $social = $this->decimal($this->first($payload, ['social_score', 'Social Score', 'تقييم الحالة الاجتماعية  (30)', "تقييم الحالة \nالاجتماعية  (30)", 'تقييم الحالة الاجتماعية من 35', 'التقييم الاجتماعي']));
        $technical = $this->decimal($this->first($payload, ['technical_score', 'Technical Score', 'تقييم الحالة الفنية (70)', "تقييم الحالة \nالفنية (70)", 'التقييم الفني']));
        $total = $this->decimal($this->first($payload, ['total_score', 'final_score', 'Total Score', 'التقييم الكلي']));

        if ($social === null && $technical === null && $total === null) {
            return;
        }

        HeksScore::query()->updateOrCreate(
            ['heks_beneficiary_id' => $beneficiary->id, 'source' => $service],
            [
                'grant_amount' => $this->decimal($this->first($payload, ['grant_amount', 'grant', 'GRANT', 'Intervention (ILS)', "Intervention \n(ILS)"])),
                'payment_1' => $this->decimal($this->first($payload, ['payment_1', 'Payment_1', '30%'])),
                'payment_2' => $this->decimal($this->first($payload, ['payment_2', 'Payment_2', '50%'])),
                'payment_3' => $this->decimal($this->first($payload, ['payment_3', 'Payment_3', '20%'])),
                'social_score' => $social,
                'technical_score' => $technical,
                'total_score' => $total ?? (($social ?? 0) + ($technical ?? 0)),
                'classification' => $this->first($payload, ['classification', 'Classification', 'priority', 'التصنيف', 'الأولوية']),
                'raw_data' => $payload,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncLabels(HeksBeneficiary $beneficiary, array $payload, string $service): void
    {
        $labels = [
            'screening' => ['screening', 'Screening'],
            'damage_status' => ['damage_status', 'Damage assessment', 'تقييم حالة ضرر المأوى'],
            'roof_status' => ['roof_status', 'Roof condition', 'حالة السقف'],
            'kitchen_status' => ['kitchen_status', 'General kitchen condition', 'حالة المطبخ'],
            'occupancy_status' => ['occupancy_status', 'حالة الإشغال الحالي للوحدة السكنية', 'نوع الإشغال الحالي:', 'حالة الإشغال'],
            'final_recommendation' => ['final_recommendation', 'recommendations', 'توصيات نهائية'],
        ];

        foreach ($labels as $key => $candidates) {
            $value = $this->first($payload, $candidates);

            if ($value === '') {
                continue;
            }

            HeksLabel::query()->updateOrCreate(
                ['heks_beneficiary_id' => $beneficiary->id, 'source' => $service, 'label_key' => $key],
                [
                    'label_value' => $value,
                    'version' => $this->first($payload, ['__version__', '_submission___version__']),
                    'raw_data' => $payload,
                ]
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncAllSurveyAnswers(HeksBeneficiary $beneficiary, array $payload, string $service): void
    {
        foreach ($payload as $key => $value) {
            if ($this->shouldSkipSurveyAnswer($key, $value)) {
                continue;
            }

            $displayValue = $this->displayValue($value);

            if ($displayValue === '') {
                continue;
            }

            HeksLabel::query()->updateOrCreate(
                [
                    'heks_beneficiary_id' => $beneficiary->id,
                    'source' => $service,
                    'label_key' => $this->surveyAnswerLabelKey($key),
                ],
                [
                    'label_value' => $displayValue,
                    'version' => $this->first($payload, ['__version__', '_submission___version__']),
                    'raw_data' => [
                        'field_key' => $key,
                        'field_label' => $this->cleanFieldLabel($key),
                        'value' => $value,
                        'source' => $service,
                    ],
                ]
            );
        }
    }

    private function shouldSkipSurveyAnswer(string $key, mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $normalized = $this->normalizeKey($key);

        return $normalized === ''
            || str_contains($normalized, 'uuid')
            || str_contains($normalized, 'parenttablename')
            || str_contains($normalized, 'parentindex')
            || str_contains($normalized, 'validationstatus')
            || str_contains($normalized, 'submittedby')
            || str_contains($normalized, 'tags')
            || str_contains($normalized, 'notes')
            || str_contains($normalized, 'index');
    }

    private function surveyAnswerLabelKey(string $key): string
    {
        $cleanLabel = Str::of($this->cleanFieldLabel($key))
            ->limit(70, '')
            ->toString();

        return 'survey:'.substr(sha1($key), 0, 12).':'.$cleanLabel;
    }

    private function cleanFieldLabel(string $key): string
    {
        return Str::of($key)
            ->replace(["\r", "\n", "\t"], ' ')
            ->replace(['/', '_'], ' ')
            ->squish()
            ->toString();
    }

    private function displayValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'نعم' : 'لا';
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $flatPayload
     */
    private function syncAttachments(HeksBeneficiary $beneficiary, array $payload, array $flatPayload, string $service): void
    {
        $attachments = Arr::get($payload, '_attachments', []);

        if (is_array($attachments)) {
            foreach (array_values($attachments) as $index => $attachment) {
                if (! is_array($attachment)) {
                    continue;
                }

                $url = (string) ($attachment['download_url'] ?? $attachment['download_large_url'] ?? $attachment['url'] ?? '');
                $filename = (string) ($attachment['filename'] ?? basename(parse_url($url, PHP_URL_PATH) ?: ''));

                if ($url === '' && $filename === '') {
                    continue;
                }

                HeksAttachment::query()->updateOrCreate(
                    [
                        'heks_beneficiary_id' => $beneficiary->id,
                        'source' => $service,
                        'filename' => $filename,
                    ],
                    [
                        'url' => $url,
                        'source_index' => $index,
                        'attachment_type' => $this->attachmentType($filename),
                        'raw_data' => $attachment,
                    ]
                );
            }
        }

        foreach ($flatPayload as $key => $value) {
            if (! is_string($value) || $value === '') {
                continue;
            }

            if (! $this->looksLikeAttachment($key, $value)) {
                continue;
            }

            HeksAttachment::query()->updateOrCreate(
                [
                    'heks_beneficiary_id' => $beneficiary->id,
                    'source' => $service,
                    'filename' => basename(parse_url($value, PHP_URL_PATH) ?: $value),
                ],
                [
                    'url' => Str::startsWith($value, ['http://', 'https://']) ? $value : null,
                    'parent_table' => $key,
                    'attachment_type' => $this->attachmentType($value),
                    'raw_data' => ['field' => $key, 'value' => $value],
                ]
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $flatPayload
     */
    private function syncBoqItems(HeksBeneficiary $beneficiary, array $payload, array $flatPayload, string $service, ?HeksFollowUp $followUp): int
    {
        $rows = $this->boqRows($payload, $flatPayload);
        $saved = 0;

        foreach ($rows as $index => $row) {
            $flatRow = $this->flatten($row);
            $description = $this->first($flatRow, ['description', 'item_description', 'وصف البند', 'البند']);

            if ($description === '') {
                continue;
            }

            $quantity = $this->decimal($this->first($flatRow, ['quantity', 'qty', 'الكمية'])) ?? 0;
            $unitPrice = $this->decimal($this->first($flatRow, ['unit_price_ils', 'unit_price', 'سعر الوحدة', 'تكلفة الوحدة ILS'])) ?? 0;
            $totalPrice = $this->decimal($this->first($flatRow, ['total_price_ils', 'total_price', 'الإجمالي'])) ?? ($quantity * $unitPrice);
            $itemCode = $this->first($flatRow, ['item_code', 'item_no', 'رقم البند']);

            HeksBoqItem::query()->updateOrCreate(
                [
                    'heks_beneficiary_id' => $beneficiary->id,
                    'heks_follow_up_id' => $followUp?->id,
                    'source' => $service,
                    'item_code' => $itemCode !== '' ? $itemCode : null,
                    'description' => $description,
                ],
                [
                    'section' => $this->first($flatRow, ['section', 'القسم']),
                    'unit' => $this->first($flatRow, ['unit', 'الوحدة']),
                    'quantity' => $quantity,
                    'unit_price_ils' => $unitPrice,
                    'total_price_ils' => $totalPrice,
                    'notes' => $this->first($flatRow, ['notes', 'ملاحظات']),
                    'raw_data' => array_merge($flatRow, ['_kobo_row_index' => $index]),
                ]
            );

            $saved++;
        }

        return $saved;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $flatPayload
     * @return array<int, array<string, mixed>>
     */
    private function boqRows(array $payload, array $flatPayload): array
    {
        foreach (['boq_items', 'items', 'BOQ', 'جدول_الكميات'] as $key) {
            $rows = Arr::get($payload, $key);

            if (is_array($rows) && array_is_list($rows)) {
                return array_values(array_filter($rows, 'is_array'));
            }
        }

        $description = $this->first($flatPayload, ['description', 'item_description', 'وصف البند', 'البند']);

        if ($description !== '') {
            return [$flatPayload];
        }

        $catalogRows = $this->catalogQuantityRows($flatPayload);

        if ($catalogRows !== []) {
            return $catalogRows;
        }

        return $this->questionQuantityRows($flatPayload);
    }

    /**
     * @param  array<string, mixed>  $flatPayload
     * @return array<int, array<string, mixed>>
     */
    private function catalogQuantityRows(array $flatPayload): array
    {
        $catalogItems = HeksBoqCatalogItem::query()
            ->where('is_active', true)
            ->whereNotNull('item_code')
            ->orderBy('sort_order')
            ->get();
        $rows = [];

        foreach ($catalogItems as $catalogItem) {
            $itemCode = trim((string) $catalogItem->item_code);

            if ($itemCode === '') {
                continue;
            }

            $quantity = $this->quantityForCatalogItem($flatPayload, $itemCode);

            if ($quantity === null || $quantity <= 0) {
                continue;
            }

            $unitPrice = (float) $catalogItem->unit_price_ils;

            $rows[] = [
                'section' => $catalogItem->section,
                'item_code' => $itemCode,
                'description' => $catalogItem->description,
                'unit' => $catalogItem->unit,
                'quantity' => $quantity,
                'unit_price_ils' => $unitPrice,
                'total_price_ils' => $quantity * $unitPrice,
                'notes' => 'Imported from KoBo quantity field',
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $flatPayload
     */
    private function quantityForCatalogItem(array $flatPayload, string $itemCode): ?float
    {
        $normalizedCode = $this->normalizeKey($itemCode);

        foreach ($flatPayload as $key => $value) {
            $quantity = $this->decimal((string) $value);

            if ($quantity === null || $quantity <= 0) {
                continue;
            }

            $normalizedKey = $this->normalizeKey($key);

            if (! str_contains($normalizedKey, $normalizedCode)) {
                continue;
            }

            if (! $this->looksLikeQuantityKey($key) && ! str_contains($normalizedKey, 'boq')) {
                continue;
            }

            return $quantity;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $flatPayload
     * @return array<int, array<string, mixed>>
     */
    private function questionQuantityRows(array $flatPayload): array
    {
        $rows = [];

        foreach ($flatPayload as $key => $value) {
            $quantity = $this->decimal((string) $value);

            if ($quantity === null || $quantity <= 0 || ! $this->looksLikeQuantityKey($key)) {
                continue;
            }

            $rows[] = [
                'description' => Str::of($key)->replace(['/', '_'], ' ')->squish()->toString(),
                'quantity' => $quantity,
                'unit_price_ils' => 0,
                'total_price_ils' => 0,
                'notes' => 'Imported from unmapped KoBo BOQ quantity field',
            ];
        }

        return $rows;
    }

    private function looksLikeQuantityKey(string $key): bool
    {
        $normalized = Str::lower($key);

        return str_contains($normalized, 'quantity')
            || str_contains($normalized, 'qty')
            || str_contains($normalized, 'boq')
            || str_contains($normalized, 'item')
            || str_contains($normalized, 'كمية')
            || str_contains($normalized, 'البند');
    }

    private function normalizeKey(string $value): string
    {
        return Str::lower((string) preg_replace('/[^\pL\pN]+/u', '', $value));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function beneficiaryName(array $payload): string
    {
        $candidates = [
            'name',
            'Name',
            'beneficiary_name',
            'beneficiary_full_name',
            "\u{0627}\u{0633}\u{0645} \u{0627}\u{0644}\u{0645}\u{0633}\u{062A}\u{0641}\u{064A}\u{062F}",
            "\u{0627}\u{0644}\u{0645}\u{0633}\u{062A}\u{0641}\u{064A}\u{062F}",
            "\u{0627}\u{0633}\u{0645} \u{0631}\u{0628} \u{0627}\u{0644}\u{0623}\u{0633}\u{0631}\u{0629}",
            "\u{0627}\u{0633}\u{0645} \u{0627}\u{0644}\u{0634}\u{062E}\u{0635} \u{0627}\u{0644}\u{0645}\u{0642}\u{0627}\u{0628}\u{0644}",
        ];

        foreach ($candidates as $candidate) {
            $name = $this->first($payload, [$candidate]);

            if ($name !== '' && ! $this->isInvalidBeneficiaryName($name)) {
                return $name;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $candidates
     */
    private function first(array $payload, array $candidates): string
    {
        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $payload) && filled($payload[$candidate])) {
                $value = trim((string) $payload[$candidate]);

                if (! $this->isInvalidValue($value)) {
                    return $value;
                }
            }

            if ($this->isGenericCandidate($candidate)) {
                continue;
            }

            $match = $this->findByKeyPart($payload, [$candidate]);

            if ($match !== '') {
                return $match;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $needles
     */
    private function findByKeyPart(array $payload, array $needles): string
    {
        foreach ($payload as $key => $value) {
            if (! filled($value) || is_array($value) || $this->isComputedFieldKey($key)) {
                continue;
            }

            $value = trim((string) $value);

            if ($this->isInvalidValue($value)) {
                continue;
            }

            $normalizedKey = $this->normalizeKey($key);

            foreach ($needles as $needle) {
                $normalizedNeedle = $this->normalizeKey($needle);

                if ($normalizedNeedle !== '' && str_contains($normalizedKey, $normalizedNeedle)) {
                    return $value;
                }
            }
        }

        return '';
    }

    private function isGenericCandidate(string $candidate): bool
    {
        return in_array($this->normalizeKey($candidate), ['name', 'code', 'id', 'phone', 'mobile', 'area'], true);
    }

    private function isComputedFieldKey(string $key): bool
    {
        return str_contains($key, '${')
            || str_contains($key, '}')
            || str_contains($key, ':${');
    }

    private function isInvalidValue(string $value): bool
    {
        $normalized = $this->normalizeKey($value);

        return in_array($normalized, ['na', 'n/a', 'n/a#', 'na#', '#na', '#n/a', 'null', 'undefined'], true)
            || str_starts_with($normalized, 'na')
            || str_starts_with($normalized, '#na');
    }

    private function isInvalidBeneficiaryName(string $value): bool
    {
        $normalized = $this->normalizeKey($value);

        return $this->isInvalidValue($value)
            || in_array($normalized, [
                "\u{0646}\u{0641}\u{0633}\u{0647}",
                "\u{0646}\u{0641}\u{0633}\u{0647}\u{0627}",
                "\u{0646}\u{0641}\u{0633}\u{0627}\u{0644}\u{0634}\u{062E}\u{0635}",
                "\u{0630}\u{0627}\u{062A}\u{0647}",
            ], true);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function flatten(array $payload, string $prefix = ''): array
    {
        $flat = [];

        foreach ($payload as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}/{$key}";

            if (is_array($value) && ! array_is_list($value)) {
                $flat += $this->flatten($value, $path);
            } else {
                $flat[$path] = $value;
                $flat[(string) $key] = $value;
            }
        }

        return $flat;
    }

    private function decimal(string $value): ?float
    {
        if ($value === '') {
            return null;
        }

        $normalized = preg_replace('/[^\d.\-]/', '', str_replace(',', '', $value));

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function date(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function looksLikeAttachment(string $key, string $value): bool
    {
        $lowerKey = Str::lower($key);
        $lowerValue = Str::lower($value);

        return str_contains($lowerKey, 'photo')
            || str_contains($lowerKey, 'image')
            || str_contains($lowerKey, 'attachment')
            || str_contains($lowerKey, 'boq')
            || str_contains($lowerKey, 'صورة')
            || str_contains($lowerKey, 'مرفق')
            || preg_match('/\.(jpg|jpeg|png|webp|pdf|xlsx|xls)(\?.*)?$/', $lowerValue) === 1;
    }

    private function attachmentType(string $value): string
    {
        $lower = Str::lower($value);

        if (str_contains($lower, 'boq') || str_contains($lower, '.xls')) {
            return 'follow_up_boq';
        }

        if (preg_match('/\.(jpg|jpeg|png|webp)(\?.*)?$/', $lower) === 1) {
            return 'shelter_photo';
        }

        return 'kobo_attachment';
    }
}
