<?php

use Symfony\Component\Process\Process;

it('keeps preview filtering summaries pagination and map features consistent', function (): void {
    $script = <<<'JS'
import assert from 'node:assert/strict';
import { filterPreviewRecords, summarizePreviewRecords, paginatePreviewRecords, previewMapFeatures } from './assets/js/custom/DamageAssessment/dashboard-preview.js';
const statuses = {completed:{label:'مكتمل',color:'#50cd89'},review:{label:'بانتظار المراجعة',color:'#ffc700'},blocked:{label:'متعذّر التقييم',color:'#f1416c'}};
const records = [
    {id:1,code:'DEMO-001',sector:'buildings',sectorLabel:'المباني',governorate:'غزة',municipality:'غزة',date:'2026-09-20',status:'completed',longitude:34.46,latitude:31.51},
    {id:2,code:'DEMO-002',sector:'buildings',sectorLabel:'المباني',governorate:'غزة',municipality:'الزهراء',date:'2026-09-21',status:'review',longitude:34.4,latitude:31.47},
    {id:3,code:'DEMO-003',sector:'housing',sectorLabel:'الوحدات السكانية',governorate:'رفح',municipality:'رفح',date:'2026-09-22',status:'blocked',longitude:34.25,latitude:31.28},
    {id:4,code:'DEMO-004',sector:'buildings',sectorLabel:'المباني',governorate:'رفح',municipality:'رفح',date:'2026-09-23',status:'completed',longitude:34.26,latitude:31.29},
];
const ids = filters => filterPreviewRecords(records,filters,statuses).map(row=>row.id);
assert.deepEqual(ids({sector:'all'}),[1,2,3,4]);
assert.deepEqual(ids({sector:'buildings',governorate:'غزة',municipality:'الزهراء',from:'2026-09-21',to:'2026-09-21',query:'demo-002'}),[2]);
assert.deepEqual(ids({from:'2026-09-20',to:'2026-09-22'}),[1,2,3]);
assert.deepEqual(ids({query:'  DEMO-003  '}),[3]);
assert.deepEqual(ids({query:'المراجعة'}),[2]);
assert.deepEqual(ids({governorate:'رفح',municipality:'الزهراء'}),[]);
assert.deepEqual(ids({from:'2026-10-01'}),[]);
assert.deepEqual(ids({query:'لا يوجد'}),[]);
const filtered = filterPreviewRecords(records,{sector:'buildings'},statuses);
assert.deepEqual(summarizePreviewRecords(filtered),{total:3,completed:2,review:1,blocked:0});
const features = previewMapFeatures(filtered,statuses);
assert.deepEqual(features.map(feature=>feature.attributes.id),[1,2,4]);
assert.equal(features[0].geometry.longitude,34.46);
assert.equal(features[1].symbol.color,statuses.review.color);
assert.deepEqual(previewMapFeatures([],statuses),[]);
assert.deepEqual(summarizePreviewRecords([]),{total:0,completed:0,review:0,blocked:0});
const first = paginatePreviewRecords(filtered,1,2);
const last = paginatePreviewRecords(filtered,99,2);
assert.deepEqual(first.rows.map(row=>row.id),[1,2]);
assert.deepEqual(last.rows.map(row=>row.id),[4]);
assert.deepEqual([last.page,last.pages,last.start,last.end],[2,2,3,3]);
assert.deepEqual(paginatePreviewRecords([],4),{rows:[],page:1,pages:1,start:0,end:0});
assert.equal(records.length,4);
console.log('Preview behavior verified');
JS;

    $process = new Process(['node', '--input-type=module', '-e', $script], dirname(__DIR__, 2));
    $process->run();

    expect($process->getExitCode())->toBe(0, $process->getErrorOutput());
    expect($process->getOutput())->toContain('Preview behavior verified');
});
