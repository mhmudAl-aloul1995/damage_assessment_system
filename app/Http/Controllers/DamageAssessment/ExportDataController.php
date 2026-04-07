<?php
namespace App\Http\Controllers\DamageAssessment;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            ]);

            $buildingColumns = $request->input('building_columns', []);
            $housingColumns = $request->input('housing_columns', []);
            $selectedFilters = $request->input('filters', []);
            $familyMembersFrom = $request->input('family_members_from');
            $familyMembersTo = $request->input('family_members_to');

            
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

            // إذا كان عندك فلتر أفراد الأسرة أو فلتر على أعمدة housing
            $needsHousingJoinForFamily = !is_null($familyMembersFrom) || !is_null($familyMembersTo);
            if ($needsHousingJoinForFamily && !$housingJoined) {
                $query->leftJoin('housing_units as h', 'b.globalid', '=', 'h.parentglobalid');
                $housingJoined = true;
            }

            // select columns
            $query->selectRaw(implode(', ', $selects));

            // family members total expression
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

            // لو تريد أيضًا تصدير العدد نفسه كعمود
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

            // فلتر عدد أفراد الأسرة
            if (!is_null($familyMembersFrom)) {
                $query->having('family_members_total', '>=', (int) $familyMembersFrom);
            }

            if (!is_null($familyMembersTo)) {
                $query->having('family_members_total', '<=', (int) $familyMembersTo);
            }

            $rows = $query->get();

            $fileName = 'export_buildings_housing_' . now()->format('Y_m_d_H_i_s') . '.csv';
            $path = storage_path('app/public/' . $fileName);

            $handle = fopen($path, 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            if ($rows->count() > 0) {
                fputcsv($handle, array_keys((array) $rows->first()));

                foreach ($rows as $row) {
                    fputcsv($handle, (array) $row);
                }
            } else {
                fputcsv($handle, ['No Data']);
            }

            fclose($handle);

            return response()->download($path)->deleteFileAfterSend(true);

        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}