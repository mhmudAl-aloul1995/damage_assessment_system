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


    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\DB;
    use Rap2hpoutre\FastExcel\FastExcel;
    use Mpdf\Mpdf;

    public function export(Request $request)
    {
        try {
            ini_set('memory_limit', '1024M');
            ini_set('max_execution_time', 600);

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

            $assessmentLabels = DB::table('assessments')
                ->selectRaw('TRIM(name) as name, COALESCE(NULLIF(TRIM(hint), ""), TRIM(name)) as hint')
                ->pluck('hint', 'name')
                ->toArray();

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

            $selects = [];

            foreach ($buildingColumns as $column) {
                $selects[] = "b.`{$column}` as `building_{$column}`";
            }

            foreach ($housingColumns as $column) {
                $selects[] = "h.`{$column}` as `housing_{$column}`";
            }

            if (empty($selects)) {
                return back()->with('error', 'لا يوجد أعمدة صالحة للتصدير.');
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

                if (in_array($field, $validBuildingColumns, true)) {
                    $query->whereIn("b.$field", $values);
                } elseif (in_array($field, $validHousingColumns, true)) {
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

            /*
            |--------------------------------------------------------------------------
            | Build raw headers + display headers
            |--------------------------------------------------------------------------
            */
            $rawHeaders = [];

            foreach ($buildingColumns as $column) {
                $rawHeaders[] = "building_{$column}";
            }

            foreach ($housingColumns as $column) {
                $rawHeaders[] = "housing_{$column}";
            }

            if ($needsHousingJoinForFamily) {
                $rawHeaders[] = 'family_members_total';
            }

            $displayHeaders = [];
            foreach ($rawHeaders as $rawHeader) {
                $cleanHeader = str_replace(['building_', 'housing_'], '', $rawHeader);
                $cleanHeader = trim($cleanHeader);

                $displayHeaders[$rawHeader] = $assessmentLabels[$cleanHeader] ?? $cleanHeader;
            }

            if (isset($displayHeaders['family_members_total'])) {
                $displayHeaders['family_members_total'] = 'إجمالي عدد أفراد الأسرة';
            }

            /*
            |--------------------------------------------------------------------------
            | Count protection
            |--------------------------------------------------------------------------
            */
            $countQuery = clone $query;
            $totalRows = $countQuery->count();

            if ($totalRows === 0) {
                return back()->with('error', 'لا يوجد بيانات للتصدير.');
            }

            /*
            |--------------------------------------------------------------------------
            | PDF EXPORT
            |--------------------------------------------------------------------------
            */
            if ($exportType === 'pdf') {
                if (count($displayHeaders) > 15) {
                    return back()->with('error', 'PDF مناسب لعدد أعمدة قليل فقط. استخدم Excel.');
                }

                if ($totalRows > 3000) {
                    return back()->with('error', 'عدد الصفوف كبير جداً لملف PDF. استخدم Excel.');
                }

                $rows = $query->get()->map(function ($row) {
                    $clean = [];
                    foreach ((array) $row as $key => $value) {
                        $clean[$key] = is_scalar($value) || is_null($value) ? (string) $value : '';
                    }
                    return $clean;
                });

                $html = view('exports.buildings_pdf', [
                    'rows' => $rows,
                    'rawHeaders' => $rawHeaders,
                    'displayHeaders' => $displayHeaders,
                ])->render();

                $mpdf = new Mpdf([
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
            |--------------------------------------------------------------------------
            | EXCEL EXPORT - FASTEXCEL + CURSOR
            |--------------------------------------------------------------------------
            */
            $fileName = 'export_' . now()->format('Y_m_d_H_i_s') . '.xlsx';

            return (new FastExcel(
                $query->orderBy('b.id')->cursor()->map(function ($row) use ($rawHeaders, $displayHeaders) {
                    $rowArray = (array) $row;
                    $exportRow = [];

                    foreach ($rawHeaders as $rawHeader) {
                        $label = $displayHeaders[$rawHeader] ?? $rawHeader;
                        $value = $rowArray[$rawHeader] ?? '';

                        if (is_bool($value)) {
                            $value = $value ? 1 : 0;
                        } elseif (is_array($value) || is_object($value)) {
                            $value = '';
                        }

                        $exportRow[$label] = $value;
                    }

                    return $exportRow;
                })
            ))->download($fileName);

        } catch (\Throwable $e) {
            \Log::error('Export failed', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'فشل التصدير: ' . $e->getMessage());
        }
    }
}