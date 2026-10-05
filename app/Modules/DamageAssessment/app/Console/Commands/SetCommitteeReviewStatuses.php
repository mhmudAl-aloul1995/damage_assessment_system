<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Building;
use App\Models\HousingUnit;
use App\services\ArcgisService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SetCommitteeReviewStatuses extends Command
{
    /** @var list<int> */
    private const BUILDING_OBJECT_IDS = [
        16708, 17363, 17310, 16856, 16833, 8889, 8254, 7724, 9663, 1523,
        781, 865, 8189, 1239, 9831, 18362, 18351, 13577, 11207, 11164,
        11140, 11064, 10493, 10487, 19308, 17521, 17906, 18654,
    ];

    /** @var list<int> */
    private const HOUSING_UNIT_OBJECT_IDS = [11239, 11267, 10891, 11370, 18992];

    protected $signature = 'committee:set-review-statuses
        {--force : Apply the changes. Without this option the command only previews them}';

    protected $description = 'Set the selected buildings and housing units to committee review, then synchronize their statuses with ArcGIS';

    public function __construct(private readonly ArcgisService $arcgisService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $buildings = Building::query()
            ->whereIn('objectid', self::BUILDING_OBJECT_IDS)
            ->get(['id', 'objectid', 'globalid']);
        $housingUnits = HousingUnit::query()
            ->whereIn('objectid', self::HOUSING_UNIT_OBJECT_IDS)
            ->get(['id', 'objectid', 'parentglobalid']);

        $missingBuildingIds = collect(self::BUILDING_OBJECT_IDS)->diff($buildings->pluck('objectid'));
        $missingHousingUnitIds = collect(self::HOUSING_UNIT_OBJECT_IDS)->diff($housingUnits->pluck('objectid'));

        if ($missingBuildingIds->isNotEmpty() || $missingHousingUnitIds->isNotEmpty()) {
            $this->error('No changes were made because some requested records were not found.');

            if ($missingBuildingIds->isNotEmpty()) {
                $this->line('Missing building objectids: '.$missingBuildingIds->implode(', '));
            }

            if ($missingHousingUnitIds->isNotEmpty()) {
                $this->line('Missing housing unit objectids: '.$missingHousingUnitIds->implode(', '));
            }

            return self::FAILURE;
        }

        $parentBuildings = Building::query()
            ->whereIn('globalid', $housingUnits->pluck('parentglobalid')->filter())
            ->get(['id', 'objectid', 'globalid']);
        $arcGisBuildingObjectIds = $buildings
            ->merge($parentBuildings)
            ->pluck('objectid')
            ->filter()
            ->unique()
            ->values();

        $this->table(['Record type', 'Count', 'Changes'], [
            ['Buildings', $buildings->count(), 'building_damage_status = committee_review; field_status = COMPLETED'],
            ['Housing units', $housingUnits->count(), 'unit_damage_status = committee_review2'],
            ['Parent buildings of housing units', $parentBuildings->count(), 'field_status = COMPLETED'],
        ]);

        if (! $this->option('force')) {
            $this->warn('Preview only: no database or ArcGIS records were changed. Re-run with --force to apply.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($buildings, $housingUnits, $parentBuildings): void {
            Building::query()
                ->whereIn('id', $buildings->pluck('id'))
                ->update([
                    'building_damage_status' => 'committee_review',
                    'field_status' => 'COMPLETED',
                ]);

            HousingUnit::query()
                ->whereIn('id', $housingUnits->pluck('id'))
                ->update(['unit_damage_status' => 'committee_review2']);

            Building::query()
                ->whereIn('id', $parentBuildings->pluck('id'))
                ->update(['field_status' => 'COMPLETED']);
        });

        try {
            $token = $this->arcgisService->getToken();
            $this->syncArcGisLayer(
                (string) config('services.arcgis.buildings_url'),
                $arcGisBuildingObjectIds,
                ['building_damage_status' => 'committee_review', 'field_status' => 'COMPLETED'],
                $token,
            );
            $this->syncArcGisLayer(
                (string) config('services.arcgis.housing_units_url'),
                $housingUnits->pluck('objectid'),
                ['unit_damage_status' => 'committee_review2'],
                $token,
            );
        } catch (RuntimeException $exception) {
            $this->error('Database changes were applied, but ArcGIS synchronization failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Database and ArcGIS statuses were updated successfully.');

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, int>  $objectIds
     * @param  array<string, string>  $attributes
     */
    private function syncArcGisLayer(string $layerUrl, Collection $objectIds, array $attributes, string $token): void
    {
        if ($layerUrl === '' || $objectIds->isEmpty()) {
            return;
        }

        $features = $objectIds
            ->map(fn (int $objectId): array => ['attributes' => ['objectid' => $objectId, ...$attributes]])
            ->all();
        $response = Http::asForm()
            ->acceptJson()
            ->post(rtrim($layerUrl, '/').'/updateFeatures', [
                'f' => 'json',
                'token' => $token,
                'features' => json_encode($features, JSON_THROW_ON_ERROR),
            ]);

        $results = collect($response->json('updateResults', []));

        if (! $response->successful() || $results->count() !== count($features) || $results->contains(fn (array $result): bool => ! ($result['success'] ?? false))) {
            throw new RuntimeException($response->body());
        }
    }
}
