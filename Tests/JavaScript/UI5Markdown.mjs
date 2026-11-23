/**
 * Runs against the native UI5 control and packaged Markian assets, without persisted data.
 */
export async function runUI5MarkdownRegression() {
    await new Promise(fnResolve => sap.ui.getCore().attachInit(fnResolve));
    const [Markdown, JSONModel, Dialog, BindingMode] = await new Promise((fnResolve, fnReject) => {
        sap.ui.require([
            'exface/ui5Custom/Markdown', 'sap/ui/model/json/JSONModel',
            'sap/m/Dialog', 'sap/ui/model/BindingMode',
            'libs/exface/markian/editor', 'libs/exface/markian/viewer',
            'libs/exface/markian/code', 'libs/exface/markian/viewerMermaid'
        ], (...aModules) => fnResolve(aModules), fnReject);
    });
    const aResults = [];
    let iChanges = 0;
    const sInitial = '# First\n\nA {literal} paragraph\n\n<!-- hidden note -->';
    const oModel = new JSONModel({rows: [{markdown: sInitial}, {markdown: '# Second'}]});
    const oControl = new Markdown('ui5-markian-regression', {
        value: {path: 'markdown', model: 'payload'},
        height: '300px',
        change: () => iChanges++
    }).setEditorConfig({options: {initialMode: 'visual', sidebarVisible: false}});
    const oViewer = new Markdown('ui5-markian-viewer', {
        viewer: true, value: {path: 'markdown', model: 'payload'}, height: 'auto'
    }).setEditorConfig({options: {
        markdownCssUrls: [new URL('../../../../npm-asset/github-markdown-css/github-markdown-light.css', location.href).href],
        renderers: MarkianAdapter.buildCodeRenderers(document, {
            colorize: true, languageBase: new URL('../../../../axenox/markian/dist/extensions/languages/', location.href).href
        })
    }, mermaidSource: new URL('../../../../npm-asset/pele/dist/pele.global.js', location.href).href});
    let oDialog;
    /**
     * A failed condition must reject the regression rather than merely log a warning.
     */
    function check(bCondition, sMessage) {
        if (!bCondition) throw new Error(sMessage);
    }
    /**
     * Gives asynchronous view imports and native binding notifications time to finish.
     */
    function wait(iDelay = 350) {
        return new Promise(fnResolve => setTimeout(fnResolve, iDelay));
    }
    try {
        for (const oItem of [oControl, oViewer]) {
            oItem.setModel(oModel, 'payload');
            oItem.bindElement({path: '/rows/0', model: 'payload'});
            oItem.placeAt('fixtures');
        }
        sap.ui.getCore().applyChanges();
        await Promise.all([oControl._oEditor.ready, oViewer._oEditor.ready]);
        check(oControl.getValue() === sInitial, 'Initial multiline source was changed');
        check(oViewer.getValue() === sInitial, 'Viewer did not receive its relative named binding');
        check(oViewer.getDomRef().querySelector('h1').textContent === 'First', 'Viewer rendered the wrong document');
        oModel.setProperty('/rows/0/markdown', '# Prefilled\n\nText');
        await wait();
        check(oControl.getValue() === '# Prefilled\n\nText', 'Model prefill did not reach the editor');
        check(iChanges === 0, 'Host loads fired synthetic user changes');
        oControl._oEditor.instance.setMode('raw');
        await wait();
        const domSource = oControl.getDomRef().querySelector('.markian-source [contenteditable="true"]');
        check(domSource, 'Source mode did not expose an editable surface');
        domSource.focus();
        document.getSelection().selectAllChildren(domSource);
        document.execCommand('insertText', false, '# User\n\nChanged');
        await wait();
        check(oModel.getProperty('/rows/0/markdown') === '# User\n\nChanged', 'User changes did not update the bound model');
        check(iChanges === 1, 'User edit did not fire exactly one change');
        check(oViewer.getDomRef().querySelector('h1').textContent === 'User', 'Viewer did not follow model updates');
        aResults.push('relative named bindings, multiline source, prefill and user changes');

        const oOldEditor = oControl._oEditor;
        const oOldViewer = oViewer._oEditor;
        oControl.invalidate();
        oViewer.invalidate();
        sap.ui.getCore().applyChanges();
        await Promise.all([oControl._oEditor.ready, oViewer._oEditor.ready]);
        check(oControl._oEditor !== oOldEditor && oViewer._oEditor !== oOldViewer, 'Rerender reused old instances');
        check(oControl.getValue() === '# User\n\nChanged', 'Rerender lost the draft');
        check(oViewer.getDomRef().querySelector('h1').textContent === 'User', 'Rerender lost viewer content');
        check(oControl.getDomRef().querySelectorAll('.markian').length === 1, 'Rerender duplicated the editor');
        oControl.bindElement({path: '/rows/1', model: 'payload'});
        await wait();
        check(oControl.getValue() === '# Second', 'Rebinding retained the previous row');
        aResults.push('editor/viewer rerender preservation and binding-context changes');

        oControl.bindProperty('value', {path: 'markdown', model: 'payload', mode: BindingMode.OneWay});
        oControl.setValue('# Local');
        check(oModel.getProperty('/rows/1/markdown') === '# Second', 'One-way setter wrote to the model');
        oControl.setEnabled(false);
        check(oControl._oEditor.instance.readOnly, 'Disabled state did not block editing');
        oControl.setEnabled(true);
        check(!oControl._oEditor.instance.readOnly, 'Enabled state did not restore editing');
        check(oControl.getValue() === '# Local', 'Permission changes discarded source');
        oControl.setRequired(true);
        oControl.setValueStateText('Invalid Markdown').setValueState('Error');
        check(oControl.getDomRef().getAttribute('aria-invalid') === 'true', 'Validation is inaccessible');
        check(oControl.getDomRef().getAttribute('aria-required') === 'true', 'Required state is inaccessible');
        check(oControl.getDomRef().title === 'Invalid Markdown', 'Validation tooltip is missing');
        oControl.setValueState('None');
        check(oControl.getDomRef().getAttribute('aria-invalid') === 'false', 'Validation state was not cleared');
        oModel.setData({rows: [{markdown: ''}, {markdown: ''}]});
        await wait();
        check(oControl.getValue() === '' && oViewer.getValue() === '', 'Model reset left stale source');
        check(oViewer.getDomRef().querySelector('.markian-document').textContent === '', 'Empty viewer kept old output');
        aResults.push('one-way binding, permissions, validation and explicit empty values');

        oModel.setProperty('/rows/0/markdown', '```mermaid\nflowchart TD\nA-->B\n```\n\n```js\nconst value = 1;\n```');
        await wait(1200);
        check(oViewer.getDomRef().querySelector('.markian-rendered-fence[data-language="mermaid"] svg'), 'Mermaid fence did not render');
        check(oViewer.getDomRef().querySelector('.markian-rendered-fence[data-language="js"] .tok-keyword'), 'Code fence did not highlight');
        check(!oViewer.getDomRef('editor').hasAttribute('data-markian-error'), 'Viewer reported a rendering error');
        aResults.push('Pele diagrams and code highlighting');

        oControl.setValue('# Dialog\n\nText');
        oDialog = new Dialog({contentWidth: '700px', contentHeight: '400px', content: [oControl]});
        oDialog.open();
        await wait(600);
        await oControl._oEditor.ready;
        await oControl._oEditor.instance.execute('fullscreen');
        const domFullscreen = oControl._oEditor.instance.element;
        check(domFullscreen.parentNode.id === 'sap-ui-static', 'Fullscreen remains clipped inside the dialog');
        check(domFullscreen.hasAttribute('data-sap-ui-integration-popup-content'), 'Fullscreen is not registered with modal focus handling');
        oControl._oEditor.instance.setMode('raw');
        await wait();
        const domFullscreenSource = domFullscreen.querySelector('.markian-source [contenteditable="true"]');
        domFullscreenSource.focus();
        await wait();
        check(document.activeElement === domFullscreenSource, 'Modal dialog stole fullscreen editing focus');
        oControl.destroy();
        check(!domFullscreen.isConnected, 'Destroy leaked fullscreen content');
        check(!document.getElementById('ui5-markian-regression-editor'), 'Destroy leaked the editor mount');
        aResults.push('modal fullscreen focus and destruction');
        return aResults;
    } finally {
        if (oDialog) oDialog.destroy();
        oControl.destroy();
        oViewer.destroy();
        oModel.destroy();
    }
}
