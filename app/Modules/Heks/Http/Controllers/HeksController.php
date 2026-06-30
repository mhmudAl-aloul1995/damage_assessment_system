<?php

namespace App\Modules\Heks\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Heks\Http\Requests\ImportHeksSpreadsheetRequest;
use App\Modules\Heks\Http\Requests\UpdateHeksBeneficiaryRequest;
use App\Modules\Heks\Http\Requests\UpdateHeksFollowUpRequest;
use App\Modules\Heks\Http\Requests\UpdateHeksLabelRequest;
use App\Modules\Heks\Http\Requests\UpdateHeksScoreRequest;
use App\Modules\Heks\Models\HeksBeneficiary;
use App\Modules\Heks\Models\HeksFollowUp;
use App\Modules\Heks\Models\HeksImport;
use App\Modules\Heks\Models\HeksLabel;
use App\Modules\Heks\Models\HeksScore;
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
            'latestImports' => HeksImport::query()->latest()->limit(8)->get(),
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
            ->withCount(['labels', 'followUps', 'scores'])
            ->when($request->filled('q'), function ($query) use ($request): void {
                $search = (string) $request->string('q');
                $query->where(function ($query) use ($search): void {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('identity_number', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('heks::beneficiaries', compact('beneficiaries'));
    }

    public function edit(HeksBeneficiary $beneficiary): View
    {
        $this->authorizeAccess();
        $beneficiary->load(['labels', 'followUps', 'scores']);

        return view('heks::edit', compact('beneficiary'));
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

        return back()->with('success', 'تم تحديث التصنيف.');
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
            'missingFollowUps' => HeksBeneficiary::query()->doesntHave('followUps')->count(),
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
            'labels' => HeksLabel::query()->count(),
            'follow_ups' => HeksFollowUp::query()->count(),
            'scores' => HeksScore::query()->count(),
            'imports' => HeksImport::query()->count(),
            'grant_total' => (float) HeksBeneficiary::query()->sum('grant_amount'),
        ];
    }

    private function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->hasAnyRole([
            'Database Officer',
            'Project Officer',
            'Project Officer - Borrowers',
            'Area Manager',
            'Team Leader',
            'Team Leader -INF',
            'Auditing Supervisor',
        ]), 403);
    }
}
