<?php

namespace App\Modules\Reh\Services;

use Generator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    public function updateField(int $layer, int $objectId, string $field, mixed $value, bool $retry = true): void
    {
        try {
            $response = Http::asForm()->withHeaders(['Referer' => config('reh.referer')])->connectTimeout(10)->timeout(60)
                ->post($this->base().'/'.$layer.'/updateFeatures', [
                    'f' => 'json', 'token' => $this->token(), 'rollbackOnFailure' => 'true',
                    'features' => json_encode([['attributes' => ['objectid' => $objectId, $field => $value]]], JSON_THROW_ON_ERROR),
                ]);
            $data = $response->json();
        } catch (Throwable) {
            throw new RehSyncException('انقطع الاتصال أثناء الحفظ؛ يجب التحقق من نتيجة ArcGIS.', 2001);
        }
        if ($retry && in_array($data['error']['code'] ?? null, [498, 499], true)) {
            Cache::forget($this->tokenKey());
            $this->updateField($layer, $objectId, $field, $value, false);

            return;
        }
        $result = $data['updateResults'][0] ?? null;
        if ($response->successful() && ($result['success'] ?? false) === true && (int) ($result['objectId'] ?? 0) === $objectId) {
            return;
        }
        if (isset($data['error']) || ($result && ($result['success'] ?? null) === false)) {
            throw new RehSyncException('رفضت ArcGIS تعديل الحقل. تحقق من صلاحية تحرير الطبقة وقواعدها.', 2002);
        }

        throw new RehSyncException('لم تصل نتيجة مؤكدة للحفظ في ArcGIS.', 2001);
    }

    public function unitAbsent(int $objectId, string $globalId): bool
    {
        $data = $this->request(config('reh.units_layer').'/query', ['objectIds' => $objectId, 'outFields' => 'objectid,globalid', 'returnGeometry' => 'false']);
        if (! isset($data['features']) || ! is_array($data['features']) || count($data['features']) > 1 || ($data['exceededTransferLimit'] ?? false)) {
            throw new RehSyncException('تعذر التحقق من هوية الوحدة في ArcGIS.', 2001);
        }
        if ($data['features'] === []) {
            return true;
        }
        $attributes = $data['features'][0]['attributes'] ?? [];
        if ((int) ($attributes['objectid'] ?? 0) !== $objectId || strtolower(trim((string) ($attributes['globalid'] ?? ''), '{} ')) !== strtolower(trim($globalId, '{} '))) {
            throw new RehSyncException('هوية السجل في ArcGIS لا تطابق الوحدة المحلية؛ أعد المزامنة قبل الحذف.', 2002);
        }

        return false;
    }

    public function deleteUnit(int $objectId, string $globalId, bool $retry = true): void
    {
        if ($this->unitAbsent($objectId, $globalId)) {
            return;
        }
        try {
            $response = Http::asForm()->withHeaders(['Referer' => config('reh.referer')])->connectTimeout(10)->timeout(60)
                ->post($this->base().'/'.config('reh.units_layer').'/deleteFeatures', [
                    'f' => 'json', 'token' => $this->token(), 'objectIds' => (string) $objectId, 'rollbackOnFailure' => 'true',
                ]);
            $data = $response->json();
        } catch (Throwable) {
            throw new RehSyncException('انقطع الاتصال أثناء حذف الوحدة؛ يلزم التحقق من النتيجة.', 2001);
        }
        if ($retry && in_array($data['error']['code'] ?? null, [498, 499], true)) {
            Cache::forget($this->tokenKey());
            $this->deleteUnit($objectId, $globalId, false);

            return;
        }
        $result = $data['deleteResults'][0] ?? null;
        if ($response->successful() && ($result['success'] ?? false) === true && (int) ($result['objectId'] ?? 0) === $objectId) {
            return;
        }
        if (isset($data['error']) || ($result && ($result['success'] ?? null) === false)) {
            throw new RehSyncException('رفضت ArcGIS حذف الوحدة.', 2002);
        }

        throw new RehSyncException('لم تصل نتيجة مؤكدة لحذف الوحدة من ArcGIS.', 2001);
    }

    public function unitSnapshot(int $objectId, string $globalId): array
    {
        $metadata = $this->metadata((int) config('reh.units_layer'));
        $data = $this->request(config('reh.units_layer').'/query', ['objectIds' => $objectId, 'outFields' => '*', 'returnGeometry' => 'true']);
        if (! isset($data['features']) || ! is_array($data['features']) || count($data['features']) > 1 || ($data['exceededTransferLimit'] ?? false)) {
            throw new RehSyncException('تعذر أخذ نسخة كاملة من سجل ArcGIS.', 2002);
        }
        if ($data['features'] === []) {
            return ['absent' => true];
        }
        $feature = $data['features'][0];
        if (array_diff(array_column($metadata['fields'], 'name'), array_keys($feature['attributes'] ?? [])) !== []) {
            throw new RehSyncException('استجابة ArcGIS لا تتضمن جميع حقول السجل؛ لم يتم الحذف.', 2002);
        }
        if ((int) ($feature['attributes']['objectid'] ?? 0) !== $objectId || strtolower(trim($feature['attributes']['globalid'] ?? '', '{} ')) !== strtolower($globalId)) {
            throw new RehSyncException('هوية سجل النسخة لا تطابق الوحدة المحلية.', 2002);
        }
        if (($metadata['supportsApplyEditsWithGlobalIds'] ?? false) !== true) {
            throw new RehSyncException('الطبقة لا تدعم استرجاع GlobalID؛ أُوقف الحذف لحماية إمكانية الاسترجاع.', 2002);
        }

        return ['absent' => false, 'feature' => $feature, 'metadata' => $metadata];
    }

    public function unitByGlobalId(string $globalId): ?array
    {
        $globalId = trim($globalId, '{} ');
        if (! Str::isUuid($globalId)) {
            throw new RehSyncException('معرّف الاسترجاع غير صالح.', 2002);
        }
        $data = $this->request(config('reh.units_layer').'/query', ['where' => "globalid = '{$globalId}'", 'outFields' => '*', 'returnGeometry' => 'true']);
        if (! isset($data['features']) || ! is_array($data['features']) || count($data['features']) > 1 || ($data['exceededTransferLimit'] ?? false)) {
            throw new RehSyncException('لم تصل نتيجة مؤكدة للتحقق من الاسترجاع.', 2001);
        }
        $feature = $data['features'][0] ?? null;
        if ($feature && (strtolower(trim($feature['attributes']['globalid'] ?? '', '{} ')) !== strtolower($globalId) || (int) ($feature['attributes']['objectid'] ?? 0) < 1)) {
            throw new RehSyncException('هوية السجل المسترجع غير مطابقة.', 2002);
        }

        return $feature;
    }

    public function restoreUnit(array $remote, string $globalId, bool $wasAttempted, callable $markAttempted): array
    {
        if (($remote['service_url'] ?? '') !== rtrim(config('reh.service_url'), '/') || ($remote['layer_id'] ?? null) !== (int) config('reh.units_layer')) {
            throw new RehSyncException('مصدر النسخة يختلف عن طبقة REH الحالية.', 2002);
        }
        $metadata = $this->metadata((int) config('reh.units_layer'));
        if (($metadata['supportsApplyEditsWithGlobalIds'] ?? false) !== true) {
            throw new RehSyncException('الطبقة لا تدعم الاسترجاع مع الحفاظ على GlobalID.', 2002);
        }
        $attributes = [];
        if (array_diff(array_keys($remote['feature']['attributes']), array_column($metadata['fields'], 'name')) !== []) {
            throw new RehSyncException('تغيّرت حقول الطبقة منذ أخذ النسخة. راجع بنية الطبقة قبل الاسترجاع.', 2002);
        }
        foreach ($metadata['fields'] as $field) {
            $name = $field['name'];
            if ($name !== 'objectid' && (($field['editable'] ?? true) || in_array($name, ['globalid', 'parentglobalid'], true)) && array_key_exists($name, $remote['feature']['attributes'])) {
                $attributes[$name] = $remote['feature']['attributes'][$name];
            }
        }
        $existing = $this->unitByGlobalId($globalId);
        if ($existing && ! $wasAttempted) {
            throw new RehSyncException('يوجد سجل بهذا GlobalID في ArcGIS؛ لن تُستبدل بياناته.', 2002);
        }
        if (! $existing) {
            $parentId = trim($attributes['parentglobalid'] ?? '', '{} ');
            if (! Str::isUuid($parentId)) {
                throw new RehSyncException('ربط المبنى في النسخة غير صالح.', 2002);
            }
            $parent = $this->request(config('reh.buildings_layer').'/query', ['where' => "globalid = '{$parentId}'", 'outFields' => 'objectid,globalid', 'returnGeometry' => 'false']);
            if (count($parent['features'] ?? []) !== 1 || ($parent['exceededTransferLimit'] ?? false) || strtolower(trim($parent['features'][0]['attributes']['globalid'] ?? '', '{} ')) !== strtolower($parentId)) {
                throw new RehSyncException('المبنى المرتبط بالنسخة غير موجود في ArcGIS؛ لم تتم إضافة الوحدة.', 2002);
            }
            $markAttempted();
            $feature = [...$remote['feature'], 'attributes' => $attributes];
            $this->restoreEdits(['adds' => json_encode([$feature], JSON_THROW_ON_ERROR)]);
            $existing = $this->unitByGlobalId($globalId);
        }
        if (! $existing) {
            throw new RehSyncException('لم يتأكد وجود السجل المسترجع بعد.', 2001);
        }
        foreach ($attributes as $name => $value) {
            $actual = $existing['attributes'][$name] ?? null;
            $matches = in_array($name, ['globalid', 'parentglobalid'], true) ? strtolower(trim((string) $actual, '{} ')) === strtolower(trim((string) $value, '{} ')) : $actual === $value || (is_numeric($actual) && is_numeric($value) && (string) $actual === (string) $value);
            if (! $matches) {
                throw new RehSyncException('حقول ArcGIS لا تطابق النسخة؛ يلزم مراجعة نتيجة الاسترجاع.', 2001);
            }
        }
        if (($existing['geometry'] ?? null) != ($remote['feature']['geometry'] ?? null)) {
            throw new RehSyncException('هندسة السجل المسترجع لا تطابق النسخة.', 2001);
        }
        foreach ($remote['attachments'] ?? [] as $attachment) {
            $infos = $this->attachments((int) config('reh.units_layer'), (int) $existing['attributes']['objectid']);
            $found = collect($infos)->first(fn (array $info): bool => strtolower(trim($info['globalId'] ?? '', '{} ')) === strtolower(trim($attachment['global_id'], '{} ')));
            if (! $found) {
                $markAttempted();
                $this->restoreEdits(['attachments' => json_encode(['adds' => [['globalId' => $attachment['global_id'], 'parentGlobalId' => $globalId,
                    'name' => $attachment['info']['name'], 'contentType' => $attachment['info']['contentType'], 'data' => base64_encode(Storage::disk('local')->get($attachment['path']))]]], JSON_THROW_ON_ERROR)]);
                $infos = $this->attachments((int) config('reh.units_layer'), (int) $existing['attributes']['objectid']);
                $found = collect($infos)->first(fn (array $info): bool => strtolower(trim($info['globalId'] ?? '', '{} ')) === strtolower(trim($attachment['global_id'], '{} ')));
            }
            if (! $found || ! hash_equals($attachment['sha256'], hash('sha256', $this->download((int) config('reh.units_layer'), (int) $existing['attributes']['objectid'], (int) $found['id'])))) {
                throw new RehSyncException('لم يتأكد استرجاع المرفقات كاملة؛ يلزم التحقق.', 2001);
            }
        }

        return $existing;
    }

    private function restoreEdits(array $parameters, bool $retry = true): void
    {
        try {
            $response = Http::asForm()->withHeaders(['Referer' => config('reh.referer')])->connectTimeout(10)->timeout(60)
                ->post($this->base().'/'.config('reh.units_layer').'/applyEdits', [...$parameters, 'f' => 'json', 'token' => $this->token(), 'useGlobalIds' => 'true', 'rollbackOnFailure' => 'true']);
            $data = $response->json();
        } catch (Throwable) {
            throw new RehSyncException('انقطع الاتصال أثناء الاسترجاع؛ تحقق من النتيجة قبل أي محاولة أخرى.', 2001);
        }
        if ($retry && in_array($data['error']['code'] ?? null, [498, 499], true)) {
            Cache::forget($this->tokenKey());
            $this->restoreEdits($parameters, false);

            return;
        }
        $results = isset($parameters['adds']) ? ($data['addResults'] ?? []) : ($data['attachments']['addResults'] ?? []);
        if ($response->successful() && count($results) === 1 && ($results[0]['success'] ?? false) === true) {
            return;
        }
        if (isset($data['error']) || ($results[0]['success'] ?? null) === false) {
            throw new RehSyncException('رفضت ArcGIS الاسترجاع. راجع الصلاحيات وقواعد الطبقة.', 2002);
        }

        throw new RehSyncException('لم تصل نتيجة مؤكدة للاسترجاع.', 2001);
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
