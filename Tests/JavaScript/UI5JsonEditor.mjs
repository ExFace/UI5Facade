/**
 * Run runUI5JsonEditorRegression() in JsonEditor.html.
 * Exercises the actual UI5 control, JSONModel and binding lifecycle.
 */
export async function runUI5JsonEditorRegression() {
    await new Promise(fnResolve => sap.ui.getCore().attachInit(fnResolve));
    const [JsonEditor, JSONModel, Dialog, BindingMode] = await new Promise((fnResolve, fnReject) => {
        sap.ui.require([
            'exface/ui5Custom/JsonEditor', 'sap/ui/model/json/JSONModel',
            'sap/m/Dialog', 'sap/ui/model/BindingMode'
        ], (...aModules) => fnResolve(aModules), fnReject);
    });
    const oDictionary = await (await fetch('../../../core/Translations/exface.Core.en.json')).json();
    const oTerms = {};
    for (const [sKey, sValue] of Object.entries(oDictionary)) {
        if (sKey.startsWith('WIDGET.UXONEDITOR.')) {
            oTerms[sKey.substring('WIDGET.UXONEDITOR.'.length)] = sValue;
        }
    }
    const aResults = [];
    const aErrors = [];
    let iChanges = 0;
    const oModel = new JSONModel({rows: [{json: '{"row":1}'}, {json: '{"row":2}'}]});
    const oControl = new JsonEditor('ui5-json-regression', {
        value: {path: 'json', model: 'payload'},
        height: '300px',
        change: () => iChanges++
    }).setEditorConfig({
        uxon: true, autosuggest: true, validate: false, translations: oTerms,
        schema: () => 'widget', rootPrototype: () => '', rootObject: () => '',
        helpUrl: 'about:blank', ajaxUrl: '/unused', onError: oError => aErrors.push(oError)
    });
    let oDialog;
    function check(bCondition, sMessage) {
        if (!bCondition) throw new Error(sMessage);
    }
    function wait(iDelay = 350) {
        return new Promise(fnResolve => setTimeout(fnResolve, iDelay));
    }
    function adapter() {
        return oControl.getDomRef('editor').exfJsonEditor;
    }
    try {
        oControl.setModel(oModel, 'payload');
        oControl.bindElement({path: '/rows/0', model: 'payload'});
        oControl.placeAt('fixtures');
        sap.ui.getCore().applyChanges();
        check(adapter().getEditor().get().row === 1, 'Initial relative named-model binding failed');
        oModel.setProperty('/rows/0/json', '{"prefilled":true}');
        await wait();
        check(adapter().getEditor().get().prefilled, 'Model changes did not reach the editor');
        check(iChanges === 0, 'Model changes fired synthetic user changes');
        adapter().getEditor().setMode('code');
        await wait();
        adapter().getEditor().aceEditor.setValue('{"user":true}', -1);
        await wait();
        check(JSON.parse(oModel.getProperty('/rows/0/json')).user, 'Editor changes did not update the model');
        check(iChanges === 1, 'An editor change must notify the facade once');
        aResults.push('relative named-model binding, prefill and two-way updates');

        const oOldAdapter = adapter();
        oControl.invalidate();
        sap.ui.getCore().applyChanges();
        check(adapter() !== oOldAdapter, 'Rerendering reused a destroyed editor');
        check(adapter().getEditor().get().user, 'Rerendering discarded user data');
        check(adapter().getEditor().getMode() === 'code', 'Rerendering reset the editor mode');
        check(oControl.getDomRef().querySelectorAll('.uxoneditor-preset-hint').length === 1, 'Rerendering duplicated preset hints');
        oControl.bindElement({path: '/rows/1', model: 'payload'});
        await wait();
        check(adapter().getEditor().get().row === 2, 'Rebinding reused the previous row value');
        oControl.setValue('{"programmatic":true}');
        check(JSON.parse(oModel.getProperty('/rows/1/json')).programmatic, 'Programmatic setters did not update the model');
        check(iChanges === 1, 'Programmatic control setters must not fire user changes');
        aResults.push('rerender preservation and binding-context changes');

        oControl.bindProperty('value', {path: 'json', model: 'payload', mode: BindingMode.OneWay});
        adapter().getEditor().setMode('code');
        await wait();
        adapter().getEditor().aceEditor.setValue('{"local":true}', -1);
        await wait();
        check(JSON.parse(oModel.getProperty('/rows/1/json')).programmatic, 'One-way binding wrote back into the model');
        oControl.setEnabled(false);
        check(adapter().getEditor().aceEditor.getReadOnly(), 'UI5 enabled state did not disable code editing');
        oControl.setEnabled(true);
        check(!adapter().getEditor().aceEditor.getReadOnly(), 'UI5 enabled state did not restore code editing');
        oControl.setRequired(true);
        check(adapter().getRequired(), 'UI5 required state did not reach the adapter');
        oControl.setValueStateText('Invalid test value').setValueState('Error');
        check(oControl.getDomRef().getAttribute('aria-invalid') === 'true', 'UI5 validation state is inaccessible');
        check(oControl.getDomRef().title === 'Invalid test value', 'Validation text is not shown');
        aResults.push('one-way binding, enabled/required and value-state support');

        oModel.setData({rows: [{json: ''}, {json: ''}]});
        await wait();
        check(oControl.getValue() === '' && adapter().getValue() === '', 'Model reset left stale editor values');
        oControl.setValue('{"unfinished":');
        oControl.invalidate();
        sap.ui.getCore().applyChanges();
        check(adapter().getValue() === '{"unfinished":', 'Rerendering lost invalid JSON text');
        aResults.push('model reset and invalid-text preservation');

        oControl.setValue('{}');
        adapter().getEditor().setMode('tree');
        oDialog = new Dialog({contentWidth: '700px', contentHeight: '400px', content: [oControl]});
        oDialog.open();
        await wait(600);
        oControl.getDomRef().querySelector('.exf-jsoneditor-help').click();
        const domModal = document.querySelector('.uxoneditor-modal');
        check(domModal.getAttribute('data-sap-ui-integration-popup-content') !== null, 'Popup is not registered with UI5 focus handling');
        check(Number(domModal.style.zIndex) > Number(oDialog.getDomRef().parentNode.style.zIndex || 0), 'Popup is behind the UI5 dialog');
        domModal.querySelector('.pico-close').click();
        oControl.destroy();
        check(!document.querySelector('.uxoneditor-modal'), 'Destroy leaked a UXON popup');
        check(!document.getElementById('ui5-json-regression-editor'), 'Destroy leaked the editor host');
        check(aErrors.length === 0, 'Unexpected editor errors');
        aResults.push('UI5 dialog popup integration and destruction');
        return aResults;
    } finally {
        if (oDialog) oDialog.destroy();
        oControl.destroy();
        oModel.destroy();
    }
}
