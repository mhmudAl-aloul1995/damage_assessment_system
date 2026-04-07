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

        return view('exports.index', [
            'buildingColumns' => $buildingColumns,
            'housingColumns' => $housingColumns,
            'assessmentMeta' => $assessmentMeta,
            'filters' => $filters,
        ]);
    }




    public function export(Request $request)
    {
        try {
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

            $headers = array_keys((array) $rows->first());

            if ($exportType === 'pdf') {
                $rows = $query->limit(200);
                $pdf = Pdf::loadView('exports.buildings_pdf', [
                    'rows' => $rows,
                    'headers' => $headers,
                ])->setPaper('a4', 'landscape');

                return $pdf->download('export_buildings_housing_' . now()->format('Y_m_d_H_i_s') . '.pdf');
            }

            // Excel
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Export');

            foreach ($headers as $index => $header) {
                $columnLetter = Coordinate::stringFromColumnIndex($index + 1);
                $sheet->setCellValue($columnLetter . '1', $header);
            }

            $rowNumber = 2;
            foreach ($rows as $row) {
                $rowData = array_values((array) $row);
                foreach ($rowData as $index => $value) {
                    $columnLetter = Coordinate::stringFromColumnIndex($index + 1);
                    $sheet->setCellValue($columnLetter . $rowNumber, $value);
                }
                $rowNumber++;
            }

            $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
            $lastRow = $rows->count() + 1;

            $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                    'size' => 12,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1F4E78'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
            ]);

            $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->applyFromArray([
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
            ]);

            for ($i = 1; $i <= count($headers); $i++) {
                $columnLetter = Coordinate::stringFromColumnIndex($i);
                $sheet->getColumnDimension($columnLetter)->setAutoSize(true);
            }

            $sheet->setAutoFilter("A1:{$lastColumn}{$lastRow}");
            $sheet->freezePane('A2');

            $fileName = 'export_buildings_housing_' . now()->format('Y_m_d_H_i_s') . '.xlsx';
            $path = storage_path('app/public/' . $fileName);

            $writer = new Xlsx($spreadsheet);
            $writer->save($path);

            return response()->download($path)->deleteFileAfterSend(true);

        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}