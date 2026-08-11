<?php

return [
    'source_path' => env('LAWYER_AUDIT_ASSIGNMENTS_EXCEL_PATH', storage_path('app/lawyer-audit-assignments.xlsx')),
    'worksheet_name' => env('LAWYER_AUDIT_ASSIGNMENTS_WORKSHEET', 'التأهيل'),
];
