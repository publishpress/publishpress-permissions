/* Run with jquery and jsdom available on NODE_PATH: node tests/unit/select2-native.js */
'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');

const bundles = [
    'common/lib/select2-4.0.13/js/select2.full.js',
    'common/lib/select2-4.0.13/js/select2.js',
    'common/lib/select2-4.0.13/js/select2.full.min.js',
    'common/lib/select2-4.0.13/js/select2.min.js',
    'modules/presspermit-teaser/common/libs/select2/select2.full.min.js',
    'modules/presspermit-teaser/common/libs/select2/select2.min.js',
];

for (const bundle of bundles) {
    const dom = new JSDOM('<!doctype html><html><body></body></html>', {
        runScripts: 'outside-only',
        url: 'https://example.test/wp-admin/',
    });
    const $ = require('jquery')(dom.window);
    dom.window.$ = dom.window.jQuery = $;
    for (const name of ['isArray', 'isFunction', 'trim']) {
        $[name] = () => { throw new Error(`Deprecated jQuery.${name} in ${bundle}`); };
    }
    dom.window.eval(fs.readFileSync(path.resolve(__dirname, '../..', bundle), 'utf8'));

    // Exercise the role/group multi-select, author picker, and remote user/redirect searches.
    for (const multiple of [false, true]) {
        const select = $('<select><option value="1">First</option><option value="2">Second</option></select>');
        select.prop('multiple', multiple).appendTo('body').select2({ width: '100%' });
        select.val(multiple ? ['1', '2'] : '2').trigger('change');
        assert.equal(select.select2('data').length, multiple ? 2 : 1);
        select.select2('open');
        select.select2('close');
        select.select2('destroy');
        select.remove();
    }
    const select = $('<select multiple></select>').appendTo('body');
    let aborts = 0;
    select.select2({
        tags: true,
        ajax: {
            transport(params, success) {
                success({ results: [{ id: '7', text: 'Search result' }] });
                return { abort() { aborts++; } };
            },
        },
    });
    select.select2('open');
    const adapter = select.data('select2').dataAdapter;
    adapter.query({ term: '  search  ' }, data => assert.ok(data.results.length));
    adapter.query({ term: '' }, data => assert.ok(data.results.length));
    assert.ok(aborts > 0, 'a subsequent remote query aborts the previous request');
    select.select2('destroy');
    dom.window.close();
    console.log(`OK ${bundle}`);
}
