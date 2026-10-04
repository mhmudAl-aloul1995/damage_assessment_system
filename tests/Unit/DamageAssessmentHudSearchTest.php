<?php

use Symfony\Component\Process\Process;

it('builds hud map queries for pasted object ids while preserving global id search', function (): void {
    $script = <<<'JS'
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync('app/Modules/DamageAssessment/views/dashboard/hud.blade.php', 'utf8');
const start = source.indexOf('function buildHudArcgisWhere()');
const end = source.indexOf('function updateHudFilterCount(', start);
const inputs = {};
const context = vm.createContext({
    document: {
        getElementById: id => inputs[id],
        querySelector: () => null,
    },
    hudSelectedValues: () => [],
    hudArcgisFieldName: field => field.toUpperCase(),
    escapeArcgisValue: value => value.replace(/'/g, "''"),
});
vm.runInContext(source.slice(start, end), context);

for (const input of ['123,456,789', '123\r\n456\r\n789', '123\t456 789', '123،456؛789', ', 00123;456\n789,123; ']) {
    inputs.hud_filter_search = { value: input };
    assert.equal(context.buildHudArcgisWhere(), 'OBJECTID IN (123, 456, 789)');
}
for (const input of ['123', '00123', '123,123']) {
    inputs.hud_filter_search = { value: input };
    assert.equal(context.buildHudArcgisWhere(), 'OBJECTID IN (123)');
}
for (const input of ['', ' \n\t ']) {
    inputs.hud_filter_search = { value: input };
    assert.equal(context.buildHudArcgisWhere(), '1=1');
}
inputs.hud_filter_search = { value: 'abc-def-123' };
assert.equal(context.buildHudArcgisWhere(), "GLOBALID LIKE '%abc-def-123%'");
inputs.hud_filter_search = { value: "123,456') OR 1=1--" };
assert.equal(context.buildHudArcgisWhere(), "GLOBALID LIKE '%123,456'') OR 1=1--%'");
inputs.hud_filter_search = { value: '123,456' };
inputs.hud_filter_building_name = { value: 'Building' };
assert.equal(context.buildHudArcgisWhere(), "BUILDING_NAME LIKE '%Building%' AND OBJECTID IN (123, 456)");
delete inputs.hud_filter_building_name;
inputs.hud_filter_search = { value: Array.from({ length: 1500 }, (_, index) => index + 1).join('\n') };
assert.equal(context.buildHudArcgisWhere(), 'OBJECTID IN (' + Array.from({ length: 1500 }, (_, index) => index + 1).join(', ') + ')');
console.log('HUD ObjectID filtering verified');
JS;

    $process = new Process(['node', '--input-type=module', '-e', $script], dirname(__DIR__, 2));
    $process->run();

    expect($process->getExitCode())->toBe(0, $process->getErrorOutput());
    expect($process->getOutput())->toContain('HUD ObjectID filtering verified');
});
