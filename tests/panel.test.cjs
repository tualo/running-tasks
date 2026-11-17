const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function controller() {
    let definition, task;
    const alerts = [];
    const Ext = {
        define: (name, value) => { definition = value; },
        TaskManager: {
            start: config => { task = config; return config; },
            stop: config => { assert.equal(config, task); task = null; }
        },
        Msg: { alert: (title, message) => alerts.push(message) },
        String: { htmlEncode: value => String(value).replaceAll('<', '&lt;').replaceAll('>', '&gt;') }
    };
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../src/js/lazy/controller/Panel.js'), 'utf8'), {
        Ext, fetch: async () => { throw new Error('Unexpected request'); }
    });
    return { instance: { ...definition }, alerts, task: () => task };
}

test('polling refreshes the current page and stops on destruction', () => {
    const { instance, task } = controller();
    const pages = [];
    instance.loadEntries = page => pages.push(page);
    instance.callParent = () => {};
    instance.onBoxReady();
    const poll = task();
    poll.run.call(instance, 2);
    poll.run.call(instance, 3);
    assert.deepEqual(pages, [undefined, undefined, undefined]);
    instance.destroy();
    assert.equal(task(), null);
});

test('late detail responses cannot replace a newer selection', async () => {
    const { instance } = controller();
    const pending = new Map(), loaded = [];
    instance.messageRequest = 0;
    instance.getViewModel = () => ({ getStore: () => ({ loadData: data => loaded.push(data) }) });
    instance.request = url => new Promise(resolve => pending.set(url, resolve));
    const first = instance.loadMessages('first');
    const second = instance.loadMessages('second');
    pending.get('./running-tasks/messages?id=second')({ data: ['new'] });
    await second;
    pending.get('./running-tasks/messages?id=first')({ data: ['old'] });
    await first;
    assert.deepEqual(loaded, [['new']]);
});

test('errors stop polling and encode text before displaying it', () => {
    const { instance, alerts, task } = controller();
    instance.startPolling();
    instance.showError(new Error('<script>bad</script>'));
    assert.equal(task(), null);
    assert.equal(alerts[0], '&lt;script&gt;bad&lt;/script&gt;');
});

test('stop submits the captured process ID, not a later selection', async () => {
    const { instance } = controller();
    const calls = [];
    instance.selectedId = 'original task';
    instance.loadEntries = () => calls.push('refresh');
    instance.request = async (url, options) => calls.push({ url, options });
    // Load the confirmation callback through a fresh VM with the same controller definition.
    let definition;
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../src/js/lazy/controller/Panel.js'), 'utf8'), {
        Ext: {
            define: (name, value) => { definition = value; },
            Msg: { confirm: (title, text, callback) => {
                instance.selectedId = 'another';
                calls.push(callback('yes'));
            } }
        }
    });
    definition.onStop.call(instance);
    await Promise.all(calls.filter(value => value && typeof value.then === 'function'));
    assert.equal(calls[0].url, './running-tasks/stop');
    assert.equal(calls[0].options.method, 'POST');
    assert.equal(calls[0].options.body, 'id=original%20task');
    assert.ok(calls.includes('refresh'));
});
