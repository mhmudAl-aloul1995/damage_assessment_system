<?php

namespace App\Modules\DamageAssessmentBorrowers\Services;

use App\Models\KoboRestSubmission;
use App\Modules\DamageAssessmentBorrowers\Models\DamageAssessmentBorrower;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class KoboBorrowerSubmissionSyncService
{
    /**
     * @return array{borrower: DamageAssessmentBorrower|null, status: string, error: string|null}
     */
    public function sync(KoboRestSubmission $submission): array
    {
        $payload = $submission->payload ?? [];
        $data = $this->borrowerData($payload);

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

        return DB::transaction(function () use ($attributes, $payload): array {
            $sourceUuid = $attributes['source_uuid'] ?? null;

            $borrower = filled($sourceUuid)
                ? DamageAssessmentBorrower::query()->updateOrCreate(['source_uuid' => $sourceUuid], $attributes)
                : DamageAssessmentBorrower::query()->create($attributes);

            $this->syncAttachments($borrower, $payload);
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

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function borrowerData(array $payload): array
    {
        return array_filter([
            'source_uuid' => $this->text($this->value($payload, ['_uuid', 'meta/instanceID', 'instanceID'])),
            'source_submission_id' => $this->integer($this->value($payload, ['_id', 'id', 'submission_id'])),
            'surveyed_at' => $this->text($this->value($payload, ['surveyed_at', '_submission_time', 'submission_time', 'end', 'today'])),
            'location_latitude' => $this->geolocationPart($payload, 0),
            'location_longitude' => $this->geolocationPart($payload, 1),
            'location_altitude' => $this->geolocationPart($payload, 2),
            'location_precision' => $this->geolocationPart($payload, 3),
            'form_number' => $this->text($this->value($payload, ['form_number', 'form_id', 'form_no'])),
            'loan_number' => $this->text($this->value($payload, ['loan_number', 'loan_no'])),
            'loan_status' => $this->text($this->value($payload, ['loan_status'])),
            'loan_original_amount' => $this->decimal($this->value($payload, ['loan_original_amount'])),
            'loan_total_amount' => $this->decimal($this->value($payload, ['loan_total_amount', 'total_loan_amount'])),
            'loan_portfolio_amount' => $this->decimal($this->value($payload, ['loan_portfolio_amount'])),
            'loan_net_amount' => $this->decimal($this->value($payload, ['loan_net_amount'])),
            'loan_balance' => $this->decimal($this->value($payload, ['loan_balance', 'remaining_balance'])),
            'loan_paid_amount' => $this->decimal($this->value($payload, ['loan_paid_amount', 'paid_amount'])),
            'loan_installments_count' => $this->integer($this->value($payload, ['loan_installments_count'])),
            'loan_started_at' => $this->text($this->value($payload, ['loan_started_at', 'loan_start_date'])),
            'loan_last_installment_at' => $this->text($this->value($payload, ['loan_last_installment_at'])),
            'loan_clearance_delivered' => $this->boolean($this->value($payload, ['loan_clearance_delivered'])),
            'borrower_name' => $this->text($this->value($payload, ['borrower_name', 'name', 'full_name'])),
            'borrower_id_number' => $this->text($this->value($payload, ['borrower_id_number', 'borrower_id', 'id_number', 'national_id'])),
            'family_members_count' => $this->integer($this->value($payload, ['family_members_count', 'family_count'])),
            'marital_status' => $this->text($this->value($payload, ['marital_status'])),
            'spouse_name' => $this->text($this->value($payload, ['spouse_name'])),
            'spouse_id_number' => $this->text($this->value($payload, ['spouse_id_number'])),
            'employment_status' => $this->text($this->value($payload, ['employment_status'])),
            'is_borrower_alive' => $this->boolean($this->value($payload, ['is_borrower_alive', 'borrower_alive'])) ?? true,
            'vulnerability_types' => $this->arrayValue($this->value($payload, ['vulnerability_types'])),
            'guarantors_count' => $this->integer($this->value($payload, ['guarantors_count'])),
            'guarantors_alive_status' => $this->text($this->value($payload, ['guarantors_alive_status'])),
            'deceased_guarantors' => $this->arrayValue($this->value($payload, ['deceased_guarantors'])),
            'guarantors_employment_statuses' => $this->arrayValue($this->value($payload, ['guarantors_employment_statuses'])),
            'affected_guarantors' => $this->arrayValue($this->value($payload, ['affected_guarantors'])),
            'displacement_status' => $this->text($this->value($payload, ['displacement_status'])),
            'displaced_to_governorate' => $this->text($this->value($payload, ['displaced_to_governorate', 'governorate'])),
            'current_residence_address' => $this->text($this->value($payload, ['current_residence_address'])),
            'phone_primary' => $this->text($this->value($payload, ['phone_primary', 'mobile', 'phone'])),
            'phone_secondary' => $this->text($this->value($payload, ['phone_secondary'])),
            'loan_unit_address' => $this->text($this->value($payload, ['loan_unit_address', 'address'])),
            'loan_unit_area' => $this->decimal($this->value($payload, ['loan_unit_area'])),
            'parcel_number' => $this->text($this->value($payload, ['parcel_number'])),
            'plot_number' => $this->text($this->value($payload, ['plot_number'])),
            'loan_unit_occupancy_status' => $this->text($this->value($payload, ['loan_unit_occupancy_status'])),
            'resident_households' => $this->arrayValue($this->value($payload, ['resident_households'])),
            'loan_unit_damage_status' => $this->text($this->value($payload, ['loan_unit_damage_status', 'damage_status'])),
            'notes' => $this->text($this->value($payload, ['notes', 'note'])),
        ], fn (mixed $value): bool => $value !== null && $value !== []);
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
}
