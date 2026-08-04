<?php

declare(strict_types=1);

namespace App\services;

use App\Models\Building;
use App\Models\HousingUnit;
use App\Models\VBuildingAudited;
use App\Models\VHousingUnitAudited;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ArcgisAuditedRestoreService
{
    private string $token = '';

    /**
     * @var array<string, array{object_id_field: string|null, fields: array<string, string>}>
     */
    private array $layerMetadata = [];

    /**
     * @var array<string, array<int, string>>
     */
    private array $tableColumns = [];

    public function restoreBuilding(Building $building): array
    {
        $auditedBuilding = $this->auditedBuildingFor($building);

        if ($auditedBuilding === null) {
            throw new RuntimeException('لم يتم العثور على بيانات المبنى في v_buildings_audited.');
        }

        $summary = [
            'building_arcgis_updated' => false,
            'building_local_updated' => false,
            'units_arcgis_updated' => 0,
            'units_local_updated' => 0,
            'units_skipped_arcgis' => 0,
            'units_skipped_local' => 0,
            'errors' => [],
        ];

        $this->refreshToken();

        $buildingArcgisUpdated = $this->updateArcgisFeature(
            $this->buildingLayerUrl(),
            $auditedBuilding
        );

        $summary['building_arcgis_updated'] = $buildingArcgisUpdated;

        if (! $buildingArcgisUpdated) {
            $summary['errors'][] = 'لم يتم العثور على المبنى في طبقة ArcGIS العادية.';
        }

        $summary['building_local_updated'] = $this->updateLocalModel(Building::class, 'buildings', $auditedBuilding);

        if (! $summary['building_local_updated']) {
            $summary['errors'][] = 'لم يتم العثور على المبنى في جدول buildings المحلي.';
        }

        foreach ($this->auditedUnitsFor($auditedBuilding) as $auditedUnit) {
            if ($this->updateArcgisFeature($this->housingUnitsLayerUrl(), $auditedUnit)) {
                $summary['units_arcgis_updated']++;
            } else {
                $summary['units_skipped_arcgis']++;
            }

            if ($this->updateLocalModel(HousingUnit::class, 'housing_units', $auditedUnit)) {
                $summary['units_local_updated']++;
            } else {
                $summary['units_skipped_local']++;
            }
        }

        return $summary;
    }

    private function auditedBuildingFor(Building $building): ?VBuildingAudited
    {
        if (! $this->hasIdentifier($building)) {
            return null;
        }

        return VBuildingAudited::query()
            ->where(function (Builder $query) use ($building): void {
                if (filled($building->objectid)) {
                    $query->where('objectid', $building->objectid);
                }

                if (filled($building->globalid)) {
                    $query->orWhere('globalid', $building->globalid);
                }
            })
            ->first();
    }

    /**
     * @return iterable<VHousingUnitAudited>
     */
    private function auditedUnitsFor(VBuildingAudited $building): iterable
    {
        $buildingGlobalId = $building->getAttribute('globalid');

        if (! is_string($buildingGlobalId) || $buildingGlobalId === '') {
            return [];
        }

        $query = VHousingUnitAudited::query()
            ->where('parentglobalid', $buildingGlobalId)
            ->orderBy('objectid');

        return $query->cursor();
    }

    private function updateArcgisFeature(string $layerUrl, Model $auditedRecord): bool
    {
        return $this->withTokenRetry(function (string $token) use ($layerUrl, $auditedRecord): bool {
            $metadata = $this->layerMetadata($layerUrl, $token);
            $objectIdField = $metadata['object_id_field'];

            if ($objectIdField === null) {
                throw new RuntimeException('ArcGIS layer metadata is missing the object id field.');
            }

            $targetObjectId = $this->targetObjectId($layerUrl, $auditedRecord, $metadata, $token);

            if ($targetObjectId === null) {
                return false;
            }

            $attributes = $this->arcgisAttributes($auditedRecord, $metadata['fields']);
            $attributes[$objectIdField] = $targetObjectId;

            $response = $this->http()->post($layerUrl.'/updateFeatures', [
                'f' => 'json',
                'token' => $token,
                'features' => json_encode([['attributes' => $attributes]], JSON_THROW_ON_ERROR),
            ]);

            $this->throwIfArcgisError($response, 'ArcGIS updateFeatures failed');

            if (! $response->successful()) {
                throw new RuntimeException('ArcGIS updateFeatures failed: '.$response->body());
            }

            return (bool) data_get($response->json(), 'updateResults.0.success', false);
        });
    }

    /**
     * @param  array{object_id_field: string|null, fields: array<string, string>}  $metadata
     */
    private function targetObjectId(string $layerUrl, Model $auditedRecord, array $metadata, string $token): ?int
    {
        if (! $this->hasIdentifier($auditedRecord)) {
            return null;
        }

        $objectIdField = $metadata['object_id_field'];
        $fields = $metadata['fields'];

        if ($objectIdField === null) {
            return null;
        }

        foreach (['objectid', 'globalid'] as $sourceField) {
            $value = $auditedRecord->getAttribute($sourceField);
            $targetField = $sourceField === 'objectid'
                ? $objectIdField
                : ($fields[$sourceField] ?? null);

            if ($targetField === null || $value === null || $value === '') {
                continue;
            }

            $response = $this->http()->get($layerUrl.'/query', [
                'f' => 'json',
                'token' => $token,
                'where' => $targetField.' = '.$this->whereValue($value),
                'outFields' => $objectIdField,
                'returnGeometry' => 'false',
                'resultRecordCount' => 1,
            ]);

            $this->throwIfArcgisError($response, 'ArcGIS target lookup failed');

            if (! $response->successful()) {
                throw new RuntimeException('ArcGIS target lookup failed: '.$response->body());
            }

            $objectId = $response->json('features.0.attributes.'.$objectIdField);

            if (is_numeric($objectId)) {
                return (int) $objectId;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $layerFields
     * @return array<string, mixed>
     */
    private function arcgisAttributes(Model $auditedRecord, array $layerFields): array
    {
        return collect($auditedRecord->getAttributes())
            ->except(['id', 'objectid', 'OBJECTID', 'globalid', 'GlobalID', 'GLOBALID', 'shape', 'created_at', 'updated_at'])
            ->filter(fn (mixed $value, string $field): bool => array_key_exists(strtolower($field), $layerFields))
            ->mapWithKeys(fn (mixed $value, string $field): array => [$layerFields[strtolower($field)] => $value])
            ->toArray();
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function updateLocalModel(string $modelClass, string $table, Model $auditedRecord): bool
    {
        if (! $this->hasIdentifier($auditedRecord)) {
            return false;
        }

        $attributes = $this->localAttributes($table, $auditedRecord);

        if ($attributes === []) {
            return false;
        }

        $updated = $modelClass::query()
            ->where(function (Builder $query) use ($auditedRecord): void {
                $objectId = $auditedRecord->getAttribute('objectid');
                $globalId = $auditedRecord->getAttribute('globalid');

                if ($objectId !== null && $objectId !== '') {
                    $query->where('objectid', $objectId);
                }

                if ($globalId !== null && $globalId !== '') {
                    $query->orWhere('globalid', $globalId);
                }
            })
            ->update($attributes);

        return $updated > 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function localAttributes(string $table, Model $auditedRecord): array
    {
        $columns = $this->tableColumns($table);

        return collect($auditedRecord->getAttributes())
            ->except(['id', 'objectid', 'globalid', 'shape', 'created_at', 'updated_at'])
            ->filter(fn (mixed $value, string $field): bool => in_array($field, $columns, true))
            ->toArray();
    }

    /**
     * @return array<int, string>
     */
    private function tableColumns(string $table): array
    {
        if (! array_key_exists($table, $this->tableColumns)) {
            $this->tableColumns[$table] = Schema::getColumnListing($table);
        }

        return $this->tableColumns[$table];
    }

    private function hasIdentifier(Model $record): bool
    {
        return filled($record->getAttribute('objectid'))
            || filled($record->getAttribute('globalid'));
    }

    /**
     * @return array{object_id_field: string|null, fields: array<string, string>}
     */
    private function layerMetadata(string $layerUrl, string $token): array
    {
        if (array_key_exists($layerUrl, $this->layerMetadata)) {
            return $this->layerMetadata[$layerUrl];
        }

        $response = $this->http()->get($layerUrl, [
            'f' => 'json',
            'token' => $token,
        ]);

        $this->throwIfArcgisError($response, 'ArcGIS layer metadata failed');

        if (! $response->successful()) {
            throw new RuntimeException('ArcGIS layer metadata failed: '.$response->body());
        }

        $fields = collect($response->json('fields') ?? [])
            ->pluck('name')
            ->filter(fn (mixed $field): bool => is_string($field) && $field !== '')
            ->mapWithKeys(fn (string $field): array => [strtolower($field) => $field])
            ->toArray();

        $objectIdField = $response->json('objectIdField');

        return $this->layerMetadata[$layerUrl] = [
            'object_id_field' => is_string($objectIdField) && $objectIdField !== '' ? $objectIdField : null,
            'fields' => $fields,
        ];
    }

    private function refreshToken(): string
    {
        $response = $this->http()->post('https://www.arcgis.com/sharing/rest/generateToken', [
            'username' => $this->requiredConfig('username'),
            'password' => $this->requiredConfig('password'),
            'client' => 'referer',
            'referer' => $this->requiredConfig('referer'),
            'expiration' => 60,
            'f' => 'json',
        ]);

        $data = $response->json();

        if (! $response->successful() || ! is_string($data['token'] ?? null)) {
            throw new RuntimeException('ArcGIS token failed: '.$response->body());
        }

        return $this->token = $data['token'];
    }

    private function withTokenRetry(callable $callback): mixed
    {
        if ($this->token === '') {
            $this->refreshToken();
        }

        try {
            return $callback($this->token);
        } catch (RuntimeException $exception) {
            if (! $this->isInvalidTokenException($exception)) {
                throw $exception;
            }

            $this->refreshToken();

            return $callback($this->token);
        }
    }

    private function isInvalidTokenException(RuntimeException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, '"code":498')
            || str_contains($message, '"code": 498')
            || str_contains($message, '"code":499')
            || str_contains($message, '"code": 499')
            || str_contains($message, 'Invalid token')
            || str_contains($message, 'Token Required');
    }

    private function throwIfArcgisError(Response $response, string $message): void
    {
        if ($response->json('error') === null) {
            return;
        }

        throw new RuntimeException($message.': '.$response->body());
    }

    private function whereValue(int|string $value): string
    {
        if (is_numeric($value)) {
            return (string) $value;
        }

        return "'".str_replace("'", "''", $value)."'";
    }

    private function buildingLayerUrl(): string
    {
        return rtrim($this->requiredConfig('buildings_url'), '/');
    }

    private function housingUnitsLayerUrl(): string
    {
        return rtrim($this->requiredConfig('housing_units_url'), '/');
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
        return Http::asForm()
            ->acceptJson()
            ->withHeaders(['Referer' => $this->requiredConfig('referer')])
            ->timeout(120)
            ->connectTimeout(30)
            ->retry(2, 1000, throw: false)
            ->withoutVerifying();
    }
}
