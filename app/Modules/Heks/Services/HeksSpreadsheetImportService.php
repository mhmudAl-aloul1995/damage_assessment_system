<?php

namespace App\Modules\Heks\Services;

use App\Modules\Heks\Models\HeksAttachment;
use App\Modules\Heks\Models\HeksBeneficiary;
use App\Modules\Heks\Models\HeksBoqItem;
use App\Modules\Heks\Models\HeksFollowUp;
use App\Modules\Heks\Models\HeksImport;
use App\Modules\Heks\Models\HeksLabel;
use App\Modules\Heks\Models\HeksPayment;
use App\Modules\Heks\Models\HeksScore;
use App\Modules\Heks\Models\HeksScoringWeight;
use App\Modules\Heks\Models\HeksWorkAssignment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

class HeksSpreadsheetImportService
{
    private bool $autoImportFollowUpBoqs = true;

    public function __construct(private HeksEngineerUserResolver $engineerUserResolver) {}

    private const ASSESSMENT_SHEETS = [
        'Heks Final V1',
        'Scoring-Heks Final',
        'KOBO_List',
        'Scoring-Heks- V1',
    ];

    private const SELECTED_SHEETS = [
        '125 BNFs -Data',
    ];

    private const PAYMENT_SHEETS = [
        '3دفعات',
    ];

    private const WORK_GROUP_SHEETS = [
        'مجموعات العمل',
    ];

    private const WEIGHT_SHEETS = [
        'Shelter Technical Weights',
        'T-V',
        'S-V',
    ];

    private const FOLLOW_UP_SHEETS = [
        'تقرير المتابعة -هيكس 125',
    ];

    /**
     * @return array<string, mixed>
     */
    public function preview(UploadedFile $file): array
    {
        $reader = IOFactory::createReaderForFile($file->getRealPath());
        $reader->setReadDataOnly(true);

        return [
            'filename' => $file->getClientOriginalName(),
            'sheets' => collect($reader->listWorksheetInfo($file->getRealPath()))
                ->map(fn (array $sheet): array => [
                    'name' => $sheet['worksheetName'],
                    'rows' => $sheet['totalRows'],
                    'columns' => $sheet['totalColumns'],
                    'detected_type' => $this->detectTypeFromSheetName((string) $sheet['worksheetName']),
                ])
                ->all(),
        ];
    }

    /**
     * @return array{import: HeksImport, summary: array<string, mixed>}
     */
    public function import(UploadedFile $file, string $type, ?int $userId = null, bool $autoImportFollowUpBoqs = true): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $parentCodes = $this->parentCodes($spreadsheet);
        $previousAutoImportFollowUpBoqs = $this->autoImportFollowUpBoqs;
        $this->autoImportFollowUpBoqs = $autoImportFollowUpBoqs;
        $summary = [
            'total_rows' => 0,
            'created_rows' => 0,
            'updated_rows' => 0,
            'skipped_rows' => 0,
            'sheets' => [],
        ];
        $createdImport = null;

        try {
            DB::transaction(function () use ($spreadsheet, $file, $type, $userId, $parentCodes, &$summary, &$createdImport): void {
                foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
                    $sheetType = $type === 'auto' ? $this->detectType($sheet) : $this->normalizeRequestedType($type, $sheet);

                    if ($sheetType === null) {
                        continue;
                    }

                    $sheetSummary = $this->importSheet($sheet, $sheetType, $parentCodes);
                    $summary['total_rows'] += $sheetSummary['total'];
                    $summary['created_rows'] += $sheetSummary['created'];
                    $summary['updated_rows'] += $sheetSummary['updated'];
                    $summary['skipped_rows'] += $sheetSummary['skipped'];
                    $summary['sheets'][] = array_merge(['name' => $sheet->getTitle(), 'type' => $sheetType], $sheetSummary);
                }

                $createdImport = HeksImport::query()->create([
                    'user_id' => $userId,
                    'type' => $type,
                    'filename' => $file->getClientOriginalName(),
                    'sheet_name' => collect($summary['sheets'])->pluck('name')->implode(', '),
                    'total_rows' => $summary['total_rows'],
                    'created_rows' => $summary['created_rows'],
                    'updated_rows' => $summary['updated_rows'],
                    'skipped_rows' => $summary['skipped_rows'],
                    'summary' => $summary,
                ]);
            });
        } finally {
            $this->autoImportFollowUpBoqs = $previousAutoImportFollowUpBoqs;
            $spreadsheet->disconnectWorksheets();
        }

        return [
            'import' => $createdImport,
            'summary' => $summary,
        ];
    }

    /**
     * @return array{total_rows: int, imported_rows: int, skipped_rows: int}
     */
    public function importBeneficiaryBoq(UploadedFile $file, HeksBeneficiary $beneficiary, ?HeksFollowUp $followUp = null): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getSheet(0);
        $headerRow = $this->boqHeaderRow($sheet);
        $this->assertBoqMatchesBeneficiary($file, $sheet, $beneficiary, $headerRow);
        $summary = ['total_rows' => 0, 'imported_rows' => 0, 'skipped_rows' => 0];
        $section = null;
        $source = $file->getClientOriginalName();

        DB::transaction(function () use ($sheet, $headerRow, $beneficiary, $followUp, $source, &$summary, &$section): void {
            for ($row = $headerRow + 1; $row <= $sheet->getHighestDataRow(); $row++) {
                $itemCode = $this->text($sheet->getCell([2, $row]));
                $description = $this->text($sheet->getCell([3, $row]));
                $unit = $this->text($sheet->getCell([4, $row]));
                $unitPrice = $this->decimal($this->text($sheet->getCell([5, $row]))) ?? 0.0;
                $quantity = $this->decimal($this->text($sheet->getCell([6, $row]))) ?? 0.0;

                if ($itemCode === '' && $description === '') {
                    continue;
                }

                if ($description === '' && ! preg_match('/^\d+(\.\d+)*$/', $itemCode)) {
                    $section = $itemCode;

                    continue;
                }

                if ($itemCode === '' || ! preg_match('/^\d+(\.\d+)*$/', $itemCode)) {
                    $summary['skipped_rows']++;

                    continue;
                }

                $summary['total_rows']++;

                if ($quantity <= 0 || $description === '') {
                    $summary['skipped_rows']++;

                    continue;
                }

                HeksBoqItem::query()->updateOrCreate(
                    [
                        'heks_beneficiary_id' => $beneficiary->id,
                        'heks_follow_up_id' => $followUp?->id,
                        'item_code' => $itemCode,
                        'description' => $description,
                    ],
                    [
                        'source' => $source,
                        'section' => $section,
                        'unit' => $unit,
                        'quantity' => $quantity,
                        'unit_price_ils' => $unitPrice,
                        'total_price_ils' => round($quantity * $unitPrice, 2),
                        'raw_data' => [
                            'sheet' => $sheet->getTitle(),
                            'row' => $row,
                            'boq_scope' => $followUp === null ? 'base' : 'follow_up',
                            'follow_up_id' => $followUp?->id,
                        ],
                    ]
                );

                $summary['imported_rows']++;
            }
        });

        $spreadsheet->disconnectWorksheets();

        return $summary;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function importFollowUpBoq(HeksFollowUp $followUp): ?array
    {
        $followUp->loadMissing('beneficiary');

        if (! $followUp->beneficiary) {
            return [
                'imported' => false,
                'error' => 'لا يوجد مستفيد مرتبط بهذه المتابعة.',
            ];
        }

        $attachment = HeksAttachment::query()->firstOrCreate(
            [
                'heks_beneficiary_id' => $followUp->beneficiary->id,
                'source' => "follow-up:{$followUp->id}",
                'attachment_type' => 'follow_up_boq',
            ],
            [
                'filename' => $followUp->boq_filename ?: 'BOQ',
                'url' => $followUp->boq_url,
                'parent_table' => 'manual-follow-up',
                'raw_data' => [
                    'follow_up_id' => $followUp->id,
                    'visit_number' => $followUp->visit_number,
                ],
            ]
        );

        $attachment->forceFill([
            'filename' => $followUp->boq_filename ?: $attachment->filename,
            'url' => $followUp->boq_url ?: $attachment->url,
        ])->save();

        $summary = $this->importFollowUpBoqWorkbook($followUp->beneficiary, $followUp, $attachment);

        if ($summary !== null) {
            $attachment->forceFill([
                'raw_data' => array_merge($attachment->raw_data ?? [], ['boq_import_summary' => $summary]),
            ])->save();
        }

        return $summary;
    }

    private function detectType(Worksheet $sheet): ?string
    {
        $sheetNameType = $this->detectTypeFromSheetName($sheet->getTitle());

        if ($sheetNameType !== null) {
            return $sheetNameType;
        }

        $headers = $this->headers($sheet);

        if ($this->hasAnyHeader($headers, ['Code']) && $this->hasAnyHeader($headers, ['Working condition'])) {
            return 'followups';
        }

        if ($this->hasAnyHeader($headers, ['الكود', 'رقم الطلب/الكود'])) {
            return 'assessment';
        }

        return null;
    }

    private function boqHeaderRow(Worksheet $sheet): int
    {
        for ($row = 1; $row <= min(15, $sheet->getHighestDataRow()); $row++) {
            $descriptionHeader = $this->text($sheet->getCell([3, $row]));
            $quantityHeader = $this->text($sheet->getCell([6, $row]));

            if (str_contains($descriptionHeader, 'وصف البند') && str_contains($quantityHeader, 'الكمية')) {
                return $row;
            }
        }

        throw new RuntimeException('لم يتم العثور على صف عناوين جدول الكميات في الملف.');
    }

    private function assertBoqMatchesBeneficiary(UploadedFile $file, Worksheet $sheet, HeksBeneficiary $beneficiary, int $headerRow): void
    {
        $expectedCode = $this->normalizedIdentifier((string) $beneficiary->code);
        $expectedName = $this->normalizedIdentifier((string) $beneficiary->name);
        $identifiers = [
            $file->getClientOriginalName(),
            $sheet->getTitle(),
        ];
        $lastRow = max(1, min($headerRow - 1, 15));
        $lastColumn = min($this->highestColumnIndex($sheet), 10);

        for ($row = 1; $row <= $lastRow; $row++) {
            for ($column = 1; $column <= $lastColumn; $column++) {
                $value = $this->text($sheet->getCell([$column, $row]));

                if ($value !== '') {
                    $identifiers[] = $value;
                }
            }
        }

        $matchesCode = $expectedCode !== '' && collect($identifiers)
            ->contains(fn (string $identifier): bool => str_contains($this->normalizedIdentifier($identifier), $expectedCode));
        $matchesName = $expectedName !== '' && collect($identifiers)
            ->contains(fn (string $identifier): bool => str_contains($this->normalizedIdentifier($identifier), $expectedName));

        if ($matchesCode || $matchesName) {
            return;
        }

        throw new RuntimeException("ملف جدول الكميات لا يطابق المستفيد الحالي. يرجى رفع ملف يحتوي كود الطلب {$beneficiary->code} أو اسم المستفيد {$beneficiary->name}.");
    }

    private function detectTypeFromSheetName(string $sheetName): ?string
    {
        return match (true) {
            in_array($sheetName, self::FOLLOW_UP_SHEETS, true) => 'followups',
            in_array($sheetName, self::PAYMENT_SHEETS, true) => 'payments',
            in_array($sheetName, self::WORK_GROUP_SHEETS, true) => 'work_groups',
            in_array($sheetName, self::WEIGHT_SHEETS, true) => 'weights',
            in_array($sheetName, self::SELECTED_SHEETS, true) => 'selected',
            in_array($sheetName, self::ASSESSMENT_SHEETS, true) => 'assessment',
            str_starts_with($sheetName, 'group_') => 'attachments',
            default => null,
        };
    }

    private function normalizeRequestedType(string $type, Worksheet $sheet): ?string
    {
        $detectedType = $this->detectType($sheet);

        if ($type === 'scores' && in_array($detectedType, ['assessment', 'selected', 'payments', 'work_groups', 'weights', 'attachments'], true)) {
            return $detectedType;
        }

        if ($type === 'labels' && $detectedType === 'assessment') {
            return 'assessment';
        }

        return $detectedType === $type ? $detectedType : null;
    }

    /**
     * @param  array<int, string>  $parentCodes
     * @return array{total: int, created: int, updated: int, skipped: int}
     */
    private function importSheet(Worksheet $sheet, string $type, array $parentCodes): array
    {
        if ($type === 'weights') {
            return $this->weights($sheet);
        }

        $headers = $this->headers($sheet);
        $summary = ['total' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($this->rows($sheet, $headers) as $row) {
            $summary['total']++;

            if ($type === 'attachments') {
                $stored = $this->attachment($row, $sheet->getTitle(), $parentCodes);
                $summary[$stored ? 'updated' : 'skipped']++;

                continue;
            }

            $code = $this->code($row);

            if ($code === '') {
                $summary['skipped']++;

                continue;
            }

            $beneficiary = $this->beneficiary($code, $row, $type, $sheet->getTitle());
            $wasRecentlyCreated = $beneficiary->wasRecentlyCreated;

            match ($type) {
                'followups' => $this->followUp($beneficiary, $row, $sheet->getTitle()),
                'assessment' => $this->assessment($beneficiary, $row, $sheet->getTitle()),
                'selected' => $this->selected($beneficiary, $row, $sheet->getTitle()),
                'payments' => $this->payment($beneficiary, $row, $sheet->getTitle()),
                'work_groups' => $this->workAssignment($beneficiary, $row, $sheet->getTitle()),
                default => null,
            };

            $summary[$wasRecentlyCreated ? 'created' : 'updated']++;
        }

        return $summary;
    }

    /**
     * @return array<string, int>
     */
    private function headers(Worksheet $sheet): array
    {
        $bestRow = 1;
        $bestHeaders = [];

        for ($row = 1; $row <= min(10, $sheet->getHighestDataRow()); $row++) {
            $headers = [];

            for ($column = 1; $column <= $this->highestColumnIndex($sheet); $column++) {
                $heading = $this->text($sheet->getCell([$column, $row]));

                if ($heading !== '') {
                    $headers[$heading] = $column;
                }
            }

            if (count($headers) > count($bestHeaders)) {
                $bestRow = $row;
                $bestHeaders = $headers;
            }
        }

        $bestHeaders['_header_row'] = $bestRow;

        return $bestHeaders;
    }

    /**
     * @param  array<string, int>  $headers
     * @return iterable<int, array<string, mixed>>
     */
    private function rows(Worksheet $sheet, array $headers): iterable
    {
        $headerRow = $headers['_header_row'] ?? 1;
        unset($headers['_header_row']);

        for ($rowNumber = $headerRow + 1; $rowNumber <= $sheet->getHighestDataRow(); $rowNumber++) {
            $row = ['_row_number' => $rowNumber];
            $hasValue = false;

            foreach ($headers as $heading => $column) {
                $value = $this->text($sheet->getCell([$column, $rowNumber]));
                $row[$heading] = $value;
                $hasValue = $hasValue || $value !== '';
            }

            for ($column = 1; $column <= $this->highestColumnIndex($sheet); $column++) {
                $value = $this->text($sheet->getCell([$column, $rowNumber]));
                $row["column_{$column}"] = $value;
                $hasValue = $hasValue || $value !== '';
            }

            if ($hasValue) {
                yield $rowNumber => $row;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function beneficiary(string $code, array $row, string $type, string $source): HeksBeneficiary
    {
        $beneficiary = HeksBeneficiary::query()->firstOrNew(['code' => $code]);
        $fieldEngineer = $this->first($row, ['اسم المهندس الميداني', 'Engineer Name', 'المهندس المتابع']);
        $data = array_filter([
            'name' => $this->first($row, ['Name', 'المستفيد', 'اسم المستفيد', 'اسم رب الأسرة', 'اسم الشخص المقابل']),
            'identity_number' => $this->first($row, ['الهوية', 'هوية المستفيد', 'رقم هوية رب الأسرة', 'رقم الهوية']),
            'phone' => $this->first($row, ['رقم التواصل', 'رقم التواصل2']),
            'alternate_phone' => $this->first($row, ['رقم تواصل بديل']),
            'field_engineer' => $fieldEngineer,
            'field_engineer_user_id' => $this->engineerUserResolver->resolve($fieldEngineer),
            'visit_date' => $this->date($this->first($row, ['Visit Date', 'تاريخ الزيارة'])),
            'governorate' => $this->first($row, ['المحافظة']),
            'area' => $this->first($row, ['المنطقة/التجمع']),
            'address' => $this->first($row, ['العنوان بالتفصيل']),
            'household_head_gender' => $this->first($row, ['جنس رب الأسرة']),
            'marital_status' => $this->first($row, ['الحالة الاجتماعية']),
            'displacement_status' => $this->first($row, ['حالة النزوح للأسرة حالياً']),
            'occupancy_status' => $this->first($row, ['حالة الإشغال الحالي للوحدة السكنية']),
            'damage_status' => $this->first($row, ['تقييم حالة ضرر المأوى:']),
            'grant_amount' => $this->decimal($this->first($row, ['Intervention (ILS)', 'GRANT', 'المنحة', 'قيمة العقد ILS'])),
            'payment_1' => $this->decimal($this->first($row, ['الدفعة  1', 'الدفعة 1', 'Payment_1', '30%', 'الدفعة الأولى  30% ILS'])),
            'payment_2' => $this->decimal($this->first($row, ['الدفعة 2', 'Payment_2', '50%'])),
            'payment_3' => $this->decimal($this->first($row, ['الدفعة 3', 'Payment_3', '20%'])),
            'recommendations' => $this->first($row, ['توصيات المهندس للزيارة', 'توصيات نهائية']),
            'selection_source' => in_array($type, ['selected', 'payments', 'work_groups'], true) ? $source : null,
            'selection_status' => in_array($type, ['selected', 'payments', 'work_groups'], true) ? 'selected' : null,
            'is_selected' => in_array($type, ['selected', 'payments', 'work_groups'], true) ? true : null,
            'raw_data' => array_merge($beneficiary->raw_data ?? [], [$source => $row]),
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        $beneficiary->fill($data)->save();

        return $beneficiary;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function assessment(HeksBeneficiary $beneficiary, array $row, string $source): void
    {
        $this->score($beneficiary, $row, $source);
        $this->labels($beneficiary, $row, $source);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function selected(HeksBeneficiary $beneficiary, array $row, string $source): void
    {
        $beneficiary->forceFill([
            'is_selected' => true,
            'selection_source' => $source,
            'selection_status' => 'selected',
        ])->save();

        $this->score($beneficiary, $row, $source);
        $this->labels($beneficiary, $row, $source);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function followUp(HeksBeneficiary $beneficiary, array $row, string $source): void
    {
        $engineerName = $this->first($row, ['Engineer Name']);

        $followUp = HeksFollowUp::query()->updateOrCreate(
            [
                'heks_beneficiary_id' => $beneficiary->id,
                'code' => $beneficiary->code,
                'visit_number' => HeksFollowUp::normalizeVisitNumber($this->first($row, ['visit #'])),
            ],
            [
                'visit_date' => $this->date($this->first($row, ['Visit Date'])),
                'engineer_name' => $engineerName,
                'engineer_user_id' => $this->engineerUserResolver->resolve($engineerName),
                'working_condition' => $this->first($row, ['Working condition']),
                'other_condition' => $this->first($row, ['Other condition:']),
                'completed_amount_ils' => $this->decimal($this->first($row, ['إجمالي ما تم انجازة حتى الآن ILS'])),
                'completion_percentage' => $this->decimal($this->first($row, ['نسبة الإنجاز بالأعمال %'])),
                'engineer_recommendations' => $this->first($row, ['توصيات المهندس للزيارة']),
                'boq_filename' => $this->first($row, ['Insert BOQ']),
                'boq_url' => $this->first($row, ['Insert BOQ_URL']),
                'raw_data' => $row,
            ]
        );

        $attachment = $this->followUpBoqAttachment($beneficiary, $followUp, $row, $source);

        if ($attachment !== null && $this->autoImportFollowUpBoqs) {
            $summary = $this->importFollowUpBoqWorkbook($beneficiary, $followUp, $attachment);

            if ($summary !== null) {
                $attachment->forceFill([
                    'raw_data' => array_merge($attachment->raw_data ?? [], ['boq_import_summary' => $summary]),
                ])->save();
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function followUpBoqAttachment(HeksBeneficiary $beneficiary, HeksFollowUp $followUp, array $row, string $source): ?HeksAttachment
    {
        $filename = $this->first($row, ['Insert BOQ']);
        $url = $this->first($row, ['Insert BOQ_URL']);

        if ($filename === '' && $url === '') {
            return null;
        }

        return HeksAttachment::query()->updateOrCreate(
            [
                'heks_beneficiary_id' => $beneficiary->id,
                'source' => "follow-up:{$followUp->id}",
                'attachment_type' => 'follow_up_boq',
            ],
            [
                'filename' => $filename !== '' ? $filename : 'BOQ',
                'url' => $url,
                'parent_table' => $source,
                'source_index' => (int) $this->decimal((string) ($row['_row_number'] ?? '0')) ?: null,
                'raw_data' => [
                    'follow_up_id' => $followUp->id,
                    'visit_number' => $followUp->visit_number,
                    'kobo_row' => $row,
                ],
            ]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function importFollowUpBoqWorkbook(HeksBeneficiary $beneficiary, HeksFollowUp $followUp, HeksAttachment $attachment): ?array
    {
        if (! $this->isExcelAttachment((string) $attachment->filename, (string) $attachment->url)) {
            return null;
        }

        if ($attachment->url === null || $attachment->url === '') {
            return [
                'imported' => false,
                'error' => 'لا يوجد رابط لتحميل ملف جدول الكميات.',
            ];
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'heks-boq-');

        try {
            $request = Http::timeout((int) config('services.kobotoolbox.timeout', 60))
                ->accept('*/*');

            if (str_contains((string) $attachment->url, 'kobotoolbox.org/api/')) {
                $token = (string) config('services.kobotoolbox.token', '');

                if ($token === '') {
                    return [
                        'imported' => false,
                        'error' => 'KoboToolbox token is not configured.',
                    ];
                }

                $request = $request->withHeaders([
                    'Authorization' => 'Token '.$token,
                ]);
            }

            $response = $request->get($attachment->url);

            if (! $response->successful() || $response->body() === '') {
                return [
                    'imported' => false,
                    'error' => 'تعذر تحميل ملف جدول الكميات من KoBo.',
                    'status' => $response->status(),
                ];
            }

            file_put_contents($temporaryPath, $response->body());

            return array_merge(
                ['imported' => true],
                $this->importBeneficiaryBoq(
                    new UploadedFile($temporaryPath, (string) $attachment->filename, null, null, true),
                    $beneficiary,
                    $followUp
                )
            );
        } catch (Throwable $exception) {
            return [
                'imported' => false,
                'error' => $exception->getMessage(),
            ];
        } finally {
            if (is_string($temporaryPath) && file_exists($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    private function isExcelAttachment(string $filename, string $url): bool
    {
        $filenameExtension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $urlPath = parse_url($url, PHP_URL_PATH);
        $urlExtension = is_string($urlPath) ? strtolower(pathinfo($urlPath, PATHINFO_EXTENSION)) : '';

        return in_array($filenameExtension, ['xlsx', 'xls'], true)
            || in_array($urlExtension, ['xlsx', 'xls'], true);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function score(HeksBeneficiary $beneficiary, array $row, string $source): void
    {
        HeksScore::query()->updateOrCreate(
            ['heks_beneficiary_id' => $beneficiary->id, 'source' => $source],
            [
                'grant_amount' => $this->decimal($this->first($row, ['Intervention (ILS)', 'GRANT', 'المنحة', 'قيمة العقد ILS'])),
                'payment_1' => $this->decimal($this->first($row, ['الدفعة  1', 'الدفعة 1', 'Payment_1', '30%', 'الدفعة الأولى  30% ILS'])),
                'payment_2' => $this->decimal($this->first($row, ['الدفعة 2', 'Payment_2', '50%'])),
                'payment_3' => $this->decimal($this->first($row, ['الدفعة 3', 'Payment_3', '20%'])),
                'social_score' => $this->decimal($this->first($row, ['تقييم الحالة الاجتماعية  (30)', 'تقييم الحالة الاجتماعية من 35'])),
                'technical_score' => $this->decimal($this->first($row, ['تقييم الحالة الفنية (70)'])),
                'total_score' => $this->decimal($this->first($row, ['التقييم الكلي', 'Total Score', 'Score', 'score'])),
                'classification' => $this->first($row, ['التصنيف', 'Classification', 'classification']),
                'raw_data' => $row,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function payment(HeksBeneficiary $beneficiary, array $row, string $source): void
    {
        $payment = HeksPayment::query()->updateOrCreate(
            ['heks_beneficiary_id' => $beneficiary->id, 'source' => $source],
            [
                'grant_amount' => $this->decimal($this->first($row, ['المنحة', 'قيمة العقد ILS', 'GRANT'])),
                'payment_1_amount' => $this->decimal($this->first($row, ['تاريخ دفعة 1', '30%', 'الدفعة الأولى  30% ILS'])),
                'payment_2_amount' => $this->decimal($this->first($row, ['تاريخ دفعة 2', '50%'])),
                'payment_3_amount' => $this->decimal($this->first($row, ['تاريخ دفعة 3', '20%'])),
                'payment_1_words' => $this->first($row, ['الدفعة 30% بالحروف']),
                'payment_2_words' => $this->first($row, ['الدفعة 50% بالحروف']),
                'payment_3_words' => $this->first($row, ['الدفعة 20% بالحروف']),
                'grant_words' => $this->first($row, ['المبلغ بالحروف']),
                'raw_data' => $row,
            ]
        );

        $beneficiary->forceFill([
            'grant_amount' => $payment->grant_amount ?? $beneficiary->grant_amount,
            'payment_1' => $payment->payment_1_amount ?? $beneficiary->payment_1,
            'payment_2' => $payment->payment_2_amount ?? $beneficiary->payment_2,
            'payment_3' => $payment->payment_3_amount ?? $beneficiary->payment_3,
            'payment_status' => $this->paymentStatus($payment),
            'is_selected' => true,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function workAssignment(HeksBeneficiary $beneficiary, array $row, string $source): void
    {
        $engineerName = $this->first($row, ['المهندس المتابع', 'اسم المهندس الميداني']);

        $assignment = HeksWorkAssignment::query()->updateOrCreate(
            ['heks_beneficiary_id' => $beneficiary->id, 'source' => $source],
            [
                'engineer_name' => $engineerName,
                'engineer_user_id' => $this->engineerUserResolver->resolve($engineerName),
                'contract_amount_ils' => $this->decimal($this->first($row, ['قيمة العقد ILS'])),
                'first_payment_ils' => $this->decimal($this->first($row, ['الدفعة الأولى  30% ILS'])),
                'phone' => $this->first($row, ['رقم التواصل']),
                'raw_data' => $row,
            ]
        );

        $beneficiary->forceFill(array_filter([
            'field_engineer' => $assignment->engineer_name,
            'field_engineer_user_id' => $assignment->engineer_user_id,
            'grant_amount' => $assignment->contract_amount_ils,
            'payment_1' => $assignment->first_payment_ils,
            'phone' => $assignment->phone,
            'work_group_source' => $source,
            'is_selected' => true,
        ], fn (mixed $value): bool => $value !== null && $value !== ''))->save();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function labels(HeksBeneficiary $beneficiary, array $row, string $source): void
    {
        $keys = [
            'screening' => 'يرجى تحديد ما إذا كانت هناك أي من الحالات التالية تنطبق على الوحدة السكنية.',
            'damage_status' => 'تقييم حالة ضرر المأوى:',
            'roof_status' => 'حالة السقف',
            'kitchen_status' => 'حالة المطبخ:',
            'occupancy_status' => 'حالة الإشغال الحالي للوحدة السكنية',
            'final_recommendation' => 'توصيات نهائية',
            'social_score' => 'تقييم الحالة الاجتماعية  (30)',
            'technical_score' => 'تقييم الحالة الفنية (70)',
        ];

        foreach ($keys as $labelKey => $heading) {
            $value = $this->first($row, [$heading]);

            if ($value === '') {
                continue;
            }

            HeksLabel::query()->updateOrCreate(
                ['heks_beneficiary_id' => $beneficiary->id, 'source' => $source, 'label_key' => $labelKey],
                ['label_value' => $value, 'version' => $this->first($row, ['__version__', '_submission___version__']), 'raw_data' => $row]
            );
        }
    }

    /**
     * @return array{total: int, created: int, updated: int, skipped: int}
     */
    private function weights(Worksheet $sheet): array
    {
        $summary = ['total' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0];
        $rows = $sheet->getTitle() === 'Shelter Technical Weights'
            ? $this->weightRows($sheet)
            : $this->valueRows($sheet);

        foreach ($rows as $row) {
            $summary['total']++;
            $indicator = $this->first($row, ['Indicator']);
            $questionKey = $this->first($row, ['question_key', 'Question', 'تقييم حالة ضرر المأوى:', 'حالة السقف']);

            if ($indicator === '' && $questionKey === '') {
                $summary['skipped']++;

                continue;
            }

            HeksScoringWeight::query()->updateOrCreate(
                [
                    'source' => $sheet->getTitle(),
                    'indicator' => $indicator !== '' ? $indicator : null,
                    'question_key' => $questionKey !== '' ? $questionKey : null,
                    'option_value' => $this->first($row, ['option_value']) ?: null,
                ],
                [
                    'category' => $this->first($row, ['Category']),
                    'weight' => $this->decimal($this->first($row, ['Weight (from 100)', 'Weight'])),
                    'option_score' => $this->decimal($this->first($row, ['score', 'Score'])),
                    'raw_data' => $row,
                ]
            );
            $summary['updated']++;
        }

        return $summary;
    }

    /**
     * @return iterable<int, array<string, mixed>>
     */
    private function valueRows(Worksheet $sheet): iterable
    {
        $headers = $this->headers($sheet);
        $headerRow = $headers['_header_row'] ?? 1;
        unset($headers['_header_row']);

        foreach ($headers as $question => $column) {
            if ($question === '') {
                continue;
            }

            for ($rowNumber = $headerRow + 1; $rowNumber <= $sheet->getHighestDataRow(); $rowNumber++) {
                $optionValue = $this->text($sheet->getCell([$column, $rowNumber]));

                if ($optionValue === '') {
                    continue;
                }

                yield $rowNumber => [
                    '_row_number' => $rowNumber,
                    'question_key' => $question,
                    'Question' => $question,
                    'option_value' => $optionValue,
                    'column' => $column,
                ];
            }
        }
    }

    /**
     * @return iterable<int, array<string, mixed>>
     */
    private function weightRows(Worksheet $sheet): iterable
    {
        $headerRow = $this->weightHeaderRow($sheet);
        $headers = $this->headersForRow($sheet, $headerRow);
        $lastColumn = max($this->highestColumnIndex($sheet), 8);

        for ($rowNumber = $headerRow + 1; $rowNumber <= $sheet->getHighestDataRow(); $rowNumber++) {
            $category = $this->text($sheet->getCell([1, $rowNumber]));
            $indicator = $this->text($sheet->getCell([2, $rowNumber]));
            $weight = $this->text($sheet->getCell([3, $rowNumber]));
            $question = $this->text($sheet->getCell([8, $rowNumber]));

            if ($category === '' && $indicator === '' && $question === '') {
                continue;
            }

            if (in_array(mb_strtolower($category), ['category'], true) || in_array(mb_strtolower($indicator), ['indicator'], true)) {
                continue;
            }

            if (in_array(mb_strtolower($weight), ['max', 'avg', 'min'], true)) {
                continue;
            }

            $row = [
                '_row_number' => $rowNumber,
                'Category' => $category,
                'Indicator' => $indicator,
                'Weight (from 100)' => $weight,
                'Question' => $question,
            ];

            foreach ($headers as $heading => $column) {
                if ($heading !== '') {
                    $row[$heading] = $this->text($sheet->getCell([$column, $rowNumber]));
                }
            }

            for ($column = 1; $column <= $lastColumn; $column++) {
                $row["column_{$column}"] = $this->text($sheet->getCell([$column, $rowNumber]));
            }

            yield $rowNumber => $row;
        }
    }

    private function weightHeaderRow(Worksheet $sheet): int
    {
        for ($row = 1; $row <= min(15, $sheet->getHighestDataRow()); $row++) {
            $firstColumn = mb_strtolower($this->text($sheet->getCell([1, $row])));
            $secondColumn = mb_strtolower($this->text($sheet->getCell([2, $row])));

            if ($firstColumn === 'category' && $secondColumn === 'indicator') {
                return $row;
            }
        }

        return 1;
    }

    /**
     * @return array<string, int>
     */
    private function headersForRow(Worksheet $sheet, int $row): array
    {
        $headers = [];

        for ($column = 1; $column <= $this->highestColumnIndex($sheet); $column++) {
            $heading = $this->text($sheet->getCell([$column, $row]));

            if ($heading !== '') {
                $headers[$heading] = $column;
            }
        }

        return $headers;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $parentCodes
     */
    private function attachment(array $row, string $source, array $parentCodes): bool
    {
        $filename = $this->first($row, ['صور الوحدة السكنية', 'قم بتصوير المستندات المتوفرة', 'Photos']);
        $url = $this->first($row, ['صور الوحدة السكنية_URL', 'قم بتصوير المستندات المتوفرة_URL', 'Photos_URL']);
        $filename = $filename !== '' ? $filename : $this->firstExact($row, ['column_1', 'column_3']);
        $url = $url !== '' ? $url : $this->firstExact($row, ['column_2', 'column_4']);
        $parentIndex = (int) $this->decimal($this->first($row, ['_parent_index']));
        $beneficiary = isset($parentCodes[$parentIndex])
            ? HeksBeneficiary::query()->where('code', $parentCodes[$parentIndex])->first()
            : null;

        if ($filename === '' && $url === '') {
            return false;
        }

        HeksAttachment::query()->updateOrCreate(
            [
                'source' => $source,
                'filename' => $filename,
                'parent_index' => $parentIndex > 0 ? $parentIndex : null,
                'source_index' => (int) $this->decimal($this->first($row, ['_index'])),
            ],
            [
                'heks_beneficiary_id' => $beneficiary?->id,
                'url' => $url,
                'parent_table' => $this->first($row, ['_parent_table_name']),
                'attachment_type' => str_contains($source, 'lm1ok19') ? 'shelter_photo' : 'document',
                'raw_data' => $row,
            ]
        );

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function parentCodes(Spreadsheet $spreadsheet): array
    {
        $codes = [];

        foreach (self::ASSESSMENT_SHEETS as $sheetName) {
            $sheet = $spreadsheet->getSheetByName($sheetName);

            if ($sheet === null) {
                continue;
            }

            $headers = $this->headers($sheet);
            $index = 1;

            foreach ($this->rows($sheet, $headers) as $row) {
                $code = $this->code($row);

                if ($code !== '') {
                    $codes[$index] = $code;
                }

                $index++;
            }

            if ($codes !== []) {
                return $codes;
            }
        }

        return $codes;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function code(array $row): string
    {
        $code = $this->first($row, ['Code', 'الكود', 'رقم الطلب/الكود']);

        return preg_match('/^[A-Z]{1,4}\d+/i', $code) ? $code : '';
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $keys
     */
    private function firstExact(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (($row[$key] ?? '') !== '') {
                return trim((string) $row[$key]);
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $keys
     */
    private function first(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (($row[$key] ?? '') !== '') {
                return trim((string) $row[$key]);
            }
        }

        foreach ($row as $heading => $value) {
            foreach ($keys as $key) {
                if (
                    is_string($heading)
                    && str_contains($this->normalizedHeading($heading), $this->normalizedHeading($key))
                    && trim((string) $value) !== ''
                ) {
                    return trim((string) $value);
                }
            }
        }

        return '';
    }

    /**
     * @param  array<string, int>  $headers
     * @param  array<int, string>  $keys
     */
    private function hasAnyHeader(array $headers, array $keys): bool
    {
        foreach ($keys as $key) {
            if (isset($headers[$key])) {
                return true;
            }

            foreach (array_keys($headers) as $heading) {
                if (is_string($heading) && str_contains($this->normalizedHeading($heading), $this->normalizedHeading($key))) {
                    return true;
                }
            }
        }

        return false;
    }

    private function normalizedHeading(string $heading): string
    {
        return preg_replace('/\s+/u', ' ', trim($heading)) ?? trim($heading);
    }

    private function normalizedIdentifier(string $value): string
    {
        $normalized = mb_strtolower($value);

        return preg_replace('/[^\p{L}\p{N}]+/u', '', $normalized) ?? $normalized;
    }

    private function paymentStatus(HeksPayment $payment): string
    {
        if ($payment->payment_1_amount !== null && $payment->payment_2_amount !== null && $payment->payment_3_amount !== null) {
            return 'paid_100';
        }

        if ($payment->payment_1_amount !== null && $payment->payment_2_amount !== null) {
            return 'paid_80';
        }

        if ($payment->payment_1_amount !== null) {
            return 'paid_30';
        }

        return 'pending';
    }

    private function text(Cell $cell): string
    {
        try {
            $value = $cell->isFormula() ? $cell->getOldCalculatedValue() : $cell->getValue();
        } catch (Throwable) {
            $value = $cell->getValue();
        }

        return trim((string) ($value ?? ''));
    }

    private function decimal(string $value): ?float
    {
        $normalized = str_replace([',', ' ', '%'], '', $value);

        return $normalized !== '' && is_numeric($normalized) ? (float) $normalized : null;
    }

    private function date(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }

    private function highestColumnIndex(Worksheet $sheet): int
    {
        $index = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        if ($index > 350) {
            throw new RuntimeException('ملف HEKS يحتوي عددا غير متوقع من الأعمدة.');
        }

        return $index;
    }
}
