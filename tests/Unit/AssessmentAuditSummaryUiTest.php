<?php

test('building summary prefers the visible objectid answer over stale summary metadata', function () {
    $view = file_get_contents(dirname(__DIR__, 2).'/app/Modules/DamageAssessment/views/audit/assessmentAudit.blade.php');

    expect($view)
        ->toContain('function buildingSummaryValue(field, row)')
        ->toContain("if (field === 'objectid')")
        ->toContain("let answerValue = cleanAuditText($('<div>').html(row.answer || '').text())")
        ->toContain("return cleanAuditText(row.summaryValue || $('<div>').html(row.answer || '').text())")
        ->not->toContain("let value = row ? cleanAuditText(row.summaryValue || $('<div>').html(row.answer || '').text()) : cleanAuditText(BUILDING_SUMMARY_VALUES[field] || '-')");
});
