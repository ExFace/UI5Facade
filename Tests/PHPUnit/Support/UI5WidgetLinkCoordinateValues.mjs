import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {createRequire} from 'node:module';
import {dirname, resolve} from 'node:path';
import {fileURLToPath} from 'node:url';
import test, {beforeEach} from 'node:test';

const oScripts = JSON.parse(readFileSync(0, 'utf8'));
const require = createRequire(import.meta.url);
const exfTools = require(resolve(dirname(fileURLToPath(import.meta.url)), '..', '..', '..', '..',
    'core', 'Facades', 'AbstractAjaxFacade', 'js', 'exfTools.js'));
let oTable;
beforeEach(() => {
    oTable = {
        rows: [{Id: 'a', Amount: 0}, {Id: 'b', Amount: false}, {Id: 'c', Amount: 7}, {Id: 'd', Amount: 7}],
        selected: [{Id: 'off-page', Amount: 8}, {Id: 'c', Amount: 7}],
        getModel() { return {getData: () => ({rows: this.rows})}; }
    };
});
const sap = {ui: {getCore: () => ({byId: () => oTable})}};

function read(sCoordinate) {
    return new Function('sap', 'exfTools', `return (${oScripts[sCoordinate]});`)(sap, exfTools);
}

test('numeric coordinates read raw rows at the specified index and retain zero and false', () => {
    assert.equal(read('AMOUNT$0'), 0);
    assert.equal(read('Amount$1'), false);
    assert.equal(read('Amount$2'), 7);
    assert.equal(read('Amount$99'), '');
    assert.deepEqual(read('$2'), [oTable.rows[2]]);
    assert.deepEqual(read('$99'), []);
});

test('default selection and explicit scopes keep rows separate from action projections', () => {
    assert.deepEqual(read(''), oTable.selected);
    assert.deepEqual(read('$~loaded'), oTable.rows);
    assert.equal(read('Amount'), '8|7');
    assert.equal(read('Amount$~selected'), '8|7');
    assert.equal(read('Amount$~loaded'), '0|false|7|7');
    assert.equal(read('~rowcount'), 4);
});

test('aggregates use scope, column metadata delimiter and real shared aggregation logic', () => {
    assert.equal(read('Amount$~loaded:SUM'), 14);
    assert.equal(read('Amount$~selected:SUM'), 15);
    assert.equal(read('Amount$~loaded:LIST_DISTINCT'), '0|false|7');
    assert.equal(read('Amount$~selected:COUNT'), 2);
    assert.equal(read('Amount$~selected:AVG'), 7.5);
    oTable.selected = [];
    assert.equal(read('Amount$~selected:SUM'), 0);
    assert.equal(read('Amount$~selected:COUNT'), 0);
});

test('editing an unselected row refreshes scoped live values without changing selection', () => {
    const oChanges = {};
    const aLastLoaded = structuredClone(oTable.rows);
    oTable.selected = [];
    oTable.unrelatedRefresh = 0;
    oTable.rows[0].Amount = 4;
    oTable.readAggregate = () => read('Amount$~loaded:SUM');
    oTable.getModel = sName => {
        if (sName === 'data_changes') {
            return {
                getProperty: sPath => sPath === '/watching' ? true : oChanges,
                setProperty: () => {}
            };
        }
        if (sName === 'data_last_loaded') {
            return {getData: () => ({rows: aLastLoaded})};
        }
        return {getData: () => ({rows: oTable.rows})};
    };
    new Function('sap', 'oDataChanged', oScripts.editWatcher)(sap, {rows: oTable.rows});
    assert.equal(oChanges.a.Amount, 4);
    assert.equal(oTable.scopedValue, 18);
    assert.deepEqual(oTable.selected, []);
    assert.equal(oTable.unrelatedRefresh, 0, 'Edits must not invoke unrelated refresh callbacks');
});
