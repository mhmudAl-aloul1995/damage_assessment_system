<?php

namespace App\Modules\DamageAssessmentBorrowers\Services;

use App\Models\KoboRestSubmission;
use App\Modules\DamageAssessmentBorrowers\Models\BorrowerBoqCatalogItem;
use App\Modules\DamageAssessmentBorrowers\Models\BorrowerPricingSetting;
use App\Modules\DamageAssessmentBorrowers\Models\DamageAssessmentBorrower;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KoboBorrowerSubmissionSyncService
{
    /**
     * @var array<string, string>
     */
    private const DAMAGE_STATUSES = [
        'هدم كلي' => 'destroyed',
        '1' => 'destroyed',
        'متضرر بليغ غير صالح للسكن' => 'severe_uninhabitable',
        '2' => 'severe_uninhabitable',
        'متضرر بليغ صالح للسكن' => 'severe_habitable',
        '3' => 'severe_habitable',
        'متضرر أضرار طفيفة' => 'minor',
        'أضرار طفيفة' => 'minor',
        '4' => 'minor',
        'destroyed' => 'destroyed',
        'severe_uninhabitable' => 'severe_uninhabitable',
        'severe_habitable' => 'severe_habitable',
        'minor' => 'minor',
    ];

    /**
     * @return array{borrower: DamageAssessmentBorrower|null, status: string, error: string|null}
     */
    public function sync(KoboRestSubmission $submission, ?string $borrowerNameField = null, ?array $fieldMap = null): array
    {
        $payload = $submission->payload ?? [];
        $data = $this->borrowerData($payload, $borrowerNameField, $fieldMap);
        $data = $this->applyFullDemolitionValuation($data);

        if (blank($data['borrower_name'] ?? null)) {
            return [
                'borrower' => null,
                'status' => 'skipped',
                'error' => 'Kobo submission does not include borrower_name.',
            ];
        }

        $analysis = app(BorrowerRiskAnalysisService::class)->analyze($data);
        $attributes = array_merge($data, $analysis, [
            'submitted_by_name' => 'KoboToolbox',
        ]);

        return DB::transaction(function () use ($attributes, $payload, $submission): array {
            $sourceUuid = $attributes['source_uuid'] ?? null;

            $sourceUuidBorrower = filled($sourceUuid)
                ? DamageAssessmentBorrower::query()->where('source_uuid', $sourceUuid)->first()
                : null;

            $borrower = filled($attributes['borrower_id_number'] ?? null)
                ? DamageAssessmentBorrower::query()
                    ->where('borrower_id_number', $attributes['borrower_id_number'])
                    ->first()
                : null;
            $matchedByFormNumber = false;

            if (! $borrower instanceof DamageAssessmentBorrower && filled($attributes['form_number'] ?? null)) {
                $borrower = $this->borrowerByFormNumber((string) $attributes['form_number']);
                $matchedByFormNumber = $borrower instanceof DamageAssessmentBorrower;
            }

            if ($borrower instanceof DamageAssessmentBorrower && $sourceUuidBorrower instanceof DamageAssessmentBorrower && ! $sourceUuidBorrower->is($borrower)) {
                $sourceUuidBorrower->forceFill(['source_uuid' => null])->save();
            }

            if (! $borrower instanceof DamageAssessmentBorrower) {
                $borrower = $sourceUuidBorrower;
            }

            if ($borrower instanceof DamageAssessmentBorrower) {
                if ($matchedByFormNumber && filled($borrower->borrower_id_number) && filled($attributes['borrower_id_number'] ?? null) && $borrower->borrower_id_number !== $attributes['borrower_id_number']) {
                    unset($attributes['borrower_id_number']);
                }

                $borrower->fill($attributes)->save();
            } else {
                $borrower = DamageAssessmentBorrower::query()->create($attributes);
            }

            $this->syncAttachments($borrower, $payload);
            $this->syncBoqItems($borrower, $this->boqItems($payload));
            $this->syncKoboAnswers($borrower, $submission, $payload);
            $borrower->forceFill([
                'attachments_count' => $borrower->attachments()->count(),
            ])->save();

            return [
                'borrower' => $borrower->refresh(),
                'status' => 'synced',
                'error' => null,
            ];
        });
    }

    private function borrowerByFormNumber(string $formNumber): ?DamageAssessmentBorrower
    {
        $formNumberKey = $this->formNumberKey($formNumber);

        if ($formNumberKey === '') {
            return null;
        }

        return DamageAssessmentBorrower::query()
            ->whereNotNull('form_number')
            ->where('form_number', '<>', '')
            ->whereRaw("UPPER(REPLACE(form_number, ' ', '')) = ?", [$formNumberKey])
            ->first();
    }

    private function formNumberKey(mixed $value): string
    {
        return strtoupper(preg_replace('/\s+/u', '', $this->text($value) ?? '') ?? '');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function borrowerData(array $payload, ?string $borrowerNameField = null, ?array $fieldMap = null): array
    {
        $fieldMap ??= $this->configuredFieldMap();

        return array_filter([
            'source_uuid' => $this->text($this->value($payload, ['_uuid', 'meta/instanceID', 'instanceID'])),
            'source_submission_id' => $this->integer($this->value($payload, ['_id', 'id', 'submission_id'])),
            'surveyed_at' => $this->text($this->mappedValue($payload, $fieldMap, 'surveyed_at', ['surveyed_at', '_submission_time', 'submission_time', 'end', 'today'])),
            'location_latitude' => $this->geolocationPart($payload, 0),
            'location_longitude' => $this->geolocationPart($payload, 1),
            'location_altitude' => $this->geolocationPart($payload, 2),
            'location_precision' => $this->geolocationPart($payload, 3),
            'form_number' => $this->formNumber($payload, $fieldMap),
            'loan_number' => $this->text($this->mappedValue($payload, $fieldMap, 'loan_number', ['loan_number', 'loan_no'])),
            'loan_status' => $this->text($this->mappedValue($payload, $fieldMap, 'loan_status', ['loan_status'])),
            'loan_original_amount' => $this->decimal($this->mappedValue($payload, $fieldMap, 'loan_original_amount', ['loan_original_amount'])),
            'loan_total_amount' => $this->decimal($this->mappedValue($payload, $fieldMap, 'loan_total_amount', ['loan_total_amount', 'total_loan_amount'])),
            'loan_portfolio_amount' => $this->decimal($this->mappedValue($payload, $fieldMap, 'loan_portfolio_amount', ['loan_portfolio_amount'])),
            'loan_net_amount' => $this->decimal($this->mappedValue($payload, $fieldMap, 'loan_net_amount', ['loan_net_amount'])),
            'loan_balance' => $this->decimal($this->mappedValue($payload, $fieldMap, 'loan_balance', ['loan_balance', 'remaining_balance'])),
            'loan_paid_amount' => $this->decimal($this->mappedValue($payload, $fieldMap, 'loan_paid_amount', ['loan_paid_amount', 'paid_amount'])),
            'loan_installments_count' => $this->integer($this->mappedValue($payload, $fieldMap, 'loan_installments_count', ['loan_installments_count'])),
            'loan_started_at' => $this->text($this->mappedValue($payload, $fieldMap, 'loan_started_at', ['loan_started_at', 'loan_start_date'])),
            'loan_last_installment_at' => $this->text($this->mappedValue($payload, $fieldMap, 'loan_last_installment_at', ['loan_last_installment_at'])),
            'loan_clearance_delivered' => $this->boolean($this->mappedValue($payload, $fieldMap, 'loan_clearance_delivered', ['loan_clearance_delivered'])),
            'borrower_name' => $this->borrowerName($payload, $borrowerNameField),
            'borrower_id_number' => $this->text($this->mappedValue($payload, $fieldMap, 'borrower_id_number', ['borrower_id_number', 'borrower_id', 'beneficiary_id_number', 'beneficiary_id', 'applicant_id_number', 'id_number', 'national_id'])),
            'family_members_count' => $this->integer($this->mappedValue($payload, $fieldMap, 'family_members_count', ['family_members_count', 'family_count'])),
            'marital_status' => $this->text($this->mappedValue($payload, $fieldMap, 'marital_status', ['marital_status'])),
            'spouse_name' => $this->text($this->mappedValue($payload, $fieldMap, 'spouse_name', ['spouse_name'])),
            'spouse_id_number' => $this->text($this->mappedValue($payload, $fieldMap, 'spouse_id_number', ['spouse_id_number'])),
            'employment_status' => $this->text($this->mappedValue($payload, $fieldMap, 'employment_status', ['employment_status'])),
            'is_borrower_alive' => $this->boolean($this->mappedValue($payload, $fieldMap, 'is_borrower_alive', ['is_borrower_alive', 'borrower_alive'])) ?? true,
            'vulnerability_types' => $this->arrayValue($this->mappedValue($payload, $fieldMap, 'vulnerability_types', ['vulnerability_types'])),
            'guarantors_count' => $this->integer($this->mappedValue($payload, $fieldMap, 'guarantors_count', ['guarantors_count'])),
            'guarantors_alive_status' => $this->text($this->mappedValue($payload, $fieldMap, 'guarantors_alive_status', ['guarantors_alive_status'])),
            'deceased_guarantors' => $this->arrayValue($this->mappedValue($payload, $fieldMap, 'deceased_guarantors', ['deceased_guarantors'])),
            'guarantors_employment_statuses' => $this->arrayValue($this->mappedValue($payload, $fieldMap, 'guarantors_employment_statuses', ['guarantors_employment_statuses'])),
            'affected_guarantors' => $this->arrayValue($this->mappedValue($payload, $fieldMap, 'affected_guarantors', ['affected_guarantors'])),
            'displacement_status' => $this->text($this->mappedValue($payload, $fieldMap, 'displacement_status', ['displacement_status'])),
            'displaced_to_governorate' => $this->text($this->mappedValue($payload, $fieldMap, 'displaced_to_governorate', ['displaced_to_governorate', 'governorate'])),
            'current_residence_address' => $this->text($this->mappedValue($payload, $fieldMap, 'current_residence_address', ['current_residence_address'])),
            'phone_primary' => $this->text($this->mappedValue($payload, $fieldMap, 'phone_primary', ['phone_primary', 'mobile', 'phone'])),
            'phone_secondary' => $this->text($this->mappedValue($payload, $fieldMap, 'phone_secondary', ['phone_secondary'])),
            'loan_unit_address' => $this->text($this->mappedValue($payload, $fieldMap, 'loan_unit_address', ['loan_unit_address', 'address'])),
            'loan_unit_area' => $this->decimal($this->mappedValue($payload, $fieldMap, 'loan_unit_area', ['loan_unit_area'])),
            'loan_unit_floor_type' => app(BorrowerDamageValuationService::class)->normalizeFloorType($this->mappedValue($payload, $fieldMap, 'loan_unit_floor_type', ['loan_unit_floor_type', 'floor_type', 'floor', 'الطابق'])),
            'parcel_number' => $this->text($this->mappedValue($payload, $fieldMap, 'parcel_number', ['parcel_number'])),
            'plot_number' => $this->text($this->mappedValue($payload, $fieldMap, 'plot_number', ['plot_number'])),
            'loan_unit_occupancy_status' => $this->text($this->mappedValue($payload, $fieldMap, 'loan_unit_occupancy_status', ['loan_unit_occupancy_status'])),
            'resident_households' => $this->arrayValue($this->mappedValue($payload, $fieldMap, 'resident_households', ['resident_households'])),
            'loan_unit_damage_status' => $this->damageStatus($payload, $fieldMap),
            'notes' => $this->text($this->mappedValue($payload, $fieldMap, 'notes', ['notes', 'note'])),
        ], fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>|null
     */
    private function boqItems(array $payload): ?array
    {
        $quantities = $this->boqQuantities($payload);

        if ($quantities === null) {
            return null;
        }

        $catalogItems = Schema::hasTable('damage_assessment_borrower_boq_catalog_items')
            ? BorrowerBoqCatalogItem::query()->orderBy('sort_order')->get()
            : collect();

        return collect($quantities)
            ->map(function (array $quantity) use ($catalogItems): array {
                $description = $quantity['source_column'];
                $normalizedDescription = $this->normalizeDescription($description);
                $catalogItem = $catalogItems->first(function (BorrowerBoqCatalogItem $catalogItem) use ($normalizedDescription): bool {
                    $catalogDescription = (string) $catalogItem->normalized_description;

                    return $catalogDescription !== ''
                        && (str_contains($normalizedDescription, $catalogDescription)
                            || str_contains($catalogDescription, mb_substr($normalizedDescription, 0, 120)));
                });
                $unitPrice = (float) ($catalogItem?->unit_price ?? 0);
                $exchangeRate = $this->currentExchangeRate();
                $itemQuantity = (float) $quantity['quantity'];

                return [
                    'catalog_item_id' => $catalogItem?->id,
                    'source_column' => $description,
                    'source_key' => sha1($description),
                    'item_code' => $catalogItem?->item_code,
                    'description' => $catalogItem?->description ?? $description,
                    'unit' => $catalogItem?->unit ?? $this->unitFromDescription($description),
                    'unit_price' => $unitPrice,
                    'exchange_rate' => $exchangeRate,
                    'unit_price_ils' => round($unitPrice * $exchangeRate, 2),
                    'quantity' => $itemQuantity,
                    'total_price' => round($itemQuantity * $unitPrice, 2),
                    'total_price_ils' => round($itemQuantity * $unitPrice * $exchangeRate, 2),
                    'sort_order' => $quantity['sort_order'],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{source_column: string, quantity: float, sort_order: int}>|null
     */
    private function boqQuantities(array $payload): ?array
    {
        $explicitQuantities = $this->explicitBoqQuantities($payload);

        if ($explicitQuantities !== null) {
            return $explicitQuantities;
        }

        $mappedQuantities = $this->mappedBoqQuantities($payload);

        if ($mappedQuantities !== null) {
            return $mappedQuantities;
        }

        $scannedQuantities = $this->scannedBoqQuantities($payload);

        return $scannedQuantities === [] ? null : $scannedQuantities;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{source_column: string, quantity: float, sort_order: int}>|null
     */
    private function explicitBoqQuantities(array $payload): ?array
    {
        $rawQuantities = $this->value($payload, ['boq_quantities', 'boq_items']);

        if (! is_array($rawQuantities)) {
            return null;
        }

        return collect($rawQuantities)
            ->filter(fn (mixed $quantity): bool => is_array($quantity))
            ->map(function (array $quantity, int $index): ?array {
                $sourceColumn = $this->text($quantity['source_column'] ?? $quantity['description'] ?? $quantity['item'] ?? null);
                $itemQuantity = $this->decimal($quantity['quantity'] ?? $quantity['qty'] ?? null);

                if ($sourceColumn === null || $itemQuantity === null || $itemQuantity <= 0) {
                    return null;
                }

                return [
                    'source_column' => $sourceColumn,
                    'quantity' => $itemQuantity,
                    'sort_order' => (int) ($quantity['sort_order'] ?? $index + 1),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{source_column: string, quantity: float, sort_order: int}>|null
     */
    private function mappedBoqQuantities(array $payload): ?array
    {
        $fieldMap = config('services.kobotoolbox.borrower_boq_field_map', []);

        if (! is_array($fieldMap) || $fieldMap === []) {
            return null;
        }

        return collect($fieldMap)
            ->map(function (mixed $aliases, string $description) use ($payload): ?array {
                $aliases = is_array($aliases) ? $aliases : [$aliases];
                $quantity = $this->decimal($this->value($payload, array_values(array_filter($aliases, 'is_string'))));

                if ($quantity === null || $quantity <= 0) {
                    return null;
                }

                return [
                    'source_column' => $description,
                    'quantity' => $quantity,
                    'sort_order' => 0,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{source_column: string, quantity: float, sort_order: int}>
     */
    private function scannedBoqQuantities(array $payload): array
    {
        $quantities = [];

        foreach ($this->lookup($payload) as $key => $value) {
            $header = (string) $key;

            if (! $this->looksLikeBoqHeader($header)) {
                continue;
            }

            $quantity = $this->decimal($value);

            if ($quantity === null || $quantity <= 0) {
                continue;
            }

            $quantities[] = [
                'source_column' => $header,
                'quantity' => $quantity,
                'sort_order' => count($quantities) + 1,
            ];
        }

        return $quantities;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $fieldMap
     */
    private function damageStatus(array $payload, array $fieldMap): ?string
    {
        $value = $this->text($this->mappedValue($payload, $fieldMap, 'loan_unit_damage_status', [
            'loan_unit_damage_status',
            'damage_status',
            'group_lv9gw32/__007',
            '__007',
            'الوضع الانشائي للوحدة السكنية المستهدفة بالقرض',
            'المعلومات الفنية للوحدة المستهدفة / الوضع الانشائي للوحدة السكنية المستهدفة بالقرض',
        ]));

        if ($value === null) {
            return null;
        }

        return self::DAMAGE_STATUSES[$value] ?? $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyFullDemolitionValuation(array $data): array
    {
        $value = app(BorrowerDamageValuationService::class)->fullDemolitionValueUsd(
            $data['loan_unit_area'] ?? null,
            $data['loan_unit_floor_type'] ?? null,
            $data['loan_unit_damage_status'] ?? null,
        );

        if ($value === null) {
            return $data;
        }

        $exchangeRate = (float) ($data['exchange_rate'] ?? 3.2);

        return array_merge($data, [
            'boq_total_usd' => $value,
            'exchange_rate' => $exchangeRate,
            'boq_total_ils' => round($value * $exchangeRate, 2),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $fieldMap
     * @param  array<int, string>  $fallbackAliases
     */
    private function mappedValue(array $payload, array $fieldMap, string $targetField, array $fallbackAliases): mixed
    {
        $mappedAliases = $fieldMap[$targetField] ?? [];
        $mappedAliases = is_array($mappedAliases) ? $mappedAliases : [$mappedAliases];

        return $this->value($payload, array_values(array_filter(array_merge($mappedAliases, $fallbackAliases))));
    }

    /**
     * @return array<string, mixed>
     */
    private function configuredFieldMap(): array
    {
        $fieldMap = config('services.kobotoolbox.borrower_field_map', []);

        return is_array($fieldMap) ? $fieldMap : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $aliases
     */
    private function value(array $payload, array $aliases): mixed
    {
        $lookup = $this->lookup($payload);

        foreach ($aliases as $alias) {
            if (array_key_exists($alias, $lookup)) {
                return $lookup[$alias];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function borrowerName(array $payload, ?string $borrowerNameField = null): ?string
    {
        if (filled($borrowerNameField)) {
            $configuredValue = $this->text($this->value($payload, [$borrowerNameField]));

            if ($configuredValue !== null) {
                return $configuredValue;
            }
        }

        $explicitValue = $this->value($payload, [
            'borrower_name',
            'borrower_full_name',
            'full_name_borrower',
            'name_borrower',
            'beneficiary_name',
            'beneficiary_full_name',
            'applicant_name',
            'applicant_full_name',
            'client_name',
            'customer_name',
            'owner_name',
            'name_of_borrower',
            'name_of_beneficiary',
            'full_name',
            'name',
        ]);

        if ($this->text($explicitValue) !== null) {
            return $this->text($explicitValue);
        }

        $candidates = [];

        foreach ($this->lookup($payload) as $key => $value) {
            $text = $this->text($value);

            if ($text === null || $this->looksLikeNonNameValue($text)) {
                continue;
            }

            $score = $this->borrowerNameKeyScore((string) $key);

            if ($score > 0) {
                $candidates[] = [
                    'score' => $score,
                    'key' => (string) $key,
                    'value' => $text,
                ];
            }
        }

        usort($candidates, fn (array $first, array $second): int => $second['score'] <=> $first['score']);

        return $candidates[0]['value'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $fieldMap
     */
    private function formNumber(array $payload, array $fieldMap): ?string
    {
        $explicitValue = $this->text($this->mappedValue($payload, $fieldMap, 'form_number', [
            'form_number',
            'form_id',
            'form_no',
            'رقم الاستمارة',
            'رقم الاستمارة ',
        ]));

        if ($explicitValue !== null) {
            return $explicitValue;
        }

        foreach ($this->lookup($payload) as $key => $value) {
            $text = $this->text($value);

            if ($text === null || ! $this->looksLikeFormNumber($text)) {
                continue;
            }

            $normalizedKey = mb_strtolower(str_replace(['-', '_'], ' ', (string) $key));

            if (str_contains($normalizedKey, 'form') || str_contains($normalizedKey, 'استمارة')) {
                return $text;
            }
        }

        foreach ($this->lookup($payload) as $value) {
            $text = $this->text($value);

            if ($text !== null && $this->looksLikeFormNumber($text)) {
                return $text;
            }
        }

        return null;
    }

    private function looksLikeFormNumber(string $value): bool
    {
        return preg_match('/^\s*I\s*D\s*B\s*\d+\s*$/i', $value) === 1;
    }

    private function borrowerNameKeyScore(string $key): int
    {
        $key = strtolower(str_replace(['-', ' '], '_', $key));
        $baseKey = strtolower(str_replace(['-', ' '], '_', basename($key)));

        foreach (['_uuid', 'uuid', '_id', 'id', 'token', 'status', 'note', 'phone', 'mobile', 'date', 'start', 'end', 'time', 'amount', 'total', 'balance'] as $blocked) {
            if ($baseKey === $blocked || str_contains($baseKey, $blocked.'_') || str_contains($baseKey, '_'.$blocked)) {
                return 0;
            }
        }

        if (str_contains($key, 'borrower') && str_contains($key, 'name')) {
            return 100;
        }

        if (str_contains($key, 'beneficiary') && str_contains($key, 'name')) {
            return 90;
        }

        if (str_contains($key, 'applicant') && str_contains($key, 'name')) {
            return 80;
        }

        if ((str_contains($key, 'client') || str_contains($key, 'customer') || str_contains($key, 'owner')) && str_contains($key, 'name')) {
            return 70;
        }

        if (in_array($baseKey, ['full_name', 'name'], true)) {
            return 60;
        }

        if (str_contains($baseKey, 'name')) {
            return 40;
        }

        return 0;
    }

    private function looksLikeNonNameValue(string $value): bool
    {
        if (strlen($value) < 3 || strlen($value) > 255) {
            return true;
        }

        if (preg_match('/^[-+]?\d+([.,]\d+)?$/', $value) === 1) {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function lookup(array $payload, string $prefix = ''): array
    {
        $lookup = [];

        foreach ($payload as $key => $value) {
            $fullKey = $prefix === '' ? (string) $key : $prefix.'/'.$key;
            $lookup[$fullKey] = $value;
            $lookup[(string) $key] = $value;
            $lookup[basename((string) $key)] = $value;

            if (is_array($value) && ! array_is_list($value)) {
                $lookup = array_replace($lookup, $this->lookup($value, $fullKey));
            }
        }

        return $lookup;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function geolocationPart(array $payload, int $index): ?float
    {
        $geolocation = Arr::get($payload, '_geolocation') ?? $this->value($payload, ['geolocation', 'location']);

        if (is_array($geolocation)) {
            return $this->decimal($geolocation[$index] ?? null);
        }

        if (is_string($geolocation)) {
            return $this->decimal(preg_split('/\s+/', trim($geolocation))[$index] ?? null);
        }

        return null;
    }

    private function text(mixed $value): ?string
    {
        if ($value === null || is_array($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function integer(mixed $value): ?int
    {
        $value = $this->text($value);

        return $value === null || ! is_numeric($value) ? null : (int) $value;
    }

    private function decimal(mixed $value): ?float
    {
        $value = $this->text($value);

        if ($value === null) {
            return null;
        }

        $normalized = str_replace([',', ' '], '', $value);

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function boolean(mixed $value): ?bool
    {
        $value = $this->text($value);

        if ($value === null) {
            return null;
        }

        return match (strtolower($value)) {
            '1', 'true', 'yes', 'y', 'alive' => true,
            '0', 'false', 'no', 'n', 'dead' => false,
            default => null,
        };
    }

    /**
     * @return array<int|string, mixed>|null
     */
    private function arrayValue(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        $value = $this->text($value);

        if ($value === null) {
            return null;
        }

        return preg_split('/[\s,]+/', $value, flags: PREG_SPLIT_NO_EMPTY) ?: null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncAttachments(DamageAssessmentBorrower $borrower, array $payload): void
    {
        $attachments = Arr::get($payload, '_attachments', []);

        if (! is_array($attachments)) {
            return;
        }

        $seenIndexes = [];

        foreach (array_values($attachments) as $index => $attachment) {
            if (! is_array($attachment)) {
                continue;
            }

            $sourceIndex = $index + 1;
            $seenIndexes[] = $sourceIndex;

            $borrower->attachments()->updateOrCreate(
                ['source_index' => $sourceIndex],
                [
                    'filename' => $this->text($attachment['filename'] ?? $attachment['name'] ?? null),
                    'url' => $this->text($attachment['download_url'] ?? $attachment['downloadUrl'] ?? $attachment['url'] ?? null),
                    'source_index' => $sourceIndex,
                ]
            );
        }

        if ($seenIndexes !== []) {
            $borrower->attachments()
                ->whereNotIn('source_index', $seenIndexes)
                ->delete();
        }
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $boqItems
     */
    private function syncBoqItems(DamageAssessmentBorrower $borrower, ?array $boqItems): void
    {
        if ($boqItems === null) {
            return;
        }

        $seenKeys = [];

        foreach ($boqItems as $boqItem) {
            $seenKeys[] = $boqItem['source_key'];
            $borrower->boqItems()->updateOrCreate(
                ['source_key' => $boqItem['source_key']],
                $boqItem
            );
        }

        $borrower->boqItems()
            ->when($seenKeys !== [], fn ($query) => $query->whereNotIn('source_key', $seenKeys))
            ->when($seenKeys === [], fn ($query) => $query)
            ->delete();

        $borrower->forceFill([
            'boq_total_usd' => collect($boqItems)->sum('total_price'),
            'exchange_rate' => $boqItems[0]['exchange_rate'] ?? $this->currentExchangeRate(),
            'boq_total_ils' => collect($boqItems)->sum('total_price_ils'),
        ])->save();
    }

    private function currentExchangeRate(): float
    {
        if (Schema::hasTable('damage_assessment_borrower_pricing_settings')) {
            $exchangeRate = BorrowerPricingSetting::query()->value('exchange_rate');

            if ($exchangeRate !== null) {
                return (float) $exchangeRate;
            }
        }

        if (! Schema::hasColumn('damage_assessment_borrower_boq_catalog_items', 'unit_price_ils')) {
            return 3.2;
        }

        $catalogItem = BorrowerBoqCatalogItem::query()
            ->where('unit_price', '>', 0)
            ->where('unit_price_ils', '>', 0)
            ->first();

        if (! $catalogItem instanceof BorrowerBoqCatalogItem) {
            return 3.2;
        }

        return round((float) $catalogItem->unit_price_ils / (float) $catalogItem->unit_price, 4);
    }

    private function looksLikeBoqHeader(string $header): bool
    {
        if (str_starts_with($header, '_') || mb_strlen($header) < 30) {
            return false;
        }

        foreach (['م2', 'م3', 'عدد', 'م.ط', 'مقطوعية'] as $unit) {
            if (str_contains($header, $unit)) {
                return true;
            }
        }

        return str_contains($header, 'شبكة كهرباء');
    }

    private function normalizeDescription(string $description): string
    {
        $description = mb_strtolower($description);
        $description = preg_replace('/\([^)]*\)/u', ' ', $description) ?? $description;
        $description = preg_replace('/[^\p{Arabic}\p{L}\p{N}]+/u', ' ', $description) ?? $description;

        return mb_substr(trim(preg_replace('/\s+/u', ' ', $description) ?? $description), 0, 255);
    }

    private function unitFromDescription(string $description): ?string
    {
        foreach (['م2', 'م3', 'عدد', 'م.ط', 'مقطوعية'] as $unit) {
            if (str_contains($description, $unit)) {
                return $unit;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncKoboAnswers(DamageAssessmentBorrower $borrower, KoboRestSubmission $submission, array $payload): void
    {
        if (! Schema::hasTable('damage_assessment_borrower_kobo_answers')) {
            return;
        }

        $answers = $this->surveyAnswers($payload);
        $seenHashes = [];

        foreach ($answers as $answer) {
            $seenHashes[] = $answer['field_hash'];
            $borrower->koboAnswers()->updateOrCreate(
                ['field_hash' => $answer['field_hash']],
                array_merge($answer, [
                    'kobo_rest_submission_id' => $submission->id,
                ])
            );
        }

        $borrower->koboAnswers()
            ->when($seenHashes !== [], fn ($query) => $query->whereNotIn('field_hash', $seenHashes))
            ->when($seenHashes === [], fn ($query) => $query)
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{field_hash: string, field_key: string, field_label: string, value: ?string, raw_value: mixed, sort_order: int}>
     */
    private function surveyAnswers(array $payload): array
    {
        $answers = [];

        foreach ($this->flattenSurveyPayload($payload) as $fieldKey => $value) {
            if ($this->isIgnoredSurveyField($fieldKey, $value)) {
                continue;
            }

            $answers[] = [
                'field_hash' => sha1($fieldKey),
                'field_key' => $fieldKey,
                'field_label' => $this->surveyFieldLabel($fieldKey),
                'value' => $this->surveyAnswerText($value),
                'raw_value' => $value,
                'sort_order' => count($answers) + 1,
            ];
        }

        return $answers;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function flattenSurveyPayload(array $payload, string $prefix = ''): array
    {
        $answers = [];

        foreach ($payload as $key => $value) {
            $fieldKey = $prefix === '' ? (string) $key : $prefix.'/'.$key;

            if (is_array($value) && ! array_is_list($value)) {
                $answers = array_replace($answers, $this->flattenSurveyPayload($value, $fieldKey));

                continue;
            }

            $answers[$fieldKey] = $value;
        }

        return $answers;
    }

    private function isIgnoredSurveyField(string $fieldKey, mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $baseKey = basename($fieldKey);

        return in_array($baseKey, [
            '_attachments',
            '_geolocation',
            '_id',
            '_notes',
            '_status',
            '_submission_time',
            '_submitted_by',
            '_tags',
            '_uuid',
            '_validation_status',
            '_version',
            '_xform_id_string',
            'instanceID',
        ], true);
    }

    private function surveyFieldLabel(string $fieldKey): string
    {
        $label = basename($fieldKey);
        $label = str_replace(['_', '-'], ' ', $label);

        return trim($label) !== '' ? trim($label) : $fieldKey;
    }

    private function surveyAnswerText(mixed $value): ?string
    {
        if (is_array($value)) {
            if (array_is_list($value) && collect($value)->every(fn (mixed $item): bool => ! is_array($item))) {
                return collect($value)
                    ->map(fn (mixed $item): string => (string) $item)
                    ->filter(fn (string $item): bool => trim($item) !== '')
                    ->implode(', ');
            }

            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
        }

        return $this->text($value);
    }
}
