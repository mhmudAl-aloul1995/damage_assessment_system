<?php

namespace App\Modules\Heks\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Heks\Http\Requests\ImportHeksSpreadsheetRequest;
use App\Modules\Heks\Http\Requests\UpdateHeksBeneficiaryRequest;
use App\Modules\Heks\Http\Requests\UpdateHeksFollowUpRequest;
use App\Modules\Heks\Http\Requests\UpdateHeksLabelRequest;
use App\Modules\Heks\Http\Requests\UpdateHeksScoreRequest;
use App\Modules\Heks\Models\HeksAttachment;
use App\Modules\Heks\Models\HeksBeneficiary;
use App\Modules\Heks\Models\HeksFollowUp;
use App\Modules\Heks\Models\HeksImport;
use App\Modules\Heks\Models\HeksLabel;
use App\Modules\Heks\Models\HeksPayment;
use App\Modules\Heks\Models\HeksScore;
use App\Modules\Heks\Models\HeksScoringWeight;
use App\Modules\Heks\Models\HeksWorkAssignment;
use App\Modules\Heks\Services\HeksSpreadsheetImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HeksController extends Controller
{
    public function dashboard(): View
    {
        $this->authorizeAccess();

        return view('heks::dashboard', [
            'stats' => $this->stats(),
            'pipeline' => $this->pipeline(),
            'latestImports' => HeksImport::query()->latest()->limit(8)->get(),
            'engineerWorkload' => HeksWorkAssignment::query()
                ->selectRaw('engineer_name, count(*) as cases_count, coalesce(sum(contract_amount_ils), 0) as contract_total')
                ->whereNotNull('engineer_name')
                ->groupBy('engineer_name')
                ->orderByDesc('cases_count')
                ->limit(8)
                ->get(),
            'paymentStatusDistribution' => HeksBeneficiary::query()
                ->selectRaw('payment_status, count(*) as aggregate')
                ->whereNotNull('payment_status')
                ->groupBy('payment_status')
                ->pluck('aggregate', 'payment_status'),
            'labelDistribution' => HeksLabel::query()
                ->selectRaw('label_key, count(*) as aggregate')
                ->groupBy('label_key')
                ->orderByDesc('aggregate')
                ->limit(8)
                ->pluck('aggregate', 'label_key'),
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
            'followUps' => fn ($query) => $query->latest('visit_date')->latest(),
            'scores' => fn ($query) => $query->latest(),
            'payments' => fn ($query) => $query->latest(),
            'workAssignments' => fn ($query) => $query->latest(),
            'attachments' => fn ($query) => $query->latest(),
        ]);

        return view('heks::edit', [
            'beneficiary' => $beneficiary,
            'rawDataSections' => $this->rawDataSections($beneficiary),
        ]);
    }

    public function update(UpdateHeksBeneficiaryRequest $request, HeksBeneficiary $beneficiary): RedirectResponse
    {
        $beneficiary->update($request->validated());

        return back()->with('success', 'تم تحديث بيانات المستفيد.');
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

    public function followUps(): View
    {
        $this->authorizeAccess();

        return view('heks::follow-ups', [
            'followUps' => HeksFollowUp::query()->with('beneficiary')->latest()->paginate(25),
        ]);
    }

    public function updateFollowUp(UpdateHeksFollowUpRequest $request, HeksFollowUp $followUp): RedirectResponse
    {
        $followUp->update($request->validated());

        return back()->with('success', 'تم تحديث المتابعة.');
    }

    public function scores(): View
    {
        $this->authorizeAccess();

        return view('heks::scores', [
            'scores' => HeksScore::query()->with('beneficiary')->latest()->paginate(25),
        ]);
    }

    public function updateScore(UpdateHeksScoreRequest $request, HeksScore $score): RedirectResponse
    {
        $score->update($request->validated());

        return back()->with('success', 'تم تحديث الدرجات.');
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
     * @return array<string, int|float>
     */
    private function stats(): array
    {
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
    private function pipeline(): array
    {
        return [
            ['label' => 'تم تقييمها', 'count' => HeksBeneficiary::query()->count(), 'tone' => 'primary'],
            ['label' => 'تم اختيارها', 'count' => HeksBeneficiary::query()->where('is_selected', true)->count(), 'tone' => 'success'],
            ['label' => 'تم توزيعها', 'count' => HeksBeneficiary::query()->has('workAssignments')->count(), 'tone' => 'info'],
            ['label' => 'استلمت دفعة أولى', 'count' => HeksBeneficiary::query()->whereIn('payment_status', ['paid_30', 'paid_80', 'paid_100'])->count(), 'tone' => 'warning'],
            ['label' => 'تمت متابعتها', 'count' => HeksBeneficiary::query()->has('followUps')->count(), 'tone' => 'dark'],
            ['label' => 'اكتمل دفعها', 'count' => HeksBeneficiary::query()->where('payment_status', 'paid_100')->count(), 'tone' => 'success'],
        ];
    }

    private function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->hasRole('Database Officer'), 403);
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
}
