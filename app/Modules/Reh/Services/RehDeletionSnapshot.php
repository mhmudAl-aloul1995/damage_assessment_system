<?php

namespace App\Modules\Reh\Services;

use App\Modules\Reh\Models\RehAuditLog;
use App\Modules\Reh\Models\RehCostEstimate;
use App\Modules\Reh\Models\RehUnit;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RehDeletionSnapshot
{
    public function __construct(private RehArcgisClient $client) {}

    public function local(RehUnit $unit): array
    {
        $estimates = app(RehPricing::class)->ready() ? RehCostEstimate::query()->where('unit_id', $unit->id)->with('lines')->get() : collect();

        return ['version' => 1, 'captured_at' => now()->toIso8601String(), 'unit' => $unit->getAttributes(),
            'building' => $unit->building?->getAttributes(),
            'reviews' => $unit->reviews()->get()->map(fn ($review): array => $review->getAttributes())->all(),
            'cost_estimates' => $estimates->map(fn ($estimate): array => ['attributes' => $estimate->getAttributes(), 'lines' => $estimate->lines->map(fn ($line): array => $line->getAttributes())->all()])->all(),
            'field_audits' => RehAuditLog::query()->where('record_kind', 'unit')->where('global_id', $unit->global_id)->get()->map(fn ($log): array => $log->getAttributes())->all()];
    }

    public function remote(RehUnit $unit, RehAuditLog $log): array
    {
        $remote = $this->client->unitSnapshot($unit->object_id, $unit->global_id);
        if ($remote['absent']) {
            return $remote;
        }
        abort_unless(strtolower(trim($remote['feature']['attributes']['parentglobalid'] ?? '', '{} ')) === strtolower($unit->building->global_id), 409, 'ربط الوحدة في ArcGIS لا يطابق المبنى المحلي. أعد المزامنة قبل الحذف.');
        $attachments = [];
        foreach ($remote['metadata']['hasAttachments'] ?? false ? $this->client->attachments((int) config('reh.units_layer'), $unit->object_id) : [] as $attachment) {
            $content = $this->client->download((int) config('reh.units_layer'), $unit->object_id, (int) $attachment['id']);
            abort_unless(strlen($content) === (int) ($attachment['size'] ?? -1), 502, 'لم تكتمل نسخة المرفق؛ لم يتم الحذف.');
            $path = 'reh-deletion-snapshots/'.$log->id.'/'.Str::uuid();
            abort_unless(Storage::disk('local')->put($path, $content) && hash_equals(hash('sha256', $content), hash('sha256', Storage::disk('local')->get($path))), 503, 'تعذر حفظ نسخة المرفقات؛ لم يتم الحذف.');
            $attachments[] = ['info' => $attachment, 'path' => $path, 'sha256' => hash('sha256', $content), 'global_id' => $attachment['globalId'] ?? (string) Str::uuid()];
        }

        return [...$remote, 'attachments' => $attachments, 'service_url' => rtrim(config('reh.service_url'), '/'), 'layer_id' => (int) config('reh.units_layer')];
    }

    public function store(RehAuditLog $log, array $snapshot): void
    {
        $log->update(['values' => [...$log->values, 'snapshot' => $snapshot, 'snapshot_sha256' => $this->checksum($snapshot)]]);
    }

    public function read(RehAuditLog $log): array
    {
        $snapshot = $log->values['snapshot'] ?? [];
        abort_unless(($snapshot['version'] ?? null) === 1 && hash_equals($log->values['snapshot_sha256'] ?? '', $this->checksum($snapshot)), 409, 'النسخة الكاملة غير متوفرة أو فشل التحقق من سلامتها. لم يتم الاسترجاع.');
        foreach ($snapshot['remote']['attachments'] ?? [] as $attachment) {
            abort_unless(Storage::disk('local')->exists($attachment['path']) && hash_equals($attachment['sha256'], hash('sha256', Storage::disk('local')->get($attachment['path']))), 409, 'أحد المرفقات المؤرشفة مفقود أو تغيّر. لم يتم الاسترجاع.');
        }

        return $snapshot;
    }

    private function checksum(array $snapshot): string
    {
        return hash('sha256', json_encode($this->ordered($snapshot), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private function ordered(array $values): array
    {
        if (! array_is_list($values)) {
            ksort($values);
        }
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = $this->ordered($value);
            }
        }

        return $values;
    }
}
