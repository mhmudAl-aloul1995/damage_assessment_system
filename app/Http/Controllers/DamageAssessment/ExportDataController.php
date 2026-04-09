<?php
namespace App\Http\Controllers\DamageAssessment;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Mpdf\Mpdf;
use App\Models\Assessment;
class ExportDataController extends Controller
{
    public function index()
    {
        $buildingColumns = DB::getSchemaBuilder()->getColumnListing('buildings');
        $housingColumns = DB::getSchemaBuilder()->getColumnListing('housing_units');

        $assessmentMeta = DB::table('assessments')
            ->select('name', 'label', 'hint')
            ->whereNotNull('name')
            ->get()
            ->mapWithKeys(function ($item) {
                $name = trim($item->name);

                return [
                    $name => [
                        'label' => trim($item->label ?? ''),
                        'hint' => trim($item->hint ?? ''),
                    ]
                ];
            })
            ->toArray();

        $assessmentNames = array_keys($assessmentMeta);

        $buildingColumns = array_values(array_filter($buildingColumns, function ($column) use ($assessmentNames) {
            return in_array(trim($column), $assessmentNames);
        }));

        $housingColumns = array_values(array_filter($housingColumns, function ($column) use ($assessmentNames) {
            return in_array(trim($column), $assessmentNames);
        }));

        $filters = DB::table('filters')
            ->select('list_name', 'name', 'label')
            ->orderBy('list_name')
            ->orderBy('label')
            ->get()
            ->groupBy('list_name');
        $assessmentLabels = Assessment::pluck('label', 'name');
        return view('exports.index', [
            'assessmentLabels' => $assessmentLabels,
            'buildingColumns' => $buildingColumns,
            'housingColumns' => $housingColumns,
            'assessmentMeta' => $assessmentMeta,
            'filters' => $filters,
        ]);
    }


    public function export(Request $request)
    {
        try {

            ini_set('memory_limit', '1024M');
            ini_set('max_execution_time', 300);

            $request->validate([
                'building_columns' => ['nullable', 'array'],
                'building_columns.*' => ['string'],
                'housing_columns' => ['nullable', 'array'],
                'housing_columns.*' => ['string'],
                'filters' => ['nullable', 'array'],
                'family_members_from' => ['nullable', 'numeric', 'min:0'],
                'family_members_to' => ['nullable', 'numeric', 'min:0'],
                'export_type' => ['required', 'in:excel,pdf'],
            ]);

            $buildingColumns = $request->input('building_columns', []);
            $housingColumns = $request->input('housing_columns', []);
            $selectedFilters = $request->input('filters', []);
            $familyMembersFrom = $request->input('family_members_from');
            $familyMembersTo = $request->input('family_members_to');
            $exportType = $request->input('export_type', 'excel');

            if (empty($buildingColumns) && empty($housingColumns)) {
                return back()->with('error', 'يرجى اختيار عمود واحد على الأقل للتصدير.');
            }

            $validBuildingColumns = DB::getSchemaBuilder()->getColumnListing('buildings');
            $validHousingColumns = DB::getSchemaBuilder()->getColumnListing('housing_units');

            $buildingColumns = array_values(array_intersect($buildingColumns, $validBuildingColumns));
            $housingColumns = array_values(array_intersect($housingColumns, $validHousingColumns));

            if (empty($buildingColumns) && empty($housingColumns)) {
                return back()->with('error', 'الأعمدة المختارة غير صالحة.');
            }

            /*
            |--------------------------------------------------
            | labels from assessments table
            | IMPORTANT: trim name to avoid mismatch
            |--------------------------------------------------
            */
            $assessmentLabels = DB::table('assessments')
                ->selectRaw('TRIM(name) as name, COALESCE(NULLIF(TRIM(hint), ""), TRIM(name)) as label')
                ->pluck('hint', 'name')
                ->toArray();

            $selects = [];

            foreach ($buildingColumns as $column) {
                $selects[] = "b.`{$column}` as `building_{$column}`";
            }

            foreach ($housingColumns as $column) {
                $selects[] = "h.`{$column}` as `housing_{$column}`";
            }

            $query = DB::table('buildings as b');
            $housingJoined = false;

            if (!empty($housingColumns)) {
                $query->leftJoin('housing_units as h', 'b.globalid', '=', 'h.parentglobalid');
                $housingJoined = true;
            }

            $needsHousingJoinForFamily = !is_null($familyMembersFrom) || !is_null($familyMembersTo);

            if ($needsHousingJoinForFamily && !$housingJoined) {
                $query->leftJoin('housing_units as h', 'b.globalid', '=', 'h.parentglobalid');
                $housingJoined = true;
            }

            $query->selectRaw(implode(', ', $selects));

            $familyMembersExpression = "
        (
            COALESCE(CAST(NULLIF(h.mchildren_001, '') AS UNSIGNED), 0) +
            COALESCE(CAST(NULLIF(h.melderly, '') AS UNSIGNED), 0) +
            COALESCE(CAST(NULLIF(h.myoung, '') AS UNSIGNED), 0) +
            COALESCE(CAST(NULLIF(h.fchildren, '') AS UNSIGNED), 0) +
            COALESCE(CAST(NULLIF(h.fyoung_001, '') AS UNSIGNED), 0) +
            COALESCE(CAST(NULLIF(h.felderly, '') AS UNSIGNED), 0)
        )
        ";

            if ($needsHousingJoinForFamily) {
                $query->addSelect(DB::raw("$familyMembersExpression as family_members_total"));
            }

            foreach ($selectedFilters as $field => $values) {
                $values = array_filter((array) $values, fn($v) => $v !== null && $v !== '');

                if (empty($values)) {
                    continue;
                }

                if (in_array($field, $validBuildingColumns)) {
                    $query->whereIn("b.$field", $values);
                } elseif (in_array($field, $validHousingColumns)) {
                    if (!$housingJoined) {
                        $query->leftJoin('housing_units as h', 'b.globalid', '=', 'h.parentglobalid');
                        $housingJoined = true;
                    }

                    $query->whereIn("h.$field", $values);
                }
            }

            if (!is_null($familyMembersFrom)) {
                $query->having('family_members_total', '>=', (int) $familyMembersFrom);
            }

            if (!is_null($familyMembersTo)) {
                $query->having('family_members_total', '<=', (int) $familyMembersTo);
            }

            $rows = $query->get();

            if ($rows->isEmpty()) {
                return back()->with('error', 'لا يوجد بيانات للتصدير.');
            }

            /*
            |--------------------------------------------------
            | RAW HEADERS + DISPLAY HEADERS
            |--------------------------------------------------
            */
            $rawHeaders = array_keys((array) $rows->first());

            $displayHeaders = [];
            foreach ($rawHeaders as $rawHeader) {
                $cleanHeader = str_replace(['building_', 'housing_'], '', $rawHeader);
                $cleanHeader = trim($cleanHeader);

                $displayHeaders[$rawHeader] = $assessmentLabels[$cleanHeader] ?? $cleanHeader;
            }

            // optional custom label
            if (isset($displayHeaders['family_members_total'])) {
                $displayHeaders['family_members_total'] = 'إجمالي عدد أفراد الأسرة';
            }

            /*
            |--------------------------------------------------
            | PDF EXPORT
            |--------------------------------------------------
            */
            if ($exportType === 'pdf') {

                if (count($displayHeaders) > 15) {
                    return back()->with('error', 'PDF مناسب لعدد أعمدة قليل فقط. استخدم Excel.');
                }

                $pdfRows = $rows->map(function ($row) {
                    $clean = [];
                    foreach ((array) $row as $key => $value) {
                        $clean[$key] = is_scalar($value) || is_null($value) ? (string) $value : '';
                    }
                    return $clean;
                });

                $html = view('exports.buildings_pdf', [
                    'rows' => $pdfRows,
                    'rawHeaders' => $rawHeaders,
                    'displayHeaders' => $displayHeaders,
                ])->render();

                $mpdf = new \Mpdf\Mpdf([
                    'mode' => 'utf-8',
                    'format' => 'A4-L',
                    'default_font' => 'dejavusans',
                    'directionality' => 'rtl',
                    'autoScriptToLang' => true,
                    'autoLangToFont' => true,
                    'margin_top' => 28,
                    'margin_bottom' => 18,
                    'margin_left' => 8,
                    'margin_right' => 8,
                ]);

                $mpdf->SetTitle('Damage Assessment Report');
                $mpdf->SetAuthor(config('app.name'));
                $mpdf->SetHTMLFooter('
                <div style="border-top:1px solid #999; font-size:10px; padding-top:6px; text-align:center; color:#666;">
                    <span>تاريخ التصدير: ' . now()->format('Y-m-d H:i') . '</span>
                    &nbsp; | &nbsp;
                    <span>الصفحة {PAGENO} من {nbpg}</span>
                </div>
            ');

                $mpdf->WriteHTML($html);

                return response($mpdf->Output('export.pdf', 'S'))
                    ->header('Content-Type', 'application/pdf')
                    ->header('Content-Disposition', 'attachment; filename="export_' . now()->format('Y_m_d_H_i_s') . '.pdf"');
            }

            /*
            |--------------------------------------------------
            | EXCEL EXPORT
            |--------------------------------------------------
            */
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Export');

            // write display headers
            $headerIndex = 1;
            foreach ($rawHeaders as $rawHeader) {
                $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($headerIndex);
                $sheet->setCellValue($columnLetter . '1', $displayHeaders[$rawHeader] ?? $rawHeader);
                $headerIndex++;
            }

            // write row data in same order as raw headers
            $rowNumber = 2;
            foreach ($rows as $row) {
                $rowArray = (array) $row;

                $colIndex = 1;
                foreach ($rawHeaders as $rawHeader) {
                    $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                    $sheet->setCellValue($columnLetter . $rowNumber, $rowArray[$rawHeader] ?? '');
                    $colIndex++;
                }

                $rowNumber++;
            }

            $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($rawHeaders));
            $lastRow = $rows->count() + 1;

            $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                    'size' => 12,
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1F4E78'],
                ],
            ]);

            $sheet->getStyle("A1:{$lastColumn}{$lastRow}")
                ->getBorders()
                ->getAllBorders()
                ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

            for ($i = 1; $i <= count($rawHeaders); $i++) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $fileName = 'export_' . now()->format('Y_m_d_H_i_s') . '.xlsx';
            $path = storage_path('app/public/' . $fileName);

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($path);

            return response()->download($path)->deleteFileAfterSend(true);

        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}