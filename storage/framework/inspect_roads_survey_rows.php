<?php
require dirname(__DIR__, 2).'/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$path = 'C:/Users/hp/Downloads/RD01-Damage Assessment of Roads Facilities.xlsx';
$sheet = IOFactory::load($path)->getSheetByName('survey');
$highestRow = min($sheet->getHighestRow(), 220);
$rows = $sheet->rangeToArray('A1:F'.$highestRow, null, true, true, true);
foreach ($rows as $index => $row) {
    $values = [$row['A'] ?? null, $row['B'] ?? null, $row['C'] ?? null, $row['D'] ?? null, $row['E'] ?? null, $row['F'] ?? null];
    $trimmed = array_map(static fn ($value) => is_string($value) ? trim($value) : $value, $values);
    if (implode('', array_filter($trimmed, static fn ($value) => $value !== null && $value !== '')) !== '') {
        echo $index . ': ' . json_encode($trimmed, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
}
