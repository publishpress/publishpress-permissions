/* Run with jquery and playwright on NODE_PATH: node tests/browser/select2-workflows.js */
'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../..');
const baseline = process.argv.find(arg => arg.startsWith('--baseline='))?.slice('--baseline='.length);
const preloadLegacy = process.argv.includes('--preload-legacy');

function asset(file) {
    if (baseline) {
        const previousPath = file.replace('select2-4.1.0/dist/', 'select2-4.0.13/');
        return execFileSync('git', ['show', `${baseline}:${previousPath}`], { cwd: root, encoding: 'utf8', maxBuffer: 5 * 1024 * 1024 });
    }
    return fs.readFileSync(path.join(root, file), 'utf8');
}

async function choose(page, selector, term, label) {
    const control = page.locator(selector);
    if (!await control.evaluate(element => window.jQuery(element).data('select2').isOpen())) {
        await control.locator('xpath=following-sibling::span[contains(@class,"select2-container")]').locator('.select2-selection').click();
    }
    const request = page.waitForRequest(req => {
        const params = new URL(req.url()).searchParams;
        return params.get('pp_agent_search') === term || params.get('search') === term;
    });
    await page.locator('.select2-container--open .select2-search__field').last().fill(term);
    await request;
    await page.getByRole('option', { name: label, exact: true }).click();
}

async function fieldValue(page, selector) {
    return page.locator(selector).evaluate(element => window.jQuery(element).val());
}

async function waitValue(page, selector, value) {
    await page.waitForFunction(({ selector, value }) => document.querySelector(selector).value === value, { selector, value });
}

async function fixture(browser, mode, suffix, width) {
    const rendered = JSON.parse(execFileSync('php', [path.join(__dirname, 'fixtures/select2.php'), mode], { encoding: 'utf8' }));
    if (mode !== 'permissions' && !baseline) {
        for (const entries of [rendered.scripts, rendered.styles]) {
            const select2 = Object.values(entries).filter(item => item.src.includes('/common/lib/select2-'));
            assert.equal(select2.length, 1, `${mode} registers one Select2 asset of each type`);
            assert.equal(select2[0].version, '4.1.0');
            assert.ok(select2[0].src.includes('/select2-4.1.0/dist/'));
        }
    }
    const context = await browser.newContext({ viewport: { width, height: 900 } });
    const page = await context.newPage();
    const errors = [];
    const requests = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('dialog', dialog => dialog.accept());
    await page.route('https://permissions.test/**', async route => {
        const url = new URL(route.request().url());
        if (url.pathname.startsWith('/wp-admin/')) {
            requests.push(Object.fromEntries(url.searchParams));
            if (url.searchParams.get('action') === 'pp_search_posts') {
                await route.fulfill({ contentType: 'application/json', body: JSON.stringify([{ ID: 2, post_title: 'New page' }]) });
            } else if (url.searchParams.get('pp_ajax_item') === 'get_agent_exception_ui') {
                await route.fulfill({ contentType: 'text/html', body: '<!--ppSfx-->read|page|user<--ppSfx--><!--ppResponse--><tr data-legacy-agent="2"><td><select name="pp_exceptions[page][read][user][item][2]"><option value="2">Enabled</option></select></td></tr><--ppResponse-->' });
            } else {
                await route.fulfill({ contentType: 'text/html', body: '<option value="9">Saved User</option><option value="2">New User</option><option value="3">Another User</option>' });
            }
        } else {
            await route.fulfill({ contentType: 'text/html', body: '<!doctype html><html><head></head><body><form id="fixture">' + rendered.markup + '</form></body></html>' });
        }
    });
    await page.goto('https://permissions.test/fixture');
    await page.addStyleTag({ content: 'body{font-family:Arial,sans-serif;margin:0;padding:20px}form{max-width:900px}.pp-agents-selection{width:100%}.pp-agent-type-content{display:block!important}textarea{min-height:80px}' });
    for (const css of ['common/lib/select2-4.1.0/dist/css/select2.min.css', 'common/css/presspermit.css', 'common/css/item-edit.css', 'common/css/item-edit-tabbed.css']) {
        await page.addStyleTag({ content: asset(css) });
    }
    await page.addScriptTag({ content: fs.readFileSync(require.resolve('jquery/dist/jquery.js'), 'utf8') });
    if (preloadLegacy) {
        await page.addScriptTag({ content: fs.readFileSync(require.resolve('select2-legacy/dist/js/select2.full.min.js'), 'utf8') });
    }
    await page.addScriptTag({ content: asset('common/lib/select2-4.1.0/dist/js/select2.full.min.js') });
    await page.evaluate(localized => {
        Object.assign(window, localized);
        window.ppPermissions = new Proxy({ confirmDeleteItem: 'Remove %s?', searchUsers: 'Search users', addedToList: 'Added ' }, { get: (values, key) => values[key] || String(key) });
        window.jQuery.fx.off = true;
    }, rendered.localized);
    const scripts = mode === 'teaser'
        ? [`modules/presspermit-teaser/common/js/settings${suffix}.js`]
        : mode === 'permissions'
            ? [`common/js/item-edit-tabbed${suffix}.js`]
            : [`common/js/listbox${suffix}.js`, `common/js/${mode === 'legacy' ? 'agent-exception-select' : 'agent-select'}${suffix}.js`, `common/js/admin${suffix}.js`];
    for (const script of scripts) await page.addScriptTag({ content: asset(script) });
    const selector = mode === 'members' ? '[id="v2_agent_search_text_member::user"]'
        : mode === 'author' ? '[id="v2_agent_search_text_select-author::user"]'
            : mode === 'legacy' ? '[id="v2_agent_search_text_read:page:user"]'
            : mode === 'permissions' ? '.pp-user-search-select2' : '#teaser_redirect_anon_page_post';
    await page.waitForFunction(selector => !!window.jQuery(selector).data('select2'), selector);
    if (['members', 'author', 'legacy'].includes(mode)) {
        assert.equal(await page.locator(selector).evaluate(element => window.jQuery(element).data('select2').$selection.hasClass('pp-select2-container')), true, 'Permissions styling survives either library load order');
    }

    if (mode === 'members') {
        await page.locator('.select2-selection__choice__remove').click();
        await waitValue(page, '#member_csv', '');
        await choose(page, selector, 'new', 'New User');
        await waitValue(page, '#member_csv', '2,');
        const data = await page.locator('#fixture').evaluate(form => Object.fromEntries(new FormData(form)));
        assert.equal(data.member_csv, '2,', 'membership changes reach the normal form submission');
        assert.ok(requests.some(req => req.pp_topic === 'member' && req.pp_agent_type === 'user' && req.pp_agent_id === '55'));
        // Two handles can refer to the central library; loading it again must not replace an initialized instance.
        await page.evaluate(selector => { window.previousInstance = window.jQuery(selector).data('select2'); }, selector);
        await page.addScriptTag({ content: asset('common/lib/select2-4.1.0/dist/js/select2.full.min.js') });
        assert.equal(await page.evaluate(selector => window.previousInstance === window.jQuery(selector).data('select2'), selector), true);
        await page.locator('.select2-selection .select2-search__field').press('Backspace');
        await waitValue(page, '#member_csv', '');
        await choose(page, selector, 'another', 'Another User');
        await waitValue(page, '#member_csv', '3,');
    } else if (mode === 'author') {
        await choose(page, selector, 'author', 'New User');
        await waitValue(page, '#post_author_override', '2');
        assert.equal(await page.locator('#fixture').evaluate(form => new FormData(form).get('post_author_override')), '2');
        assert.ok(requests.some(req => req.pp_topic === 'select-author' && req.pp_agent_type === 'user'));
    } else if (mode === 'legacy') {
        await choose(page, selector, 'new', 'New User');
        await page.locator('[data-legacy-agent="2"]').waitFor();
        assert.deepEqual(await fieldValue(page, selector), [], 'the legacy picker clears after adding the permission row');
        assert.ok(requests.some(req => req.pp_ajax_item === 'get_agent_exception_ui' && req.agent_ids === '2' && req.item_id === '42' && req.id_sfx === 'read:page:user'));
        assert.equal(await page.locator('#fixture').evaluate(form => new FormData(form).get('pp_exceptions[page][read][user][item][2]')), '2');
    } else if (mode === 'permissions') {
        await choose(page, selector, 'new', 'New User');
        await page.locator('.pp-permission-list-item[data-agent-id="2"]').waitFor();
        assert.deepEqual(await fieldValue(page, selector), []);
        const item = 'pp_exceptions[page][read][user][item][2]';
        const children = 'pp_exceptions[page][read][user][children][2]';
        let submitted = await page.locator('#fixture').evaluate(form => Object.fromEntries(new FormData(form)));
        assert.equal(submitted[item], '2');
        assert.equal(submitted[children], '');
        assert.ok(requests.some(req => req.pp_topic === 'read:page:user' && req.pp_metagroups === '0'));
        await page.locator('.pp-permission-list-item[data-agent-id="2"] .pp-delete-item').click();
        submitted = await page.locator('#fixture').evaluate(form => Object.fromEntries(new FormData(form)));
        assert.equal(submitted[item], '', 'removing a user clears its submitted exception');
    } else {
        assert.equal(await fieldValue(page, selector), '9', 'a previously saved redirect stays selected');
        await choose(page, selector, 'new page', 'New page');
        assert.equal(await fieldValue(page, selector), '2');
        await page.locator(selector).locator('xpath=following-sibling::span').locator('.select2-selection__clear').click();
        assert.equal(await fieldValue(page, selector), null, 'the selected redirect can be cleared');
        await page.locator('#teaser_redirect_anon_post_type_post').selectOption('post');
        assert.equal(await page.locator(selector).getAttribute('data-post-type'), 'post');
        await choose(page, selector, 'post search', 'New page');
        assert.ok(requests.some(req => req.action === 'pp_search_posts' && req.post_type === 'post' && req.search === 'post search' && req.nonce === 'test'));
        assert.equal(await page.locator('#fixture').evaluate(form => new FormData(form).get('teaser_redirect_anon_page[post]')), '2');
    }
    assert.deepEqual(errors, [], 'no browser JavaScript errors');
    const field = page.locator('.select2-selection--multiple .select2-search__field').first();
    if (await field.count()) {
        const size = await field.boundingBox();
        assert.ok(size.height <= 35, 'the inline search must not inherit full-size WordPress textarea height');
    }
    await context.close();
    console.log(`OK ${baseline || '4.1.0'}${preloadLegacy ? ' (legacy preloaded)' : ''} ${mode} ${suffix || 'production'} ${width}px`);
}

(async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        for (const suffix of ['.dev', '']) {
            for (const width of [1280, 375]) {
                for (const mode of ['members', 'author', 'legacy', 'permissions', 'teaser']) {
                    await fixture(browser, mode, suffix, width);
                }
            }
        }
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
