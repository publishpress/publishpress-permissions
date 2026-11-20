// Run with jsdom and jquery installed: node tests/unit/agent-picker-labels.cjs
const { JSDOM } = require('jsdom');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const path = require('node:path');
(async () => {
    for (const file of ['agent-select.dev.js', 'agent-select.js']) {
        for (const select2 of [false, true]) {
            const dom = new JSDOM('<select id="target"></select><select id="agent_results_target"></select>', { runScripts: 'outside-only' });
            const { window } = dom;
            const $ = require('jquery')(window);
            window.jQuery = $;
            const label = '\" onmouseover=\"globalThis.compromised=1\" ><img src=x onerror=alert(1)>';
            if (select2) {
                const input = window.document.createElement('select');
                input.id = 'v2_agent_search_text_target::group';
                window.document.body.append(input);
                $.fn.select2 = () => [{ id: 7, text: label }];
            } else {
                const option = new window.Option(label, 7);
                option.selected = true;
                window.document.querySelector('#agent_results_target').append(option);
            }
            window.eval(fs.readFileSync(path.join(__dirname, '../../common/js', file), 'utf8'));
            window.presspermitSelectAgents('target', 'group');
            await new Promise(resolve => window.setTimeout(resolve, 25));
            const option = window.document.querySelector('#target option');
            assert.ok(option, file + ': option created');
            assert.equal(option.textContent, label);
            assert.equal(option.title, label);
            assert.deepEqual(option.getAttributeNames().sort(), ['class', 'title', 'value']);
            assert.equal(window.document.querySelectorAll('img,[onmouseover],[onerror]').length, 0);
            dom.window.close();
        }
    }
    console.log('OK: Select2 and legacy labels remain inert in development and production scripts');
})().catch(error => { console.error(error); process.exit(1); });
