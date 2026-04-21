<?php
require dirname(__DIR__, 2).'/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$path = 'C:/Users/hp/Downloads/RD01-Damage Assessment of Roads Facilities.xlsx';
echo file_exists($path) ? "FOUND\n" : "MISSING\n";
try {
    $spreadsheet = IOFactory::load($path);
    foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
        echo 'SHEET: '.$sheet->getTitle().PHP_EOL;
        $highestRow = min($sheet->getHighestRow(), 12);
        $highestColumn = $sheet->getHighestColumn();
        $rows = $sheet->rangeToArray('A1:'.$highestColumn.$highestRow, null, true, true, true);
        foreach ($rows as $index => $row) {
            $values = array_values(array_filter($row, static fn ($value) => $value !== null && $value !== ''));
            if ($values !== []) {
                echo $index.': '.json_encode($values, JSON_UNESCAPED_UNICODE).PHP_EOL;
            }
        }
        echo str_repeat('-', 40).PHP_EOL;
    }
} catch (Throwable $e) {
    echo 'ERROR: '.$e->getMessage().PHP_EOL;
}
