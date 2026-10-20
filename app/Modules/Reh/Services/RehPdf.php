<?php

namespace App\Modules\Reh\Services;

use App\Models\User;
use App\Modules\Reh\Models\RehUnit;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class RehPdf
{
    public function __construct(private RehSurvey $survey, private RehScoring $scoring) {}

    public static function columnValue(string $key, mixed $value): string
    {
        if ($value === null) {
            return 'غير متوفر';
        }
        $text = (string) $value;
        if ($key === 'sf_location') {
            $text = str_replace(',', ', ', $text);
        }

        return preg_replace('/(\S{40})(?=\S)/u', '$1 ', $text) ?? $text;
    }

    public function results(Collection $units, array $filters, User $user): Response
    {
        $rows = $units->map(function (RehUnit $unit) use ($user): array {
            $score = $user->can('reh.scores.view') ? $this->scoring->calculate($unit) : null;

            return [
                'unit' => $unit->old_objectid, 'building' => $unit->building->old_objectid,
                'owner' => $unit->owner_name ?: 'غير مسجل',
                'location' => $this->survey->value('governorate', $unit->building->governorate).' / '.$this->survey->value('neighborhood', $unit->building->neighborhood),
                'workflow' => RehSurvey::WORKFLOW[$unit->workflow_status],
                'score' => $score ? ($score['total'] ?? 'معلّقة') : 'محجوب',
                'priority' => $score ? (RehScoring::PRIORITIES[$score['priority']] ?? 'غير مصنفة') : 'محجوب',
                'missing' => $unit->source_missing || $unit->building->source_missing,
            ];
        });
        $summary = app(RehAdvancedFilters::class)->summary($filters);
        $selectedBuilding = $units->first()?->building;
        if ($selectedBuilding && (! empty($filters['building_id']) || ! empty($filters['building_object_id']))) {
            $summary['معرّف المبنى'] = $selectedBuilding->old_objectid;
        }
        $columns = $filters['columns'] ?? null;
        $export = new RehUnitsExport(RehUnit::query(), $user->can('reh.sensitive'), $user->can('reh.scores.view'), $columns);
        $columnRows = $columns ? $units->map(fn (RehUnit $unit): array => array_combine($columns, $export->row($unit))) : collect();
        $columnGroups = $columns ? array_chunk(array_combine($columns, $export->headings()), 5, true) : [];
        if (count($columnGroups) > 1) {
            $headings = array_combine($columns, $export->headings());
            $anchor = [$columns[0] => $headings[$columns[0]]];
            $columnGroups = array_map(fn (array $group): array => $anchor + $group, array_chunk(array_slice($headings, 1, null, true), 4, true));
        }
        $columnSections = [];
        if (count($columns ?? []) > 30) {
            $definitions = RehExportColumns::definitions();
            foreach ($columns as $key) {
                $columnSections[$definitions[$key]['group']][$key] = $definitions[$key]['label'];
            }
        }
        $html = view('reh::pdf.results', ['rows' => $rows, 'columnRows' => $columnRows, 'columnGroups' => $columnGroups, 'columnSections' => $columnSections, 'buildingCount' => $units->pluck('building_id')->unique()->count(), 'filterSummary' => $summary, 'generatedAt' => now()->format('Y-m-d H:i'), 'generatedBy' => $user->name])->render();

        return $this->download($html, 'REH-results-'.now()->format('Y-m-d').'.pdf');
    }

    public function unit(RehUnit $unit, User $user): Response
    {
        $html = view('reh::pdf.unit', $this->unitData($unit, $user))->render();
        $reference = preg_match('/^[A-Za-z0-9_-]+$/', $unit->old_objectid) ? $unit->old_objectid : 'unavailable';

        return $this->download($html, 'REH-unit-'.$reference.'.pdf');
    }

    public function unitData(RehUnit $unit, User $user): array
    {
        $unit->loadMissing('building');
        $sensitive = $user->can('reh.sensitive');
        $canSeeScores = $user->can('reh.scores.view');
        $score = $this->scoring->calculate($unit);
        $scoreGroups = ['التقييم الهندسي' => $score['engineering']];
        if ($sensitive) {
            $scoreGroups['التقييم الاجتماعي · أسرة المالك'] = $score['owner'];
            if ($score['other']) {
                $scoreGroups['التقييم الاجتماعي · الأسر الأخرى'] = $score['other'];
            }
        }

        return [
            'unitReference' => $unit->old_objectid, 'buildingReference' => $unit->building->old_objectid,
            'ownerName' => $unit->owner_name ?: 'غير مسجل', 'generatedAt' => now()->format('Y-m-d H:i'), 'generatedBy' => $user->name,
            'visitDate' => $this->survey->value('visit_date', $unit->building->surveyData()['visit_date'] ?? null),
            'engineer' => $this->survey->value('engineer_name', $unit->building->engineer),
            'location' => $this->survey->value('governorate', $unit->building->governorate).' / '.$this->survey->value('neighborhood', $unit->building->neighborhood),
            'workflow' => RehSurvey::WORKFLOW[$unit->workflow_status],
            'total' => $canSeeScores ? ($score['total'] ?? 'بانتظار الاستكمال') : 'محجوب',
            'priority' => $canSeeScores ? (RehScoring::PRIORITIES[$score['priority']] ?? 'غير مصنفة') : 'محجوب',
            'social' => $sensitive ? ($score['social_total'] ?? 'بانتظار الاستكمال') : 'محجوب',
            'engineering' => $score['engineering']['total'] ?? 'بانتظار الاستكمال',
            'identifiers' => ['معرّف المبنى السابق' => $unit->building->old_objectid, 'معرّف المبنى الحالي' => $unit->building->object_id ?? 'غير متوفر', 'معرّف الوحدة السابق' => $unit->old_objectid, 'معرّف الوحدة الحالي' => $unit->object_id ?? 'غير متوفر'],
            'buildingSections' => $this->survey->sections($unit->building->surveyData() ?? [], 'building', $sensitive),
            'unitSections' => $this->survey->sections($unit->surveyData() ?? [], 'unit', $sensitive),
            'scoreGroups' => $scoreGroups, 'sensitive' => $sensitive,
            'quantities' => $this->survey->quantities($unit),
            'sourceMissing' => $unit->source_missing || $unit->building->source_missing,
            'syncedAt' => $unit->synced_at?->format('Y-m-d H:i') ?? 'غير متوفر',
        ];
    }

    private function download(string $html, string $filename): Response
    {
        $directory = storage_path('app/mpdf');
        File::ensureDirectoryExists($directory);
        $pdf = new Mpdf([
            'mode' => 'utf-8', 'format' => 'A4', 'default_font' => 'dejavusans', 'default_font_size' => 10,
            'margin_left' => 15, 'margin_right' => 15, 'margin_top' => 18, 'margin_bottom' => 18,
            'autoScriptToLang' => true, 'autoLangToFont' => true, 'tempDir' => $directory,
        ]);
        $pdf->SetDirectionality('rtl');
        $pdf->SetTitle('REH - Palestinian Housing Council');
        $pdf->SetHTMLFooter('<div dir="rtl" style="font-size:8pt;color:#607b72;border-top:1px solid #d5e3dc;padding-top:6px">المجلس الفلسطيني للإسكان · تأهيل المساكن <span style="color:#a88545"> | <span dir="ltr">{PAGENO} / {nbpg}</span></span></div>');
        $pdf->WriteHTML($html);

        return response($pdf->Output('', Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
