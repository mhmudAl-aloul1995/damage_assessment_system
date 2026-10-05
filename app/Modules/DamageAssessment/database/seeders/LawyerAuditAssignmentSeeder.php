<?php

namespace Database\Seeders;

use App\Models\LawyerAuditAssignment;
use App\Support\Audit\RestrictedLawyerAuditAccess;
use Illuminate\Database\Seeder;
use RuntimeException;
use XMLReader;
use ZipArchive;

class LawyerAuditAssignmentSeeder extends Seeder
{
    public function run(): void
    {
        $path = (string) config('lawyer_audit_assignments.source_path');

        if (! is_file($path)) {
            throw new RuntimeException("Lawyer audit assignments Excel file was not found at [{$path}].");
        }

        $sharedStrings = $this->sharedStrings($path);
        $records = [];
        $excelIndex = 0;

        foreach ($this->worksheetPaths($path) as $worksheetPath) {
            $headerMap = [];

            foreach ($this->worksheetRows($path, $worksheetPath, $sharedStrings) as $rowNumber => $row) {
                if ($headerMap === []) {
                    $headerMap = $this->headerMap($row);

                    if ($this->hasRequiredHeaders($headerMap)) {
                        continue;
                    }

                    $headerMap = [];

                    continue;
                }

                $housingGlobalid = RestrictedLawyerAuditAccess::normalizeGlobalid($this->value($row, $headerMap, 'GlobalID'));
                $buildingGlobalid = RestrictedLawyerAuditAccess::normalizeGlobalid($this->value($row, $headerMap, 'ParentGlobalID'));

                if ($housingGlobalid === '' || $buildingGlobalid === '') {
                    continue;
                }

                $excelIndex++;

                $records[] = [
                    'excel_index' => $excelIndex,
                    'source_row_number' => $rowNumber,
                    'lawyer_name' => RestrictedLawyerAuditAccess::lawyerForExcelIndex($excelIndex),
                    'building_objectid' => $this->integerValue($row, $headerMap, 'ObjectID للمبنى'),
                    'housing_unit_objectid' => $this->integerValue($row, $headerMap, 'ObjectID للوحدة'),
                    'building_globalid' => $buildingGlobalid,
                    'housing_unit_globalid' => $housingGlobalid,
                    'unit_owner' => $this->stringValue($row, $headerMap, '7.4 Unit Owner'),
                    'owner_full_name' => $this->ownerFullName($row, $headerMap),
                    'id_number' => $this->stringValue($row, $headerMap, '9.1 ID Number'),
                    'mobile_number' => $this->stringValue($row, $headerMap, '7.5 Mobile number'),
                    'unit_damage_status' => $this->stringValue($row, $headerMap, '7.7 Unit Damage Status'),
                    'floor_number' => $this->stringValue($row, $headerMap, '8.1 Floor Number'),
                    'housing_unit_number' => $this->stringValue($row, $headerMap, '8.2 Housing Unit Number'),
                    'governorate' => $this->stringValue($row, $headerMap, '12.7 Governorate'),
                    'locality' => $this->stringValue($row, $headerMap, '12.8 Locality'),
                    'neighborhood' => $this->stringValue($row, $headerMap, '12.9 Neighborhood'),
                    'street' => $this->stringValue($row, $headerMap, '12.10 Street'),
                    'closest_facility' => $this->stringValue($row, $headerMap, '12.11 Closest Facility'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($records !== []) {
                break;
            }
        }

        if ($records === []) {
            throw new RuntimeException('No lawyer audit assignments were imported. Make sure the Excel file has GlobalID and ParentGlobalID columns.');
        }

        LawyerAuditAssignment::query()->delete();
        LawyerAuditAssignment::query()->insert($records);

        $this->command?->info('Imported '.count($records).' lawyer audit assignments.');
    }

    /**
     * @return array<int, string>
     */
    private function sharedStrings(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException("Unable to open Excel file [{$path}].");
        }

        if ($zip->locateName('xl/sharedStrings.xml') === false) {
            $zip->close();

            return [];
        }

        $reader = new XMLReader;
        $reader->open('zip://'.str_replace('\\', '/', $path).'#xl/sharedStrings.xml');

        $strings = [];

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->name !== 'si') {
                continue;
            }

            $value = '';
            $depth = $reader->depth;

            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->name === 'si' && $reader->depth === $depth) {
                    break;
                }

                if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 't') {
                    $value .= $reader->readString();
                }
            }

            $strings[] = $value;
        }

        $reader->close();
        $zip->close();

        return $strings;
    }

    /**
     * @return list<string>
     */
    private function worksheetPaths(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException("Unable to open Excel file [{$path}].");
        }

        $paths = [];
        $preferredPath = $this->worksheetPathByName(
            $zip,
            (string) config('lawyer_audit_assignments.worksheet_name')
        );

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);

            if (is_string($name) && preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name) === 1) {
                $paths[] = $name;
            }
        }

        $zip->close();
        sort($paths, SORT_NATURAL);

        if ($preferredPath !== null) {
            $paths = array_values(array_unique([$preferredPath, ...$paths]));
        }

        if ($paths === []) {
            throw new RuntimeException("No worksheets were found in Excel file [{$path}].");
        }

        return $paths;
    }

    private function worksheetPathByName(ZipArchive $zip, string $worksheetName): ?string
    {
        $worksheetName = trim($worksheetName);

        if ($worksheetName === '') {
            return null;
        }

        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relationshipsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if (! is_string($workbookXml) || ! is_string($relationshipsXml)) {
            return null;
        }

        $workbook = new \SimpleXMLElement($workbookXml);
        $workbook->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $relationshipId = null;

        foreach ($workbook->xpath('//main:sheets/main:sheet') ?: [] as $sheet) {
            if (trim((string) $sheet['name']) !== $worksheetName) {
                continue;
            }

            $relationshipId = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];

            break;
        }

        if ($relationshipId === null || $relationshipId === '') {
            return null;
        }

        $relationships = new \SimpleXMLElement($relationshipsXml);

        foreach ($relationships->children('http://schemas.openxmlformats.org/package/2006/relationships')->Relationship as $relationship) {
            if ((string) $relationship['Id'] !== $relationshipId) {
                continue;
            }

            $target = ltrim((string) $relationship['Target'], '/');

            return str_starts_with($target, 'xl/')
                ? $target
                : 'xl/'.$target;
        }

        return null;
    }

    /**
     * @param  array<int, string>  $sharedStrings
     * @return \Generator<int, array<int, mixed>>
     */
    private function worksheetRows(string $path, string $worksheetPath, array $sharedStrings): \Generator
    {
        $reader = new XMLReader;
        $reader->open('zip://'.str_replace('\\', '/', $path).'#'.$worksheetPath);

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->name !== 'row') {
                continue;
            }

            $rowNumber = (int) $reader->getAttribute('r');
            $row = [];
            $depth = $reader->depth;

            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->name === 'row' && $reader->depth === $depth) {
                    break;
                }

                if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'c') {
                    $cellReference = (string) $reader->getAttribute('r');
                    $cellType = (string) $reader->getAttribute('t');
                    $row[$this->cellColumnIndex($cellReference)] = $this->cellValue($reader, $cellType, $sharedStrings);
                }
            }

            yield $rowNumber => $row;
        }

        $reader->close();
    }

    /**
     * @param  array<int, string>  $sharedStrings
     */
    private function cellValue(XMLReader $reader, string $cellType, array $sharedStrings): mixed
    {
        $value = null;
        $depth = $reader->depth;

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->name === 'c' && $reader->depth === $depth) {
                break;
            }

            if ($reader->nodeType === XMLReader::ELEMENT && in_array($reader->name, ['v', 't'], true)) {
                $value = $reader->readString();
            }
        }

        if ($cellType === 's' && is_numeric($value)) {
            return $sharedStrings[(int) $value] ?? null;
        }

        return $value;
    }

    private function cellColumnIndex(string $cellReference): int
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($cellReference)) ?: 'A';
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index - 1;
    }

    /**
     * @param  array<int, mixed>  $headers
     * @return array<string, int>
     */
    private function headerMap(array $headers): array
    {
        $map = [];

        foreach ($headers as $index => $header) {
            $header = trim((string) $header);

            if ($header === '') {
                continue;
            }

            $map[$header] = $index;
            $map[$this->normalizedHeader($header)] = $index;
        }

        return $map;
    }

    /**
     * @param  array<string, int>  $headerMap
     */
    private function hasRequiredHeaders(array $headerMap): bool
    {
        return $this->hasHeader($headerMap, 'GlobalID')
            && $this->hasHeader($headerMap, 'ParentGlobalID');
    }

    /**
     * @param  array<string, int>  $headerMap
     */
    private function hasHeader(array $headerMap, string $header): bool
    {
        return array_key_exists($header, $headerMap)
            || array_key_exists($this->normalizedHeader($header), $headerMap);
    }

    private function normalizedHeader(string $header): string
    {
        return strtolower((string) preg_replace('/\s+/u', '', trim($header)));
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $headerMap
     */
    private function value(array $row, array $headerMap, string $header): mixed
    {
        $index = $headerMap[$header] ?? $headerMap[$this->normalizedHeader($header)] ?? null;

        return $index === null ? null : ($row[$index] ?? null);
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $headerMap
     */
    private function stringValue(array $row, array $headerMap, string $header): ?string
    {
        $value = trim((string) $this->value($row, $headerMap, $header));

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $headerMap
     */
    private function integerValue(array $row, array $headerMap, string $header): ?int
    {
        $value = $this->value($row, $headerMap, $header);

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $headerMap
     */
    private function ownerFullName(array $row, array $headerMap): ?string
    {
        $parts = array_filter([
            $this->stringValue($row, $headerMap, '9.3.1 First Name'),
            $this->stringValue($row, $headerMap, '9.3.2 Second Name (Father)'),
            $this->stringValue($row, $headerMap, '9.3.3 Third Name (Grandfather)'),
            $this->stringValue($row, $headerMap, '9.3.4 Last Name'),
        ]);

        return $parts === [] ? null : implode(' ', $parts);
    }
}
