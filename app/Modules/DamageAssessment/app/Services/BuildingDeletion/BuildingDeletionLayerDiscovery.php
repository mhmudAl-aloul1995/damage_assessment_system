<?php

namespace App\services\BuildingDeletion;

class BuildingDeletionLayerDiscovery
{
    /**
     * @return array<string, array{name: string, role: string, record_type: string, url: string}>
     */
    public function layers(): array
    {
        $layers = [];

        $baseBuildingsUrl = $this->configuredLayerUrl('source_service', 'source_buildings_layer')
            ?? (string) config('services.arcgis.buildings_url', '');
        $baseUnitsUrl = $this->configuredLayerUrl('source_service', 'source_units_layer')
            ?? (string) config('services.arcgis.housing_units_url', '');

        if (filled($baseBuildingsUrl)) {
            $layers['base_buildings'] = [
                'name' => 'Base Buildings',
                'role' => 'base',
                'record_type' => 'building',
                'url' => $this->normalizeLayerUrl($baseBuildingsUrl, 0),
            ];
        }

        if (filled($baseUnitsUrl)) {
            $layers['base_housing_units'] = [
                'name' => 'Base Housing Units',
                'role' => 'base',
                'record_type' => 'housing_unit',
                'url' => $this->normalizeLayerUrl($baseUnitsUrl, 1),
            ];
        }

        $auditedBuildingsUrl = $this->configuredLayerUrl('target_service', 'target_buildings_layer');
        $auditedUnitsUrl = $this->configuredLayerUrl('target_service', 'target_units_layer');

        if ($auditedBuildingsUrl !== null) {
            $layers['audited_buildings'] = [
                'name' => 'Audited/Target Buildings',
                'role' => 'audited',
                'record_type' => 'building',
                'url' => $auditedBuildingsUrl,
            ];
        }

        if ($auditedUnitsUrl !== null) {
            $layers['audited_housing_units'] = [
                'name' => 'Audited/Target Housing Units',
                'role' => 'audited',
                'record_type' => 'housing_unit',
                'url' => $auditedUnitsUrl,
            ];
        }

        return $layers;
    }

    /**
     * @return array<int, string>
     */
    public function deletionOrder(): array
    {
        return [
            'base_housing_units',
            'audited_housing_units',
            'base_buildings',
            'audited_buildings',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function deletionPlan(): array
    {
        return [
            'sync_direction' => 'ArcGIS source/base layers are pulled into local base tables by sync:arcgis-layers; audited local cache is uploaded/reconciled into target_service by arcgis:reconcile-target.',
            'target_equals_audited' => filled(config('services.arcgis.target_service')),
            'reasoning' => 'Housing units are deleted before buildings, and base/source is cleaned as well as audited/target so scheduled sync or target reconciliation cannot recreate target rows from surviving source/cache data.',
            'dry_run' => (bool) config('services.arcgis.building_deletion_dry_run', false),
            'layers' => $this->layers(),
            'order' => $this->deletionOrder(),
        ];
    }

    private function configuredLayerUrl(string $serviceKey, string $layerKey): ?string
    {
        $service = config('services.arcgis.'.$serviceKey);

        if (! is_string($service) || trim($service) === '') {
            return null;
        }

        return rtrim($service, '/').'/'.config('services.arcgis.'.$layerKey);
    }

    private function normalizeLayerUrl(string $url, int $defaultLayer): string
    {
        $url = rtrim($url, '/');

        if (preg_match('#/FeatureServer$#i', $url)) {
            return $url.'/'.$defaultLayer;
        }

        return $url;
    }
}
