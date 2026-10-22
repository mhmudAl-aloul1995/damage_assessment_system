<?php

namespace App\Modules\Reh\Services;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class RehBudgetExport implements WithMultipleSheets
{
    public function __construct(private array $budget, private array $filters) {}

    public function sheets(): array
    {
        $summary = [
            [],
            ['ملخص ميزانية التأهيل'],
            ['التكلفة محسوبة حسب الفلاتر؛ اكتمال التسعير لا يعني اعتماد التكلفة.'],
            [],
            ['نطاق الحساب', 'عدد الوحدات', 'القيمة USD', 'المقابل بالشيكل', 'معنى المبلغ'],
            ['مكتملة التسعير', $this->budget['complete'], $this->budget['usd'], $this->budget['book'] ? $this->budget['total'] : null, 'تكلفة الوحدات التي اكتملت أسعارها وكمياتها.'],
            ['غير مكتملة التسعير', $this->budget['partial'], $this->budget['partialUsd'], $this->budget['book'] ? $this->budget['partialTotal'] : null, 'مبالغ البنود المسعّرة فقط؛ لا تمثل التكلفة النهائية لهذه الوحدات.'],
            ['إجمالي الوحدات في الكشف', count($this->budget['units'])],
            [],
            ['إصدار الأسعار', $this->budget['book']?->name ?? 'غير متوفر'],
            ['تاريخ استخراج الكشف', now()->format('Y-m-d H:i')],
            ['شيكل لكل دولار', $this->budget['book'] ? (float) $this->budget['book']->exchange_rate : null],
            ['الضريبة', 'لم تُضف أي ضريبة'],
            [],
            ['الفلاتر المطبقة'],
        ];
        foreach ($this->filters as $name => $value) {
            $summary[] = [$name, is_array($value) ? implode('، ', $value) : $value];
        }
        if ($this->filters === []) {
            $summary[] = ['النطاق', 'كل الوحدات المتاحة ضمن صلاحيات المستخدم'];
        }

        $units = [[], ['تكاليف الوحدات'], ['المبلغ الجزئي يشمل البنود المسعّرة فقط. «لم تُحسب تكلفة» تعني نقص بيانات أو أسعار، ولا تعني تكلفة صفرية.'], [],
            ['معرّف حصر الوحدة', 'معرّف حصر المبنى', 'حالة التسعير', 'المبلغ المحسوب USD', 'المقابل بالشيكل', 'عدد نواقص التسعير', 'المطلوب للاستكمال']];
        foreach ($this->budget['units'] as $unit) {
            $hasAmount = $unit['complete'] || ($unit['priced'] ?? $unit['total'] > 0);
            $issues = collect($unit['issues'] ?? [])->countBy(fn (string $issue): string => explode(' · ', $issue, 2)[1] ?? $issue)
                ->map(fn (int $count, string $issue): string => $issue.' ('.$count.')')->implode('؛ ');
            $units[] = [(string) $unit['reference'], (string) $unit['building'],
                $unit['complete'] ? 'مكتملة التسعير' : ($hasAmount ? 'مبلغ جزئي' : 'لم تُحسب تكلفة'),
                $hasAmount ? $unit['usd'] : null, $hasAmount ? $unit['total'] : null, $unit['missing'],
                $unit['complete'] ? 'لا توجد نواقص تسعير' : ($issues ?: 'راجع تفاصيل التكلفة في ملف الوحدة')];
        }
        $items = [[], ['كشف احتياجات الأعمال'], ['يشمل كل الوحدات المطابقة، بما فيها غير المكتملة؛ لذلك يختلف نطاقه عن إجمالي الوحدات مكتملة التسعير.'], [],
            ['كود البند', 'وصف البند', 'الكمية المطلوبة', 'الوحدة', 'القيمة USD', 'المقابل بالشيكل', 'حالة سعر البند']];
        foreach ($this->budget['items'] as $item) {
            $items[] = [(string) $item['code'], $item['label'], $item['quantity'], $item['unit'], $item['usd'],
                $item['missing'] ? null : $item['total'], $item['missing'] ? 'بانتظار السعر' : 'مسعّر'];
        }

        return [
            new RehBudgetSheet('ملخص الميزانية', $summary, [36, 32, 23, 23, 65], ['C' => '#,##0.00', 'D' => '#,##0.00'], false),
            new RehBudgetSheet('تكاليف الوحدات', $units, [23, 23, 24, 26, 26, 23, 65], ['D' => '#,##0.00', 'E' => '#,##0.00', 'F' => '#,##0'], true, 'C'),
            new RehBudgetSheet('كشف الاحتياجات', $items, [16, 75, 24, 16, 26, 26, 24], ['C' => '#,##0.###', 'E' => '#,##0.00', 'F' => '#,##0.00'], true, 'G'),
        ];
    }
}
