<?php

namespace App\services;

use App\Models\Assessment;
use App\Models\Building;
use App\Models\BuildingStatus;
use App\Models\BuildingStatusHistory;
use App\Models\HousingStatus;
use App\Models\HousingStatusHistory;
use App\Models\HousingUnit;
use App\Models\User;
use App\Support\Navigation\SectorNavigation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class MobileDamageDetailService
{
    public function __construct(private SectorOverviewService $overview, private ArcgisService $arcgis) {}

    public function canAudit(User $user, string $sector): bool
    {
        if (! in_array($sector, ['buildings', 'housing-units'], true)) {
            return false;
        }
        $tab = collect(SectorNavigation::forUser($sector, $user))->firstWhere('key', 'audit');

        return collect($tab['items'] ?? [])->contains('url', route('audit.auditBuilding', ['sector' => $sector]));
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function detail(User $user, string $sector, int $id, array $filters): array
    {
        $basic = $this->overview->record($sector, $id, $filters);
        $allowed = $this->canAudit($user, $sector);
        $fields = [];
        if ($allowed) {
            $row = $this->overview->inquiryModel($sector, $id, $filters);
            $columns = $row->getConnection()->getSchemaBuilder()->getColumnListing($row->getTable());
            $labels = ['building_type' => 'نوع المبنى', 'building_use' => 'استخدام المبنى', 'building_age' => 'عمر المبنى',
                'floor_nos' => 'عدد الطوابق', 'ground_floor_area__m2' => 'مساحة الطابق الأرضي', 'floor_area_m2' => 'مساحة الطابق المتكرر',
                'units_nos' => 'عدد الوحدات', 'date_of_damage' => 'تاريخ الضرر', 'building_address' => 'عنوان المبنى',
                'housing_unit_type' => 'نوع الوحدة', 'floor_number' => 'رقم الطابق', 'housing_unit_number' => 'رقم الوحدة',
                'unit_direction' => 'اتجاه الوحدة', 'damaged_area_m2' => 'المساحة المتضررة', 'number_of_rooms' => 'عدد الغرف',
                'legal_challenge' => 'التحديات القانونية', 'owner_name' => 'اسم المالك', 'owner_id' => 'هوية المالك', 'id_number1' => 'رقم الهوية'];
            $labelColumns = array_values(array_filter(['name', 'label', 'label_ar', 'label_en'], fn (string $column): bool => Schema::hasColumn('assessments', $column)));
            foreach (Assessment::query()->select($labelColumns)->orderBy('id')->limit(500)->get() as $field) {
                if (filled($field->name)) {
                    $labels[$field->name] = $field->label_ar ?: $field->label ?: $field->label_en ?: $field->name;
                }
            }
            $blocked = ['all_data', 'arcgis_hash', 'arcgis_synced_at', 'password', 'token', 'deviceid', 'simserial', 'subscriberid', 'username', 'creator', 'editor'];
            foreach (array_intersect(array_keys($labels), $columns) as $key) {
                if (in_array($key, $blocked, true)) {
                    continue;
                }
                $value = $row->getRawOriginal($key);
                $fields[] = ['key' => $key, 'label' => $labels[$key], 'value' => is_scalar($value) ? (string) $value : null];
            }
        }

        return [...$basic, 'details' => $fields, 'capabilities' => ['audit_history' => $allowed, 'attachments' => $allowed, 'full_details' => $allowed]];
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function history(User $user, string $sector, int $id, array $filters, string $track, int $page): array
    {
        abort_unless($this->canAudit($user, $sector), 403);
        $row = $this->overview->inquiryModel($sector, $id, $filters);
        $housing = $sector === 'housing-units';
        $type = $track === 'legal' ? 'Legal Auditor' : 'QC/QA Engineer';
        $class = $housing ? HousingStatusHistory::class : BuildingStatusHistory::class;
        $key = $housing ? 'housing_id' : 'building_id';
        $relation = $housing ? 'assessment_status' : 'status';
        $query = $class::query()->where($key, $row->objectid)->where('type', $type);
        if (! (clone $query)->exists()) {
            $class = $housing ? HousingStatus::class : BuildingStatus::class;
            $query = $class::query()->where($key, $row->objectid)->where('type', $type);
        }
        $result = $query->with(['user', $relation])->orderByDesc('created_at')->orderByDesc('id')->paginate(20, ['*'], 'page', $page);

        return ['data' => $result->getCollection()->map(function (Model $entry) use ($relation, $track): array {
            $status = $entry->{$relation};

            return ['id' => (int) $entry->id, 'track' => $track, 'status' => $status?->name,
                'label' => $status?->label_ar ?: $status?->label_en ?: $status?->name,
                'user_name' => $entry->user?->name, 'notes' => $entry->notes,
                'created_at' => $entry->created_at?->toIso8601String()];
        })->all(), 'total' => $result->total(), 'current_page' => $result->currentPage(), 'last_page' => $result->lastPage()];
    }

    /** @param array<string, mixed> $filters
     * @return array{row: Model, token: string, layer: int, attachments: array<int, array<string, mixed>>}
     */
    private function attachmentContext(User $user, string $sector, int $id, array $filters): array
    {
        abort_unless($this->canAudit($user, $sector), 403);
        $row = $this->overview->inquiryModel($sector, $id, $filters);
        abort_unless(filled($row->objectid), 404);
        $token = $this->arcgis->getToken(true);
        $layer = $this->arcgis->getLayerId($sector === 'housing-units' ? HousingUnit::class : Building::class);
        $result = $this->arcgis->getAttachmentsResult($row->objectid, $layer, $token, true);
        abort_unless($result['success'] ?? false, 502, 'تعذّر جلب المرفقات من الخدمة.');

        return ['row' => $row, 'token' => $token, 'layer' => $layer, 'attachments' => $result['attachments'] ?? []];
    }

    /** @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function attachments(User $user, string $sector, int $id, array $filters): array
    {
        $context = $this->attachmentContext($user, $sector, $id, $filters);

        return collect($context['attachments'])->filter(fn (array $item): bool => isset($item['id']))->map(fn (array $item): array => [
            'id' => (int) $item['id'], 'name' => $item['name'] ?? 'Attachment', 'content_type' => $item['contentType'] ?? 'application/octet-stream',
            'size' => isset($item['size']) ? (int) $item['size'] : null,
            'viewable' => in_array($item['contentType'] ?? '', ['image/jpeg', 'image/png', 'application/pdf'], true) && isset($item['size']) && is_numeric($item['size']) && (int) $item['size'] <= 15 * 1024 * 1024,
        ])->values()->all();
    }

    /** @param array<string, mixed> $filters
     * @return array{body: string, content_type: string}
     */
    public function attachment(User $user, string $sector, int $id, int $attachmentId, array $filters): array
    {
        $context = $this->attachmentContext($user, $sector, $id, $filters);
        $item = collect($context['attachments'])->first(fn (array $item): bool => (int) ($item['id'] ?? 0) === $attachmentId);
        abort_unless($item, 404);
        $mime = $item['contentType'] ?? '';
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'application/pdf'], true), 415);
        abort_if(! isset($item['size']) || ! is_numeric($item['size']) || (int) $item['size'] > 15 * 1024 * 1024, 413);
        $download = $this->arcgis->downloadAttachment($context['row']->objectid, $context['layer'], $attachmentId, $context['token'], true);
        abort_unless(($download['success'] ?? false) && is_string($download['body'] ?? null), 502, 'تعذّر تحميل المرفق.');
        abort_if(strlen($download['body']) > 15 * 1024 * 1024, 413);

        return ['body' => $download['body'], 'content_type' => $mime];
    }
}
