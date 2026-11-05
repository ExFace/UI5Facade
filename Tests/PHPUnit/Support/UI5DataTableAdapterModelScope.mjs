// Runs generated adapters against isolated model and transport boundaries, not live services.
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import test from 'node:test';

const scripts = JSON.parse(readFileSync(0, 'utf8'));

/**
 * Creates distinct models and records exactly when guarded selection resets happen.
 */
function createBoundary() {
    const savedRows = [{Id: 1}, {Id: 2}];
    const changes = [];
    const tableModel = {
        data: {rows: savedRows},
        getData() { return this.data; },
        setData(data) { changes.push('table data'); this.data = data; }
    };
    const otherModel = {
        data: {rows: savedRows},
        getData() { return this.data; },
        setData(data) { changes.push('other data'); this.data = data; }
    };
    const table = {
        __modifyingSelection: false,
        selected: [0, 1],
        getModel: () => tableModel,
        getBinding: () => ({}),
        clearSelection() {
            assert.equal(this.__modifyingSelection, true);
            assert.deepEqual(tableModel.getData().rows, savedRows);
            changes.push('clear selection');
            this.selected = [];
        }
    };
    return {table, tableModel, otherModel, savedRows, changes};
}

/**
 * Executes the actual adapter script while supplying deterministic transport responses.
 */
async function runAdapter(name, operation, boundary, ownModel, mode = 'success') {
    const targetModel = ownModel ? boundary.tableModel : boundary.otherModel;
    const rows = [{Id: 1}];
    const params = {data: {rows: [{Id: 1}], filters: {conditions: []}}};
    let pending;
    const track = promise => {
        pending = promise;
        return {then: (...args) => track(promise.then(...args))};
    };
    const ajax = {
        extend: Object.assign,
        ajax(options) {
            if (mode === 'error') {
                options.error({status: 500, responseText: 'Test failure'});
            } else {
                options.success({success: true, rows});
            }
            return Promise.resolve(targetModel);
        }
    };
    const sap = {
        ui: {
            getCore: () => ({byId: () => boundary.table}),
            model: {
                Filter: class {},
                FilterOperator: {EQ: 'EQ'},
                odata: {v2: {ODataModel: class {
                    read(path, options) {
                        if (mode === 'error') {
                            options.error({});
                        } else {
                            options.success({results: rows});
                        }
                    }
                }}}
            }
        }
    };
    const preloader = {
        getPreload: () => track(Promise.resolve(mode === 'fallback' ? undefined : {response: {rows}}))
    };
    const pwa = {
        data: {
            get: () => track(mode === 'error'
                ? Promise.reject(new Error('Offline cache failure'))
                : Promise.resolve(mode === 'fallback' ? undefined : {rows})),
            getRowsAddedOffline: () => []
        }
    };
    const tools = {data: {filterRows: data => data}};
    const source = `
        let loaded = 0, errors = 0, offlineErrors = 0;
        const request = (function() { ${scripts[name][operation]} })();
        return waitForRequest(request).then(() => ({loaded, errors, offlineErrors}));
    `;
    return new Function(
        'targetModel', 'params', 'sap', '$', 'exfLauncher', 'exfPreloader',
        'exfPWA', 'exfTools', 'setTimeout', 'component', 'console', 'waitForRequest', source
    )(
        targetModel, params, sap, ajax, {isOnline: () => name !== 'offline'}, preloader,
        pwa, tools, fn => fn(), {showAjaxErrorDialog() {}}, {log() {}, warn() {}},
        request => Promise.resolve(request === undefined ? pending : request)
    );
}

for (const name of ['ajax', 'odata', 'preload', 'offline']) {
    for (const ownModel of [true, false]) {
        test(`${name}: read resets only the table's own model (${ownModel ? 'own' : 'other'})`, async () => {
            const boundary = createBoundary();
            const result = await runAdapter(name, 'read', boundary, ownModel);
            assert.equal(result.loaded, 1);
            assert.deepEqual(boundary.changes, ownModel
                ? ['clear selection', 'table data'] : ['other data']);
            assert.deepEqual(boundary.table.selected, ownModel ? [] : [0, 1]);
            assert.equal(boundary.table.__modifyingSelection, false);
            assert.deepEqual(boundary.savedRows, [{Id: 1}, {Id: 2}]);
        });

        test(`${name}: prefill never resets table selection (${ownModel ? 'own' : 'other'})`, async () => {
            const boundary = createBoundary();
            const result = await runAdapter(name, 'prefill', boundary, ownModel);
            assert.equal(result.loaded, 1);
            assert.equal(boundary.changes.includes('clear selection'), false);
            assert.deepEqual(boundary.table.selected, [0, 1]);
            assert.equal(boundary.table.__modifyingSelection, false);
        });
    }
}

for (const name of ['ajax', 'odata', 'offline']) {
    test(`${name}: failed reads leave selection and models unchanged`, async () => {
        const boundary = createBoundary();
        if (name === 'ajax') {
            const result = await runAdapter(name, 'read', boundary, true, 'error');
            assert.equal(result.errors, 1);
            assert.equal(result.loaded, 0);
        } else {
            await assert.rejects(runAdapter(name, 'read', boundary, true, 'error'));
        }
        assert.deepEqual(boundary.changes, []);
        assert.deepEqual(boundary.table.selected, [0, 1]);
    });
}

for (const name of ['preload', 'offline']) {
    for (const operation of ['read', 'prefill']) {
        for (const ownModel of [true, false]) {
            test(`${name}: ${operation} fallback preserves model scope (${ownModel ? 'own' : 'other'})`, async () => {
                const boundary = createBoundary();
                const result = await runAdapter(name, operation, boundary, ownModel, 'fallback');
                assert.equal(result.loaded, 1);
                assert.equal(boundary.changes.filter(change => change === 'clear selection').length,
                    operation === 'read' && ownModel ? 1 : 0);
            });
        }
    }
}

test('explicit reset without a target still clears the table under the selection guard', () => {
    const boundary = createBoundary();
    const sap = {ui: {getCore: () => ({byId: () => boundary.table})}};
    new Function('sap', scripts.reset)(sap);
    assert.deepEqual(boundary.changes, ['clear selection']);
    assert.equal(boundary.table.__modifyingSelection, false);
});
