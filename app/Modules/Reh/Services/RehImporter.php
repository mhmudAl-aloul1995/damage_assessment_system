<?php

namespace App\Modules\Reh\Services;

use App\Modules\Reh\Models\RehBuilding;
use App\Modules\Reh\Models\RehUnit;
use Illuminate\Support\Str;

class RehImporter
{
    public function __construct(private RehScoring $scoring) {}

    public function building(array $feature): RehBuilding
    {
        $source = $feature['attributes'];
        $id = $this->identifier($source['globalid'] ?? null);
        $hash = $this->hash([...$source, '_geometry' => $feature['geometry'] ?? null]);

        return (new RehBuilding)->getConnection()->transaction(function () use ($source, $id, $hash, $feature): RehBuilding {
            $building = RehBuilding::query()->where('global_id', $id)->lockForUpdate()->first() ?? new RehBuilding(['global_id' => $id]);
            $changed = $building->exists && ($building->source_missing || ($building->source_hash !== $hash
                && $this->hash([...($building->source ?? []), '_geometry' => $building->geometry]) !== $hash));
            $building->fill([
                'object_id' => $source['objectid'], 'name' => $source['building_name'] ?? $source['building_code'] ?? null,
                'governorate' => $source['governorate'] ?? null, 'neighborhood' => $source['neighborhood'] ?? null,
                'engineer' => $source['engineer_name'] ?? null, 'social_worker' => $source['social_worker_name'] ?? null,
                'creator' => $source['Creator'] ?? $source['creator'] ?? null,
                'source' => $source, 'geometry' => $feature['geometry'] ?? null, 'source_hash' => $hash,
                'source_missing' => false, 'synced_at' => now(),
            ])->save();
            if ($changed) {
                foreach ($building->units()->lockForUpdate()->get() as $unit) {
                    $this->invalidate($unit, 'تغيّرت بيانات المبنى في المصدر.');
                }
            }

            return $building;
        });
    }

    public function unit(array $feature): RehUnit
    {
        $source = $feature['attributes'];
        $id = $this->identifier($source['globalid'] ?? null);
        $building = RehBuilding::query()->where('global_id', $this->identifier($source['parentglobalid'] ?? null))->first();
        if (! $building || $building->source_missing) {
            throw new RehSyncException('معرّف المبنى المرتبط بهذه الوحدة غير موجود في طبقة المباني.', 1001);
        }
        $hash = $this->hash($source);

        return (new RehUnit)->getConnection()->transaction(function () use ($source, $id, $building, $hash): RehUnit {
            $unit = RehUnit::query()->where('global_id', $id)->lockForUpdate()->first() ?? new RehUnit(['global_id' => $id]);
            $scoreBefore = null;
            $review = null;
            if ($unit->exists && ($unit->source_missing || ($unit->source_hash !== $hash && $this->hash($unit->source ?? []) !== $hash))) {
                $scoreBefore = $this->scoring->snapshot($unit);
                $review = $this->invalidate($unit, 'تغيّرت بيانات الوحدة في المصدر؛ راجع البيانات والكميات قبل إعادة الاعتماد.');
            }
            $unit->fill([
                'building_id' => $building->id, 'object_id' => $source['objectid'], 'code' => $source['unit_code'] ?? $source['unit_selection'] ?? null,
                'owner_name' => $source['owner_full_name'] ?? null, 'owner_identity' => $source['owner_identity_number'] ?? null,
                'floor_number' => $source['floor_number'] ?? null, 'apartment_number' => $source['apartment_number'] ?? null,
                'creator' => $source['Creator'] ?? $source['creator'] ?? null,
                'source' => $source, 'source_hash' => $hash, 'source_missing' => false, 'synced_at' => now(),
            ])->save();

            $review?->update(['changes' => ['scoring' => ['before' => $scoreBefore, 'after' => $this->scoring->snapshot($unit)]]]);

            return $unit;
        });
    }

    public function markMissing(array $buildingIds, array $unitIds): void
    {
        (new RehUnit)->getConnection()->transaction(function () use ($buildingIds, $unitIds): void {
            RehBuilding::query()->whereNotIn('id', $buildingIds)->update(['source_missing' => true]);
            $missing = RehUnit::query()->where('source_missing', false)->where(function ($query) use ($unitIds, $buildingIds): void {
                $query->whereNotIn('id', $unitIds)->orWhereNotIn('building_id', $buildingIds);
            })->lockForUpdate()->get();
            foreach ($missing as $unit) {
                $this->invalidate($unit, 'السجل أو مبناه غير موجود في آخر مزامنة مكتملة.', 'source_missing');
                $unit->update(['source_missing' => true]);
            }
        });
    }

    private function invalidate(RehUnit $unit, string $note, string $action = 'source_changed'): \App\Modules\Reh\Models\RehReview
    {
        $unit->update(['workflow_status' => 'source_changed', 'engineering_status' => 'pending', 'social_status' => 'pending', 'decision' => 'pending', 'version' => $unit->version + 1]);

        return $unit->reviews()->create(['action' => $action, 'note' => $note]);
    }

    private function identifier(mixed $value): string
    {
        $value = strtolower(trim((string) $value, '{} '));
        if (! Str::isUuid($value)) {
            throw new RehSyncException('معرّف GlobalID غير صالح في بيانات المصدر.', 1002);
        }

        return $value;
    }

    private function hash(array $source): string
    {
        foreach (array_keys($source) as $field) {
            if (in_array(strtolower($field), ['creationdate', 'creator', 'editdate', 'editor', 'objectid'], true)) {
                unset($source[$field]);
            }
        }
        ksort($source);

        return hash('sha256', json_encode($source, JSON_THROW_ON_ERROR));
    }
}
