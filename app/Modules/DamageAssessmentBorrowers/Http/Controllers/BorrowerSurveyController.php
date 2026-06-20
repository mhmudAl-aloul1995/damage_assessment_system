<?php

namespace App\Modules\DamageAssessmentBorrowers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Modules\DamageAssessmentBorrowers\ImportBorrowerSpreadsheetRequest;
use App\Http\Requests\Modules\DamageAssessmentBorrowers\UpdateBorrowerPricingRequest;
use App\Modules\DamageAssessmentBorrowers\Http\Requests\StoreBorrowerSurveyRequest;
use App\Modules\DamageAssessmentBorrowers\Models\BorrowerBoqCatalogItem;
use App\Modules\DamageAssessmentBorrowers\Models\DamageAssessmentBorrower;
use App\Modules\DamageAssessmentBorrowers\Services\BorrowerRiskAnalysisService;
use App\Modules\DamageAssessmentBorrowers\Services\BorrowerSpreadsheetImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class BorrowerSurveyController extends Controller
{
    public function index(): View
    {
        $this->authorizeAccess();

        return view('damage-assessment-borrowers::index', [
            'stats' => $this->stats(),
            'isFormPage' => false,
        ]);
    }

    public function create(): View
    {
        $this->authorizeAccess();

        return view('damage-assessment-borrowers::index', [
            'isFormPage' => true,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorizeAccess();

        $query = DamageAssessmentBorrower::query()->with('submitter:id,name');

        if ($request->filled('risk_level')) {
            $query->where('risk_level', (string) $request->string('risk_level'));
        }

        if ($request->filled('q')) {
            $search = (string) $request->string('q');
            $query->where(function ($query) use ($search): void {
                $query->where('borrower_name', 'like', "%{$search}%")
                    ->orWhere('borrower_id_number', 'like', "%{$search}%")
                    ->orWhere('phone_primary', 'like', "%{$search}%");
            });
        }

        $borrowers = $query
            ->latest()
            ->limit(250)
            ->get()
            ->map(fn (DamageAssessmentBorrower $borrower): array => $this->row($borrower));

        return response()->json([
            'status' => true,
            'stats' => $this->stats(),
            'data' => $borrowers,
        ]);
    }

    public function store(StoreBorrowerSurveyRequest $request, BorrowerRiskAnalysisService $riskAnalysis): JsonResponse
    {
        $validated = $request->validated();
        $analysis = $riskAnalysis->analyze($validated);

        $borrower = DamageAssessmentBorrower::query()->create(array_merge($validated, $analysis, [
            'submitted_by' => $request->user()->id,
            'submitted_by_name' => $request->user()->name,
        ]));

        $borrower->load([
            'attachments',
            'boqItems' => fn ($query) => $query->orderBy('sort_order'),
            'residentHouseholds',
            'submitter:id,name',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'تم حفظ استبيان المقترض بنجاح.',
            'borrower' => $this->row($borrower),
            'analysis' => $analysis,
            'stats' => $this->stats(),
        ]);
    }

    public function import(ImportBorrowerSpreadsheetRequest $request, BorrowerSpreadsheetImportService $importer): JsonResponse
    {
        try {
            if ($request->hasFile('boq_file')) {
                $importer->importPriceCatalog($request->file('boq_file')->getRealPath());
            }

            $summary = $importer->importWorkbook($request->file('borrowers_file')->getRealPath());
        } catch (RuntimeException $exception) {
            return response()->json([
                'status' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable) {
            return response()->json([
                'status' => false,
                'message' => 'تعذر قراءة ملف Excel. تأكد من أنه ملف الاستبيان الصحيح.',
            ], 422);
        }

        return response()->json([
            'status' => true,
            'message' => "تم استيراد {$summary['created']} سجل وتحديث {$summary['updated']} سجل، مع تجاوز {$summary['skipped']} سجل يحتاج مراجعة.",
            'summary' => $summary,
            'stats' => $this->stats(),
        ]);
    }

    public function show(DamageAssessmentBorrower $borrower): JsonResponse
    {
        $this->authorizeAccess();
        $borrower->load('submitter:id,name');

        return response()->json([
            'status' => true,
            'borrower' => array_merge($borrower->toArray(), [
                'risk_label' => $this->riskLabel($borrower->risk_level),
                'submitted_by_display' => $borrower->submitter?->name ?? $borrower->submitted_by_name,
            ]),
        ]);
    }

    public function pricing(DamageAssessmentBorrower $borrower): View
    {
        $this->authorizePricingAccess();

        $borrower->load(['boqItems' => fn ($query) => $query->orderBy('sort_order')]);
        $catalogItems = BorrowerBoqCatalogItem::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('damage-assessment-borrowers::pricing', [
            'borrower' => $borrower,
            'pricingRows' => $this->pricingRows($borrower, $catalogItems),
        ]);
    }

    public function updatePricing(UpdateBorrowerPricingRequest $request, DamageAssessmentBorrower $borrower): RedirectResponse
    {
        $validated = $request->validated();
        $items = collect($validated['items'] ?? [])
            ->map(function (array $item): array {
                $quantity = (float) ($item['quantity'] ?? 0);
                $unitPrice = (float) ($item['unit_price'] ?? 0);
                $sourceColumn = (string) $item['source_column'];

                return [
                    'catalog_item_id' => filled($item['catalog_item_id'] ?? null) ? (int) $item['catalog_item_id'] : null,
                    'source_column' => $sourceColumn,
                    'source_key' => ($item['source_key'] ?? '') ?: sha1($sourceColumn),
                    'item_code' => $item['item_code'] ?? null,
                    'description' => (string) $item['description'],
                    'unit' => $item['unit'] ?? null,
                    'unit_price' => $unitPrice,
                    'quantity' => $quantity,
                    'total_price' => round($quantity * $unitPrice, 2),
                    'sort_order' => (int) ($item['sort_order'] ?? 0),
                ];
            })
            ->filter(fn (array $item): bool => $item['quantity'] > 0 || $item['unit_price'] > 0)
            ->values();

        DB::transaction(function () use ($borrower, $items): void {
            $seenKeys = [];

            foreach ($items as $item) {
                $seenKeys[] = $item['source_key'];
                $borrower->boqItems()->updateOrCreate(
                    ['source_key' => $item['source_key']],
                    $item
                );
            }

            $borrower->boqItems()
                ->when($seenKeys !== [], fn ($query) => $query->whereNotIn('source_key', $seenKeys))
                ->when($seenKeys === [], fn ($query) => $query)
                ->delete();

            $borrower->forceFill([
                'boq_total_usd' => $items->sum('total_price'),
            ])->save();
        });

        return redirect()
            ->route('damage-assessment-borrowers.pricing', $borrower)
            ->with('success', 'تم حفظ تسعير المستفيد بنجاح.');
    }

    /**
     * @return array<string, int>
     */
    private function stats(): array
    {
        return [
            'total' => DamageAssessmentBorrower::query()->count(),
            'critical' => DamageAssessmentBorrower::query()->where('risk_level', 'critical')->count(),
            'high' => DamageAssessmentBorrower::query()->where('risk_level', 'high')->count(),
            'displaced' => DamageAssessmentBorrower::query()->where('displacement_status', 'displaced')->count(),
            'destroyed' => DamageAssessmentBorrower::query()->where('loan_unit_damage_status', 'destroyed')->count(),
            'inactive_guarantors' => DamageAssessmentBorrower::query()
                ->whereIn('guarantors_alive_status', ['no', 'none'])
                ->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(DamageAssessmentBorrower $borrower): array
    {
        return [
            'id' => $borrower->id,
            'borrower_name' => $borrower->borrower_name,
            'borrower_id_number' => $borrower->borrower_id_number,
            'phone_primary' => $borrower->phone_primary,
            'displacement_status' => $borrower->displacement_status,
            'displacement_label' => $this->optionLabel($borrower->displacement_status),
            'governorate' => $borrower->displaced_to_governorate,
            'loan_unit_damage_status' => $borrower->loan_unit_damage_status,
            'damage_label' => $this->optionLabel($borrower->loan_unit_damage_status),
            'boq_total_usd' => (float) $borrower->boq_total_usd,
            'attachments_count' => $borrower->attachments_count,
            'risk_level' => $borrower->risk_level,
            'risk_label' => $this->riskLabel($borrower->risk_level),
            'risk_score' => $borrower->risk_score,
            'risk_reasons' => $borrower->risk_reasons ?? [],
            'submitted_by' => $borrower->submitter?->name ?? $borrower->submitted_by_name,
            'created_at' => $borrower->created_at?->format('Y-m-d H:i'),
            'pricing_url' => route('damage-assessment-borrowers.pricing', $borrower),
        ];
    }

    private function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->hasAnyRole([
            'Field Engineer',
            'Database Officer',
            'Project Officer',
            'Area Manager',
            'Team Leader',
            'Team Leader -INF',
            'Auditing Supervisor',
        ]), 403);
    }

    private function authorizePricingAccess(): void
    {
        abort_unless(auth()->user()?->hasAnyRole([
            'Database Officer',
            'Project Officer',
            'Area Manager',
            'Team Leader',
            'Team Leader -INF',
            'Auditing Supervisor',
        ]), 403);
    }

    /**
     * @param  Collection<int, BorrowerBoqCatalogItem>  $catalogItems
     * @return Collection<int, array<string, mixed>>
     */
    private function pricingRows(DamageAssessmentBorrower $borrower, Collection $catalogItems): Collection
    {
        $existingItems = $borrower->boqItems->keyBy('source_key');
        $rows = $catalogItems
            ->map(function (BorrowerBoqCatalogItem $catalogItem) use ($existingItems): array {
                $sourceColumn = $catalogItem->source_column ?: $catalogItem->description;
                $sourceKey = $catalogItem->source_key ?: sha1($sourceColumn);
                $existingItem = $existingItems->get($sourceKey);

                return [
                    'catalog_item_id' => $catalogItem->id,
                    'source_column' => $sourceColumn,
                    'source_key' => $sourceKey,
                    'item_code' => $catalogItem->item_code,
                    'description' => $catalogItem->description,
                    'unit' => $catalogItem->unit,
                    'unit_price' => $existingItem?->unit_price ?? $catalogItem->unit_price,
                    'quantity' => $existingItem?->quantity ?? 0,
                    'total_price' => $existingItem?->total_price ?? 0,
                    'sort_order' => $catalogItem->sort_order,
                ];
            });

        $catalogKeys = $rows->pluck('source_key')->all();
        $orphanRows = $borrower->boqItems
            ->reject(fn ($item): bool => in_array($item->source_key, $catalogKeys, true))
            ->map(fn ($item): array => [
                'catalog_item_id' => $item->catalog_item_id,
                'source_column' => $item->source_column,
                'source_key' => $item->source_key,
                'item_code' => $item->item_code,
                'description' => $item->description,
                'unit' => $item->unit,
                'unit_price' => $item->unit_price,
                'quantity' => $item->quantity,
                'total_price' => $item->total_price,
                'sort_order' => $item->sort_order,
            ]);

        return $rows->merge($orphanRows)->values();
    }

    private function riskLabel(?string $riskLevel): string
    {
        return match ($riskLevel) {
            'critical' => 'حرج',
            'high' => 'مرتفع',
            'medium' => 'متوسط',
            default => 'منخفض',
        };
    }

    private function optionLabel(?string $value): string
    {
        return match ($value) {
            'married' => 'متزوج/ة',
            'single' => 'أعزب/عزباء',
            'widowed' => 'أرمل/ة',
            'divorced' => 'مطلق/ة',
            'abandoned' => 'مهجور/ة',
            'working' => 'على رأس عمله',
            'retired' => 'متقاعد',
            'not_working' => 'لا يعمل',
            'displaced' => 'نازح',
            'returned' => 'عائد إلى منزله',
            'resident' => 'مقيم',
            'owner_borrower' => 'المقترض نفسه',
            'tenants' => 'مستأجرين',
            'displaced_hosted' => 'نازحين أو مستضافين',
            'buyers' => 'مشترين',
            'heirs' => 'وارثين',
            'none_due_damage' => 'لا يوجد بسبب الضرر',
            'destroyed' => 'هدم كلي',
            'severe_uninhabitable' => 'متضرر بليغ غير صالح للسكن',
            'severe_habitable' => 'متضرر بليغ صالح للسكن',
            'minor' => 'أضرار طفيفة',
            default => $value ?? '-',
        };
    }
}
