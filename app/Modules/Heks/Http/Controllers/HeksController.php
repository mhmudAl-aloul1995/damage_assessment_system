<?php

namespace App\Modules\Heks\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Heks\Http\Requests\ImportHeksBoqItemsRequest;
use App\Modules\Heks\Http\Requests\ImportHeksSpreadsheetRequest;
use App\Modules\Heks\Http\Requests\StoreHeksBoqCatalogItemRequest;
use App\Modules\Heks\Http\Requests\StoreHeksBoqItemRequest;
use App\Modules\Heks\Http\Requests\UpdateHeksBeneficiaryRequest;
use App\Modules\Heks\Http\Requests\UpdateHeksBoqCatalogItemRequest;
use App\Modules\Heks\Http\Requests\UpdateHeksBoqItemRequest;
use App\Modules\Heks\Http\Requests\UpdateHeksBoqPricingRequest;
use App\Modules\Heks\Http\Requests\UpdateHeksFollowUpRequest;
use App\Modules\Heks\Http\Requests\UpdateHeksLabelRequest;
use App\Modules\Heks\Http\Requests\UpdateHeksScoreRequest;
use App\Modules\Heks\Http\Requests\UpdateHeksSurveyValueRequest;
use App\Modules\Heks\Models\HeksAttachment;
use App\Modules\Heks\Models\HeksBeneficiary;
use App\Modules\Heks\Models\HeksBoqCatalogItem;
use App\Modules\Heks\Models\HeksBoqItem;
use App\Modules\Heks\Models\HeksFollowUp;
use App\Modules\Heks\Models\HeksImport;
use App\Modules\Heks\Models\HeksKoboFieldMapping;
use App\Modules\Heks\Models\HeksLabel;
use App\Modules\Heks\Models\HeksPayment;
use App\Modules\Heks\Models\HeksScore;
use App\Modules\Heks\Models\HeksScoringWeight;
use App\Modules\Heks\Models\HeksSurveyValueHistory;
use App\Modules\Heks\Models\HeksWorkAssignment;
use App\Modules\Heks\Services\HeksEngineerUserResolver;
use App\Modules\Heks\Services\HeksKoboValueDisplayService;
use App\Modules\Heks\Services\HeksSpreadsheetImportService;
use App\Support\BrowsershotConfiguration;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class HeksController extends Controller
{
    private const SURVEY_VALUE_EDITING_ENABLED = false;

    public function dashboard(Request $request): View
    {
        $this->authorizeAccess();
        $beneficiaries = HeksBeneficiary::query()
            ->select([
                'id',
                'code',
                'name',
                'identity_number',
                'phone',
                'field_engineer',
                'visit_date',
                'governorate',
                'area',
                'address',
                'household_head_gender',
                'marital_status',
                'displacement_status',
                'occupancy_status',
                'damage_status',
                'grant_amount',
                'payment_1',
                'payment_2',
                'payment_3',
                'recommendations',
                'is_selected',
                'selection_status',
                'payment_status',
                'work_group_source',
                'created_at',
                'updated_at',
            ])
            ->withCount(['labels', 'followUps', 'scores', 'payments', 'workAssignments', 'attachments'])
            ->with([
                'scores' => fn ($query) => $query->latest(),
                'payments',
                'followUps',
                'attachments',
                'workAssignments.engineerUser:id,name,name_en,username_arcgis',
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
        $result = $importer->import(
            $request->file('file'),
            (string) $request->validated('type'),
            $request->user()?->id,
            false,
            $request->file('labels_file')
        );

        return redirect()
            ->route('heks.imports')
            ->with('success', "تم الاستيراد: {$result['summary']['created_rows']} جديد، {$result['summary']['updated_rows']} تحديث، {$result['summary']['skipped_rows']} متجاوز. تم حفظ روابط BOQ للمتابعات دون تنزيل جماعي لتجنب انقطاع الطلب.");
    }

    public function beneficiaries(Request $request): View
    {
        $this->authorizeAccess();

        $beneficiaries = HeksBeneficiary::query()
            ->with('fieldEngineerUser:id,name,name_en,username_arcgis')
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
            ->when($request->filled('engineer'), function ($query) use ($request): void {
                $engineer = (string) $request->string('engineer');

                if (ctype_digit($engineer)) {
                    $query->where('field_engineer_user_id', (int) $engineer);

                    return;
                }

                $query->where('field_engineer', $engineer);
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('heks::beneficiaries', [
            'beneficiaries' => $beneficiaries,
            'engineers' => $this->beneficiaryEngineerOptions(),
        ]);
    }

    public function edit(HeksBeneficiary $beneficiary, HeksKoboValueDisplayService $displayService): View
    {
        $this->authorizeAccess();
        $beneficiary->load([
            'labels' => fn ($query) => $query->latest(),
            'fieldEngineerUser:id,name,name_en,username_arcgis',
            'followUps' => fn ($query) => $query->with(['boqItems', 'engineerUser:id,name,name_en,username_arcgis'])->latest('visit_date')->latest(),
            'scores' => fn ($query) => $query->latest(),
            'payments' => fn ($query) => $query->latest(),
            'workAssignments' => fn ($query) => $query->with('engineerUser:id,name,name_en,username_arcgis')->latest(),
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
            'damageStatusDisplay' => $this->beneficiaryDamageStatusDisplay($beneficiary, $displayService),
            'basicDisplay' => $this->beneficiaryBasicDisplayValues($beneficiary, $displayService),
            'rawDataSections' => $this->rawDataSections($beneficiary),
            'surveySections' => $this->surveySections($beneficiary, $displayService),
            'imageAttachments' => $this->imageAttachments($beneficiary),
            'scoringComponents' => $this->scoringComponents(),
            'priorityMatrix' => $this->priorityMatrix(),
            'socialAssessmentRows' => $this->socialAssessmentRows($beneficiary),
            'technicalAssessmentRows' => $this->technicalAssessmentRows($beneficiary, $displayService),
        ]);
    }

    public function exportSurveyPdf(HeksBeneficiary $beneficiary, HeksKoboValueDisplayService $displayService): PdfBuilder
    {
        $this->authorizeAccess();
        $beneficiary->load([
            'fieldEngineerUser:id,name,name_en,username_arcgis',
            'surveyValueHistories' => fn ($query) => $query->with('user')->latest(),
        ]);

        $filename = 'heks-survey-'.preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($beneficiary->code ?: $beneficiary->id)).'.pdf';

        return Pdf::view('heks::pdf.beneficiary-survey', [
            'beneficiary' => $beneficiary,
            'surveySections' => $this->surveySections($beneficiary, $displayService),
            'generatedAt' => now()->format('Y-m-d H:i'),
        ])
            ->format('a4')
            ->name($filename)
            ->withBrowsershot(function (Browsershot $browsershot): void {
                app(BrowsershotConfiguration::class)->apply($browsershot);
            });
    }

    private function beneficiaryDamageStatusDisplay(HeksBeneficiary $beneficiary, HeksKoboValueDisplayService $displayService): string
    {
        $rawValue = trim((string) ($beneficiary->damage_status ?? ''));

        if ($rawValue === '') {
            return '-';
        }

        foreach (['q_059', 'Damage status', 'Damage assessment', 'تقييم حالة ضرر المأوى', 'تقييم حالة ضرر المأوى:'] as $fieldKey) {
            $resolved = $displayService->resolve('heks-main', $fieldKey, $rawValue);
            $display = trim((string) ($resolved['display'] ?? ''));

            if (($resolved['resolved'] ?? false) && $display !== '') {
                return $display;
            }
        }

        return $rawValue;
    }

    /**
     * @return array<string, string>
     */
    private function beneficiaryBasicDisplayValues(HeksBeneficiary $beneficiary, HeksKoboValueDisplayService $displayService): array
    {
        $fields = [
            'field_engineer' => ['identification/q_087', 'q_087', 'اسم المهندس الميداني'],
            'governorate' => ['governorate', 'المحافظة'],
            'area' => ['area_001', 'area', 'community', 'المنطقة', 'التجمع/ المنطقة', 'التجمع'],
            'displacement_status' => ['displacement_status', 'حالة النزوح حاليا للأسرة', 'حالة النزوح'],
            'occupancy_status' => ['occupancy_type', 'occupancy_status', 'حالة الإشغال الحالي للوحدة السكنية', 'نوع الإشغال الحالي:', 'حالة الإشغال'],
            'damage_status' => ['q_059', 'damage_status', 'Damage assessment', 'تقييم حالة ضرر المأوى', 'تقييم حالة ضرر المأوى:', 'حالة الضرر'],
            'recommendations' => ['recommendations', 'final_recommendation', 'توصيات نهائية', 'التوصيات'],
        ];

        return collect($fields)
            ->mapWithKeys(fn (array $candidates, string $field): array => [
                $field => $this->beneficiaryBasicDisplayValue($beneficiary, $displayService, $field, $candidates),
            ])
            ->all();
    }

    /**
     * @param  array<int, string>  $candidates
     */
    private function beneficiaryBasicDisplayValue(HeksBeneficiary $beneficiary, HeksKoboValueDisplayService $displayService, string $field, array $candidates): string
    {
        $rawValue = trim((string) ($beneficiary->{$field} ?? ''));

        if ($rawValue === '') {
            return '';
        }

        foreach ($this->beneficiaryRawDataSources($beneficiary) as $source) {
            foreach ($candidates as $candidate) {
                $resolved = $displayService->resolve($source, $candidate, $rawValue);
                $display = trim((string) ($resolved['display'] ?? ''));

                if (($resolved['resolved'] ?? false) && $display !== '') {
                    return $display;
                }
            }
        }

        foreach ($this->beneficiaryRawDataValuesMatching($beneficiary, $rawValue) as $match) {
            $resolved = $displayService->resolve($match['source'], $match['field'], $rawValue);
            $display = trim((string) ($resolved['display'] ?? ''));

            if (($resolved['resolved'] ?? false) && $display !== '') {
                return $display;
            }
        }

        return $rawValue;
    }

    /**
     * @return array<int, string>
     */
    private function beneficiaryRawDataSources(HeksBeneficiary $beneficiary): array
    {
        $rawData = $beneficiary->raw_data;

        if (! is_array($rawData)) {
            return ['heks-main'];
        }

        return collect(array_keys($rawData))
            ->filter(fn (string|int $source): bool => is_string($source) && $source !== '')
            ->push('heks-main')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{source: string, field: string}>
     */
    private function beneficiaryRawDataValuesMatching(HeksBeneficiary $beneficiary, string $rawValue): array
    {
        $rawData = $beneficiary->raw_data;

        if (! is_array($rawData)) {
            return [];
        }

        $matches = [];

        foreach ($rawData as $source => $values) {
            if (! is_string($source) || ! is_array($values)) {
                continue;
            }

            foreach ($this->flattenSurveyValues($values) as $field => $value) {
                if (! is_scalar($value) || trim((string) $value) !== $rawValue) {
                    continue;
                }

                $matches[] = ['source' => $source, 'field' => (string) $field];
            }
        }

        return $matches;
    }

    public function attachment(HeksBeneficiary $beneficiary, HeksAttachment $attachment): Response
    {
        $this->authorizeAccess();
        abort_unless((int) $attachment->heks_beneficiary_id === (int) $beneficiary->id, SymfonyResponse::HTTP_NOT_FOUND);

        $url = $this->attachmentUrl($attachment);
        abort_unless($url !== '', SymfonyResponse::HTTP_NOT_FOUND, 'Attachment URL is not available.');

        $request = Http::timeout((int) config('services.kobotoolbox.timeout', 60))
            ->accept('*/*');

        if ($this->isKoboAttachmentUrl($url)) {
            $token = (string) config('services.kobotoolbox.token', '');
            abort_if($token === '', SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY, 'KoboToolbox token is not configured.');

            $request = $request->withHeaders([
                'Authorization' => 'Token '.$token,
            ]);
        }

        $response = $request->get($url);
        abort_unless($response->successful(), SymfonyResponse::HTTP_NOT_FOUND, 'Attachment could not be downloaded.');

        $contentType = $response->header('Content-Type') ?: 'application/octet-stream';
        abort_if(str_contains($contentType, 'application/json'), SymfonyResponse::HTTP_NOT_FOUND, 'Attachment response is not a file.');

        return response($response->body(), SymfonyResponse::HTTP_OK, [
            'Cache-Control' => 'private, max-age=3600',
            'Content-Disposition' => 'inline; filename="'.$this->attachmentFilename($attachment).'"',
            'Content-Type' => $contentType,
        ]);
    }

    public function update(UpdateHeksBeneficiaryRequest $request, HeksBeneficiary $beneficiary, HeksEngineerUserResolver $engineerUserResolver): RedirectResponse
    {
        $data = $request->validated();

        if (array_key_exists('field_engineer', $data)) {
            $data['field_engineer_user_id'] = $engineerUserResolver->resolve($data['field_engineer']);
        }

        $beneficiary->update($data);

        return back()->with('success', 'تم تحديث بيانات المستفيد.');
    }

    public function updateSurveyValue(UpdateHeksSurveyValueRequest $request, HeksBeneficiary $beneficiary, HeksKoboValueDisplayService $displayService): RedirectResponse
    {
        $this->authorizeAccess();
        abort_unless(self::SURVEY_VALUE_EDITING_ENABLED, 403);

        $data = $request->validated();
        $rawData = $beneficiary->raw_data ?? [];
        $source = $data['source'];
        $fieldKey = $data['field_key'];
        $newValue = $data['value'] ?? null;

        abort_unless(is_array($rawData) && array_key_exists($source, $rawData) && is_array($rawData[$source]) && array_key_exists($fieldKey, $rawData[$source]), 404);

        $oldValue = $this->surveyDisplayValue($rawData[$source][$fieldKey] ?? null);
        $fieldType = (string) ($data['field_type'] ?? '');
        $newValue = $displayService->rawValueForStorage($newValue, $fieldType);

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

    public function pricingCatalog(): View
    {
        $this->authorizeAccess();
        $this->ensureBoqCatalogSeeded();

        $items = HeksBoqCatalogItem::query()
            ->orderBy('sort_order')
            ->orderBy('section')
            ->orderBy('item_code')
            ->get();

        return view('heks::pricing-catalog', [
            'items' => $items,
            'sections' => $items->pluck('section')->filter()->unique()->sort()->values(),
            'units' => $items->pluck('unit')->filter()->unique()->sort()->values(),
            'activeCount' => $items->where('is_active', true)->count(),
            'totalItems' => $items->count(),
        ]);
    }

    public function storePricingCatalogItem(StoreHeksBoqCatalogItemRequest $request): RedirectResponse
    {
        HeksBoqCatalogItem::query()->create($this->catalogPayload($request->validated()));

        return back()->with('success', 'تمت إضافة بند إلى جدول التسعير.');
    }

    public function updatePricingCatalogItem(UpdateHeksBoqCatalogItemRequest $request, HeksBoqCatalogItem $catalogItem): RedirectResponse
    {
        $catalogItem->update($this->catalogPayload($request->validated()));

        return back()->with('success', 'تم تحديث بند جدول التسعير.');
    }

    public function destroyPricingCatalogItem(HeksBoqCatalogItem $catalogItem): RedirectResponse
    {
        $this->authorizeAccess();
        $catalogItem->delete();

        return back()->with('success', 'تم حذف بند من جدول التسعير.');
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
                'engineerUser:id,name,name_en,username_arcgis',
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
            ->when($request->filled('engineer'), function ($query) use ($request): void {
                $engineer = (string) $request->string('engineer');

                if (ctype_digit($engineer)) {
                    $query->where('engineer_user_id', (int) $engineer);

                    return;
                }

                $query->where('engineer_name', $engineer);
            })
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
            'engineers' => $this->followUpEngineerOptions(),
            'visitNumbers' => HeksFollowUp::query()
                ->whereNotNull('visit_number')
                ->where('visit_number', '<>', '')
                ->distinct()
                ->orderBy('visit_number')
                ->pluck('visit_number'),
        ]);
    }

    public function updateFollowUp(UpdateHeksFollowUpRequest $request, HeksFollowUp $followUp, HeksEngineerUserResolver $engineerUserResolver): RedirectResponse
    {
        $data = $request->validated();

        if (array_key_exists('engineer_name', $data)) {
            $data['engineer_user_id'] = $engineerUserResolver->resolve($data['engineer_name']);
        }

        $followUp->update($data);

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
        $this->ensureBoqCatalogSeeded();

        return HeksBoqCatalogItem::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('section')
            ->orderBy('item_code')
            ->get()
            ->map(fn (HeksBoqCatalogItem $item): array => [
                'section' => (string) $item->section,
                'item_code' => (string) $item->item_code,
                'description' => (string) $item->description,
                'unit' => (string) $item->unit,
                'unit_price_ils' => (float) $item->unit_price_ils,
            ])
            ->values();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function catalogPayload(array $data): array
    {
        return [
            'section' => $data['section'] ?? null,
            'item_code' => $data['item_code'] ?? null,
            'description' => (string) $data['description'],
            'unit' => $data['unit'] ?? null,
            'unit_price_ils' => (float) ($data['unit_price_ils'] ?? 0),
            'notes' => $data['notes'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    private function ensureBoqCatalogSeeded(): void
    {
        if (HeksBoqCatalogItem::query()->exists()) {
            return;
        }

        foreach ($this->defaultBoqCatalogItems() as $index => $item) {
            HeksBoqCatalogItem::query()->create([
                ...$item,
                'is_active' => true,
                'sort_order' => $index + 1,
            ]);
        }
    }

    private function defaultBoqCatalogItems(): \Illuminate\Support\Collection
    {
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

    private function beneficiaryEngineerOptions(): EloquentCollection
    {
        $userIds = HeksBeneficiary::query()
            ->whereNotNull('field_engineer_user_id')
            ->distinct()
            ->pluck('field_engineer_user_id');

        return User::query()
            ->whereIn('id', $userIds)
            ->orderBy('name')
            ->get(['id', 'name', 'name_en', 'username_arcgis']);
    }

    private function followUpEngineerOptions(): EloquentCollection
    {
        $userIds = HeksFollowUp::query()
            ->whereNotNull('engineer_user_id')
            ->distinct()
            ->pluck('engineer_user_id');

        return User::query()
            ->whereIn('id', $userIds)
            ->orderBy('name')
            ->get(['id', 'name', 'name_en', 'username_arcgis']);
    }

    /**
     * @param  EloquentCollection<int, HeksBeneficiary>  $beneficiaries
     * @return array<string, array{cases_count: int, contract_total: float}>
     */
    private function engineerWorkload(EloquentCollection $beneficiaries): array
    {
        return $beneficiaries
            ->flatMap(fn (HeksBeneficiary $beneficiary) => $beneficiary->workAssignments)
            ->filter(fn (HeksWorkAssignment $assignment): bool => filled($assignment->engineer_name) || filled($assignment->engineerUser?->name))
            ->groupBy(fn (HeksWorkAssignment $assignment): string => $assignment->engineerUser?->name ?? (string) $assignment->engineer_name)
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

    private function attachmentUrl(HeksAttachment $attachment): string
    {
        $value = trim((string) ($attachment->url ?: $attachment->filename));

        if ($value === '') {
            return '';
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        $mediaFile = ltrim(str_replace('\\', '/', $value), '/');

        return 'https://kc.kobotoolbox.org/media/original?'.http_build_query([
            'media_file' => $mediaFile,
        ]);
    }

    private function isKoboAttachmentUrl(string $url): bool
    {
        return str_contains($url, 'kobotoolbox.org/api/')
            || str_contains($url, 'kc.kobotoolbox.org/media/');
    }

    private function attachmentFilename(HeksAttachment $attachment): string
    {
        $filename = basename(str_replace('\\', '/', (string) ($attachment->filename ?: parse_url((string) $attachment->url, PHP_URL_PATH))));

        return $filename !== '' ? $filename : 'heks-attachment';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function surveySections(HeksBeneficiary $beneficiary, HeksKoboValueDisplayService $displayService): array
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
        $displayLabels = $this->surveyDisplayLabels(array_keys($rawData));
        $choiceLabels = $this->surveyChoiceLabels(array_keys($rawData));
        $sortOrders = $this->surveySortOrders(array_keys($rawData));
        $templateMappings = $this->surveyTemplateMappings(array_keys($rawData));
        $seenQuestionLabels = [];
        $histories = $beneficiary->surveyValueHistories
            ->groupBy(fn (HeksSurveyValueHistory $history): string => $history->source.'|'.$history->field_key);

        foreach ($rawData as $source => $values) {
            $originalValues = is_array($values) ? $values : ['value' => $values];
            $values = $this->flattenSurveyValues($originalValues);
            $templateItems = $templateMappings[(string) $source] ?? [];

            foreach ($templateItems as $templateItem) {
                $key = (string) $templateItem['field_key'];
                $question = (string) $templateItem['question'];

                if ($this->isHiddenSurveyKey($key)) {
                    continue;
                }

                [$valueKey, $value] = $this->surveyTemplateValue($beneficiary, $values, $key, $question);

                if ($this->shouldHideUnansweredOtherSpecifyField($key, $question, $value)) {
                    $this->markSurveySeenKeys($seen, (string) $source, $key, $valueKey);

                    continue;
                }

                if ($this->isTechnicalSurveyPlaceholderValue($value, $key)) {
                    $this->markSurveySeenKeys($seen, (string) $source, $key, $valueKey);
                    $seenQuestionLabels[$this->surveyQuestionSignature($question)] = true;

                    continue;
                }

                $resolvedValue = $displayService->resolve((string) $source, $key, $value);
                $usesChoices = $this->usesSurveyChoiceDisplay($resolvedValue['type']);
                $displayValue = $resolvedValue['resolved']
                    ? $resolvedValue['display']
                    : $this->surveyDisplayValue(
                        $value,
                        $usesChoices ? $key : null,
                        $usesChoices ? (string) $source : null,
                        $choiceLabels
                    );
                $uniqueKey = (string) $source.'|'.$key;

                if (isset($seen[$uniqueKey])) {
                    continue;
                }

                $this->markSurveySeenKeys($seen, (string) $source, $key, $valueKey);
                $seenQuestionLabels[$this->surveyQuestionSignature($question)] = true;
                $sectionKey = $this->surveySectionKey($key);
                $section = $sections->get($sectionKey) ?? $this->koboSurveySection($sectionKey);
                $section['items'][] = [
                    'question' => $question,
                    'field_key' => $key,
                    'value' => $displayValue,
                    'raw_value' => $this->surveyDisplayValue($value),
                    'field_type' => $resolvedValue['type'],
                    'warning' => $resolvedValue['warning'],
                    'choices' => $usesChoices
                        ? ($resolvedValue['choices'] !== []
                            ? $resolvedValue['choices']
                            : $this->surveyChoiceOptions($value, $key, (string) $source, $choiceLabels))
                        : [],
                    'sort_order' => $templateItem['sort_order'],
                    'source' => (string) $source,
                    'editable' => false,
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

            foreach ($values as $key => $value) {
                $key = (string) $key;

                if ($this->isHiddenSurveyKey($key) || $value === null || $value === '') {
                    continue;
                }

                if ($this->isTechnicalSurveyPlaceholderValue($value, $key)) {
                    continue;
                }

                $resolvedValue = $displayService->resolve((string) $source, $key, $value);
                $usesChoices = $this->usesSurveyChoiceDisplay($resolvedValue['type']);
                $displayValue = $resolvedValue['resolved']
                    ? $resolvedValue['display']
                    : $this->surveyDisplayValue(
                        $value,
                        $usesChoices ? $key : null,
                        $usesChoices ? (string) $source : null,
                        $choiceLabels
                    );

                if ($displayValue === '') {
                    continue;
                }

                $uniqueKey = (string) $source.'|'.$key;

                if (isset($seen[$uniqueKey])) {
                    continue;
                }

                $seen[$uniqueKey] = true;
                $sectionKey = $this->surveySectionKey($key);
                $section = $sections->get($sectionKey) ?? $this->koboSurveySection($sectionKey);
                $questionLabel = $this->surveyQuestionLabel($key, (string) $source, $displayLabels);
                $questionSignature = $this->surveyQuestionSignature($questionLabel);

                if ($questionSignature !== '' && isset($seenQuestionLabels[$questionSignature]) && ! $this->isRepeatableSurveyKey($key)) {
                    continue;
                }

                $seenQuestionLabels[$questionSignature] = true;
                $section['items'][] = [
                    'question' => $this->surveyContextualQuestionLabel($key, $questionLabel),
                    'field_key' => $key,
                    'value' => $displayValue,
                    'raw_value' => $this->surveyDisplayValue($value),
                    'field_type' => $resolvedValue['type'],
                    'warning' => $resolvedValue['warning'],
                    'choices' => $usesChoices
                        ? ($resolvedValue['choices'] !== []
                            ? $resolvedValue['choices']
                            : $this->surveyChoiceOptions($value, $key, (string) $source, $choiceLabels))
                        : [],
                    'sort_order' => $this->surveySortOrder($key, (string) $source, $sortOrders),
                    'source' => (string) $source,
                    'editable' => self::SURVEY_VALUE_EDITING_ENABLED
                        && array_key_exists($key, $originalValues)
                        && is_scalar($originalValues[$key]),
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
            ->map(fn (array $section, string $sectionKey): array => $this->resolveSurveySectionLabels($section, $sectionKey))
            ->map(fn (array $section): array => $this->sortSurveySectionItems($section))
            ->sortBy(fn (array $section, string $sectionKey): string => sprintf(
                '%04d-%d',
                $this->surveySectionSortOrder($section, $sectionKey),
                str_starts_with($sectionKey, 'kobo:') ? 0 : 1
            ))
            ->all();
    }

    /**
     * @param  array<int, string|int>  $sources
     * @return array<string, array<int, array{field_key: string, question: string, sort_order: int}>>
     */
    private function surveyTemplateMappings(array $sources): array
    {
        $serviceNamesBySource = collect($sources)
            ->mapWithKeys(fn (string|int $source): array => [(string) $source => $this->surveySourceLookupKeys((string) $source)])
            ->all();
        $serviceNames = collect($serviceNamesBySource)->flatten()->unique()->values()->all();

        $mappingsByService = HeksKoboFieldMapping::query()
            ->whereIn('service_name', $serviceNames)
            ->whereNotNull('display_label')
            ->whereNotNull('notes')
            ->get(['service_name', 'kobo_field', 'display_label', 'notes'])
            ->filter(fn (HeksKoboFieldMapping $mapping): bool => $this->mappingFormOrder((string) $mapping->notes) !== null)
            ->groupBy('service_name');

        return collect($serviceNamesBySource)
            ->map(function (array $lookupServices) use ($mappingsByService): array {
                return collect($lookupServices)
                    ->flatMap(fn (string $service): array => ($mappingsByService->get($service) ?? collect())->all())
                    ->map(function (HeksKoboFieldMapping $mapping): array {
                        return [
                            'field_key' => (string) $mapping->kobo_field,
                            'question' => $this->surveyContextualQuestionLabel(
                                (string) $mapping->kobo_field,
                                trim((string) $mapping->display_label)
                            ),
                            'sort_order' => $this->mappingFormOrder((string) $mapping->notes) ?? PHP_INT_MAX,
                        ];
                    })
                    ->filter(fn (array $mapping): bool => $mapping['field_key'] !== '' && $mapping['question'] !== '')
                    ->unique('field_key')
                    ->sortBy('sort_order')
                    ->values()
                    ->all();
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function flattenSurveyValues(array $values, string $prefix = ''): array
    {
        $flat = [];

        foreach ($values as $key => $value) {
            $key = (string) $key;
            $path = $prefix === '' ? $key : "{$prefix}/{$key}";

            if (is_array($value)) {
                if (array_is_list($value)) {
                    foreach ($value as $index => $item) {
                        $itemPath = $path.'['.($index + 1).']';

                        if (is_array($item)) {
                            $flat = array_replace($flat, $this->flattenSurveyValues($item, $itemPath));
                        } else {
                            $flat[$itemPath] = $item;
                        }
                    }

                    continue;
                }

                $flat = array_replace($flat, $this->flattenSurveyValues($value, $path));

                continue;
            }

            $flat[$path] = $value;
        }

        return $flat;
    }

    private function surveySectionKey(string $key): string
    {
        $pathSection = $this->surveyPathSectionKey($key);

        if ($pathSection !== null) {
            return $pathSection;
        }

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

    private function surveyPathSectionKey(string $key): ?string
    {
        if (! str_contains($key, '/')) {
            return null;
        }

        $section = preg_replace('/\[\d+\]$/', '', explode('/', $key)[0]) ?: '';
        $section = trim($section);

        if ($section === '' || str_starts_with($section, '_')) {
            return null;
        }

        return 'kobo:'.$section;
    }

    /**
     * @param  array<int, string|int>  $sources
     * @return array<string, array<string, string>>
     */
    private function surveyDisplayLabels(array $sources): array
    {
        $serviceNames = collect($sources)
            ->map(fn (string|int $source): string => (string) $source)
            ->flatMap(fn (string $source): array => $this->surveySourceLookupKeys($source))
            ->unique()
            ->values()
            ->all();

        return HeksKoboFieldMapping::query()
            ->whereIn('service_name', $serviceNames)
            ->whereNotNull('display_label')
            ->get(['service_name', 'kobo_field', 'display_label'])
            ->groupBy('service_name')
            ->map(fn ($mappings): array => $mappings
                ->flatMap(function (HeksKoboFieldMapping $mapping): array {
                    $field = (string) $mapping->kobo_field;
                    $label = trim((string) $mapping->display_label);

                    if ($field === '' || $label === '') {
                        return [];
                    }

                    return collect($this->surveyFieldLookupKeys($field))
                        ->mapWithKeys(fn (string $lookupKey): array => [$lookupKey => $label])
                        ->all();
                })
                ->all())
            ->all();
    }

    /**
     * @param  array<int, string|int>  $sources
     * @return array<string, array<string, array<string, string>>>
     */
    private function surveyChoiceLabels(array $sources): array
    {
        $serviceNames = collect($sources)
            ->map(fn (string|int $source): string => (string) $source)
            ->flatMap(fn (string $source): array => $this->surveySourceLookupKeys($source))
            ->unique()
            ->values()
            ->all();

        return HeksKoboFieldMapping::query()
            ->whereIn('service_name', $serviceNames)
            ->whereNotNull('notes')
            ->get(['service_name', 'kobo_field', 'notes'])
            ->groupBy('service_name')
            ->map(fn ($mappings): array => $mappings
                ->flatMap(function (HeksKoboFieldMapping $mapping): array {
                    $choiceLabels = $this->mappingChoiceLabels((string) $mapping->notes);

                    if ($choiceLabels === []) {
                        return [];
                    }

                    return collect($this->surveyFieldLookupKeys((string) $mapping->kobo_field))
                        ->mapWithKeys(fn (string $lookupKey): array => [$lookupKey => $choiceLabels])
                        ->all();
                })
                ->all())
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function mappingChoiceLabels(string $notes): array
    {
        $decoded = json_decode($notes, true);

        if (! is_array($decoded) || ! is_array($decoded['choice_labels'] ?? null)) {
            return [];
        }

        return collect($decoded['choice_labels'])
            ->filter(fn (mixed $label, mixed $value): bool => is_string($value) && is_string($label) && trim($label) !== '')
            ->mapWithKeys(fn (string $label, string $value): array => [$value => trim($label)])
            ->all();
    }

    /**
     * @param  array<int, string|int>  $sources
     * @return array<string, array<string, int>>
     */
    private function surveySortOrders(array $sources): array
    {
        $serviceNames = collect($sources)
            ->map(fn (string|int $source): string => (string) $source)
            ->flatMap(fn (string $source): array => $this->surveySourceLookupKeys($source))
            ->unique()
            ->values()
            ->all();

        return HeksKoboFieldMapping::query()
            ->whereIn('service_name', $serviceNames)
            ->whereNotNull('notes')
            ->get(['service_name', 'kobo_field', 'notes'])
            ->groupBy('service_name')
            ->map(fn ($mappings): array => $mappings
                ->flatMap(function (HeksKoboFieldMapping $mapping): array {
                    $formOrder = $this->mappingFormOrder((string) $mapping->notes);

                    if ($formOrder === null) {
                        return [];
                    }

                    return collect($this->surveyFieldLookupKeys((string) $mapping->kobo_field))
                        ->mapWithKeys(fn (string $lookupKey): array => [$lookupKey => $formOrder])
                        ->all();
                })
                ->all())
            ->all();
    }

    private function mappingFormOrder(string $notes): ?int
    {
        $decoded = json_decode($notes, true);
        $formOrder = is_array($decoded) ? ($decoded['form_order'] ?? null) : null;

        return is_numeric($formOrder) ? (int) $formOrder : null;
    }

    private function usesSurveyChoiceDisplay(?string $fieldType): bool
    {
        return in_array($fieldType, ['select_one', 'select_multiple'], true);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array{0: string, 1: mixed}
     */
    private function surveyTemplateValue(HeksBeneficiary $beneficiary, array $values, string $key, string $question): array
    {
        foreach ($this->surveyFieldLookupKeys($key) as $lookupKey) {
            if (array_key_exists($lookupKey, $values)) {
                return [$lookupKey, $values[$lookupKey]];
            }
        }

        if (array_key_exists($question, $values)) {
            return [$question, $values[$question]];
        }

        $fallbackValue = $this->surveyBeneficiaryFallbackValue($beneficiary, $key, $question);

        if ($fallbackValue !== null && $fallbackValue !== '') {
            return [$key, $fallbackValue];
        }

        return [$key, null];
    }

    private function surveyBeneficiaryFallbackValue(HeksBeneficiary $beneficiary, string $key, string $question): mixed
    {
        $signature = $this->normalizedDashboardText($key.' '.$question);
        $fallbacks = [
            'household_head_gender' => ['family_info/head_gender', 'head_gender', 'جنس رب الأسرة'],
            'marital_status' => ['family_info/marital_status', 'marital_status', 'الحالة الاجتماعية'],
            'governorate' => ['governorate', 'family_info/governorate', 'المحافظة'],
            'area' => ['area_001', 'area', 'family_info/area', 'المنطقة'],
            'displacement_status' => ['displacement_status', 'حالة النزوح'],
            'occupancy_status' => ['occupancy_type', 'occupancy_status', 'housing_info/occupancy_type', 'حالة الإشغال'],
            'damage_status' => ['q_059', 'damage_status', 'حالة الضرر', 'تقييم حالة ضرر المأوى'],
            'recommendations' => ['recommendations', 'final_recommendation', 'التوصيات'],
        ];

        foreach ($fallbacks as $attribute => $needles) {
            foreach ($needles as $needle) {
                $normalizedNeedle = $this->normalizedDashboardText($needle);

                if ($normalizedNeedle !== '' && str_contains($signature, $normalizedNeedle)) {
                    return $beneficiary->{$attribute};
                }
            }
        }

        return null;
    }

    private function shouldHideUnansweredOtherSpecifyField(string $key, string $question, mixed $value): bool
    {
        if ($this->surveyDisplayValue($value) !== '') {
            return false;
        }

        $normalized = str($key.' '.$question)
            ->lower()
            ->replace(['أ', 'إ', 'آ'], 'ا')
            ->toString();

        return str_contains($normalized, 'other')
            || str_contains($normalized, 'specify')
            || str_contains($normalized, 'اخر')
            || str_contains($normalized, 'حدد');
    }

    /**
     * @param  array<string, bool>  $seen
     */
    private function markSurveySeenKeys(array &$seen, string $source, string $key, string $valueKey): void
    {
        foreach ([...$this->surveyFieldLookupKeys($key), $valueKey] as $seenKey) {
            if ($seenKey !== '') {
                $seen[$source.'|'.$seenKey] = true;
            }
        }
    }

    /**
     * @param  array<string, array<string, string>>  $displayLabels
     */
    private function surveyQuestionLabel(string $key, string $source, array $displayLabels): string
    {
        foreach ($this->surveySourceLookupKeys($source) as $serviceName) {
            foreach ($this->surveyFieldLookupKeys($key) as $lookupKey) {
                $label = $displayLabels[$serviceName][$lookupKey] ?? null;

                if (is_string($label) && trim($label) !== '') {
                    return trim($label);
                }
            }
        }

        return $key;
    }

    /**
     * @param  array<string, array<string, int>>  $sortOrders
     */
    private function surveySortOrder(string $key, string $source, array $sortOrders): int
    {
        foreach ($this->surveySourceLookupKeys($source) as $serviceName) {
            foreach ($this->surveyFieldLookupKeys($key) as $lookupKey) {
                $sortOrder = $sortOrders[$serviceName][$lookupKey] ?? null;

                if (is_int($sortOrder)) {
                    return $sortOrder;
                }
            }
        }

        return PHP_INT_MAX;
    }

    /**
     * @param  array{title: string, description: string, tone: string, items: array<int, mixed>}  $section
     * @return array{title: string, description: string, tone: string, items: array<int, mixed>}
     */
    private function sortSurveySectionItems(array $section): array
    {
        $section['items'] = array_values(collect($section['items'])
            ->sortBy(fn (array $item): int => (int) ($item['sort_order'] ?? PHP_INT_MAX))
            ->reduce(function (array $items, array $item): array {
                $signature = $this->surveyQuestionSignature((string) ($item['question'] ?? ''));

                if ($signature === '' || $this->isRepeatableSurveyKey((string) ($item['field_key'] ?? ''))) {
                    $items[] = $item;

                    return $items;
                }

                foreach ($items as $index => $existing) {
                    if ($this->surveyQuestionSignature((string) ($existing['question'] ?? '')) !== $signature) {
                        continue;
                    }

                    if ($this->surveyItemHasMeaningfulValue($item) && ! $this->surveyItemHasMeaningfulValue($existing)) {
                        $items[$index] = $item;
                    }

                    return $items;
                }

                $items[] = $item;

                return $items;
            }, []));

        return $section;
    }

    private function surveyItemHasMeaningfulValue(array $item): bool
    {
        $value = trim((string) ($item['value'] ?? ''));

        if ($value !== '') {
            return true;
        }

        return collect($item['choices'] ?? [])
            ->contains(fn (array $choice): bool => (bool) ($choice['selected'] ?? false));
    }

    private function surveyQuestionSignature(string $question): string
    {
        return str($question)
            ->replaceMatches('/\s+/u', ' ')
            ->trim()
            ->lower()
            ->toString();
    }

    private function isRepeatableSurveyKey(string $key): bool
    {
        return str_contains($key, '[');
    }

    private function isTechnicalSurveyPlaceholderValue(mixed $value, string $key): bool
    {
        if (! is_scalar($value)) {
            return false;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return false;
        }

        return in_array($value, $this->surveyFieldLookupKeys($key), true)
            || $this->isKoboTechnicalFieldToken($value);
    }

    private function isKoboTechnicalFieldToken(string $value): bool
    {
        if (preg_match('/^q_\d+(?:_\d+)?$/i', $value) === 1) {
            return true;
        }

        if (preg_match('/^_\d{3}$/', $value) === 1) {
            return true;
        }

        return in_array(strtolower($value), [
            'id',
            'gps',
            'visit_date',
            'application_code',
            'respondent_name',
            'head_name',
            'area_001',
            'address_001',
        ], true);
    }

    private function surveyContextualQuestionLabel(string $key, string $label): string
    {
        $context = $this->surveyFamilyCompositionContext($key);

        if ($context === null) {
            return $label;
        }

        $label = trim($label);

        if ($label === '' || str_contains($label, $context)) {
            return $label;
        }

        return "{$context} - {$label}";
    }

    private function surveyFamilyCompositionContext(string $key): ?string
    {
        $key = str($key)->replace('\\', '/')->toString();
        $contexts = [
            'group_hg1bp50' => 'أفراد الأسرة 8 سنوات وأقل',
            'group_og8mf61' => 'أفراد الأسرة 8-17 سنة',
            'group_sa8tl07' => 'أفراد الأسرة 18-59 سنة',
            'group_lx0gs10' => 'أفراد الأسرة 60 سنة فأكبر',
            'group_yc1bi89' => 'أفراد من ذوي الإعاقة 8 سنوات وأقل',
            'group_rv05e07' => 'أفراد من ذوي الإعاقة 8-17 سنة',
            'group_ko8jy58' => 'أفراد من ذوي الإعاقة 18-59 سنة',
            'group_tn2fj34' => 'أفراد من ذوي الإعاقة 60 سنة فأكبر',
            'group_ch1ve28' => 'أفراد لديهم أمراض مزمنة 8 سنوات وأقل',
            'group_wl5vo92' => 'أفراد لديهم أمراض مزمنة 8-17 سنة',
            'group_ny7er61' => 'أفراد لديهم أمراض مزمنة 18-59 سنة',
            'group_kt4pe39' => 'أفراد لديهم أمراض مزمنة 60 سنة فأكبر',
            'group_bf8it91' => 'أفراد مصابين في الحرب 8 سنوات وأقل',
            'group_zi8md17' => 'أفراد مصابين في الحرب 8-17 سنة',
            'group_sr9gf28' => 'أفراد مصابين في الحرب 18-59 سنة',
            'group_wk1zf21' => 'أفراد مصابين في الحرب 60 سنة فأكبر',
            'group_fa5xa06' => 'التكوين الأسري للأسر الممتدة',
        ];

        foreach ($contexts as $group => $context) {
            if (str_contains($key, "/{$group}/")) {
                return $context;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function surveySourceLookupKeys(string $source): array
    {
        $normalizedSource = str($source)
            ->lower()
            ->replace(['_', '-'], ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();

        return array_values(array_unique(array_filter([
            $source,
            str_replace('_', '-', $source),
            str_replace('-', '_', $source),
            str_contains($normalizedSource, 'heks') && str_contains($normalizedSource, 'final') ? 'heks-main' : null,
            match ($source) {
                'Heks Final V1' => 'heks-main',
                'heks_main' => 'heks-main',
                'heks_followup' => 'heks-followups',
                'heks_boq' => 'heks-boq',
                'heks_followup_boq' => 'heks-followup-boq',
                default => null,
            },
        ])));
    }

    /**
     * @return array<int, string>
     */
    private function surveyFieldLookupKeys(string $field): array
    {
        $keys = [$field];
        $withoutListIndexes = preg_replace('/\[\d+\]/', '', $field);

        if (is_string($withoutListIndexes) && $withoutListIndexes !== $field) {
            $keys[] = $withoutListIndexes;
        }

        if (str_contains($field, '/')) {
            $keys[] = substr($field, strpos($field, '/') + 1);
            $keys[] = str($field)->beforeLast('/')->toString();
        }

        return array_values(array_unique(array_filter($keys)));
    }

    /**
     * @return array{title: string, description: string, tone: string, items: array<int, mixed>}
     */
    private function koboSurveySection(string $sectionKey): array
    {
        $section = str_starts_with($sectionKey, 'kobo:')
            ? substr($sectionKey, 5)
            : $sectionKey;
        $label = $this->configuredKoboSectionLabel($section);

        return [
            'title' => $label['title'] ?? $this->humanizeKoboSection($section),
            'description' => $label['description'] ?? 'قسم مستورد من بنية نموذج Kobo الأصلية.',
            'tone' => 'secondary',
            'items' => [],
        ];
    }

    /**
     * @param  array{title: string, description: string, tone: string, items: array<int, mixed>}  $section
     * @return array{title: string, description: string, tone: string, items: array<int, mixed>}
     */
    private function resolveSurveySectionLabels(array $section, string $sectionKey): array
    {
        $sectionName = str_starts_with($sectionKey, 'kobo:')
            ? substr($sectionKey, 5)
            : $sectionKey;
        $configuredLabel = $this->configuredKoboSectionLabel($sectionName);

        if ($configuredLabel !== null) {
            $section['title'] = $configuredLabel['title'];
            $section['description'] = $configuredLabel['description'] ?? $section['description'];

            return $section;
        }

        $inferredLabel = $this->inferSurveySectionLabel($section['items']);

        if ($inferredLabel !== null) {
            $section['title'] = $inferredLabel['title'];
            $section['description'] = $inferredLabel['description'];
        }

        return $section;
    }

    /**
     * @return array{title: string, description?: string}|null
     */
    private function configuredKoboSectionLabel(string $section): ?array
    {
        $labels = (array) config('heks_kobo.section_labels', []);
        $normalizedSection = str($section)->replace('-', '_')->lower()->toString();
        $label = $labels[$normalizedSection] ?? $labels[$section] ?? null;

        if (! is_array($label)) {
            return null;
        }

        $title = trim((string) ($label['title'] ?? ''));

        if ($title === '') {
            return null;
        }

        return [
            'title' => $title,
            'description' => trim((string) ($label['description'] ?? '')),
        ];
    }

    /**
     * @param  array{title: string, description: string, tone: string, items: array<int, mixed>}  $section
     */
    private function surveySectionSortOrder(array $section, string $sectionKey): int
    {
        $sectionName = str_starts_with($sectionKey, 'kobo:')
            ? substr($sectionKey, 5)
            : $sectionKey;
        $sectionName = str($sectionName)->replace('-', '_')->lower()->toString();
        $orders = (array) config('heks_kobo.section_order', []);

        if (isset($orders[$sectionName]) && is_numeric($orders[$sectionName])) {
            return (int) $orders[$sectionName];
        }

        $title = (string) $section['title'];

        return match (true) {
            str_contains($title, 'التعريف') => 10,
            str_contains($title, 'الهشاشة') => 20,
            str_contains($title, 'الحماية') => 30,
            str_contains($title, 'التكوين') => 40,
            str_contains($title, 'الوحدة السكنية') => 50,
            str_contains($title, 'المعيشية') => 60,
            str_contains($title, 'المأوى') || str_contains($title, 'المأوي') => 70,
            str_contains($title, 'المستندات') => 80,
            str_contains($title, 'صور') => 90,
            str_contains($title, 'توصيات') => 100,
            str_contains($title, 'النظام') => 110,
            default => 500,
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{title: string, description: string}|null
     */
    private function inferSurveySectionLabel(array $items): ?array
    {
        $text = str(collect($items)
            ->flatMap(fn (array $item): array => [
                (string) ($item['question'] ?? ''),
                (string) ($item['field_key'] ?? ''),
            ])
            ->implode(' '))
            ->lower()
            ->toString();

        return match (true) {
            str_contains($text, 'الحماية')
                || str_contains($text, 'آمن')
                || str_contains($text, 'امن')
                || str_contains($text, 'uxo')
                || str_contains($text, 'erw')
                || str_contains($text, 'مخلفات')
                || str_contains($text, 'إحالة')
                || str_contains($text, 'احالة') => [
                    'title' => 'معلومات الحماية',
                    'description' => 'سلامة الوحدة، المخاطر، الإحالات، وسهولة الوصول الآمن.',
                ],
            str_contains($text, 'التكوين')
                || str_contains($text, 'أفراد الأسرة')
                || str_contains($text, 'افراد الاسرة')
                || str_contains($text, 'ذوي إعاقة')
                || str_contains($text, 'ذوي اعاقة')
                || str_contains($text, 'أمراض مزمنة')
                || str_contains($text, 'امراض مزمنة')
                || str_contains($text, 'مصابين') => [
                    'title' => 'معلومات التكوين الأسري',
                    'description' => 'توزيع أفراد الأسرة حسب العمر، الجنس، الإعاقة، الأمراض، والإصابات.',
                ],
            str_contains($text, 'دخل')
                || str_contains($text, 'العمل')
                || str_contains($text, 'المساعدات')
                || str_contains($text, 'الخصوصية')
                || str_contains($text, 'المعيشية') => [
                    'title' => 'تقييم الظروف المعيشية للأسرة',
                    'description' => 'الدخل، العمل، المساعدات الغذائية، الاحتياجات الأساسية، والخصوصية.',
                ],
            str_contains($text, 'السقف')
                || str_contains($text, 'الجدران')
                || str_contains($text, 'الأبواب')
                || str_contains($text, 'الابواب')
                || str_contains($text, 'النوافذ')
                || str_contains($text, 'المياه')
                || str_contains($text, 'المطبخ')
                || str_contains($text, 'الحمام')
                || str_contains($text, 'المأوى')
                || str_contains($text, 'المأوي') => [
                    'title' => 'تقييم حالة المأوى',
                    'description' => 'حالة الضرر، السقف، الجدران، الغرف، الأبواب، المياه، المطبخ، والحمام.',
                ],
            str_contains($text, 'المستندات')
                || str_contains($text, 'ملكية')
                || str_contains($text, 'عقد')
                || str_contains($text, 'بنكي') => [
                    'title' => 'المستندات',
                    'description' => 'أوراق الملكية، العقود، المستندات الثبوتية، والحسابات البنكية.',
                ],
            str_contains($text, 'صور الوحدة')
                || str_contains($text, 'تصوير')
                || str_contains($text, 'upload file') => [
                    'title' => 'صور الوحدة السكنية',
                    'description' => 'صور المبنى والوحدة السكنية من الداخل والخارج.',
                ],
            str_contains($text, 'توصيات')
                || str_contains($text, 'التدخل')
                || str_contains($text, 'ملاحظات') => [
                    'title' => 'توصيات نهائية',
                    'description' => 'حالة التدخل، المستندات، الملاحظات، والتوصيات النهائية.',
                ],
            default => null,
        };
    }

    private function humanizeKoboSection(string $section): string
    {
        $configuredLabel = $this->configuredKoboSectionLabel($section);

        if ($configuredLabel !== null) {
            return $configuredLabel['title'];
        }

        return str($section)
            ->replace(['_', '-'], ' ')
            ->headline()
            ->toString();
    }

    /**
     * @param  array<string, array<string, array<string, string>>>  $choiceLabels
     */
    private function surveyDisplayValue(mixed $value, ?string $key = null, ?string $source = null, array $choiceLabels = []): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'نعم' : 'لا';
        }

        if (is_scalar($value)) {
            $value = trim((string) $value);

            return $this->surveyChoiceDisplayValue($value, $key, $source, $choiceLabels) ?: $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    /**
     * @param  array<string, array<string, array<string, string>>>  $choiceLabels
     */
    private function surveyChoiceDisplayValue(string $value, ?string $key, ?string $source, array $choiceLabels): string
    {
        if ($value === '' || $key === null || $source === null) {
            return '';
        }

        foreach ($this->surveySourceLookupKeys($source) as $serviceName) {
            foreach ($this->surveyChoiceLookupSets($key) as $lookupSet) {
                $choices = $choiceLabels[$serviceName][$lookupSet['key']] ?? null;

                if (! is_array($choices) || $choices === []) {
                    continue;
                }

                if ($lookupSet['choice'] !== null) {
                    return $this->isSelectedKoboChoiceValue($value)
                        ? (string) ($choices[$lookupSet['choice']] ?? $lookupSet['choice'])
                        : '';
                }

                if (isset($choices[$value])) {
                    return $choices[$value];
                }

                $parts = preg_split('/\s+/', $value) ?: [];
                $labels = collect($parts)
                    ->filter()
                    ->map(fn (string $part): string => $choices[$part] ?? $part)
                    ->all();

                if ($labels !== [] && $labels !== $parts) {
                    return implode('، ', $labels);
                }
            }
        }

        return '';
    }

    /**
     * @return array<int, array{key: string, choice: ?string}>
     */
    private function surveyChoiceLookupSets(string $key): array
    {
        $lookupSets = collect($this->surveyFieldLookupKeys($key))
            ->map(fn (string $lookupKey): array => ['key' => $lookupKey, 'choice' => null])
            ->all();

        if (str_contains($key, '/')) {
            $segments = explode('/', $key);
            $choice = array_pop($segments);

            while ($segments !== []) {
                $lookupSets[] = [
                    'key' => implode('/', $segments),
                    'choice' => $choice,
                ];

                array_shift($segments);
            }
        }

        return collect($lookupSets)
            ->unique(fn (array $lookupSet): string => $lookupSet['key'].'|'.($lookupSet['choice'] ?? ''))
            ->values()
            ->all();
    }

    private function isSelectedKoboChoiceValue(string $value): bool
    {
        $value = trim($value);

        if ($value === '') {
            return false;
        }

        $normalized = str($value)->lower()->trim()->toString();

        return ! in_array($normalized, ['0', '0_', '_', 'false', 'no', 'n', 'not_selected', 'لا'], true);
    }

    /**
     * @param  array<string, array<string, array<string, string>>>  $choiceLabels
     * @return array<int, array{value: string, label: string, selected: bool}>
     */
    private function surveyChoiceOptions(mixed $value, string $key, string $source, array $choiceLabels): array
    {
        if (! is_scalar($value)) {
            return [];
        }

        $rawValue = trim((string) $value);

        if ($rawValue === '') {
            return [];
        }

        foreach ($this->surveySourceLookupKeys($source) as $serviceName) {
            foreach ($this->surveyChoiceLookupSets($key) as $lookupSet) {
                $choices = $choiceLabels[$serviceName][$lookupSet['key']] ?? null;

                if (! is_array($choices) || $choices === []) {
                    continue;
                }

                if ($lookupSet['choice'] !== null) {
                    if (! $this->isSelectedKoboChoiceValue($rawValue)) {
                        return [];
                    }

                    return collect($choices)
                        ->map(fn (string $label, string $choiceValue): array => [
                            'value' => $choiceValue,
                            'label' => $label,
                            'selected' => $choiceValue === $lookupSet['choice'],
                        ])
                        ->values()
                        ->all();
                }

                $selectedValues = collect(preg_split('/\s+/', $rawValue) ?: [])
                    ->filter()
                    ->values()
                    ->all();

                if ($selectedValues === []) {
                    $selectedValues = [$rawValue];
                }

                return collect($choices)
                    ->map(fn (string $label, string $choiceValue): array => [
                        'value' => $choiceValue,
                        'label' => $label,
                        'selected' => in_array($choiceValue, $selectedValues, true),
                    ])
                    ->values()
                    ->all();
            }
        }

        return [];
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
    private function technicalAssessmentRows(HeksBeneficiary $beneficiary, HeksKoboValueDisplayService $displayService): \Illuminate\Support\Collection
    {
        $labels = $beneficiary->labels->keyBy('label_key');
        $rawData = collect($beneficiary->raw_data ?? [])
            ->filter(fn (mixed $section): bool => is_array($section))
            ->flatMap(fn (array $section): array => $section);

        return HeksScoringWeight::query()
            ->where('source', 'Shelter Technical Weights')
            ->orderBy('id')
            ->get()
            ->map(function (HeksScoringWeight $weight) use ($labels, $rawData, $displayService): array {
                $weightRawData = is_array($weight->raw_data) ? $weight->raw_data : [];
                $category = $weight->category ?: ($weightRawData['Category'] ?? $weightRawData['column_1'] ?? null);
                $indicator = $weight->indicator ?: ($weightRawData['Indicator'] ?? $weightRawData['column_2'] ?? null);
                $question = $weight->question_key ?: ($weightRawData['Question'] ?? $weightRawData['column_8'] ?? $indicator);
                $label = $question ? $labels->get($question) : null;
                $rawValue = $question ? $rawData->get($question) : null;
                $value = $label?->label_value ?? $rawValue;
                $source = $label ? (string) $label->source : ($rawValue !== null ? 'raw_data' : '');
                $displayValue = $this->technicalAssessmentDisplayValue(
                    $displayService,
                    str_starts_with($source, 'heks-') ? $source : 'heks-main',
                    (string) ($question ?? ''),
                    $value
                );

                return [
                    'category' => $category,
                    'indicator' => $indicator,
                    'question' => $question,
                    'weight' => $weight->weight,
                    'max' => $weightRawData['Max'] ?? $weightRawData['column_4'] ?? null,
                    'avg' => $weightRawData['AVG'] ?? $weightRawData['column_5'] ?? null,
                    'min' => $weightRawData['Min'] ?? $weightRawData['column_6'] ?? null,
                    'value' => $displayValue,
                    'score' => $displayValue,
                    'source' => $source,
                ];
            });
    }

    private function technicalAssessmentDisplayValue(HeksKoboValueDisplayService $displayService, string $source, string $question, mixed $value): mixed
    {
        if (! is_scalar($value) || trim((string) $value) === '' || $question === '') {
            return $value;
        }

        $resolved = $displayService->resolve($source, $question, trim((string) $value));

        return ($resolved['resolved'] ?? false) ? $resolved['display'] : $value;
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
