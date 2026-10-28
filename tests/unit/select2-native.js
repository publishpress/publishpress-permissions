/* Run with jquery and jsdom available on NODE_PATH: node tests/unit/select2-native.js */
'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');

const bundles = [
    'common/lib/select2-4.1.0/dist/js/select2.full.js',
    'common/lib/select2-4.1.0/dist/js/select2.js',
    'common/lib/select2-4.1.0/dist/js/select2.full.min.js',
    'common/lib/select2-4.1.0/dist/js/select2.min.js',
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
        if (multiple) {
            select.data('select2').$selection.find('.select2-selection__choice__remove').first().trigger('click');
            assert.deepEqual(Array.from(select.val()), ['2'], 'the 4.1 remove button deselects its own item');
        }
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

    dom.window.CSS = { escape: $.escapeSelector };
    dom.window.ppListbox = {
        placeholder: { user: 'Search for a user', pp_group: 'Search for a group' },
        omit_admins: '0',
        metagroups: '1',
    };
    $.ajax = () => {
        const request = $.Deferred();
        request.abort = () => {};
        request.resolve('<option value="10">Found user</option>');
        return request;
    };

    // Exercise the real Permissions initializer, including its CSS option and special topic IDs.
    for (const script of ['listbox.dev.js', 'listbox.js']) {
        dom.window.eval(fs.readFileSync(path.resolve(__dirname, '../../common/js', script), 'utf8'));
        for (const topic of ['member', 'select-author', 'read:post:user']) {
            const multiple = topic !== 'select-author';
            const id = topic.includes(':') ? topic : `${topic}::user`;
            const fixture = $('<table class="pp-item-exceptions-ui"><tbody><tr><td class="picker"></td><td class="pp-members-current"><select id="member"><option value="9">Existing member</option></select></td><td class="pp-current-item-exceptions"><table><tr><td><input type="hidden" value="9"></td></tr></table></td></tr></tbody></table>').appendTo('body');
            const picker = $('<select><option value="1">First</option><option value="2">Second</option></select>')
                .attr('id', 'v2_agent_search_text_' + id).prop('multiple', multiple).val(multiple ? ['1'] : '1')
                .appendTo(fixture.find('.picker'));
            const author = $('<select id="post_author_override"><option value="3">Original author</option></select>').appendTo(fixture.find('.picker'));
            fixture.find('.picker').DynamicListbox({
                topic: $.escapeSelector(topic),
                search_id: 'agent_search_text_' + $.escapeSelector(topic),
                results_id: 'agent_results_' + $.escapeSelector(topic),
                button_id: 'agent_submit_' + $.escapeSelector(topic),
                agent_type: 'user',
                agent_id: 5,
                ajaxurl: 'https://example.test/wp-admin/',
            });
            const instance = picker.data('select2');
            assert.ok(instance, `${script}: ${topic} initializes`);
            assert.ok(instance.$selection.hasClass('pp-select2-container'), '4.1 selectionCssClass preserves the Permissions styling hook');
            if (multiple) {
                assert.equal(instance.$selection.find('textarea.select2-search__field').length, 1, '4.1 multi-select uses an inline textarea');
                assert.equal(instance.$selection.find('button.select2-selection__choice__remove').length, 1, 'selected items retain a usable remove button');
            }
            const results = instance.dataAdapter.ajaxOptions.processResults('<option value="9">Existing</option><option value="10">New</option>');
            assert.deepEqual(Array.from(results.results, item => item.id), ['10'], 'current entries are omitted from AJAX search results');
            picker.select2('open');
            assert.ok($('.select2-dropdown.pp-select2-dropdown').length, '4.1 preserves the custom dropdown class');
            picker.select2('close');
            picker.val(multiple ? ['1', '2'] : '2').trigger('change').trigger({ type: 'select2:select', params: { data: { id: '2', text: 'Second' } } });
            if (topic === 'select-author') {
                assert.equal(author.val(), '2', 'author selection updates the Classic Editor override');
            } else if (topic.includes(':')) {
                const inputs = () => fixture.find('input[name="pp_exceptions[post][read][user][item][2]"]');
                assert.equal(inputs().last().val(), '2', 'a selected user is included in the permissions submission');
                picker.val(['1']).trigger('change').trigger({ type: 'select2:unselect', params: { data: { id: '2', text: 'Second' } } });
                assert.equal(inputs().last().val(), '', 'an unselected user is cleared in the permissions submission');
            }
            picker.select2('destroy');
            fixture.remove();
        }
    }
    dom.window.close();
    console.log(`OK ${bundle}: native APIs, AJAX/tags, and source/production Permissions pickers`);
}
