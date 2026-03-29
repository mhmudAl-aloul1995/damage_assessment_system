<?php

namespace App\Imports;

use App\Models\User;
use App\Models\Attendance;
use App\Models\AttendanceImportLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithTitle;
use Spatie\Permission\Models\Role;

class AttendanceSheetImport implements ToCollection, WithTitle
{
    public function __construct(
        protected AttendanceImportLog $log,
        protected string $sheetName,
        protected ?int $updatedBy = null,
        protected ?string $region = null
    ) {
    }

    public function title(): string
    {
        return $this->sheetName;
    }

    public function collection(Collection $rows)
    {
        $dayHeaderRowIndex = 2; // الصف 3
        $startRowIndex = 4;     // الصف 5

        $dayRow = $rows->get($dayHeaderRowIndex);

        if (!$dayRow instanceof Collection) {
            return;
        }

        [$year, $month] = $this->detectYearMonthFromSheetName($this->sheetName);

        $this->log->increment('total_rows', max(0, $rows->count() - $startRowIndex));

        $attendanceRows = [];

        foreach ($rows->slice($startRowIndex) as $row) {

            if (!$row instanceof Collection)
                continue;

            $this->log->increment('processed_rows');

            // =====================
            // قراءة الأعمدة
            // =====================
            $nameEn = trim((string) ($row->get(2) ?? '')); // C
            $nameAr = trim((string) ($row->get(3) ?? '')); // D
            $positionRaw = trim((string) ($row->get(4) ?? '')); // E
            $idNo = trim((string) ($row->get(5) ?? '')); // F
            $contractRaw = trim((string) ($row->get(6) ?? '')); // G
            $phone = trim((string) ($row->get(7) ?? '')); // H

            if ($idNo === '' && $nameEn === '')
                continue;

            $contractType = $this->normalizeContract($contractRaw);
            $roleName = $this->normalizeRoleFromPosition($positionRaw);
            $region = $this->region;
            // =====================
            // User
            // =====================
            $user = $idNo ? User::where('id_no', $idNo)->first() : null;

            if (!$user) {
                $user = User::create([
                    'name' => $nameAr ?: $nameEn ?: 'Imported User',
                    'name_en' => $nameEn ?: null,
                    'id_no' => $idNo ?: null,
                    'phone' => $phone ?: null,
                    'contract_type' => $contractType,
                    'region' => $this->region,
                    'email' => $this->generateUniqueEmail($idNo, $nameEn, $nameAr),
                    'password' => bcrypt(Str::random(12)),
                ]);

                // 🔥 Auto Create Role + Assign
                if ($roleName) {
                    $role = Role::firstOrCreate([
                        'name' => $roleName,
                        'guard_name' => 'web',
                    ]);

                    if (!$user->hasRole($role->name)) {
                        $user->assignRole($role);
                    }
                }

                $this->log->increment('created_users');

            } else {

                $user->update([
                    'name' => $nameAr ?: $user->name,
                    'name_en' => $nameEn ?: $user->name_en,
                    'phone' => $phone ?: $user->phone,
                    'region' => $region ?: $user->region,
                    'contract_type' => $contractType ?: $user->contract_type,
                ]);

                // 🔥 Assign role if not exists
                if ($roleName) {
                    $role = Role::firstOrCreate([
                        'name' => $roleName,
                        'guard_name' => 'web',
                    ]);

                    if (!$user->hasRole($role->name)) {
                        $user->assignRole($role);
                    }
                }
            }

            // =====================
            // Attendance (Bulk Insert)
            // =====================
            for ($col = 9; $col <= 39; $col++) {

                $dayNumber = $dayRow->get($col);
                $value = $row->get($col);

                if (!is_numeric($dayNumber))
                    continue;
                if ($value === null || $value === '')
                    continue;

                $status = (int) $value;
                if (!in_array($status, [0, 1], true))
                    continue;

                $date = Carbon::create($year, $month, (int) $dayNumber)->format('Y-m-d');

                $attendanceRows[] = [
                    'user_id' => $user->id,
                    'date' => $date,
                    'status' => $status,
                    'updated_by' => $this->updatedBy,
                ];
            }

            // 🔥 Batch insert كل 500
            if (count($attendanceRows) >= 500) {

                Attendance::upsert(
                    $attendanceRows,
                    ['user_id', 'date'],
                    ['status', 'updated_by']
                );

                $this->log->increment('imported_records', count($attendanceRows));

                $attendanceRows = [];
            }
        }

        // 🔥 آخر دفعة
        if (!empty($attendanceRows)) {
            Attendance::upsert(
                $attendanceRows,
                ['user_id', 'date'],
                ['status', 'updated_by']
            );

            $this->log->increment('imported_records', count($attendanceRows));
        }
    }

    // =====================
    // Contract Mapping
    // =====================
    protected function normalizeContract(?string $value): ?string
    {
        $v = strtolower(trim((string) $value));

        return match (true) {
            str_contains($v, 'phc') => 'phc',
            str_contains($v, 'undp') => 'undp',
            str_contains($v, 'mopwh') => 'mopwh',
            str_contains($v, 'pef') => 'pef',
            default => null,
        };
    }

    // =====================
    // Role Mapping
    // =====================
    protected function normalizeRoleFromPosition(?string $value): ?string
    {
        $v = trim((string) $value);

        return match (true) {
            str_contains($v, 'QC/QA Engineer') => 'QC/QA Engineer',
            str_contains($v, 'Field Engineer') => 'Field Engineer',
            str_contains($v, 'legal') => 'Legal Auditor',
            str_contains($v, 'Auditing Supervisor') => 'Auditing Supervisor',
            str_contains($v, 'Area Manager') => 'Area Manager',
            str_contains($v, 'area manager') => 'area manager',
            str_contains($v, 'Team Leader -INF') => 'Team Leader -INF',
            str_contains($v, 'Team leader') => 'Team leader',
            default => '',
        };
    }

    // =====================
    // Generate Email
    // =====================
    protected function generateUniqueEmail(?string $idNo, ?string $nameEn, ?string $nameAr): string
    {
        $base = $idNo ?: Str::slug($nameEn ?: $nameAr ?: 'user');
        $base = preg_replace('/[^a-zA-Z0-9]/', '', (string) $base);
        $base = strtolower($base ?: 'user');

        $email = $base . '@import.local';
        $counter = 1;

        while (User::where('email', $email)->exists()) {
            $email = $base . $counter . '@import.local';
            $counter++;
        }

        return $email;
    }

    // =====================
    // Detect Month/Year
    // =====================
    protected function detectYearMonthFromSheetName(?string $sheetName): array
    {
        $currentYear = now()->year;

        $monthMap = [
            'jan' => 1,
            'feb' => 2,
            'mar' => 3,
            'apr' => 4,
            'may' => 5,
            'jun' => 6,
            'jul' => 7,
            'aug' => 8,
            'sep' => 9,
            'oct' => 10,
            'nov' => 11,
            'dec' => 12
        ];

        $sheet = strtolower((string) $sheetName);

        $month = 1;
        foreach ($monthMap as $key => $num) {
            if (str_contains($sheet, $key)) {
                $month = $num;
                break;
            }
        }

        preg_match('/(20\d{2})/', $sheet, $matches);
        $year = isset($matches[1]) ? (int) $matches[1] : $currentYear;

        return [$year, $month];
    }
    protected function normalizeRegion(?string $value): ?string
    {
        $v = strtolower(trim((string) $value));

        return match (true) {
            str_contains($v, 'north') => 'north',
            str_contains($v, 'south') => 'south',
            str_contains($v, 'شمال') => 'north',
            str_contains($v, 'جنوب') => 'south',
            default => null,
        };
    }

}