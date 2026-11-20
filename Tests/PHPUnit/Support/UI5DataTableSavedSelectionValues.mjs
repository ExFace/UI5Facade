// Executes PHP-generated selection values and change handlers with independent logical and native selections.
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import test from 'node:test';

const scripts = JSON.parse(readFileSync(0, 'utf8'));

function createTable() {
    const data = {};
    return {
        rows: [{Id: 'page-1-a'}, {Id: 'page-1-b'}],
        saved: [{Id: 'page-1-a'}, {Id: 'page-1-b'}],
        indices: [0, 1],
        selectNotifications: 0,
        changeNotifications: 0,
        getModel(name) {
            return name === 'selections'
                ? {getProperty: () => this.saved}
                : {getData: () => ({rows: this.rows})};
        },
        getSelectedIndices() { return this.indices; },
        getSelectedIndex() { return this.indices[0] ?? -1; },
        getContextByIndex(index) { return {getObject: () => this.rows[index]}; },
        getSelectedContexts() { return this.indices.map(index => this.getContextByIndex(index)); },
        data(key, value) {
            if (arguments.length > 1) {
                data[key] = value;
            }
            return data[key];
        }
    };
}

function execute(name, script, table) {
    return new Function('sap', 'exfTools', 'oTable',
        script === 'change' ? scripts[name][script] : `return (${scripts[name][script]});`)(
        {ui: {getCore: () => ({byId: () => table})}},
        {data: {
            compareData: (left, right) => JSON.stringify(left) === JSON.stringify(right),
            indexOfRow: (aRows, oRow, sUidCol) => aRows.findIndex(oCandidate => oCandidate[sUidCol] === oRow[sUidCol])
        }},
        table
    );
}

for (const name of ['saved', 'responsive']) {
    test(`${name}: conditions retain off-page rows while native selection remains page-local`, () => {
        const table = createTable();
        assert.deepEqual(execute(name, 'rows', table), table.saved);
        assert.equal(execute(name, 'values', table), 'page-1-a,page-1-b');
        execute(name, 'change', table);
        assert.equal(table.changeNotifications, 1);

        table.rows = [{Id: 'page-2-a'}];
        table.indices = [];
        assert.deepEqual(execute(name, 'rows', table), table.saved);
        assert.equal(execute(name, 'values', table), 'page-1-a,page-1-b');
        assert.deepEqual(execute(name, 'native', table), []);
        assert.equal(execute(name, 'rowCount', table), 1);
        execute(name, 'change', table);
        assert.equal(table.changeNotifications, 1, 'Paging alone does not change the logical selection');
        assert.equal(table.selectNotifications, 2, 'Select hooks still execute');

        table.saved = [...table.saved, table.rows[0]];
        table.indices = [0];
        execute(name, 'change', table);
        assert.equal(table.changeNotifications, 2);
        assert.equal(execute(name, 'values', table), 'page-1-a,page-1-b,page-2-a');

        table.indices = [];
        table.saved = table.saved.slice(0, 2);
        execute(name, 'change', table);
        assert.equal(table.changeNotifications, 3);

        table.saved = [table.saved[0]];
        execute(name, 'change', table);
        assert.equal(table.changeNotifications, 4, 'Removing an off-page row is a logical change');
        assert.equal(execute(name, 'values', table), 'page-1-a');

        table.saved = [];
        execute(name, 'change', table);
        assert.equal(table.changeNotifications, 5);
        assert.deepEqual(execute(name, 'rows', table), []);
        assert.equal(execute(name, 'values', table), '');
    });
}

for (const name of ['unsaved', 'single']) {
    test(`${name}: values and change detection continue using the current page`, () => {
        const table = createTable();
        table.indices = [0];
        table.saved = [table.rows[0]];
        assert.deepEqual(execute(name, 'rows', table), [table.rows[0]]);
        assert.equal(execute(name, 'values', table), 'page-1-a');
        execute(name, 'change', table);

        table.rows = [{Id: 'page-2-a'}];
        table.indices = [];
        table.saved = [];
        assert.deepEqual(execute(name, 'rows', table), []);
        assert.equal(execute(name, 'values', table), '');
        execute(name, 'change', table);
        assert.equal(table.changeNotifications, 2);

        table.saved = [];
        execute(name, 'change', table);
        assert.equal(table.changeNotifications, 2, 'Stored off-page data does not affect local-only values');
    });
}

for (const name of ['saved', 'responsive', 'unsaved']) {
    test(`${name}: current-page selection includes collapsed rows but excludes off-page rows`, () => {
        const table = createTable();
        const aPageSelection = [...table.saved];
        const oOffPageRow = {Id: 'another-page'};
        if (name !== 'unsaved') {
            table.saved = [...table.saved, oOffPageRow];
        }
        table.indices = [0];
        table.rows = table.rows.map(oRow => ({...oRow, label: 'reloaded'}));

        assert.deepEqual(execute(name, 'native', table), [table.rows[0]]);
        assert.deepEqual(execute(name, 'page', table), aPageSelection);
        assert.deepEqual(execute(name, 'all', table), table.saved);

        table.indices = [];
        assert.deepEqual(execute(name, 'page', table), aPageSelection, 'Collapsing every group retains page selections');

        table.rows = [];
        assert.deepEqual(execute(name, 'page', table), []);
        assert.deepEqual(execute(name, 'all', table), table.saved);
        table.saved = [];
        assert.deepEqual(execute(name, 'page', table), []);
        assert.deepEqual(execute(name, 'all', table), []);
    });
}

test('single: both scopes use native selection rather than the restoration snapshot', () => {
    const table = createTable();
    table.indices = [0];
    assert.deepEqual(execute('single', 'page', table), [table.rows[0]]);
    assert.deepEqual(execute('single', 'all', table), [table.rows[0]]);
    table.indices = [];
    assert.deepEqual(execute('single', 'page', table), []);
    assert.deepEqual(execute('single', 'all', table), []);
});
