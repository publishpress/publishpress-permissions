/* Run with jquery and jsdom available on NODE_PATH: node tests/unit/teaser-style-tab.js */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');

async function checkBundle(suffix) {
    const dom = new JSDOM('<!doctype html><html><body><form id="pp_settings_form"><input id="selected_post_type" value="post"></form></body></html>', { runScripts: 'outside-only', url: 'https://example.test/wp-admin/' });
    const $ = require('jquery')(dom.window);
    dom.window.jQuery = dom.window.$ = $;
    dom.window.presspermitTeaser = { strings: {}, url: '/ajax', nonce: 'test' };
    $.fn.select2 = function () { return this; };
    for (const [type, color] of [['post', '#123456'], ['page', '#abcdef']]) {
        const active = type === 'post' ? ' active' : '';
        const selector = `<select class="pp-current-post-type"><option value="post">Posts</option><option value="page">Pages</option></select>`;
        $('form').append(`<div class="pp-teaser-settings-container${active}" data-post-type="${type}"><select class="pp-teaser-type-select"><option value="1">No teaser text</option></select></div>`);
        $('form').append(`<div class="pp-teaser-style-options-container${active}" data-post-type="${type}">${selector}<div class="pp-teaser-notice-style-settings"><input class="pp-color-picker" name="teaser_notice_bg_color[${type}]" value="${color}"><div class="pp-teaser-notice-preview">Message</div></div></div>`);
        $('form').append(`<div class="pp-teaser-site-preview" data-post-type="${type}"><div class="pp-teaser-notice-preview" data-blocked-message="Message"></div></div>`);
    }
    for (const script of [`settings${suffix}.js`, `settings-teaser-preview${suffix}.js`]) {
        dom.window.eval(fs.readFileSync(path.resolve(__dirname, '../../modules/presspermit-teaser/common/js', script), 'utf8'));
    }
    await new Promise(resolve => dom.window.setTimeout(resolve, 30));
    const postStyle = $('.pp-teaser-style-options-container[data-post-type="post"]');
    postStyle.find('.pp-color-picker').trigger('change');
    assert.equal($('.pp-teaser-site-preview[data-post-type="post"]').data('theme-teaser-payload').noticeStyle.backgroundColor, '#123456');
    assert.equal(postStyle.find('.pp-teaser-notice-preview').css('background-color'), 'rgb(18, 52, 86)');
    postStyle.find('.pp-current-post-type').val('page').trigger('change');
    const pageStyle = $('.pp-teaser-style-options-container[data-post-type="page"]');
    assert.ok(pageStyle.hasClass('active'));
    assert.ok(!postStyle.hasClass('active'));
    pageStyle.find('.pp-color-picker').trigger('change');
    assert.equal($('.pp-teaser-site-preview[data-post-type="page"]').data('theme-teaser-payload').noticeStyle.backgroundColor, '#abcdef');
    assert.equal(pageStyle.find('.pp-teaser-notice-preview').css('background-color'), 'rgb(171, 205, 239)');
    assert.equal(postStyle.find('.pp-color-picker').val(), '#123456');
    dom.window.close();
    console.log(`OK teaser style tab ${suffix || 'production'}: switching, values, focused preview, theme payload`);
}

(async () => {
    await checkBundle('.dev');
    await checkBundle('');
})().catch(error => { console.error(error); process.exitCode = 1; });
