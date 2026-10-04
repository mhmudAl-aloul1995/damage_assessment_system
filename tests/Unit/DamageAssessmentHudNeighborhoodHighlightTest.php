<?php

use Symfony\Component\Process\Process;

it('highlights matching neighborhoods and clears stale spatial query results', function (): void {
    $script = <<<'JS'
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync('app/Modules/DamageAssessment/views/dashboard/hud.blade.php', 'utf8');
const start = source.indexOf('let hudNeighborhoodFilterVersion = 0;');
const end = source.indexOf('function updateHudFilterCount(', start);
const highlight = { definitionExpression: '1=0' };
const spatialReference = { wkid: 3857 };
const search = { value: '123,456' };
const buildingQueries = [];
const spatialQueries = [];
const errors = [];
let buildingIds = [123, 456];
let idQueries = 0;
let omitGeometry = false;
const buildings = {
    objectIdField: 'OBJECTID',
    createQuery: () => ({}),
    queryObjectIds: async query => {
        idQueries++;
        assert.ok(query.where.includes('OBJECTID'));
        return buildingIds;
    },
    queryFeatures: async query => {
        buildingQueries.push(query);
        assert.equal(query.returnGeometry, true);
        assert.equal(query.outSpatialReference, spatialReference);
        return { features: query.objectIds.map(id => ({ geometry: omitGeometry ? null : { id } })) };
    },
};
const neighborhoods = {
    objectIdField: 'NEIGHBORHOOD_ID',
    spatialReference,
    load: async () => {},
    createQuery: () => ({}),
    queryObjectIds: async query => {
        spatialQueries.push(query);
        assert.equal(query.spatialRelationship, 'intersects');
        assert.ok(query.geometry);
        const geometries = query.geometry.parts || [query.geometry];
        return geometries.map(geometry => geometry.id < 200 ? 10 : 20);
    },
};
const context = vm.createContext({
    document: { getElementById: () => search },
    neighborhoodsBoundaryLayerUrl: 'https://example.test/FeatureServer/0',
    filteredNeighborhoodsLayer: highlight,
    buildingsLayer: buildings,
    neighborhoodsBoundaryLayer: neighborhoods,
    geometryEngine: { union: geometries => ({ parts: geometries }) },
    console: { error: (...args) => errors.push(args) },
});
vm.runInContext(source.slice(start, end), context);

const where = "OBJECTID IN (123, 456) AND municipalitie = 'Gaza'";
await context.updateHudNeighborhoodHighlight(where);
assert.equal(highlight.definitionExpression, 'NEIGHBORHOOD_ID IN (10, 20)');
assert.equal(buildingQueries[0].where, where);
assert.deepEqual(Array.from(buildingQueries[0].objectIds), [123, 456]);
assert.equal(spatialQueries[0].geometry.parts.length, 2);

buildingIds = Array.from({ length: 205 }, (_, index) => index + 1);
buildingQueries.length = 0;
await context.updateHudNeighborhoodHighlight('OBJECTID IN (1, 2, 3)');
assert.deepEqual(buildingQueries.map(query => query.objectIds.length), [100, 100, 5]);
assert.equal(highlight.definitionExpression, 'NEIGHBORHOOD_ID IN (10, 20)');

buildingIds = [];
await context.updateHudNeighborhoodHighlight(where);
assert.equal(highlight.definitionExpression, '1=0');

buildingIds = [123];
omitGeometry = true;
await context.updateHudNeighborhoodHighlight(where);
assert.equal(highlight.definitionExpression, '1=0');
omitGeometry = false;

const previousQueryCount = idQueries;
for (const value of ['', ' \n ', 'abc-def', '123,invalid']) {
    search.value = value;
    highlight.definitionExpression = 'NEIGHBORHOOD_ID IN (10)';
    await context.updateHudNeighborhoodHighlight(where);
    assert.equal(highlight.definitionExpression, '1=0');
}
assert.equal(idQueries, previousQueryCount);

search.value = '123';
const originalSpatialQuery = neighborhoods.queryObjectIds;
let finishOldQuery;
neighborhoods.queryObjectIds = () => new Promise(resolve => { finishOldQuery = resolve; });
const pendingReset = context.updateHudNeighborhoodHighlight(where);
await new Promise(resolve => setImmediate(resolve));
context.clearHudNeighborhoodHighlight();
finishOldQuery([10]);
await pendingReset;
assert.equal(highlight.definitionExpression, '1=0');

const pendingOldFilter = context.updateHudNeighborhoodHighlight(where);
await new Promise(resolve => setImmediate(resolve));
neighborhoods.queryObjectIds = originalSpatialQuery;
buildingIds = [456];
search.value = '456';
await context.updateHudNeighborhoodHighlight('OBJECTID IN (456)');
assert.equal(highlight.definitionExpression, 'NEIGHBORHOOD_ID IN (20)');
finishOldQuery([10]);
await pendingOldFilter;
assert.equal(highlight.definitionExpression, 'NEIGHBORHOOD_ID IN (20)');

neighborhoods.queryObjectIds = async () => [];
await context.updateHudNeighborhoodHighlight(where);
assert.equal(highlight.definitionExpression, '1=0');
neighborhoods.queryObjectIds = async () => { throw new Error('Unavailable'); };
await context.updateHudNeighborhoodHighlight(where);
assert.equal(highlight.definitionExpression, '1=0');
assert.equal(errors.length, 1);
console.log('HUD neighborhood highlighting verified');
JS;

    $process = new Process(['node', '--input-type=module', '-e', $script], dirname(__DIR__, 2));
    $process->run();

    expect($process->getExitCode())->toBe(0, $process->getErrorOutput());
    expect($process->getOutput())->toContain('HUD neighborhood highlighting verified');
});
