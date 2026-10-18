<?php

namespace App\Modules\Reh\Services;

use Generator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class RehArcgisClient
{
    public function metadata(int $layer): array
    {
        $data = $this->request((string) $layer);
        $fields = array_column($data['fields'] ?? [], 'name');
        foreach ($layer === (int) config('reh.units_layer') ? ['globalid', 'objectid', 'parentglobalid'] : ['globalid', 'objectid'] as $field) {
            if (! in_array($field, $fields, true)) {
                throw new RehSyncException('بنية خدمة REH لا تطابق حقول الربط المطلوبة.');
            }
        }

        return $data;
    }

    public function records(int $layer): Generator
    {
        $this->metadata($layer);
        $data = $this->request($layer.'/query', ['where' => '1=1', 'returnIdsOnly' => 'true']);
        if (! array_key_exists('objectIds', $data)) {
            throw new RehSyncException('لم تُرجع الخدمة قائمة معرفات السجلات.');
        }
        foreach (array_chunk($data['objectIds'] ?? [], 100) as $ids) {
            yield from $this->chunk($layer, $ids);
        }
    }

    private function chunk(int $layer, array $ids): Generator
    {
        $data = $this->request($layer.'/query', ['objectIds' => implode(',', $ids), 'outFields' => '*', 'returnGeometry' => 'true', 'outSR' => 4326]);
        if (($data['exceededTransferLimit'] ?? false) && count($ids) > 1) {
            foreach (array_chunk($ids, (int) ceil(count($ids) / 2)) as $part) {
                yield from $this->chunk($layer, $part);
            }

            return;
        }
        if (! isset($data['features']) || count($data['features']) !== count($ids)) {
            throw new RehSyncException('تغيّرت سجلات المصدر أثناء القراءة أو كانت الاستجابة ناقصة؛ أعد المزامنة.');
        }
        foreach ($data['features'] as $feature) {
            yield $feature;
        }
    }

    public function attachments(int $layer, int $objectId): array
    {
        $data = $this->request($layer.'/'.$objectId.'/attachments');
        if (! isset($data['attachmentInfos'])) {
            throw new RehSyncException('تعذر قراءة قائمة المرفقات.');
        }

        return $data['attachmentInfos'];
    }

    public function download(int $layer, int $objectId, int $attachmentId): string
    {
        try {
            $response = Http::withHeaders(['Referer' => config('reh.referer')])->connectTimeout(10)->timeout(60)
                ->get($this->base().'/'.$layer.'/'.$objectId.'/attachments/'.$attachmentId, ['token' => $this->token()]);
            if (! $response->successful() || strlen($response->body()) > 25 * 1024 * 1024 || str_contains($response->header('Content-Type'), 'json')) {
                throw new RehSyncException;
            }

            return $response->body();
        } catch (Throwable) {
            throw new RehSyncException('تعذر تحميل المرفق من ArcGIS.');
        }
    }

    public function request(string $path, array $parameters = [], bool $retry = true): array
    {
        try {
            $response = Http::withHeaders(['Referer' => config('reh.referer')])->connectTimeout(10)->timeout(60)
                ->get($this->base().'/'.$path, [...$parameters, 'f' => 'json', 'token' => $this->token()]);
            $data = $response->json();
        } catch (Throwable) {
            throw new RehSyncException('تعذر الاتصال بخدمة ArcGIS. تحقق من الاتصال وإعدادات الحساب.');
        }
        if ($retry && in_array($data['error']['code'] ?? null, [498, 499], true)) {
            Cache::forget($this->tokenKey());

            return $this->request($path, $parameters, false);
        }
        if (! $response->successful() || ! is_array($data) || isset($data['error'])) {
            throw new RehSyncException('رفضت خدمة ArcGIS الطلب أو أعادت استجابة غير صالحة. تحقق من الصلاحيات.');
        }

        return $data;
    }

    private function token(): string
    {
        return Cache::remember($this->tokenKey(), 3000, function (): string {
            if (! config('reh.username') || ! config('reh.password')) {
                throw new RehSyncException('إعدادات حساب ArcGIS غير مكتملة.');
            }
            $data = Http::asForm()->connectTimeout(10)->timeout(30)->post('https://www.arcgis.com/sharing/rest/generateToken', [
                'f' => 'json', 'username' => config('reh.username'), 'password' => config('reh.password'),
                'client' => 'referer', 'referer' => config('reh.referer'), 'expiration' => 60,
            ])->json();
            if (! is_string($data['token'] ?? null) || $data['token'] === '') {
                throw new RehSyncException('تعذر تسجيل الدخول إلى ArcGIS.');
            }

            return $data['token'];
        });
    }

    private function tokenKey(): string
    {
        return 'reh:token:'.hash('sha256', $this->base().'|'.config('reh.username').'|'.config('reh.referer'));
    }

    private function base(): string
    {
        return rtrim(config('reh.service_url'), '/');
    }
}
