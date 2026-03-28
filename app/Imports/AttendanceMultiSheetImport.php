<?php

namespace App\Imports;

use App\Models\User;
use App\Models\Attendance;
use App\Models\AttendanceImportLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class AttendanceMultiSheetImport implements WithMultipleSheets
{
    public function __construct(
        protected AttendanceImportLog $log
    ) {}

    public function sheets(): array
    {
        return [
            '*' => new AttendanceSheetImport($this->log),
        ];
    }
}

class AttendanceSheetImport implements ToCollection
{
    public function __construct(
        protected AttendanceImportLog $log
    ) {}

    public function collection(Collection $rows)
    {
        $sheetName = method_exists($rows, 'getTitle') ? $rows->getTitle() : null;

        // افتراضات ملفك الحالي
        $dayHeaderRowIndex = 2; // الصف 3
        $startRowIndex = 4;     // الصف 5

        $dayRow = $rows->get($dayHeaderRowIndex);
        if (!$dayRow instanceof Collection) {
            return;
        }

        // حاول استخراج الشهر والسنة من اسم الشيت
        [$year, $month] = $this->detectYearMonthFromSheetName($sheetName);

        foreach ($rows->slice($startRowIndex) as $row) {
            if (!$row instanceof Collection) {
                continue;
            }

            $this->log->increment('processed_rows');

            $nameEn      = trim((string) ($row->get(2) ?? '')); // C
            $nameAr      = trim((string) ($row->get(3) ?? '')); // D
            $idNo        = trim((string) ($row->get(5) ?? '')); // F
            $contractRaw = trim((string) ($row->get(6) ?? '')); // G
            $phone       = trim((string) ($row->get(7) ?? '')); // H

            if ($idNo === '' && $nameEn === '') {
                continue;
            }

            $contractType = $this->normalizeContract($contractRaw);

            $user = null;

            if ($idNo !== '') {
                $user = User::where('id_no', $idNo)->first();
            }

            // إنشاء تلقائي إذا غير موجود
            if (!$user) {
                $user = User::create([
                    'name' => $nameAr ?: $nameEn ?: 'Imported User',
                    'name_en' => $nameEn ?: null,
                    'id_no' => $idNo ?: null,
                    'phone' => $phone ?: null,
                    'contract_type' => $contractType,
                    'email' => $this->generateUniqueEmail($idNo, $nameEn, $nameAr),
                    'password' => bcrypt(Str::random(12)),
                ]);

                $this->log->increment('created_users');
            } else {
                $user->update([
                    'name' => $nameAr ?: $user->name,
                    'name_en' => $nameEn ?: $user->name_en,
                    'phone' => $phone ?: $user->phone,
                    'contract_type' => $contractType ?: $user->contract_type,
                ]);
            }

            // J إلى AN = 9 إلى 39
            for ($col = 9; $col <= 39; $col++) {
                $dayNumber = $dayRow->get($col);
                $value = $row->get($col);

                if (!is_numeric($dayNumber)) {
                    continue;
                }

                if ($value === null || $value === '') {
                    continue;
                }

                $status = (int) $value;
                if (!in_array($status, [0, 1], true)) {
                    continue;
                }

                $date = Carbon::create($year, $month, (int) $dayNumber)->format('Y-m-d');

                Attendance::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'date' => $date,
                    ],
                    [
                        'status' => $status,
                        'updated_by' => auth()->id(),
                    ]
                );

                $this->log->increment('imported_records');
            }
        }
    }

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

    protected function detectYearMonthFromSheetName(?string $sheetName): array
    {
        $currentYear = now()->year;
        $monthMap = [
            'january' => 1, 'jan' => 1,
            'february' => 2, 'feb' => 2,
            'march' => 3, 'mar' => 3,
            'april' => 4, 'apr' => 4,
            'may' => 5,
            'june' => 6, 'jun' => 6,
            'july' => 7, 'jul' => 7,
            'august' => 8, 'aug' => 8,
            'september' => 9, 'sep' => 9,
            'october' => 10, 'oct' => 10,
            'november' => 11, 'nov' => 11,
            'december' => 12, 'dec' => 12,
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
}