<?php

namespace App\Modules\Heks\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Heks\Http\Requests\ImportHeksBoqItemsRequest;
use App\Modules\Heks\Http\Requests\ImportHeksSpreadsheetRequest;
use App\Modules\Heks\Http\Requests\StoreHeksBoqItemRequest;
use App\Modules\Heks\Http\Requests\UpdateHeksBeneficiaryRequest;
use App\Modules\Heks\Http\Requests\UpdateHeksBoqItemRequest;
use App\Modules\Heks\Http\Requests\UpdateHeksBoqPricingRequest;
use App\Modules\Heks\Http\Requests\UpdateHeksFollowUpRequest;
use App\Modules\Heks\Http\Requests\UpdateHeksLabelRequest;
use App\Modules\Heks\Http\Requests\UpdateHeksScoreRequest;
use App\Modules\Heks\Http\Requests\UpdateHeksSurveyValueRequest;
use App\Modules\Heks\Models\HeksAttachment;
use App\Modules\Heks\Models\HeksBeneficiary;
use App\Modules\Heks\Models\HeksBoqItem;
use App\Modules\Heks\Models\HeksFollowUp;
use App\Modules\Heks\Models\HeksImport;
use App\Modules\Heks\Models\HeksLabel;
use App\Modules\Heks\Models\HeksPayment;
use App\Modules\Heks\Models\HeksScore;
use App\Modules\Heks\Models\HeksScoringWeight;
use App\Modules\Heks\Models\HeksSurveyValueHistory;
use App\Modules\Heks\Models\HeksWorkAssignment;
use App\Modules\Heks\Services\HeksSpreadsheetImportService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HeksController extends Controller
{
    public function dashboard(Request $request): View
    {
        $this->authorizeAccess();
        $beneficiaries = HeksBeneficiary::query()
            ->with([
                'scores' => fn ($query) => $query->latest(),
                'payments',
                'followUps',
                'attachments',
                'workAssignments',
                'labels',
                'boqItems',
            ])
            ->latest()
            ->get();

        $filteredBeneficiaries = $this->filteredDashboardBeneficiaries($beneficiaries, $request);

        return view('heks::dashboard', [
            'filters' => $this->dashboardFilters($request),
            'filterOptions' => $this->dashboardFilterOptions($beneficiaries),
            'stats' => $this->stats($filteredBeneficiaries),
            'pipeline' => $this->pipeline($filteredBeneficiaries),
            'filteredBeneficiaries' => $filteredBeneficiaries->take(50),
            'filteredCount' => $filteredBeneficiaries->count(),
            'damageDistribution' => $this->distribution($filteredBeneficiaries, 'damage_status'),
            'occupancyDistribution' => $this->distribution($filteredBeneficiaries, 'occupancy_status'),
            'genderDistribution' => $this->distribution($filteredBeneficiaries, 'household_head_gender'),
            'displacementDistribution' => $this->distribution($filteredBeneficiaries, 'displacement_status'),
            'classificationDistribution' => $filteredBeneficiaries
                ->map(fn (HeksBeneficiary $beneficiary): ?string => $beneficiary->scores->first()?->classification)
                ->filter()
                ->countBy(),
            'populationSummary' => $this->populationSummary($filteredBeneficiaries),
            'boqCount' => $filteredBeneficiaries->sum(fn (HeksBeneficiary $beneficiary): int => $beneficiary->boqItems->count()),
            'latestImports' => HeksImport::query()->latest()->limit(8)->get(),
            'engineerWorkload' => $this->engineerWorkload($filteredBeneficiaries),
            'paymentStatusDistribution' => $this->distribution($filteredBeneficiaries, 'payment_status'),
        ]);
    }

    public function imports(): View
    {
        $this->authorizeAccess();

        return view('heks::imports', [
            'imports' => HeksImport::query()->with('user:id,name')->latest()->paginate(15),
        ]);
    }

    public function preview(ImportHeksSpreadsheetRequest $request, HeksSpreadsheetImportService $importer): RedirectResponse
    {
        return back()->with('preview', $importer->preview($request->file('file')));
    }

    public function import(ImportHeksSpreadsheetRequest $request, HeksSpreadsheetImportService $importer): RedirectResponse
    {
        $result = $importer->import($request->file('file'), (string) $request->validated('type'), $request->user()?->id);

        return redirect()
            ->route('heks.imports')
            ->with('success', "تم الاستيراد: {$result['summary']['created_rows']} جديد، {$result['summary']['updated_rows']} تحديث، {$result['summary']['skipped_rows']} متجاوز.");
    }

    public function beneficiaries(Request $request): View
    {
        $this->authorizeAccess();

        $beneficiaries = HeksBeneficiary::query()
            ->withCount(['labels', 'followUps', 'scores', 'payments', 'workAssignments', 'attachments'])
            ->when($request->filled('q'), function ($query) use ($request): void {
                $search = (string) $request->string('q');
                $query->where(function ($query) use ($search): void {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('identity_number', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('selected'), fn ($query) => $query->where('is_selected', $request->boolean('selected')))
            ->when($request->filled('engineer'), fn ($query) => $query->where('field_engineer', (string) $request->string('engineer')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('heks::beneficiaries', [
            'beneficiaries' => $beneficiaries,
            'engineers' => HeksBeneficiary::query()->whereNotNull('field_engineer')->distinct()->orderBy('field_engineer')->pluck('field_engineer'),
        ]);
    }

    public function edit(HeksBeneficiary $beneficiary): View
    {
        $this->authorizeAccess();
        $beneficiary->load([
            'labels' => fn ($query) => $query->latest(),
            'followUps' => fn ($query) => $query->with('boqItems')->latest('visit_date')->latest(),
            'scores' => fn ($query) => $query->latest(),
            'payments' => fn ($query) => $query->latest(),
            'workAssignments' => fn ($query) => $query->latest(),
            'attachments' => fn ($query) => $query->latest(),
            'boqItems' => fn ($query) => $query->whereNull('heks_follow_up_id')->orderBy('section')->orderBy('item_code')->latest(),
            'surveyValueHistories' => fn ($query) => $query->with('user')->latest(),
        ]);

        return view('heks::edit', [
            'beneficiary' => $beneficiary,
            'boqTotal' => (float) $beneficiary->boqItems->sum('total_price_ils'),
            'boqCatalog' => $this->boqCatalog(),
            'boqSections' => $this->boqCatalog()->pluck('section')->filter()->unique()->sort()->values(),
            'boqUnits' => $this->boqCatalog()->pluck('unit')->filter()->unique()->sort()->values(),
            'rawDataSections' => $this->rawDataSections($beneficiary),
            'surveySections' => $this->surveySections($beneficiary),
            'imageAttachments' => $this->imageAttachments($beneficiary),
            'scoringComponents' => $this->scoringComponents(),
            'priorityMatrix' => $this->priorityMatrix(),
            'socialAssessmentRows' => $this->socialAssessmentRows($beneficiary),
            'technicalAssessmentRows' => $this->technicalAssessmentRows($beneficiary),
        ]);
    }

    public function update(UpdateHeksBeneficiaryRequest $request, HeksBeneficiary $beneficiary): RedirectResponse
    {
        $beneficiary->update($request->validated());

        return back()->with('success', 'تم تحديث بيانات المستفيد.');
    }

    public function updateSurveyValue(UpdateHeksSurveyValueRequest $request, HeksBeneficiary $beneficiary): RedirectResponse
    {
        $this->authorizeAccess();

        $data = $request->validated();
        $rawData = $beneficiary->raw_data ?? [];
        $source = $data['source'];
        $fieldKey = $data['field_key'];
        $newValue = $data['value'] ?? null;

        abort_unless(is_array($rawData) && array_key_exists($source, $rawData) && is_array($rawData[$source]) && array_key_exists($fieldKey, $rawData[$source]), 404);

        $oldValue = $this->surveyDisplayValue($rawData[$source][$fieldKey] ?? null);
        $newValue = $newValue !== null ? trim($newValue) : null;

        if ($oldValue === ($newValue ?? '')) {
            return back()->with('success', 'لا يوجد تغيير على قيمة الاستبيان.');
        }

        $rawData[$source][$fieldKey] = $newValue;

        DB::transaction(function () use ($beneficiary, $rawData, $source, $fieldKey, $oldValue, $newValue): void {
            $beneficiary->forceFill(['raw_data' => $rawData])->save();

            HeksSurveyValueHistory::query()->create([
                'heks_beneficiary_id' => $beneficiary->id,
                'user_id' => auth()->id(),
                'source' => $source,
                'field_key' => $fieldKey,
                'old_value' => $oldValue,
                'new_value' => $newValue,
            ]);
        });

        return back()->with('success', 'تم تحديث قيمة الاستبيان وحفظ سجل التعديل.');
    }

    public function pricing(HeksBeneficiary $beneficiary): View
    {
        $this->authorizeAccess();
        $beneficiary->load(['boqItems' => fn ($query) => $query->whereNull('heks_follow_up_id')->orderBy('section')->orderBy('item_code')->orderBy('id')]);
        $pricingRows = $this->heksPricingRows($beneficiary);

        return view('heks::pricing', [
            'beneficiary' => $beneficiary,
            'pricingRows' => $pricingRows,
            'boqTotal' => (float) $beneficiary->boqItems->sum('total_price_ils'),
            'pricingSections' => $pricingRows
                ->groupBy('section')
                ->map(fn (\Illuminate\Support\Collection $rows, string $section): array => [
                    'section' => $section !== '' ? $section : 'بدون قسم',
                    'items_count' => $rows->count(),
                    'priced_count' => $rows->filter(fn (array $row): bool => (float) $row['quantity'] > 0)->count(),
                    'total' => (float) $rows->sum('total_price_ils'),
                ])
                ->values(),
        ]);
    }

    public function updateBoqPricing(UpdateHeksBoqPricingRequest $request, HeksBeneficiary $beneficiary): RedirectResponse
    {
        $items = collect($request->validated('items') ?? [])
            ->map(function (array $item): array {
                $quantity = (float) ($item['quantity'] ?? 0);
                $unitPrice = (float) ($item['unit_price_ils'] ?? 0);

                return [
                    'source' => $item['source'] ?? 'pricing',
                    'section' => $item['section'] ?? null,
                    'item_code' => $item['item_code'] ?? null,
                    'description' => (string) $item['description'],
                    'unit' => $item['unit'] ?? null,
                    'quantity' => $quantity,
                    'unit_price_ils' => $unitPrice,
                    'total_price_ils' => round($quantity * $unitPrice, 2),
                    'notes' => $item['notes'] ?? null,
                ];
            })
            ->filter(fn (array $item): bool => $item['quantity'] > 0)
            ->values();

        DB::transaction(function () use ($beneficiary, $items): void {
            $seen = [];

            foreach ($items as $item) {
                $key = sha1(($item['item_code'] ?? '').'|'.$item['description']);
                $seen[] = $key;

                $beneficiary->boqItems()->updateOrCreate(
                    [
                        'item_code' => $item['item_code'],
                        'description' => $item['description'],
                        'heks_follow_up_id' => null,
                    ],
                    [
                        ...$item,
                        'raw_data' => ['pricing_key' => $key],
                    ]
                );
            }

            $beneficiary->boqItems()->whereNull('heks_follow_up_id')->get()->each(function (HeksBoqItem $item) use ($seen): void {
                $key = sha1(($item->item_code ?? '').'|'.$item->description);

                if (! in_array($key, $seen, true)) {
                    $item->delete();
                }
            });
        });

        return redirect()
            ->route('heks.beneficiaries.pricing', $beneficiary)
            ->with('success', 'تم حفظ تسعير جدول الكميات بنجاح.');
    }

    public function storeBoqItem(StoreHeksBoqItemRequest $request, HeksBeneficiary $beneficiary): RedirectResponse
    {
        $beneficiary->boqItems()->create($this->boqPayload([
            ...$request->validated(),
            'heks_follow_up_id' => null,
        ]));

        return back()->with('success', 'تمت إضافة بند جدول الكميات.');
    }

    public function importBoqItems(ImportHeksBoqItemsRequest $request, HeksBeneficiary $beneficiary, HeksSpreadsheetImportService $importer): RedirectResponse
    {
        try {
            $summary = $importer->importBeneficiaryBoq($request->file('file'), $beneficiary);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['file' => $exception->getMessage()]);
        }

        return back()->with('success', "تم استيراد {$summary['imported_rows']} بند جدول كميات، وتم تجاوز {$summary['skipped_rows']} بند.");
    }

    public function updateBoqItem(UpdateHeksBoqItemRequest $request, HeksBoqItem $boqItem): RedirectResponse
    {
        $boqItem->update($this->boqPayload($request->validated()));

        return back()->with('success', 'تم تحديث بند جدول الكميات.');
    }

    public function destroyBoqItem(HeksBoqItem $boqItem): RedirectResponse
    {
        $this->authorizeAccess();
        $boqItem->delete();

        return back()->with('success', 'تم حذف بند جدول الكميات.');
    }

    public function labels(): View
    {
        $this->authorizeAccess();

        return view('heks::labels', [
            'labels' => HeksLabel::query()->with('beneficiary')->latest()->paginate(25),
        ]);
    }

    public function updateLabel(UpdateHeksLabelRequest $request, HeksLabel $label): RedirectResponse
    {
        $label->update($request->validated());

        return back()->with('success', 'تم تحديث معيار التقييم.');
    }

    public function followUps(Request $request): View
    {
        $this->authorizeAccess();

        $query = HeksFollowUp::query()
            ->with([
                'beneficiary.attachments',
                'boqItems' => fn ($query) => $query->orderBy('section')->orderBy('item_code')->orderBy('id'),
            ])
            ->when($request->filled('q'), function ($query) use ($request): void {
                $search = (string) $request->string('q');
                $query->where(function ($query) use ($search): void {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhereHas('beneficiary', function ($query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('identity_number', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('engineer'), fn ($query) => $query->where('engineer_name', (string) $request->string('engineer')))
            ->when($request->filled('visit_number'), fn ($query) => $query->where('visit_number', (string) $request->string('visit_number')))
            ->when($request->filled('visit_from'), fn ($query) => $query->whereDate('visit_date', '>=', (string) $request->string('visit_from')))
            ->when($request->filled('visit_to'), fn ($query) => $query->whereDate('visit_date', '<=', (string) $request->string('visit_to')))
            ->when($request->string('boq_status')->toString() === 'with_boq', function ($query): void {
                $query->where(function ($query): void {
                    $query->whereNotNull('boq_filename')->orWhereNotNull('boq_url');
                });
            })
            ->when($request->string('boq_status')->toString() === 'without_boq', function ($query): void {
                $query->whereNull('boq_filename')->whereNull('boq_url');
            });

        $boqAttachments = HeksAttachment::query()
            ->where('attachment_type', 'follow_up_boq')
            ->get();

        return view('heks::follow-ups', [
            'followUps' => $query->latest()->paginate(25)->withQueryString(),
            'followUpSummary' => [
                'total' => HeksFollowUp::query()->count(),
                'with_boq' => HeksFollowUp::query()
                    ->where(fn ($query) => $query->whereNotNull('boq_filename')->orWhereNotNull('boq_url'))
                    ->count(),
                'imported_boq' => $boqAttachments
                    ->filter(fn (HeksAttachment $attachment): bool => (bool) data_get($attachment->raw_data, 'boq_import_summary.imported'))
                    ->count(),
                'failed_boq' => $boqAttachments
                    ->filter(fn (HeksAttachment $attachment): bool => data_get($attachment->raw_data, 'boq_import_summary.imported') === false)
                    ->count(),
                'completed_amount' => HeksFollowUp::query()->sum('completed_amount_ils'),
            ],
            'engineers' => HeksFollowUp::query()
                ->whereNotNull('engineer_name')
                ->where('engineer_name', '<>', '')
                ->distinct()
                ->orderBy('engineer_name')
                ->pluck('engineer_name'),
            'visitNumbers' => HeksFollowUp::query()
                ->whereNotNull('visit_number')
                ->where('visit_number', '<>', '')
                ->distinct()
                ->orderBy('visit_number')
                ->pluck('visit_number'),
        ]);
    }

    public function updateFollowUp(UpdateHeksFollowUpRequest $request, HeksFollowUp $followUp): RedirectResponse
    {
        $followUp->update($request->validated());

        return back()->with('success', 'تم تحديث المتابعة.');
    }

    public function importFollowUpBoq(HeksFollowUp $followUp, HeksSpreadsheetImportService $importer): RedirectResponse
    {
        $this->authorizeAccess();

        $summary = $importer->importFollowUpBoq($followUp);

        if ($summary === null) {
            return back()->with('error', 'ملف BOQ لهذه الزيارة ليس ملف Excel قابل للترحيل.');
        }

        if (($summary['imported'] ?? false) === false) {
            return back()->with('error', $summary['error'] ?? 'تعذر ترحيل جدول الكميات من ملف Excel.');
        }

        return back()->with('success', 'تم ترحيل '.number_format((int) ($summary['imported_rows'] ?? 0)).' بند من Excel إلى قاعدة البيانات.');
    }

    public function followUpBoq(HeksFollowUp $followUp): View
    {
        $this->authorizeAccess();
        $followUp->load([
            'beneficiary',
            'boqItems' => fn ($query) => $query->orderBy('section')->orderBy('item_code')->orderBy('id'),
        ]);

        return view('heks::follow-up-boq', [
            'followUp' => $followUp,
            'beneficiary' => $followUp->beneficiary,
            'boqTotal' => (float) $followUp->boqItems->sum('total_price_ils'),
        ]);
    }

    public function scores(): View
    {
        $this->authorizeAccess();

        return view('heks::scores', [
            'scores' => HeksScore::query()->with('beneficiary')->latest()->paginate(25),
            'scoreSummary' => [
                'total' => HeksScore::query()->count(),
                'average_social' => HeksScore::query()->avg('social_score'),
                'average_technical' => HeksScore::query()->avg('technical_score'),
                'average_total' => HeksScore::query()->avg('total_score'),
            ],
            'classifications' => HeksScore::query()
                ->selectRaw('classification, count(*) as aggregate')
                ->whereNotNull('classification')
                ->where('classification', '<>', '')
                ->groupBy('classification')
                ->orderByDesc('aggregate')
                ->pluck('aggregate', 'classification'),
        ]);
    }

    public function updateScore(UpdateHeksScoreRequest $request, HeksScore $score): RedirectResponse
    {
        $score->update($request->validated());

        return back()->with('success', 'تم تحديث الدرجات.');
    }

    public function storeScore(UpdateHeksScoreRequest $request, HeksBeneficiary $beneficiary): RedirectResponse
    {
        $beneficiary->scores()->create(array_merge($request->validated(), [
            'source' => $request->validated('source') ?: 'manual',
        ]));

        return back()->with('success', 'تمت إضافة سجل الدرجات.');
    }

    public function quality(): View
    {
        $this->authorizeAccess();

        return view('heks::quality', [
            'missingIdentity' => HeksBeneficiary::query()->whereNull('identity_number')->orWhere('identity_number', '')->count(),
            'missingScores' => HeksBeneficiary::query()->doesntHave('scores')->count(),
            'missingPayments' => HeksBeneficiary::query()->where('is_selected', true)->doesntHave('payments')->count(),
            'missingFollowUps' => HeksBeneficiary::query()->where('is_selected', true)->doesntHave('followUps')->count(),
            'unlinkedAttachments' => HeksAttachment::query()->whereNull('heks_beneficiary_id')->count(),
            'duplicateIdentities' => HeksBeneficiary::query()
                ->selectRaw('identity_number, count(*) as aggregate')
                ->whereNotNull('identity_number')
                ->where('identity_number', '<>', '')
                ->groupBy('identity_number')
                ->having('aggregate', '>', 1)
                ->pluck('aggregate', 'identity_number'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function boqPayload(array $data): array
    {
        $quantity = (float) ($data['quantity'] ?? 0);
        $unitPrice = (float) ($data['unit_price_ils'] ?? 0);

        return [
            ...$data,
            'total_price_ils' => round($quantity * $unitPrice, 2),
        ];
    }

    private function heksPricingRows(HeksBeneficiary $beneficiary): \Illuminate\Support\Collection
    {
        $existingItems = $beneficiary->boqItems
            ->keyBy(fn (HeksBoqItem $item): string => sha1(($item->item_code ?? '').'|'.$item->description));

        $rows = $this->boqCatalog()
            ->map(function (array $catalogItem, int $index) use ($existingItems): array {
                $key = sha1(($catalogItem['item_code'] ?? '').'|'.$catalogItem['description']);
                $existing = $existingItems->get($key);
                $quantity = $existing?->quantity ?? 0;
                $unitPrice = $existing?->unit_price_ils ?? $catalogItem['unit_price_ils'];

                return [
                    'id' => $existing?->id,
                    'key' => $key,
                    'source' => $existing?->source ?? 'catalog',
                    'section' => $existing?->section ?? $catalogItem['section'],
                    'item_code' => $existing?->item_code ?? $catalogItem['item_code'],
                    'description' => $existing?->description ?? $catalogItem['description'],
                    'unit' => $existing?->unit ?? $catalogItem['unit'],
                    'quantity' => $quantity,
                    'unit_price_ils' => $unitPrice,
                    'total_price_ils' => $existing?->total_price_ils ?? 0,
                    'notes' => $existing?->notes,
                    'sort_order' => $index,
                ];
            });

        $catalogKeys = $rows->pluck('key')->all();
        $extraRows = $beneficiary->boqItems
            ->reject(fn (HeksBoqItem $item): bool => in_array(sha1(($item->item_code ?? '').'|'.$item->description), $catalogKeys, true))
            ->map(fn (HeksBoqItem $item): array => [
                'id' => $item->id,
                'key' => sha1(($item->item_code ?? '').'|'.$item->description),
                'source' => $item->source,
                'section' => $item->section,
                'item_code' => $item->item_code,
                'description' => $item->description,
                'unit' => $item->unit,
                'quantity' => $item->quantity,
                'unit_price_ils' => $item->unit_price_ils,
                'total_price_ils' => $item->total_price_ils,
                'notes' => $item->notes,
                'sort_order' => 9999,
            ]);

        return $rows->merge($extraRows)->values();
    }

    private function boqCatalog(): \Illuminate\Support\Collection
    {
        $savedItems = HeksBoqItem::query()
            ->select(['section', 'item_code', 'description', 'unit', 'unit_price_ils'])
            ->whereNotNull('description')
            ->get()
            ->map(fn (HeksBoqItem $item): array => [
                'section' => (string) $item->section,
                'item_code' => (string) $item->item_code,
                'description' => (string) $item->description,
                'unit' => (string) $item->unit,
                'unit_price_ils' => (float) $item->unit_price_ils,
            ]);

        return collect([
            ['section' => 'أعمال الازالة', 'item_code' => '1.1', 'description' => 'أعمال هدم وإزالة أنقاض جدران من البلوك الاسمنتي أو أجزائها الخرسانية', 'unit' => 'm3', 'unit_price_ils' => 113],
            ['section' => 'أعمال الازالة', 'item_code' => '1.2', 'description' => 'أعمال إزالة أنقاض فقط أي كانت محتوياتها وسواء كانت داخل المنزل أو خارجه', 'unit' => 'm3', 'unit_price_ils' => 97],
            ['section' => 'اعمال الخرسانة', 'item_code' => '2.1', 'description' => 'توريد وصب خرسانة B200 لزوم المدات الأرضية بسمك 10سم', 'unit' => 'M2', 'unit_price_ils' => 1035],
            ['section' => 'اعمال الخرسانة', 'item_code' => '2.2', 'description' => 'توريد وصب خرسانة مسلحة B250 لزوم الأسقف بسمك 25 سم', 'unit' => 'M2', 'unit_price_ils' => 2511],
            ['section' => 'اعمال البلوك', 'item_code' => '3.1', 'description' => 'توريد و بناء بلوك اسمنتي مفرغ مستخدم بحالة جيدة مقاس 20 سم', 'unit' => 'M2', 'unit_price_ils' => 610],
            ['section' => 'اعمال البلوك', 'item_code' => '3.2', 'description' => 'توريد و بناء بلوك اسمنتي مفرغ مستخدم بحالة جيدة مقاس 15 سم', 'unit' => 'M2', 'unit_price_ils' => 585],
            ['section' => 'اعمال البلوك', 'item_code' => '3.3', 'description' => 'توريد و بناء بلوك اسمنتي مفرغ مستخدم بحالة جيدة مقاس 12 سم', 'unit' => 'M2', 'unit_price_ils' => 572],
            ['section' => 'القصارة', 'item_code' => '4.1', 'description' => 'قصارة داخلية من طبقتين رشقة مسمار وبطانة', 'unit' => 'M2', 'unit_price_ils' => 210],
            ['section' => 'البلاط', 'item_code' => '5.1', 'description' => 'توريد وتركيب بلاط نوع سيراميك أو بورسلان للأرضيات', 'unit' => 'M2', 'unit_price_ils' => 315],
            ['section' => 'المجلى', 'item_code' => '6.1', 'description' => 'توريد وتركيب كاونتر مطبخ وجه باطون', 'unit' => 'م.ط', 'unit_price_ils' => 680],
            ['section' => 'الدهان', 'item_code' => '7.1', 'description' => 'توريد ودهان لزوم الحوائط الداخلية والأسقف بوجهين', 'unit' => 'M2', 'unit_price_ils' => 15],
            ['section' => 'الخشب', 'item_code' => '8.1', 'description' => 'توريد وتركيب ضلفة باب خشب جديد', 'unit' => 'عدد', 'unit_price_ils' => 1455],
            ['section' => 'السباكة', 'item_code' => '11.1', 'description' => 'توريد وتركيب مغسلة بورسلان مقاس 50 سم', 'unit' => 'عدد', 'unit_price_ils' => 435],
            ['section' => 'الجبس', 'item_code' => '12.1', 'description' => 'توريد وتركيب قواطع من ألواح الجبس', 'unit' => 'M2', 'unit_price_ils' => 565],
        ])
            ->merge($savedItems)
            ->unique(fn (array $item): string => $item['item_code'].'|'.$item['description'])
            ->sortBy('item_code')
            ->values();
    }

    /**
     * @param  EloquentCollection<int, HeksBeneficiary>  $beneficiaries
     * @return EloquentCollection<int, HeksBeneficiary>
     */
    private function filteredDashboardBeneficiaries(EloquentCollection $beneficiaries, Request $request): EloquentCollection
    {
        $filters = $this->dashboardFilters($request);

        return $beneficiaries
            ->filter(function (HeksBeneficiary $beneficiary) use ($filters): bool {
                if ($filters['governorate'] !== '' && $beneficiary->governorate !== $filters['governorate']) {
                    return false;
                }

                if ($filters['area'] !== '' && $beneficiary->area !== $filters['area']) {
                    return false;
                }

                if ($filters['visit_from'] !== '' && ($beneficiary->visit_date?->format('Y-m-d') ?? '') < $filters['visit_from']) {
                    return false;
                }

                if ($filters['visit_to'] !== '' && ($beneficiary->visit_date?->format('Y-m-d') ?? '') > $filters['visit_to']) {
                    return false;
                }

                if ($filters['damage_status'] !== '' && $beneficiary->damage_status !== $filters['damage_status']) {
                    return false;
                }

                if ($filters['occupancy_status'] !== '' && $beneficiary->occupancy_status !== $filters['occupancy_status']) {
                    return false;
                }

                if ($filters['displacement_status'] !== '' && $beneficiary->displacement_status !== $filters['displacement_status']) {
                    return false;
                }

                if ($filters['household_head_gender'] !== '' && $beneficiary->household_head_gender !== $filters['household_head_gender']) {
                    return false;
                }

                if ($filters['classification'] !== '' && $beneficiary->scores->first()?->classification !== $filters['classification']) {
                    return false;
                }

                if ($filters['unit_type'] !== '' && $this->rawValue($beneficiary, ['نوع الوحدة السكنية']) !== $filters['unit_type']) {
                    return false;
                }

                if ($filters['income_source'] !== '' && ! $this->rawMatches($beneficiary, ['هل تمتلك الأسرة مصدر دخل ثابت أو منتظم'], $filters['income_source'])) {
                    return false;
                }

                if ($filters['food_aid'] !== '' && ! $this->rawMatches($beneficiary, ['هل تعتمد الأسرة في توفير الطعام'], $filters['food_aid'])) {
                    return false;
                }

                if ($filters['privacy'] !== '' && ! $this->rawMatches($beneficiary, ['الفصل أو الخصوصية'], $filters['privacy'])) {
                    return false;
                }

                if ($filters['overcrowding'] !== '' && ! $this->rawMatches($beneficiary, ['المساحة تكفي لعدد الأفراد'], $filters['overcrowding'])) {
                    return false;
                }

                if ($filters['household_min'] !== null && $this->householdSize($beneficiary) < $filters['household_min']) {
                    return false;
                }

                if ($filters['household_max'] !== null && $this->householdSize($beneficiary) > $filters['household_max']) {
                    return false;
                }

                foreach (['has_disability', 'war_injury', 'chronic_disease', 'uxo_risk', 'unsafe_structure', 'documents_ready', 'bank_account'] as $flag) {
                    if ($filters[$flag] && ! $this->matchesDashboardFlag($beneficiary, $flag)) {
                        return false;
                    }
                }

                return true;
            })
            ->values();
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    private function dashboardFilters(Request $request): array
    {
        return [
            'governorate' => (string) $request->string('governorate'),
            'area' => (string) $request->string('area'),
            'visit_from' => (string) $request->string('visit_from'),
            'visit_to' => (string) $request->string('visit_to'),
            'damage_status' => (string) $request->string('damage_status'),
            'unit_type' => (string) $request->string('unit_type'),
            'occupancy_status' => (string) $request->string('occupancy_status'),
            'household_head_gender' => (string) $request->string('household_head_gender'),
            'displacement_status' => (string) $request->string('displacement_status'),
            'income_source' => (string) $request->string('income_source'),
            'food_aid' => (string) $request->string('food_aid'),
            'privacy' => (string) $request->string('privacy'),
            'overcrowding' => (string) $request->string('overcrowding'),
            'classification' => (string) $request->string('classification'),
            'household_min' => $request->filled('household_min') ? max(0, (int) $request->integer('household_min')) : null,
            'household_max' => $request->filled('household_max') ? max(0, (int) $request->integer('household_max')) : null,
            'has_disability' => $request->boolean('has_disability'),
            'war_injury' => $request->boolean('war_injury'),
            'chronic_disease' => $request->boolean('chronic_disease'),
            'uxo_risk' => $request->boolean('uxo_risk'),
            'unsafe_structure' => $request->boolean('unsafe_structure'),
            'documents_ready' => $request->boolean('documents_ready'),
            'bank_account' => $request->boolean('bank_account'),
        ];
    }

    /**
     * @param  EloquentCollection<int, HeksBeneficiary>  $beneficiaries
     * @return array<string, mixed>
     */
    private function dashboardFilterOptions(EloquentCollection $beneficiaries): array
    {
        return [
            'governorates' => $beneficiaries->pluck('governorate')->filter()->unique()->sort()->values(),
            'areas' => $beneficiaries->pluck('area')->filter()->unique()->sort()->values(),
            'damageStatuses' => $beneficiaries->pluck('damage_status')->filter()->unique()->sort()->values(),
            'occupancyStatuses' => $beneficiaries->pluck('occupancy_status')->filter()->unique()->sort()->values(),
            'displacementStatuses' => $beneficiaries->pluck('displacement_status')->filter()->unique()->sort()->values(),
            'headGenders' => $beneficiaries->pluck('household_head_gender')->filter()->unique()->sort()->values(),
            'classifications' => $beneficiaries->map(fn (HeksBeneficiary $beneficiary): ?string => $beneficiary->scores->first()?->classification)->filter()->unique()->sort()->values(),
            'unitTypes' => $this->rawOptions($beneficiaries, ['نوع الوحدة السكنية']),
            'incomeSources' => $this->rawOptions($beneficiaries, ['هل تمتلك الأسرة مصدر دخل ثابت أو منتظم']),
            'foodAidOptions' => $this->rawOptions($beneficiaries, ['هل تعتمد الأسرة في توفير الطعام']),
            'privacyOptions' => $this->rawOptions($beneficiaries, ['الفصل أو الخصوصية']),
            'overcrowdingOptions' => $this->rawOptions($beneficiaries, ['المساحة تكفي لعدد الأفراد']),
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function stats(?EloquentCollection $beneficiaries = null): array
    {
        if ($beneficiaries !== null) {
            return [
                'beneficiaries' => $beneficiaries->count(),
                'selected' => $beneficiaries->where('is_selected', true)->count(),
                'labels' => $beneficiaries->sum(fn (HeksBeneficiary $beneficiary): int => $beneficiary->labels_count ?? $beneficiary->labels()->count()),
                'follow_ups' => $beneficiaries->sum(fn (HeksBeneficiary $beneficiary): int => $beneficiary->followUps->count()),
                'scores' => $beneficiaries->sum(fn (HeksBeneficiary $beneficiary): int => $beneficiary->scores->count()),
                'payments' => $beneficiaries->sum(fn (HeksBeneficiary $beneficiary): int => $beneficiary->payments->count()),
                'attachments' => $beneficiaries->sum(fn (HeksBeneficiary $beneficiary): int => $beneficiary->attachments->count()),
                'weights' => HeksScoringWeight::query()->count(),
                'imports' => HeksImport::query()->count(),
                'grant_total' => (float) $beneficiaries->sum('grant_amount'),
            ];
        }

        return [
            'beneficiaries' => HeksBeneficiary::query()->count(),
            'selected' => HeksBeneficiary::query()->where('is_selected', true)->count(),
            'labels' => HeksLabel::query()->count(),
            'follow_ups' => HeksFollowUp::query()->count(),
            'scores' => HeksScore::query()->count(),
            'payments' => HeksPayment::query()->count(),
            'attachments' => HeksAttachment::query()->count(),
            'weights' => HeksScoringWeight::query()->count(),
            'imports' => HeksImport::query()->count(),
            'grant_total' => (float) HeksBeneficiary::query()->sum('grant_amount'),
        ];
    }

    /**
     * @return array<int, array{label: string, count: int, tone: string}>
     */
    private function pipeline(?EloquentCollection $beneficiaries = null): array
    {
        if ($beneficiaries !== null) {
            return [
                ['label' => 'تم تقييمها', 'count' => $beneficiaries->count(), 'tone' => 'primary'],
                ['label' => 'تم اختيارها', 'count' => $beneficiaries->where('is_selected', true)->count(), 'tone' => 'success'],
                ['label' => 'تم توزيعها', 'count' => $beneficiaries->filter(fn (HeksBeneficiary $beneficiary): bool => $beneficiary->workAssignments->isNotEmpty())->count(), 'tone' => 'info'],
                ['label' => 'استلمت دفعة أولى', 'count' => $beneficiaries->whereIn('payment_status', ['paid_30', 'paid_80', 'paid_100'])->count(), 'tone' => 'warning'],
                ['label' => 'تمت متابعتها', 'count' => $beneficiaries->filter(fn (HeksBeneficiary $beneficiary): bool => $beneficiary->followUps->isNotEmpty())->count(), 'tone' => 'dark'],
                ['label' => 'اكتمل دفعها', 'count' => $beneficiaries->where('payment_status', 'paid_100')->count(), 'tone' => 'success'],
            ];
        }

        return [
            ['label' => 'تم تقييمها', 'count' => HeksBeneficiary::query()->count(), 'tone' => 'primary'],
            ['label' => 'تم اختيارها', 'count' => HeksBeneficiary::query()->where('is_selected', true)->count(), 'tone' => 'success'],
            ['label' => 'تم توزيعها', 'count' => HeksBeneficiary::query()->has('workAssignments')->count(), 'tone' => 'info'],
            ['label' => 'استلمت دفعة أولى', 'count' => HeksBeneficiary::query()->whereIn('payment_status', ['paid_30', 'paid_80', 'paid_100'])->count(), 'tone' => 'warning'],
            ['label' => 'تمت متابعتها', 'count' => HeksBeneficiary::query()->has('followUps')->count(), 'tone' => 'dark'],
            ['label' => 'اكتمل دفعها', 'count' => HeksBeneficiary::query()->where('payment_status', 'paid_100')->count(), 'tone' => 'success'],
        ];
    }

    /**
     * @param  EloquentCollection<int, HeksBeneficiary>  $beneficiaries
     * @return array<string, array{cases_count: int, contract_total: float}>
     */
    private function engineerWorkload(EloquentCollection $beneficiaries): array
    {
        return $beneficiaries
            ->flatMap(fn (HeksBeneficiary $beneficiary) => $beneficiary->workAssignments)
            ->filter(fn (HeksWorkAssignment $assignment): bool => filled($assignment->engineer_name))
            ->groupBy('engineer_name')
            ->map(fn ($assignments): array => [
                'cases_count' => $assignments->count(),
                'contract_total' => (float) $assignments->sum('contract_amount_ils'),
            ])
            ->sortByDesc('cases_count')
            ->take(8)
            ->all();
    }

    /**
     * @param  EloquentCollection<int, HeksBeneficiary>  $beneficiaries
     */
    private function distribution(EloquentCollection $beneficiaries, string $field): \Illuminate\Support\Collection
    {
        return $beneficiaries
            ->pluck($field)
            ->filter()
            ->countBy()
            ->sortDesc();
    }

    /**
     * @param  EloquentCollection<int, HeksBeneficiary>  $beneficiaries
     * @return array<string, int>
     */
    private function populationSummary(EloquentCollection $beneficiaries): array
    {
        return [
            'household_members' => $beneficiaries->sum(fn (HeksBeneficiary $beneficiary): int => $this->householdSize($beneficiary)),
            'female_heads' => $beneficiaries->filter(fn (HeksBeneficiary $beneficiary): bool => str_contains((string) $beneficiary->household_head_gender, 'أنث'))->count(),
            'lactating_women' => $beneficiaries->sum(fn (HeksBeneficiary $beneficiary): int => $this->rawInteger($beneficiary, ['عدد السيدات المرضعات'])),
            'disabled_people' => $beneficiaries->sum(fn (HeksBeneficiary $beneficiary): int => $this->rawInteger($beneficiary, ['calculation_PWD', 'أفراد من ذوي الإعاقة'])),
            'chronic_people' => $beneficiaries->sum(fn (HeksBeneficiary $beneficiary): int => $this->rawInteger($beneficiary, ['calculation_Sick', 'أفراد لديهم أمراض مزمنة'])),
            'war_injured_people' => $beneficiaries->sum(fn (HeksBeneficiary $beneficiary): int => $this->rawInteger($beneficiary, ['calculation_Inj', 'أفراد مصابين في الحرب'])),
        ];
    }

    private function matchesDashboardFlag(HeksBeneficiary $beneficiary, string $flag): bool
    {
        return match ($flag) {
            'has_disability' => $this->rawIsPositive($beneficiary, ['يوجد أشخاص ذوي إعاقة', 'calculation_PWD', 'أفراد من ذوي الإعاقة']),
            'war_injury' => $this->rawIsPositive($beneficiary, ['هل يوجد أفراد مصابين في الحرب', 'calculation_Inj', 'أفراد مصابين في الحرب']),
            'chronic_disease' => $this->rawIsPositive($beneficiary, ['يوجد بالأسرة أفراد لديهم أمراض مزمنة', 'calculation_Sick', 'أفراد لديهم أمراض مزمنة']),
            'uxo_risk' => $this->rawIsPositive($beneficiary, ['UXO', 'ERW', 'مخلفات حرب']),
            'unsafe_structure' => $this->rawIsPositive($beneficiary, ['غير آمنة إنشائيًا', 'غير آمنة انشائيا', 'آمنة من الناحية الهيكلية']),
            'documents_ready' => $this->rawIsPositive($beneficiary, ['أوراق ومستندات ثبوتية شخصية', 'أوراق ملكية', 'عقد ايجار']),
            'bank_account' => $this->rawIsPositive($beneficiary, ['حساب بنكي ساري المفعول']),
            default => false,
        };
    }

    private function householdSize(HeksBeneficiary $beneficiary): int
    {
        return $this->rawInteger($beneficiary, ['اجمالي عدد الأفراد بالوحدة السكنية', 'إجمالي عدد أفراد الأسرة الأساسية', 'Extended_Famil_family']);
    }

    /**
     * @param  EloquentCollection<int, HeksBeneficiary>  $beneficiaries
     * @param  array<int, string>  $keys
     */
    private function rawOptions(EloquentCollection $beneficiaries, array $keys): \Illuminate\Support\Collection
    {
        return $beneficiaries
            ->map(fn (HeksBeneficiary $beneficiary): string => $this->rawValue($beneficiary, $keys))
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }

    /**
     * @param  array<int, string>  $keys
     */
    private function rawMatches(HeksBeneficiary $beneficiary, array $keys, string $expected): bool
    {
        return $this->rawValue($beneficiary, $keys) === $expected;
    }

    /**
     * @param  array<int, string>  $keys
     */
    private function rawIsPositive(HeksBeneficiary $beneficiary, array $keys): bool
    {
        $value = $this->rawValue($beneficiary, $keys);

        if ($value === '') {
            return false;
        }

        $normalized = mb_strtolower($value);

        if (is_numeric(str_replace([',', ' '], '', $value))) {
            return (float) str_replace([',', ' '], '', $value) > 0;
        }

        return str_contains($normalized, 'نعم')
            || str_contains($normalized, 'يوجد')
            || str_contains($normalized, 'متوفر')
            || str_contains($normalized, 'ساري')
            || str_contains($normalized, 'yes');
    }

    /**
     * @param  array<int, string>  $keys
     */
    private function rawInteger(HeksBeneficiary $beneficiary, array $keys): int
    {
        $value = str_replace([',', ' '], '', $this->rawValue($beneficiary, $keys));

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @param  array<int, string>  $keys
     */
    private function rawValue(HeksBeneficiary $beneficiary, array $keys): string
    {
        $rawData = $beneficiary->raw_data;

        if (! is_array($rawData)) {
            return '';
        }

        $orderedRawData = collect($rawData)
            ->sortBy(fn (mixed $values, string|int $source): int => $source === 'Heks Final V1' ? 0 : 1)
            ->all();

        foreach ($orderedRawData as $values) {
            if (! is_array($values)) {
                continue;
            }

            foreach ($values as $heading => $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                foreach ($keys as $key) {
                    if (is_string($heading) && str_contains($this->normalizedDashboardText($heading), $this->normalizedDashboardText($key))) {
                        return trim((string) $value);
                    }
                }
            }
        }

        return '';
    }

    private function normalizedDashboardText(string $value): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

        return mb_strtolower($normalized);
    }

    private function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->hasRole('Database Officer'), 403);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function surveySections(HeksBeneficiary $beneficiary): array
    {
        $rawData = $beneficiary->raw_data;

        if (! is_array($rawData) || $rawData === []) {
            return [];
        }

        $sections = collect([
            'beneficiary' => [
                'title' => 'بيانات المستفيد والزيارة',
                'description' => 'الهوية، التواصل، الموقع، وتفاصيل الزيارة.',
                'tone' => 'primary',
                'items' => [],
            ],
            'shelter' => [
                'title' => 'تقييم المأوى والأضرار',
                'description' => 'حالة الوحدة السكنية، الضرر، الإشغال، والمرافق.',
                'tone' => 'warning',
                'items' => [],
            ],
            'social' => [
                'title' => 'الهشاشة الاجتماعية',
                'description' => 'الأسرة، العمر، الجنس، الإعاقة، المرض، والدخل.',
                'tone' => 'success',
                'items' => [],
            ],
            'protection' => [
                'title' => 'الحماية والظروف المعيشية',
                'description' => 'مؤشرات الخطر، الخصوصية، السلامة، والاحتياجات.',
                'tone' => 'danger',
                'items' => [],
            ],
            'management' => [
                'title' => 'الإدارة والتوصيات',
                'description' => 'المنحة، الدفعات، التوصيات، وملاحظات الفريق.',
                'tone' => 'info',
                'items' => [],
            ],
            'other' => [
                'title' => 'بيانات إضافية من الاستبيان',
                'description' => 'حقول مستوردة لا تنتمي مباشرة إلى الأقسام الرئيسية.',
                'tone' => 'secondary',
                'items' => [],
            ],
        ]);

        $seen = [];
        $histories = $beneficiary->surveyValueHistories
            ->groupBy(fn (HeksSurveyValueHistory $history): string => $history->source.'|'.$history->field_key);

        foreach ($rawData as $source => $values) {
            $values = is_array($values) ? $values : ['value' => $values];

            foreach ($values as $key => $value) {
                $key = (string) $key;

                if ($this->isHiddenSurveyKey($key) || $value === null || $value === '') {
                    continue;
                }

                $displayValue = $this->surveyDisplayValue($value);

                if ($displayValue === '') {
                    continue;
                }

                $uniqueKey = $key.'|'.$displayValue;

                if (isset($seen[$uniqueKey])) {
                    continue;
                }

                $seen[$uniqueKey] = true;
                $sectionKey = $this->surveySectionKey($key);
                $section = $sections->get($sectionKey);
                $section['items'][] = [
                    'question' => $key,
                    'value' => $displayValue,
                    'source' => (string) $source,
                    'history' => $histories->get((string) $source.'|'.$key, collect())
                        ->map(fn (HeksSurveyValueHistory $history): array => [
                            'old_value' => $history->old_value,
                            'new_value' => $history->new_value,
                            'user' => $history->user?->name,
                            'created_at' => $history->created_at?->format('Y-m-d H:i'),
                        ])
                        ->values()
                        ->all(),
                ];
                $sections->put($sectionKey, $section);
            }
        }

        return $sections
            ->filter(fn (array $section): bool => $section['items'] !== [])
            ->all();
    }

    private function surveySectionKey(string $key): string
    {
        $normalized = $this->normalizedDashboardText($key);

        return match (true) {
            str_contains($normalized, 'اسم')
                || str_contains($normalized, 'الكود')
                || str_contains($normalized, 'رقم')
                || str_contains($normalized, 'هوية')
                || str_contains($normalized, 'جوال')
                || str_contains($normalized, 'هاتف')
                || str_contains($normalized, 'تاريخ')
                || str_contains($normalized, 'محافظة')
                || str_contains($normalized, 'منطقة')
                || str_contains($normalized, 'عنوان')
                || str_contains($normalized, 'مهندس') => 'beneficiary',
            str_contains($normalized, 'مأوى')
                || str_contains($normalized, 'المأوى')
                || str_contains($normalized, 'سكن')
                || str_contains($normalized, 'السكنية')
                || str_contains($normalized, 'ضرر')
                || str_contains($normalized, 'السقف')
                || str_contains($normalized, 'جدران')
                || str_contains($normalized, 'حمام')
                || str_contains($normalized, 'مطبخ')
                || str_contains($normalized, 'باب')
                || str_contains($normalized, 'نافذة')
                || str_contains($normalized, 'مياه')
                || str_contains($normalized, 'صرف')
                || str_contains($normalized, 'إنارة')
                || str_contains($normalized, 'اشغال')
                || str_contains($normalized, 'إشغال') => 'shelter',
            str_contains($normalized, 'أسرة')
                || str_contains($normalized, 'الأسرة')
                || str_contains($normalized, 'افراد')
                || str_contains($normalized, 'أفراد')
                || str_contains($normalized, 'عمر')
                || str_contains($normalized, 'جنس')
                || str_contains($normalized, 'إعاقة')
                || str_contains($normalized, 'اعاقة')
                || str_contains($normalized, 'مرض')
                || str_contains($normalized, 'مزمن')
                || str_contains($normalized, 'دخل')
                || str_contains($normalized, 'غذائية')
                || str_contains($normalized, 'مرضعات')
                || str_contains($normalized, 'حوامل')
                || str_contains($normalized, 'نازح') => 'social',
            str_contains($normalized, 'خطر')
                || str_contains($normalized, 'آمن')
                || str_contains($normalized, 'امن')
                || str_contains($normalized, 'uxo')
                || str_contains($normalized, 'erw')
                || str_contains($normalized, 'مخلفات')
                || str_contains($normalized, 'خصوصية')
                || str_contains($normalized, 'فصل')
                || str_contains($normalized, 'سلامة')
                || str_contains($normalized, 'حماية') => 'protection',
            str_contains($normalized, 'منحة')
                || str_contains($normalized, 'دفعة')
                || str_contains($normalized, 'توصية')
                || str_contains($normalized, 'ملاحظ')
                || str_contains($normalized, 'عقد')
                || str_contains($normalized, 'حساب') => 'management',
            default => 'other',
        };
    }

    private function surveyDisplayValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'نعم' : 'لا';
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    private function isHiddenSurveyKey(string $key): bool
    {
        if (in_array($key, ['_submission__uuid', '_submission___version__'], true)) {
            return false;
        }

        return str_starts_with($key, '_')
            || str_contains($key, 'uuid')
            || str_contains($key, 'index')
            || str_contains($key, 'parent_table_name')
            || str_contains($key, 'parent_index');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function rawDataSections(HeksBeneficiary $beneficiary): array
    {
        $rawData = $beneficiary->raw_data;

        if (! is_array($rawData) || $rawData === []) {
            return [];
        }

        $sections = [];

        foreach ($rawData as $source => $values) {
            if (is_array($values)) {
                $filtered = array_filter($values, fn (mixed $value): bool => $value !== null && $value !== '');

                if ($filtered !== []) {
                    $sections[(string) $source] = $filtered;
                }
            }
        }

        if ($sections === []) {
            $sections['Imported data'] = array_filter($rawData, fn (mixed $value): bool => $value !== null && $value !== '');
        }

        return $sections;
    }

    private function imageAttachments(HeksBeneficiary $beneficiary): \Illuminate\Support\Collection
    {
        return $beneficiary->attachments
            ->filter(function (HeksAttachment $attachment): bool {
                $filename = mb_strtolower((string) $attachment->filename);
                $url = mb_strtolower((string) $attachment->url);
                $type = mb_strtolower((string) $attachment->attachment_type);

                return preg_match('/\.(jpg|jpeg|png|webp|gif)(\?|$)/i', $filename) === 1
                    || preg_match('/\.(jpg|jpeg|png|webp|gif)(\?|$)/i', $url) === 1
                    || str_contains($type, 'image')
                    || str_contains($type, 'photo')
                    || str_contains($type, 'صورة')
                    || str_contains($type, 'صور');
            })
            ->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{category: ?string, indicator: ?string, question: ?string, weight: mixed, max: mixed, avg: mixed, min: mixed, value: mixed, score: mixed, source: string}>
     */
    private function technicalAssessmentRows(HeksBeneficiary $beneficiary): \Illuminate\Support\Collection
    {
        $labels = $beneficiary->labels->keyBy('label_key');
        $rawData = collect($beneficiary->raw_data ?? [])
            ->filter(fn (mixed $section): bool => is_array($section))
            ->flatMap(fn (array $section): array => $section);

        return HeksScoringWeight::query()
            ->where('source', 'Shelter Technical Weights')
            ->orderBy('id')
            ->get()
            ->map(function (HeksScoringWeight $weight) use ($labels, $rawData): array {
                $weightRawData = is_array($weight->raw_data) ? $weight->raw_data : [];
                $category = $weight->category ?: ($weightRawData['Category'] ?? $weightRawData['column_1'] ?? null);
                $indicator = $weight->indicator ?: ($weightRawData['Indicator'] ?? $weightRawData['column_2'] ?? null);
                $question = $weight->question_key ?: ($weightRawData['Question'] ?? $weightRawData['column_8'] ?? $indicator);
                $label = $question ? $labels->get($question) : null;
                $rawValue = $question ? $rawData->get($question) : null;

                return [
                    'category' => $category,
                    'indicator' => $indicator,
                    'question' => $question,
                    'weight' => $weight->weight,
                    'max' => $weightRawData['Max'] ?? $weightRawData['column_4'] ?? null,
                    'avg' => $weightRawData['AVG'] ?? $weightRawData['column_5'] ?? null,
                    'min' => $weightRawData['Min'] ?? $weightRawData['column_6'] ?? null,
                    'value' => $label?->label_value ?? $rawValue,
                    'score' => $label?->label_value ?? $rawValue,
                    'source' => $label ? (string) $label->source : ($rawValue !== null ? 'raw_data' : ''),
                ];
            });
    }

    private function socialAssessmentRows(HeksBeneficiary $beneficiary): \Illuminate\Support\Collection
    {
        $labels = $beneficiary->labels->keyBy('label_key');
        $rawData = collect($beneficiary->raw_data ?? [])
            ->filter(fn (mixed $section): bool => is_array($section))
            ->flatMap(fn (array $section): array => $section);
        $matrixRows = collect($this->socialVulnerabilityMatrix())
            ->map(function (array $criterion) use ($beneficiary): array {
                $value = $this->rawValue($beneficiary, $criterion['keys']);
                $isYes = $value !== '' && $this->isPositiveSocialValue($value, $criterion['positive_terms']);

                return [
                    'factor' => $criterion['factor'],
                    'question' => $criterion['factor'],
                    'value' => $value,
                    'options' => 'No = 0 / Yes = 5',
                    'points' => $value === '' ? null : ($isYes ? 5 : 0),
                    'source' => $value !== '' ? 'Scoring matrix S -2' : '',
                ];
            });

        $importedRows = HeksScoringWeight::query()
            ->where('source', 'S-V')
            ->whereNotNull('question_key')
            ->orderBy('id')
            ->get()
            ->groupBy('question_key')
            ->map(function (\Illuminate\Support\Collection $weights, string $question) use ($labels, $rawData): array {
                $label = $labels->get($question);
                $rawValue = $rawData->get($question);

                return [
                    'factor' => $question,
                    'question' => $question,
                    'value' => $label?->label_value ?? $rawValue,
                    'options' => $weights->pluck('option_value')->filter()->unique()->values()->implode(' / '),
                    'points' => null,
                    'source' => $label ? (string) $label->source : ($rawValue !== null ? 'raw_data' : ''),
                ];
            })
            ->values();

        return $matrixRows
            ->concat($importedRows->reject(fn (array $row): bool => $matrixRows->pluck('factor')->contains($row['factor'])))
            ->values();
    }

    private function isPositiveSocialValue(string $value, array $positiveTerms): bool
    {
        $normalized = $this->normalizedDashboardText($value);

        if (is_numeric(str_replace([',', ' '], '', $value))) {
            return (float) str_replace([',', ' '], '', $value) > 0;
        }

        foreach ($positiveTerms as $term) {
            if (str_contains($normalized, $this->normalizedDashboardText($term))) {
                return true;
            }
        }

        return str_contains($normalized, 'yes')
            || str_contains($normalized, 'نعم')
            || str_contains($normalized, 'يوجد');
    }

    private function scoringComponents(): array
    {
        return [
            ['component' => 'Technical vulnerability', 'weight' => '70%', 'max_points' => 70],
            ['component' => 'Social vulnerability', 'weight' => '30%', 'max_points' => 30],
            ['component' => 'Total', 'weight' => '100%', 'max_points' => 100],
        ];
    }

    private function priorityMatrix(): array
    {
        return [
            ['score' => '80 - 100', 'priority' => 'Extreme', 'intervention' => 'urgent support (repair/seal, bathroom, kitchen)'],
            ['score' => '65 - 79', 'priority' => 'Very High', 'intervention' => 'high priority'],
            ['score' => '50 - 64', 'priority' => 'High', 'intervention' => 'medium priority'],
            ['score' => '35 - 49', 'priority' => 'Moderate', 'intervention' => 'lower priority'],
            ['score' => '<35', 'priority' => 'Low', 'intervention' => 'may not need intervention'],
        ];
    }

    private function socialVulnerabilityMatrix(): array
    {
        return [
            ['factor' => 'Female-headed household', 'keys' => ['جنس رب الأسرة', 'household head gender', 'female-headed'], 'positive_terms' => ['أنث', 'female', 'woman']],
            ['factor' => 'Household head age >60 or <18', 'keys' => ['العمر', 'عمر رب الأسرة', 'household head age', 'age of household head'], 'positive_terms' => ['أقل من 17', 'أكبر من 60', '>60', '<18']],
            ['factor' => 'At least one chronic health condition', 'keys' => ['يوجد بالأسرة أفراد لديهم أمراض مزمنة', 'هل يعاني من معيل الأسرة من مرض مزمن', 'مرض مزمن', 'أمراض مزمنة', 'chronic'], 'positive_terms' => ['نعم', 'يوجد', 'yes']],
            ['factor' => 'Survivor of violence', 'keys' => ['ناجي من العنف', 'survivor of violence', 'violence'], 'positive_terms' => ['نعم', 'يوجد', 'yes']],
            ['factor' => 'Disability present in household', 'keys' => ['يوجد أشخاص ذوي إعاقة', 'إعاقة', 'ذوي إعاقة', 'disability', 'PWD'], 'positive_terms' => ['نعم', 'يوجد', 'yes']],
            ['factor' => 'Single-parent household (Divorced, Separated, widowed, abandoned)', 'keys' => ['الحالة الاجتماعية', 'single parent', 'divorced', 'widowed', 'separated'], 'positive_terms' => ['مطلق', 'منفصل', 'أرمل', 'مهجور', 'divorced', 'separated', 'widowed', 'abandoned']],
            ['factor' => 'No adult able to support repairs', 'keys' => ['هل يوجد شخص بالغ واحد على الأقل في المنزل يمكنه المساعدة في الإصلاح أو الصيانة', 'لا يوجد بالغ', 'قادر على دعم الإصلاح', 'adult able to support repairs'], 'positive_terms' => ['لا', 'لا يوجد', 'no adult', 'not able']],
            ['factor' => 'Household can organize repairs themselves if given cash', 'keys' => ['تستطيع الأسرة تنظيم أعمال الصيانة بنفسها إذا مُنحت مبلغاً نقدياً', 'تنظيم الإصلاح', 'تنفيذ الإصلاح', 'organize repairs', 'given cash'], 'positive_terms' => ['نعم', 'قادر', 'yes', 'can']],
            ['factor' => 'Availability of valid proof of ownership/lease/hosting agreement', 'keys' => ['يتوفر إثبات ساري المفعول للملكية/الإيجار/اتفاقية الاستضافة', 'إثبات ملكية', 'عقد إيجار', 'عقد ايجار', 'استضافة', 'ownership', 'lease', 'hosting agreement'], 'positive_terms' => ['نعم', 'متوفر', 'ساري', 'yes', 'valid']],
            ['factor' => 'Children <18 present', 'keys' => ['هل يوجد أطفال أقل من 17 سنة بالأسرة', 'أطفال', 'أقل من 18', 'children', '<18'], 'positive_terms' => ['نعم', 'يوجد', 'yes']],
            ['factor' => 'Presence of pregnant/lactating women in the housing unit', 'keys' => ['يوجد نساء حوامل أو مرضعات', 'حامل', 'مرضعة', 'pregnant', 'lactating'], 'positive_terms' => ['نعم', 'يوجد', 'yes']],
            ['factor' => 'Current housing type, shared unit with more than one family', 'keys' => ['نوع المساحة المستخدمة', 'أسر ممتدة متواجدة بنفس الوحدة السكنية', 'سكن مشترك', 'أكثر من أسرة', 'shared unit', 'more than one family'], 'positive_terms' => ['مشتركة', 'مشترك', 'أكثر من أسرة', 'shared', 'yes', 'نعم']],
            ['factor' => 'No Continuous secured income, heavily depend on Food aid', 'keys' => ['هل تمتلك الأسرة مصدر دخل ثابت أو منتظم', 'هل تعتمد الأسرة في توفير الطعام على التكية و/أو المساعدات الغذائية', 'دخل ثابت', 'مساعدات غذائية', 'secured income', 'food aid'], 'positive_terms' => ['لا', 'بشكل كامل', 'بشكل جزئي', 'يعتمد', 'food aid', 'no income']],
            ['factor' => 'No or insufficient condition of furniture', 'keys' => ['هل يتوفر لدى الأسرة الاحتياجات الأساسية للمعيشة مثل مواد الفراش وأدوات المطبخ', 'الأثاث', 'حالة الأثاث', 'furniture'], 'positive_terms' => ['غير كاف', 'لا', 'سيئ', 'insufficient', 'poor', 'no']],
        ];
    }
}
