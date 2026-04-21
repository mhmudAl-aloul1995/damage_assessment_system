<?php
require dirname(__DIR__, 2).'/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$path = 'C:/Users/hp/Downloads/RD01-Damage Assessment of Roads Facilities.xlsx';
$sheet = IOFactory::load($path)->getSheetByName('choices');
$highestRow = min($sheet->getHighestRow(), 200);
$rows = $sheet->rangeToArray('A1:J'.$highestRow, null, true, true, true);
foreach ($rows as $index => $row) {
    $values = array_values(array_filter($row, static fn ($value) => $value !== null && $value !== ''));
    if ($values !== []) {
        echo $index.': '.json_encode($values, JSON_UNESCAPED_UNICODE).PHP_EOL;
    }
}
