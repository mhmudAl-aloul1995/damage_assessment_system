<?php

namespace App\Console\Commands;

use App\services\ArcgisService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EnsureArcgisPhaseFields extends Command
{
    protected $signature = 'arcgis:ensure-phase-fields';

    protected $description = 'Ensure phase_number exists in configured ArcGIS layers with default value 1';

    public function handle(ArcgisService $arcgis): int
    {
        $token = $arcgis->getToken();
        $failedLayers = [];

        foreach ($this->phaseLayers() as $name => $url) {
            if (! filled($url)) {
                $this->warn("Skipping {$name}: missing ArcGIS layer URL.");

                continue;
            }

            $result = $arcgis->ensurePhaseNumberField($url, $token);

            if ($result['success'] ?? false) {
                $this->info("phase_number is ready for {$name}.");

                continue;
            }

            $failedLayers[] = $name;
            $message = "Could not ensure phase_number for {$name}: ".($result['message'] ?? 'Unknown error');
            $this->error($message);
            Log::warning($message, ['response' => $result['response'] ?? null]);
        }

        if ($failedLayers !== []) {
            $this->error('Failed layers: '.implode(', ', $failedLayers));

            return self::FAILURE;
        }

        $this->info('ArcGIS phase fields are ready.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function phaseLayers(): array
    {
        $csoLayerUrl = (string) config('services.arcgis.cso_survey_layer_url', '');
        $csoSurveyOrganizationsLayerUrl = (string) config('services.arcgis.cso_survey_organizations_layer_url', '');
        $csoSurveyUnitsLayerUrl = (string) config('services.arcgis.cso_survey_units_layer_url', '');

        $layers = [
            'buildings' => (string) config('services.arcgis.buildings_url', ''),
            'housing_units' => (string) config('services.arcgis.housing_units_url', ''),
            'public_building_surveys' => $this->featureServerLayerUrl((string) config('services.arcgis.public_building_survey_layer_url', ''), 0),
            'public_building_survey_units' => (string) config('services.arcgis.public_building_survey_units_layer_url', ''),
            'road_facility_surveys' => $this->featureServerLayerUrl((string) config('services.arcgis.road_facility_survey_layer_url', ''), 0),
            'road_facility_survey_items' => (string) config('services.arcgis.road_facility_survey_items_layer_url', ''),
        ];

        if (filled($csoLayerUrl)) {
            $layers['cso_surveys'] = $this->featureServerLayerUrl($csoLayerUrl, 0);
        }

        if (filled($csoSurveyOrganizationsLayerUrl) || filled($csoLayerUrl)) {
            $layers['cso_survey_organizations'] = filled($csoSurveyOrganizationsLayerUrl)
                ? $csoSurveyOrganizationsLayerUrl
                : $this->featureServerLayerUrl($csoLayerUrl, 1);
        }

        if (filled($csoSurveyUnitsLayerUrl) || filled($csoLayerUrl)) {
            $layers['cso_survey_units'] = filled($csoSurveyUnitsLayerUrl)
                ? $csoSurveyUnitsLayerUrl
                : $this->featureServerLayerUrl($csoLayerUrl, 2);
        }

        return $layers;
    }

    private function featureServerLayerUrl(string $url, int $layerId): string
    {
        $url = rtrim($url, '/');

        if ($url === '') {
            return '';
        }

        if (Str::endsWith($url, '/FeatureServer')) {
            return $url.'/'.$layerId;
        }

        return $url;
    }
}
