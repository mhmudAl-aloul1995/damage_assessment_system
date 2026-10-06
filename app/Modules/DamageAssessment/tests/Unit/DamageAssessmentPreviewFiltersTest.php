<?php

use Symfony\Component\Process\Process;

it('filters live GIS layers safely and renders their actual geometry types', function (): void {
    $script = <<<'JS'
import assert from 'node:assert/strict';
import { buildGisWhere, resolveGisField, createDamageRenderer } from './assets/js/custom/DamageAssessment/dashboard-preview.js';
const fields = [
    {name:'Governorate',type:'string'}, {name:'Municipalitie',type:'string'}, {name:'Neighborhood',type:'string'},
    {name:'end',type:'date'}, {name:'CreationDate',type:'date'},
];
assert.equal(resolveGisField(fields,'governorate').name,'Governorate');
assert.equal(buildGisWhere([{name:'unit_municipalitie',type:'string'}],{municipalitie:'Gaza'},'end','OBJECTID'), '"unit_municipalitie" = \'Gaza\'');
assert.equal(buildGisWhere(fields,{},'end','OBJECTID'), '1=1');
assert.equal(buildGisWhere(fields,{},'end','OBJECTID',[]), '1=0');
assert.equal(buildGisWhere(fields,{},'end','OBJECTID',[2,2,3,'1 OR 1=1']), '("OBJECTID" IN (2,3))');
const where = buildGisWhere(fields,{governorate:"Gaza's",municipalitie:'Gaza',neighborhood:'Rimal',from:'2026-09-01',to:'2026-09-30'},'end','OBJECTID');
assert.equal(where, '"Governorate" = \'Gaza\'\'s\' AND "Municipalitie" = \'Gaza\' AND "Neighborhood" = \'Rimal\' AND "end" >= TIMESTAMP \'2026-09-01 00:00:00\' AND "end" < TIMESTAMP \'2026-10-01 00:00:00\'');
assert.match(buildGisWhere(fields,{to:'2024-02-29'},'creationdate','OBJECTID'), /2024-03-01/);
assert.throws(()=>buildGisWhere(fields,{from:'2026-02-30'},'end','OBJECTID'));
assert.throws(()=>buildGisWhere(fields,{from:'2026-09-02',to:'2026-09-01'},'end','OBJECTID'));
assert.throws(()=>buildGisWhere([],{governorate:'Gaza'},'end','OBJECTID'));
assert.throws(()=>buildGisWhere([],{from:'2026-09-01'},'end','OBJECTID'));
assert.throws(()=>buildGisWhere([{name:'end',type:'string'}],{from:'2026-09-01'},'end','OBJECTID'));
const damage = {name:'building_damage_status'};
assert.equal(createDamageRenderer('polygon',damage).defaultSymbol.type,'simple-fill');
assert.equal(createDamageRenderer('polyline',damage).defaultSymbol.type,'simple-line');
assert.equal(createDamageRenderer('point',damage).defaultSymbol.type,'simple-marker');
assert.equal(createDamageRenderer('polygon',null),null);
console.log('Live GIS behavior verified');
JS;

    $process = new Process(['node', '--input-type=module', '-e', $script], dirname(__DIR__, 5));
    $process->run();

    expect($process->getExitCode())->toBe(0, $process->getErrorOutput());
    expect($process->getOutput())->toContain('Live GIS behavior verified');
});
