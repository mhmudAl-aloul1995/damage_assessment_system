import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { test } from 'node:test';

const view = readFileSync('app/Modules/DamageAssessment/resources/views/audit/audit.blade.php', 'utf8');
const script = view.slice(view.indexOf('let engineerChangeLogTable = null;'), view.indexOf('const renderEngineerChangeLogValue'))
    .replace(/@json\(route\('([^']+)'\)\)/g, (_, route) => JSON.stringify('/' + route));

function setup() {
    const controls = new Map();
    const events = new Map();
    let reloads = 0;
    let picker;
    const $ = selector => {
        const ids = selector.split(',').map(id => id.trim());
        ids.forEach(id => {
            if (!controls.has(id)) controls.set(id, { value: '' });
        });
        return {
            val(value) {
                if (value === undefined) return controls.get(ids[0]).value;
                ids.forEach(id => { controls.get(id).value = value; });
                return this;
            },
            prop(key, value) { controls.get(ids[0])[key] = value; return this; },
            text(value) { controls.get(ids[0]).text = value; return this; },
            on(event, handler) { events.set(selector + ':' + event, handler); return this; },
            trigger() { return this; },
        };
    };
    $.param = filters => new URLSearchParams(filters).toString();
    $('#engineer_change_log_engineer, #engineer_change_log_field, #engineer_change_log_type, #engineer_change_log_search');
    const flatpickr = (selector, config) => {
        picker = {
            config,
            selectedDates: [],
            formatDate: date => date,
            setDate(dates, trigger = false) {
                this.selectedDates = dates;
                if (trigger) config.onChange(dates, dates.join(' إلى '), this);
            },
            clear(trigger = true) { this.setDate([], trigger); },
            close() { config.onClose(this.selectedDates, '', this); },
        };
        return picker;
    };
    flatpickr.l10ns = { ar: {} };
    const context = { $, flatpickr, clearTimeout() {}, window: { location: { href: '' } } };
    runInNewContext(script + '\nengineerChangeLogTable = { ajax: { reload: recordReload } };', {
        ...context, recordReload() { reloads++; },
    });
    return { picker, controls, context, reloads: () => reloads, click: id => events.get('#' + id + ':click')() };
}

test('anchors the calendar to its input inside the modal instead of document scroll coordinates', () => {
    const { picker } = setup();
    assert.equal(picker.config.static, true);
    assert.equal(picker.config.appendTo, undefined);
    assert.equal(picker.config.disableMobile, true);
});

test('applies a completed range once and exports the same dates and filters', () => {
    const ui = setup();
    ui.picker.setDate(['2026-10-01'], true);
    assert.equal(ui.reloads(), 0);
    ui.picker.setDate(['2026-10-01', '2026-10-14'], true);
    ui.picker.close();
    assert.equal(ui.reloads(), 1);
    ui.controls.get('#engineer_change_log_engineer').value = '12';
    ui.click('engineer_change_log_export');
    const url = new URL(ui.context.window.location.href, 'http://localhost');
    assert.equal(url.searchParams.get('from_date'), '2026-10-01');
    assert.equal(url.searchParams.get('to_date'), '2026-10-14');
    assert.equal(url.searchParams.get('engineer_id'), '12');
});

test('closing a single selected date applies that full day and clearing removes the range', () => {
    const ui = setup();
    ui.picker.setDate(['2026-10-14'], true);
    ui.picker.close();
    assert.deepEqual([...ui.picker.selectedDates], ['2026-10-14', '2026-10-14']);
    assert.equal(ui.reloads(), 1);
    ui.click('engineer_change_log_clear_dates');
    assert.equal(ui.reloads(), 2);
    assert.equal(ui.controls.get('#engineer_change_log_clear_dates').disabled, true);
    ui.click('engineer_change_log_export');
    assert.equal(new URL(ui.context.window.location.href, 'http://localhost').searchParams.get('from_date'), '');
});

test('reset clears all filters and refreshes the table only once', () => {
    const ui = setup();
    ui.picker.setDate(['2026-10-01', '2026-10-14'], true);
    for (const id of ['engineer', 'field', 'type', 'search']) {
        ui.controls.get('#engineer_change_log_' + id).value = 'filter';
    }
    ui.click('engineer_change_log_reset');
    assert.equal(ui.reloads(), 2);
    assert.equal(ui.picker.selectedDates.length, 0);
    for (const id of ['engineer', 'field', 'type', 'search']) {
        assert.equal(ui.controls.get('#engineer_change_log_' + id).value, '');
    }
});
