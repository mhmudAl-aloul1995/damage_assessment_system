<?php

namespace App\Modules\Reh\Services;

use App\Models\User;
use App\Modules\Reh\Models\RehCostEstimate;
use App\Modules\Reh\Models\RehPriceBook;
use App\Modules\Reh\Models\RehPriceItem;
use App\Modules\Reh\Models\RehUnit;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class RehPricing
{
    public function __construct(private RehSurvey $survey) {}

    public function ready(): bool
    {
        return Schema::hasTable('reh_price_books');
    }

    public function latest(): ?RehPriceBook
    {
        return $this->ready() ? RehPriceBook::query()->where('status', 'approved')->whereDate('effective_date', '<=', now())->latest('approved_at')->latest('id')->first() : null;
    }

    public function statusLabel(): string
    {
        if (! $this->ready()) {
            return 'بانتظار الأسعار';
        }
        if ($this->latest()) {
            return 'قائمة أسعار معتمدة';
        }
        $book = RehPriceBook::query()->latest('id')->first();

        return $book ? ($book->status === 'draft' ? 'الأسعار قيد المراجعة' : 'بانتظار سريان الأسعار') : 'بانتظار الأسعار';
    }

    public function import(array $preview, array $data, User $user): RehPriceBook
    {
        return (new RehPriceBook)->getConnection()->transaction(function () use ($preview, $data, $user): RehPriceBook {
            $book = RehPriceBook::query()->create(['name' => $data['name'], 'exchange_rate' => $data['exchange_rate'],
                'effective_date' => $data['effective_date'], 'source_name' => $preview['filename'], 'source_hash' => $preview['hash'], 'created_by' => $user->id]);
            foreach ($preview['items'] as $row) {
                $components = $row['components'];
                unset($row['components']);
                $book->items()->create($row)->components()->createMany($components);
            }
            $this->log($book, $user, 'import', $data['reason'], ['after' => ['name' => $book->name, 'exchange_rate' => $book->exchange_rate, 'source' => $book->source_name, 'items' => count($preview['items'])]]);

            return $book;
        });
    }

    public function revise(RehPriceBook $book, User $user, string $reason): RehPriceBook
    {
        return $book->getConnection()->transaction(function () use ($book, $user, $reason): RehPriceBook {
            $book = RehPriceBook::query()->lockForUpdate()->findOrFail($book->id);
            $copy = RehPriceBook::query()->create(['name' => $book->name.' · مراجعة', 'exchange_rate' => $book->exchange_rate,
                'effective_date' => now()->toDateString(), 'source_name' => $book->source_name, 'source_hash' => $book->source_hash, 'created_by' => $user->id]);
            foreach ($book->items()->with('components')->get() as $item) {
                $copy->items()->create($item->only(['code', 'field', 'category', 'label', 'description', 'unit', 'availability', 'rate', 'issue', 'source_cell']))
                    ->components()->createMany($item->components->map->only(['category', 'label', 'unit', 'quantity', 'rate', 'source_cell'])->all());
            }
            $this->log($copy, $user, 'revision', $reason, ['from_book' => $book->id]);

            return $copy;
        });
    }

    public function updateItem(RehPriceItem $item, array $data, User $user): void
    {
        $item->getConnection()->transaction(function () use ($item, $data, $user): void {
            $book = RehPriceBook::query()->lockForUpdate()->findOrFail($item->book_id);
            $this->ensureDraft($book, $data['revision']);
            $item->refresh()->load('components');
            $before = $item->toArray();
            $parts = collect($data['components'] ?? [])->keyBy('id');
            if ($parts->count() !== $item->components->count() || $parts->keys()->diff($item->components->pluck('id'))->isNotEmpty()) {
                throw ValidationException::withMessages(['components' => 'أعد فتح تحليل السعر للحصول على جميع المكونات.']);
            }
            $total = 0;
            foreach ($item->components as $component) {
                $values = $parts[$component->id];
                if ($data['availability'] === 'available' && ($values['quantity'] === null || $values['rate'] === null)) {
                    throw ValidationException::withMessages(['components' => 'أكمل كمية وسعر كل مكون قبل إتاحة البند.']);
                }
                $component->update(['quantity' => $values['quantity'], 'rate' => $values['rate']]);
                $total += (float) $values['quantity'] * (float) $values['rate'];
            }
            if ($data['availability'] === 'available' && $item->components->isEmpty()) {
                throw ValidationException::withMessages(['components' => 'لا يوجد تحليل سعر لهذا البند؛ أعد استيراده بعد استكمال المكونات.']);
            }
            if ($total > 100000000) {
                throw ValidationException::withMessages(['components' => 'إجمالي سعر البند يتجاوز الحد المسموح.']);
            }
            if ($item->issue && ! ($data['resolve_issue'] ?? false)) {
                throw ValidationException::withMessages(['resolve_issue' => 'أكد مراجعة ملاحظة الاستيراد وسبب التصحيح.']);
            }
            $item->update(['availability' => $data['availability'], 'rate' => $data['availability'] === 'available' ? round($total, 6) : null, 'issue' => null]);
            $book->increment('revision');
            $this->log($book, $user, 'item_updated', $data['reason'], ['code' => $item->code, 'before' => $before, 'after' => $item->fresh('components')->toArray()]);
        });
    }

    public function approveBook(RehPriceBook $book, array $data, User $user): void
    {
        $book->getConnection()->transaction(function () use ($book, $data, $user): void {
            $book = RehPriceBook::query()->lockForUpdate()->findOrFail($book->id);
            $this->ensureDraft($book, $data['revision']);
            if ($book->items()->count() !== 75 || $book->items()->whereNotNull('issue')->exists()
                || $book->items()->where('availability', 'available')->whereNull('rate')->exists()) {
                throw ValidationException::withMessages(['book' => 'راجع البنود الناقصة وملاحظات الاستيراد قبل اعتماد الأسعار.']);
            }
            $before = $book->only(['status', 'exchange_rate', 'effective_date']);
            $book->update(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now(), 'revision' => $book->revision + 1]);
            $this->log($book, $user, 'approved', $data['reason'], ['before' => $before, 'after' => $book->only(['status', 'exchange_rate', 'effective_date'])]);
        });
    }

    public function updateBook(RehPriceBook $book, array $data, User $user): void
    {
        $book->getConnection()->transaction(function () use ($book, $data, $user): void {
            $book = RehPriceBook::query()->lockForUpdate()->findOrFail($book->id);
            $this->ensureDraft($book, $data['revision']);
            $before = $book->only(['name', 'exchange_rate', 'effective_date']);
            $book->update(['name' => $data['name'], 'exchange_rate' => $data['exchange_rate'], 'effective_date' => $data['effective_date'], 'revision' => $book->revision + 1]);
            $this->log($book, $user, 'settings_updated', $data['reason'], ['before' => $before, 'after' => $book->only(['name', 'exchange_rate', 'effective_date'])]);
        });
    }

    public function calculate(RehUnit $unit, ?RehPriceBook $book): array
    {
        $quantities = $this->survey->quantities($unit);
        $source = $unit->surveyData();
        $prices = $book?->loadMissing('items')->items->keyBy('field') ?? collect();
        $hasAssessment = ($source['unit_needs_intervention'] ?? null) === 'no' || collect($quantities)->contains(fn (array $q): bool => is_numeric($q['quantity']) && $q['quantity'] > 0)
            || collect($quantities)->every(fn (array $q): bool => is_numeric($q['quantity']));
        $lines = [];
        $missing = [];
        foreach ($quantities as $quantity) {
            $value = $quantity['quantity'];
            $code = preg_replace('/^boq_(\d+)_(\d+)_.*/', '$1.$2', $quantity['field']);
            if ($value === null || $value === '') {
                if (! $hasAssessment || ! array_key_exists($quantity['field'], $source)) {
                    $missing[] = $code.' · كمية غير مسجلة';
                }

                continue;
            }
            if (! is_numeric($value) || $value < 0 || ($quantity['type'] === 'integer' && floor((float) $value) !== (float) $value)) {
                $missing[] = $code.' · كمية غير صالحة';

                continue;
            }
            if ((float) $value === 0.0) {
                continue;
            }
            $price = $prices->get($quantity['field']);
            $issue = ! $price ? 'بانتظار السعر' : ($price->issue ?: ($price->availability !== 'available' ? 'غير متوفر بالسوق' : ($price->rate === null ? 'بانتظار السعر' : null)));
            $amount = $issue ? null : round((float) $value * (float) $price->rate, 2);
            $lines[] = ['code' => $code, 'field' => $quantity['field'], 'label' => $price?->label ?? preg_replace('/^\s*\d+\.\d+\s*[—–-]\s*/u', '', $quantity['label']),
                'unit' => $price?->unit ?? strtok($quantity['unit'], "\n"), 'category' => $price?->category ?? $quantity['group'],
                'quantity' => $value, 'rate' => $issue ? null : $price->rate, 'amount' => $amount, 'issue' => $issue,
                'rate_usd' => $issue ? null : round((float) $price->rate / (float) $book->exchange_rate, 6),
                'amount_usd' => $amount === null ? null : round($amount / (float) $book->exchange_rate, 2)];
            if ($issue) {
                $missing[] = $code.' · '.$issue;
            }
        }
        for ($i = 1; $i <= 3; $i++) {
            if (! empty($source['additional_item_description_'.$i])) {
                $missing[] = 'عمل إضافي '.$i.' يحتاج تحليل سعر معتمد.';
            }
        }
        if (! $book) {
            $missing[] = 'لا توجد قائمة أسعار معتمدة.';
        }
        if ($unit->source_missing || ! $unit->building || $unit->building->source_missing) {
            $missing[] = 'يلزم التحقق من ربط الوحدة ومصدرها.';
        }
        $total = array_sum(array_map(fn (array $line): int => (int) round(($line['amount'] ?? 0) * 100), $lines)) / 100;
        $quantitySnapshot = array_map(fn (array $quantity): array => [$quantity['field'], is_numeric($quantity['quantity']) ? number_format((float) $quantity['quantity'], 6, '.', '') : $quantity['quantity']], $quantities);
        $fingerprint = hash('sha256', json_encode([$quantitySnapshot, (int) $unit->building_id, (bool) $unit->source_missing,
            $source['unit_needs_intervention'] ?? null, array_intersect_key($source, array_flip(['additional_item_description_1', 'additional_item_description_2', 'additional_item_description_3']))]));

        return ['lines' => $lines, 'missing' => array_values(array_unique($missing)), 'complete' => $missing === [],
            'total' => $total, 'usd' => $book ? round($total / (float) $book->exchange_rate, 2) : null, 'fingerprint' => $fingerprint, 'book' => $book];
    }

    public function approveEstimate(RehUnit $unit, RehPriceBook $book, array $data, User $user): RehCostEstimate
    {
        return $unit->getConnection()->transaction(function () use ($unit, $book, $data, $user): RehCostEstimate {
            $book = RehPriceBook::query()->lockForUpdate()->findOrFail($book->id);
            $unit = RehUnit::query()->lockForUpdate()->findOrFail($unit->id);
            $result = $this->calculate($unit, $book);
            abort_unless($result['fingerprint'] === $data['fingerprint'], 409, 'تغيرت الكميات؛ أعد فتح التكلفة قبل الاعتماد.');
            if (! $result['complete'] || $book->status !== 'approved' || $book->effective_date->isFuture() || $unit->engineering_status !== 'approved') {
                throw ValidationException::withMessages(['estimate' => 'يلزم اعتماد التدقيق الهندسي، وسريان قائمة الأسعار، واكتمال جميع البنود.']);
            }
            $existing = RehCostEstimate::query()->where('unit_id', $unit->id)->where('book_id', $book->id)->where('fingerprint', $result['fingerprint'])->first();
            if ($existing) {
                return $existing;
            }
            $estimate = RehCostEstimate::query()->create(['unit_id' => $unit->id, 'book_id' => $book->id, 'fingerprint' => $result['fingerprint'],
                'total' => $result['total'], 'total_usd' => $result['usd'], 'exchange_rate' => $book->exchange_rate, 'approved_by' => $user->id, 'reason' => $data['reason']]);
            $estimate->lines()->createMany(array_map(fn (array $line): array => array_diff_key($line, array_flip(['issue', 'category', 'rate_usd', 'amount_usd'])), $result['lines']));
            $this->log($book, $user, 'estimate_approved', $data['reason'], ['unit_id' => $unit->id, 'estimate_id' => $estimate->id, 'total' => $estimate->total, 'total_usd' => $estimate->total_usd, 'exchange_rate' => $estimate->exchange_rate]);

            return $estimate;
        });
    }

    private function ensureDraft(RehPriceBook $book, int $revision): void
    {
        abort_unless($book->status === 'draft', 409, 'الأسعار معتمدة؛ أنشئ إصدار مراجعة للتعديل.');
        abort_unless($book->revision === $revision, 409, 'تغيرت القائمة؛ حدّث الصفحة قبل الحفظ.');
    }

    public function budget(\Illuminate\Database\Eloquent\Builder $query): array
    {
        $book = $this->latest()?->load('items');
        $complete = 0;
        $partial = 0;
        $total = 0;
        $partialTotal = 0;
        $usd = 0;
        $partialUsd = 0;
        $items = [];
        $regions = [];
        $units = [];
        $query->with('building')->chunkById(200, function ($chunk) use ($book, &$complete, &$partial, &$total, &$partialTotal, &$usd, &$partialUsd, &$items, &$regions, &$units): void {
            foreach ($chunk as $unit) {
                $cost = $this->calculate($unit, $book);
                if ($cost['complete']) {
                    $complete++;
                    $total += (int) round($cost['total'] * 100);
                    $usd += (int) round(($cost['usd'] ?? 0) * 100);
                } else {
                    $partial++;
                    $partialTotal += (int) round($cost['total'] * 100);
                    $partialUsd += (int) round(($cost['usd'] ?? 0) * 100);
                }
                $region = $this->survey->value('governorate', $unit->building?->governorate);
                $regions[$region] ??= ['units' => 0, 'complete' => 0, 'total' => 0, 'usd' => 0];
                $regions[$region]['units']++;
                if ($cost['complete']) {
                    $regions[$region]['complete']++;
                    $regions[$region]['total'] += (int) round($cost['total'] * 100);
                    $regions[$region]['usd'] += (int) round(($cost['usd'] ?? 0) * 100);
                }
                $units[] = ['id' => $unit->id, 'reference' => $unit->old_objectid, 'building' => $unit->building?->old_objectid ?? 'غير متوفر', 'total' => $cost['total'], 'usd' => $cost['usd'], 'complete' => $cost['complete'], 'missing' => count($cost['missing'])];
                foreach ($cost['lines'] as $line) {
                    $items[$line['code']] ??= ['code' => $line['code'], 'label' => $line['label'], 'category' => $line['category'], 'unit' => $line['unit'], 'quantity' => 0, 'total' => 0, 'missing' => false];
                    $items[$line['code']]['quantity'] += (float) $line['quantity'];
                    $items[$line['code']]['total'] += (int) round(($line['amount'] ?? 0) * 100);
                    $items[$line['code']]['missing'] = $items[$line['code']]['missing'] || $line['amount'] === null;
                }
            }
        });
        foreach ($items as &$item) {
            $item['total'] /= 100;
            $item['usd'] = $book && ! $item['missing'] ? round($item['total'] / (float) $book->exchange_rate, 2) : null;
        }
        unset($item);
        foreach ($regions as &$region) {
            $region['total'] /= 100;
            $region['usd'] = $book ? $region['usd'] / 100 : null;
        }
        unset($region);

        return ['book' => $book, 'complete' => $complete, 'partial' => $partial, 'total' => $total / 100, 'partialTotal' => $partialTotal / 100,
            'usd' => $book ? $usd / 100 : null, 'partialUsd' => $book ? $partialUsd / 100 : null,
            'items' => collect($items)->sortByDesc('total')->values()->all(), 'regions' => $regions, 'units' => $units];
    }

    private function log(RehPriceBook $book, User $user, string $action, string $reason, array $changes): void
    {
        $book->logs()->create(['user_id' => $user->id, 'action' => $action, 'reason' => $reason, 'changes' => $changes]);
    }
}
