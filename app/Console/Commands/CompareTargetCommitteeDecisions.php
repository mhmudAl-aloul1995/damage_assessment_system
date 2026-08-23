<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CommitteeDecision;
use App\Models\HousingUnit;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CompareTargetCommitteeDecisions extends Command
{
    protected $signature = 'arcgis:compare-target-committee
        {--status= : Compare only committee decisions with this status, for example completed.}
        {--csv= : Optional CSV path. Defaults to storage/app/arcgis_target_committee_units_YYYYmmdd_His.csv.}
        {--chunk=1000 : ArcGIS query page size.}';

    protected $description = 'Report differences between target ArcGIS housing unit statuses and latest committee decisions.';

    public function handle(): int
    {
        $status = $this->option('status');
        $status = is_string($status) && $status !== '' ? $status : null;

        $this->info('Fetching target housing units...');
        $targetRows = $this->targetHousingUnits($this->generateToken());

        $this->line('Target units fetched: '.count($targetRows));
        $this->info('Comparing latest committee decisions...');

        $csvPath = $this->csvPath();
        $this->ensureDirectory(dirname($csvPath));

        $handle = fopen($csvPath, 'w');

        if ($handle === false) {
            throw new RuntimeException('Unable to write CSV report: '.$csvPath);
        }

        fputcsv($handle, [
            'result',
            'housing_unit_objectid',
            'housing_unit_globalid',
            'committee_decision_id',
            'committee_status',
            'decision_type',
            'expected_unit_damage_status',
            'target_objectid',
            'target_unit_damage_status',
        ]);

        $summary = [
            'committee_units' => 0,
            'target_found' => 0,
            'target_missing' => 0,
            'matched' => 0,
            'mismatched' => 0,
            'unknown_decision_type' => 0,
        ];

        foreach ($this->latestUnitCommitteeDecisions($status) as $decision) {
            $summary['committee_units']++;

            $expectedStatus = $this->expectedUnitDamageStatus($decision->decision_type);
            $housingUnit = $decision->decisionable;
            $objectId = $housingUnit instanceof HousingUnit ? $housingUnit->objectid : null;
            $target = $objectId === null ? null : ($targetRows[(string) $objectId] ?? null);

            if ($expectedStatus === null) {
                $result = 'unknown_decision_type';
                $summary['unknown_decision_type']++;
            } elseif ($target === null) {
                $result = 'target_missing';
                $summary['target_missing']++;
            } elseif (($target['unit_damage_status'] ?? null) === $expectedStatus) {
                $result = 'matched';
                $summary['matched']++;
                $summary['target_found']++;
            } else {
                $result = 'mismatch';
                $summary['mismatched']++;
                $summary['target_found']++;
            }

            fputcsv($handle, [
                $result,
                $objectId,
                $housingUnit instanceof HousingUnit ? $housingUnit->globalid : null,
                $decision->id,
                $decision->status,
                $decision->decision_type,
                $expectedStatus,
                $target['objectid'] ?? null,
                $target['unit_damage_status'] ?? null,
            ]);
        }

        fclose($handle);

        $this->table(['Metric', 'Value'], collect($summary)
            ->put('csv_path', $csvPath)
            ->map(fn (mixed $value, string $metric): array => [$metric, (string) $value])
            ->values()
            ->all());

        $this->info('Report finished. No database or ArcGIS rows were changed.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, array{objectid: int, unit_damage_status: mixed}>
     */
    private function targetHousingUnits(string $token): array
    {
        $rows = [];

        foreach ($this->queryLayerRows($this->targetLayerUrl(), 'objectid,old_objectid_U,unit_damage_status', $token, 'objectid ASC') as $attributes) {
            $objectId = $attributes['objectid'] ?? null;
            $oldObjectId = $attributes['old_objectid_U'] ?? null;

            if (! is_numeric($objectId) || $oldObjectId === null || $oldObjectId === '') {
                continue;
            }

            $rows[(string) $oldObjectId] = [
                'objectid' => (int) $objectId,
                'unit_damage_status' => $attributes['unit_damage_status'] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * @return iterable<int, CommitteeDecision>
     */
    private function latestUnitCommitteeDecisions(?string $status): iterable
    {
        $latestIds = CommitteeDecision::query()
            ->where('decisionable_type', HousingUnit::class)
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->selectRaw('MAX(id)')
            ->groupBy('decisionable_id');

        return CommitteeDecision::query()
            ->with('decisionable')
            ->whereIn('id', $latestIds)
            ->orderBy('decisionable_id')
            ->lazy();
    }

    private function expectedUnitDamageStatus(?string $decisionType): ?string
    {
        return match ($decisionType) {
            CommitteeDecision::TYPE_FULLY_DAMAGED => 'fully_damaged2',
            CommitteeDecision::TYPE_PARTIALLY_DAMAGED => 'partially_damaged2',
            CommitteeDecision::TYPE_HIGHER_COMMITTEE => 'committee_review2',
            default => null,
        };
    }

    /**
     * @return iterable<int, array<string, mixed>>
     */
    private function queryLayerRows(string $layerUrl, string $outFields, string $token, string $orderByFields): iterable
    {
        $chunk = max(1, (int) $this->option('chunk'));

        for ($offset = 0; ; $offset += $chunk) {
            $response = $this->http()->get($layerUrl.'/query', [
                'f' => 'json',
                'token' => $token,
                'where' => '1=1',
                'outFields' => $outFields,
                'returnGeometry' => 'false',
                'resultOffset' => $offset,
                'resultRecordCount' => $chunk,
                'orderByFields' => $orderByFields,
            ]);

            $data = $response->json();
            $this->throwIfArcgisError($data, 'ArcGIS query failed for '.$layerUrl);

            if (! $response->successful()) {
                throw new RuntimeException('ArcGIS query failed: '.$response->body());
            }

            $features = $data['features'] ?? [];

            if (! is_array($features) || $features === []) {
                break;
            }

            foreach ($features as $feature) {
                $attributes = $feature['attributes'] ?? [];

                if (is_array($attributes)) {
                    yield $attributes;
                }
            }
        }
    }

    private function generateToken(): string
    {
        $response = $this->http()
            ->asForm()
            ->post('https://www.arcgis.com/sharing/rest/generateToken', [
                'username' => $this->requiredConfig('username'),
                'password' => $this->requiredConfig('password'),
                'client' => 'referer',
                'referer' => $this->requiredConfig('referer'),
                'expiration' => 60,
                'f' => 'json',
            ]);

        $data = $response->json();
        $this->throwIfArcgisError($data, 'ArcGIS token failed');

        if (! $response->successful() || ! is_string($data['token'] ?? null)) {
            throw new RuntimeException('ArcGIS token failed: '.$response->body());
        }

        return $data['token'];
    }

    private function targetLayerUrl(): string
    {
        return $this->serviceUrl('target_service').'/'.config('services.arcgis.target_units_layer');
    }

    private function serviceUrl(string $key): string
    {
        return rtrim($this->requiredConfig($key), '/');
    }

    private function requiredConfig(string $key): string
    {
        $value = config('services.arcgis.'.$key);

        if (! is_string($value) || $value === '') {
            throw new RuntimeException("Missing ArcGIS config services.arcgis.{$key}.");
        }

        return $value;
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()
            ->withHeaders(['Referer' => $this->requiredConfig('referer')])
            ->timeout(180)
            ->connectTimeout(30)
            ->retry(2, 1000, throw: false)
            ->withoutVerifying();
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    private function throwIfArcgisError(?array $data, string $message): void
    {
        if (! is_array($data) || ! is_array($data['error'] ?? null)) {
            return;
        }

        throw new RuntimeException($message.': '.json_encode($data['error'], JSON_UNESCAPED_UNICODE));
    }

    private function csvPath(): string
    {
        $path = $this->option('csv');

        if (is_string($path) && $path !== '') {
            return $path;
        }

        return storage_path('app/arcgis_target_committee_units_'.now()->format('Ymd_His').'.csv');
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create directory: '.$directory);
        }
    }
}
