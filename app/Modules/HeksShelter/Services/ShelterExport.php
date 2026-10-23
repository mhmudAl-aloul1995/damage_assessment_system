<?php

namespace App\Modules\HeksShelter\Services;

use App\Modules\HeksShelter\Models\ShelterCase;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShelterExport
{
    public function __construct(private ShelterSurvey $survey) {}

    public function excel(Collection $cases): StreamedResponse
    {
        return response()->streamDownload(function () use ($cases): void {
            $book = new Spreadsheet;
            $sheet = $book->getActiveSheet()->setTitle('HEKS المرحلة الثالثة');
            $sheet->setRightToLeft(true);
            $fields = app(SurveyDesigner::class)->filterCatalog();
            $headers = ['الكود', 'المرحلة', 'الحالة', 'المهندس', 'المنحة / شيكل', 'المصروف / شيكل', ...array_map(fn ($field): string => $field->label, array_values($fields))];
            $sheet->fromArray($headers);
            foreach ($cases->values() as $index => $case) {
                $values = [$case->code, '3', ShelterSurvey::STATUSES[$case->status], $case->engineer?->name ?? '', $case->grant_amount, $case->payments_sum_amount ?? 0];
                foreach ($fields as $key => $field) {
                    if ($field->parent_key) {
                        $values[] = implode(' | ', array_map(fn (array $row): string => $this->survey->value($key, $row[$field->key] ?? null, $case->survey_revision_id), $case->answers[$field->parent_key] ?? []));
                    } else {
                        $values[] = $this->survey->value($key, $case->answers[$key] ?? null, $case->survey_revision_id);
                    }
                }
                foreach ($values as $column => $value) {
                    $sheet->setCellValueExplicit([$column + 1, $index + 2], (string) $value, DataType::TYPE_STRING);
                }
            }
            $sheet->freezePane('D2');
            $sheet->setAutoFilter($sheet->calculateWorksheetDimension());
            $sheet->getStyle('1:1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF5B3F8C');
            $sheet->getDefaultColumnDimension()->setWidth(24);
            (new Xlsx($book))->save('php://output');
            $book->disconnectWorksheets();
        }, 'HEKS-phase-3-'.now()->format('Y-m-d').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'private, no-store']);
    }

    public function pdf(ShelterCase $case): Response
    {
        $directory = storage_path('app/heks-shelter/pdf-temp');
        File::ensureDirectoryExists($directory);
        $defaults = (new ConfigVariables)->getDefaults();
        $fonts = (new FontVariables)->getDefaults();
        $pdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4', 'default_font' => 'hekskufi', 'default_font_size' => 9,
            'fontDir' => [base_path(), ...$defaults['fontDir']], 'fontdata' => array_replace($fonts['fontdata'], ['hekskufi' => ['R' => 'DroidArabicKufi.ttf', 'useOTL' => 0xFF]]),
            'useSubstitutions' => true, 'backupSubsFont' => ['dejavusans'], 'autoScriptToLang' => true, 'autoLangToFont' => false, 'tempDir' => $directory]);
        $pdf->SetDirectionality('rtl');
        $pdf->SetHTMLFooter('<div style="text-align:center;font-size:8pt">HEKS · المرحلة الثالثة | {PAGENO} / {nbpg}</div>');
        $pdf->WriteHTML(view('heks-shelter::pdf', ['case' => $case, 'survey' => $this->survey])->render());

        return response($pdf->Output('', Destination::STRING_RETURN), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="HEKS-3-'.$case->id.'.pdf"', 'Cache-Control' => 'private, no-store']);
    }
}
