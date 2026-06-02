<?php

namespace App\services;

use App\Models\Building;
use App\Models\CommitteeDecision;
use App\Models\CommitteeDecisionSignature;
use App\Models\CommitteeMember;
use App\Models\HousingUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class CommitteeDecisionExcelImportService
{
    public function import(string $path, bool $dryRun = false): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Excel file was not found: {$path}");
        }

        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'xlsx') {
            throw new RuntimeException('Committee decision import expects an XLSX file.');
        }

        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);
        $headerRow = array_shift($rows) ?? [];
        $headers = $this->normalizeHeaders($headerRow);
        $records = [];

        foreach ($rows as $rowNumber => $row) {
            if ($this->isBlankRow($row)) {
                continue;
            }

            $records[] = [
                'row' => $rowNumber,
                'objectid' => $this->cellByHeader($row, $headers, 'ObjectID'),
                'globalid' => $this->cellByHeader($row, $headers, 'GlobalID'),
                'decision' => $this->cell($row, 'V'),
                'action' => $this->cell($row, 'W'),
                'members' => $this->cell($row, 'X'),
                'hint' => $this->cell($row, 'Y'),
            ];
        }

        return $this->importRecords($records, $dryRun);
    }

    public function importRecords(array $records, bool $dryRun = false): array
    {
        $users = $this->userLookup();
        $summary = $this->emptySummary();

        foreach ($records as $index => $record) {
            if ($this->isBlankRecord($record)) {
                continue;
            }

            $summary['rows']++;
            $rowNumber = (int) ($record['row'] ?? $index + 1);
            $decisionText = $this->value($record['decision'] ?? null);
            $actionText = $this->value($record['action'] ?? null);
            $membersText = $this->value($record['members'] ?? null);
            $recordHint = $this->value($record['hint'] ?? null) ?: $membersText;

            if ($decisionText === '') {
                $summary['skipped_rows']++;
                $summary['issues'][] = [
                    'row' => $rowNumber,
                    'reason' => 'Decision text column is empty.',
                ];

                continue;
            }

            $decisionable = $this->resolveDecisionable(
                $this->value($record['objectid'] ?? null),
                $this->value($record['globalid'] ?? null),
                $recordHint,
            );

            if (! $decisionable instanceof Model) {
                $summary['skipped_rows']++;
                $summary['issues'][] = [
                    'row' => $rowNumber,
                    'reason' => 'No matching building or housing unit was found by ObjectID / GlobalID.',
                ];

                continue;
            }

            $memberNames = $this->parseCommitteeMembers($membersText);
            $missingUsers = [];
            $matchedUsers = [];

            foreach ($memberNames as $memberName => $title) {
                $user = $users[$this->normalizePersonName($memberName)] ?? null;

                if (! $user instanceof User) {
                    $missingUsers[] = $memberName;
                    $summary['missing_users'][] = $memberName;

                    continue;
                }

                $matchedUsers[] = [$user, $memberName, $title];
            }

            $decisionType = $this->resolveDecisionType($decisionText);

            if ($decisionType === null) {
                $summary['skipped_rows']++;
                $summary['issues'][] = [
                    'row' => $rowNumber,
                    'reason' => 'Decision text is not classified as fully or partially damaged.',
                ];

                continue;
            }

            $payload = [
                'decision_type' => $decisionType,
                'decision_text' => $decisionText,
                'action_text' => $actionText !== '' ? $actionText : null,
                'notes' => $missingUsers === [] ? null : 'Missing committee users: '.implode(', ', $missingUsers),
                'decision_date' => Carbon::today()->toDateString(),
                'status' => CommitteeDecision::STATUS_PENDING_SIGNATURES,
            ];

            if ($dryRun) {
                continue;
            }

            DB::transaction(function () use ($decisionable, $payload, $matchedUsers, &$summary): void {
                $decision = CommitteeDecision::query()->firstOrNew([
                    'decisionable_type' => $decisionable::class,
                    'decisionable_id' => $decisionable->getKey(),
                ]);
                $wasRecentlyCreated = ! $decision->exists;

                $decision->fill($payload)->save();

                if ($wasRecentlyCreated) {
                    $summary['decisions_created']++;
                } else {
                    $summary['decisions_updated']++;
                }

                foreach ($matchedUsers as [$user, $memberName, $title]) {
                    $member = CommitteeMember::query()->firstOrNew(['user_id' => $user->id]);
                    $memberWasRecentlyCreated = ! $member->exists;
                    $member->fill([
                        'name' => $user->name ?: $memberName,
                        'phone' => $user->phone,
                        'title' => $title,
                        'is_active' => true,
                        'is_required' => true,
                        'sort_order' => $member->sort_order ?? 0,
                    ])->save();

                    if ($memberWasRecentlyCreated) {
                        $summary['members_created']++;
                    } else {
                        $summary['members_updated']++;
                    }

                    $signature = CommitteeDecisionSignature::query()->firstOrNew([
                        'committee_decision_id' => $decision->id,
                        'committee_member_id' => $member->id,
                    ]);
                    $signatureWasRecentlyCreated = ! $signature->exists;
                    $signature->fill(['status' => 'pending'])->save();

                    if ($signatureWasRecentlyCreated) {
                        $summary['signatures_created']++;
                    } else {
                        $summary['signatures_updated']++;
                    }
                }
            });
        }

        $summary['missing_users'] = array_values(array_unique($summary['missing_users']));

        return $summary;
    }

    private function resolveDecisionable(string $objectId, string $globalId, string $recordHint): ?Model
    {
        $preferUnit = str_contains($recordHint, 'وحد');

        if ($preferUnit) {
            return $this->findHousingUnit($objectId, $globalId) ?? $this->findBuilding($objectId, $globalId);
        }

        return $this->findBuilding($objectId, $globalId) ?? $this->findHousingUnit($objectId, $globalId);
    }

    private function findBuilding(string $objectId, string $globalId): ?Building
    {
        if ($objectId === '' && $globalId === '') {
            return null;
        }

        return Building::query()
            ->when($objectId !== '', fn ($query) => $query->orWhere('objectid', $objectId))
            ->when($globalId !== '', fn ($query) => $query->orWhere('globalid', $globalId))
            ->first();
    }

    private function findHousingUnit(string $objectId, string $globalId): ?HousingUnit
    {
        if ($objectId === '' && $globalId === '') {
            return null;
        }

        return HousingUnit::query()
            ->when($objectId !== '', fn ($query) => $query->orWhere('objectid', $objectId))
            ->when($globalId !== '', fn ($query) => $query->orWhere('globalid', $globalId))
            ->first();
    }

    private function resolveDecisionType(string $decisionText): ?string
    {
        if (str_contains($decisionText, 'جزئي')) {
            return 'partially_damaged';
        }

        if (str_contains($decisionText, 'كلي')) {
            return 'fully_damaged';
        }

        if (str_contains($decisionText, 'جزئي')) {
            return 'partially_damaged';
        }

        if (str_contains($decisionText, 'كلي') || str_contains($decisionText, 'ظƒظ„ظٹ')) {
            return 'fully_damaged';
        }

        return null;
    }

    /**
     * @return array<string, string|null>
     */
    private function parseCommitteeMembers(string $membersText): array
    {
        $members = [];

        foreach (preg_split('/\//', $membersText) ?: [] as $memberText) {
            $memberText = trim($memberText);

            if ($memberText === '' || str_contains($memberText, 'وحدات')) {
                continue;
            }

            [$name, $title] = array_pad(preg_split('/\s*-\s*/u', $memberText, 2) ?: [], 2, null);
            $name = trim((string) $name);

            if ($name === '') {
                continue;
            }

            $members[$name] = $title !== null ? trim($title) : null;
        }

        return $members;
    }

    /**
     * @return array<string, User>
     */
    private function userLookup(): array
    {
        $lookup = [];

        User::query()
            ->get(['id', 'name', 'name_en', 'phone'])
            ->each(function (User $user) use (&$lookup): void {
                foreach ([$user->name, $user->name_en] as $name) {
                    $normalized = $this->normalizePersonName((string) $name);

                    if ($normalized !== '') {
                        $lookup[$normalized] = $user;
                    }
                }
            });

        return $lookup;
    }

    private function normalizePersonName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/^(?:م\s*\.|م\s*\/|مهندس)\s*/u', '', $name) ?? $name;
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

        return mb_strtolower(trim($name));
    }

    private function normalizeHeaders(array $headerRow): array
    {
        $headers = [];

        foreach ($headerRow as $column => $value) {
            if ($value !== null && trim((string) $value) !== '') {
                $headers[trim((string) $value)] = $column;
            }
        }

        return $headers;
    }

    private function cellByHeader(array $row, array $headers, string $header): string
    {
        $column = $headers[$header] ?? null;

        return $column !== null ? $this->cell($row, $column) : '';
    }

    private function cell(array $row, string $column): string
    {
        $value = $row[$column] ?? null;

        return $this->value($value);
    }

    private function value(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return trim((string) $value);
    }

    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function isBlankRecord(array $record): bool
    {
        foreach (['objectid', 'globalid', 'decision', 'action', 'members', 'hint'] as $key) {
            if ($this->value($record[$key] ?? null) !== '') {
                return false;
            }
        }

        return true;
    }

    private function emptySummary(): array
    {
        return [
            'rows' => 0,
            'decisions_created' => 0,
            'decisions_updated' => 0,
            'members_created' => 0,
            'members_updated' => 0,
            'signatures_created' => 0,
            'signatures_updated' => 0,
            'skipped_rows' => 0,
            'missing_users' => [],
            'issues' => [],
        ];
    }
}
