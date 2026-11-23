/**
 * Compares editor styling with native controls, including theme/density changes.
 */
export async function runUI5MarkdownThemeRegression() {
    const oCore = sap.ui.getCore();
    await new Promise(fnResolve => oCore.attachInit(fnResolve));
    const [Markdown, TextArea, Button, Toolbar, Input, Text] = await new Promise((fnResolve, fnReject) => {
        sap.ui.require([
            'exface/ui5Custom/Markdown', 'sap/m/TextArea', 'sap/m/Button', 'sap/m/Toolbar',
            'sap/m/Input', 'sap/m/Text',
            'libs/exface/markian/editor', 'libs/exface/markian/viewer'
        ], (...aModules) => fnResolve(aModules), fnReject);
    });
    const sOriginalTheme = oCore.getConfiguration().getTheme();
    const domFixture = document.getElementById('fixtures');
    const bOriginalCompact = domFixture.classList.contains('sapUiSizeCompact');
    const oInput = new TextArea({value: 'Native text'});
    const oTextInput = new Input({value: 'Native input'});
    const oText = new Text({text: 'Native text'});
    const oButton = new Button({icon: 'sap-icon://edit', type: 'Transparent'});
    const oToolbar = new Toolbar({content: [oButton]});
    const oEditor = new Markdown({value: 'Editor text', height: '300px'}).setEditorConfig({options: {
        initialMode: 'visual', sidebarVisible: false,
        markdownCssUrls: [new URL('../../../../npm-asset/github-markdown-css/github-markdown-light.css', location.href).href]
    }});
    const oCodeEditor = new Markdown({value: 'Inline `code`\n\n```\nblock()\n```', height: '220px'}).setEditorConfig({options: {
        initialMode: 'visual', sidebarVisible: false,
        markdownCssUrls: [new URL('../../../../npm-asset/github-markdown-css/github-markdown-light.css', location.href).href]
    }});
    const oViewer = new Markdown({viewer: true, value: 'Inline `code`\n\n```\nblock()\n```', height: 'auto'}).setEditorConfig({options: {
        markdownCssUrls: [new URL('../../../../npm-asset/github-markdown-css/github-markdown-light.css', location.href).href]
    }});
    const aResults = [];
    /**
     * Waits for UI5's theme assets, rather than guessing a loading delay.
     */
    async function theme(sTheme) {
        if (oCore.getConfiguration().getTheme() === sTheme) return;
        await new Promise(fnResolve => {
            const fnChanged = () => { oCore.detachThemeChanged(fnChanged); fnResolve(); };
            oCore.attachThemeChanged(fnChanged);
            oCore.applyTheme(sTheme);
        });
    }
    /**
     * Compare rendered measurements, not copies of the CSS constants.
     */
    function equal(sActual, sExpected, sMessage) {
        if (sActual !== sExpected) throw new Error(`${sMessage}: ${sActual} != ${sExpected}`);
    }
    try {
        for (const oControl of [oInput, oTextInput, oText, oToolbar, oEditor, oCodeEditor, oViewer]) oControl.placeAt('fixtures');
        oCore.applyChanges();
        await oEditor._oEditor.ready;
        await oCodeEditor._oEditor.ready;
        await oViewer._oEditor.ready;
        const oInitialViewer = oViewer._oEditor;
        const domViewer = oViewer.getDomRef().querySelector('.markian-viewer');
        const domViewerDocument = domViewer.querySelector('.markian-document');
        const domCodeDocument = oCodeEditor._oEditor.instance.element.querySelector('.markian-document');
        const domInlineCode = domCodeDocument.querySelector('p code');
        const domCodeBlock = domCodeDocument.querySelector('pre');
        if (!domInlineCode || !domCodeBlock) throw new Error('Inline code and code block were not rendered');
        const oInitialEditor = oEditor._oEditor;
        const domEditor = oInitialEditor.instance.element;
        await oInitialEditor.instance.execute('search');
        const domSearchInput = domEditor.querySelector('.markian-search input');
        if (!domSearchInput) throw new Error('Search field was not created');
        for (const sTheme of ['sap_fiori_3', 'sap_horizon', 'sap_fiori_3_dark', 'sap_horizon_dark']) {
            await theme(sTheme);
            for (const bCompact of [false, true]) {
                domFixture.classList.toggle('sapUiSizeCompact', bCompact);
                const oNative = getComputedStyle(oInput.getFocusDomRef());
                const oField = getComputedStyle(oInput.getDomRef().querySelector('.sapMInputBaseContentWrapper'));
                const oNativeButton = getComputedStyle(oButton.getDomRef('inner'));
                const domButton = domEditor.querySelector('.markian-toolbar > .markian-button');
                const oDocument = getComputedStyle(domEditor.querySelector('.markian-document'));
                equal(getComputedStyle(domEditor).borderRadius, oField.borderRadius, `${sTheme}: field corners`);
                equal(oDocument.fontFamily, oNative.fontFamily, `${sTheme}: text font`);
                equal(oDocument.fontSize, oNative.fontSize, `${sTheme}: text size`);
                equal(oDocument.lineHeight, oNative.lineHeight, `${sTheme}: text line height`);
                equal(oDocument.color, oNative.color, `${sTheme}: text colour`);
                equal(oDocument.backgroundColor, oField.backgroundColor, `${sTheme}: document background`);
                equal(oDocument.padding, oNative.padding, `${sTheme}: text padding`);
                const oViewerDocument = getComputedStyle(domViewerDocument);
                const oNativeText = getComputedStyle(oText.getDomRef());
                for (const sProperty of ['fontFamily', 'fontSize', 'color']) {
                    equal(oViewerDocument[sProperty], oNativeText[sProperty], `${sTheme}: viewer text ${sProperty}`);
                }
                equal(oViewerDocument.lineHeight, oDocument.lineHeight, `${sTheme}: viewer line height`);
                equal(oViewerDocument.padding, '0px', `${sTheme}: viewer added field padding`);
                equal(oViewerDocument.backgroundColor, 'rgba(0, 0, 0, 0)', `${sTheme}: viewer obscures its container`);
                equal(getComputedStyle(domViewer).borderWidth, '0px', `${sTheme}: viewer has an editor border`);
                for (const [domViewerCode, domEditorCode] of [
                    [domViewerDocument.querySelector('p code'), domInlineCode],
                    [domViewerDocument.querySelector('pre'), domCodeBlock]
                ]) {
                    if (!domViewerCode) throw new Error('Viewer code was not rendered');
                    for (const sProperty of ['fontFamily', 'fontSize', 'backgroundColor', 'padding', 'borderRadius']) {
                        equal(getComputedStyle(domViewerCode)[sProperty], getComputedStyle(domEditorCode)[sProperty], `${sTheme}: viewer code ${sProperty}`);
                    }
                }
                if (oViewer._oEditor !== oInitialViewer || oViewer.getValue() !== 'Inline `code`\n\n```\nblock()\n```') {
                    throw new Error('Theme changes rebuilt the viewer or changed its source');
                }
                const oOverlay = getComputedStyle(domEditor, '::after');
                for (const sProperty of ['backgroundImage', 'backgroundPosition', 'backgroundSize', 'backgroundRepeat']) {
                    equal(oOverlay[sProperty], oField[sProperty], `${sTheme}: editor field underline ${sProperty}`);
                }
                for (const domCode of [domInlineCode, domCodeBlock]) {
                    const sBackground = getComputedStyle(domCode).backgroundColor;
                    if (sBackground === 'rgba(0, 0, 0, 0)' || sBackground === getComputedStyle(domCodeDocument).backgroundColor) {
                        throw new Error(`${sTheme}: code background is indistinguishable from the document`);
                    }
                }
                equal(getComputedStyle(domButton).height, oNativeButton.height, `${sTheme}: toolbar button height`);
                equal(getComputedStyle(domButton).width, oNativeButton.minWidth, `${sTheme}: toolbar button width`);
                equal(getComputedStyle(domButton).borderRadius, oNativeButton.borderRadius, `${sTheme}: button corners`);
                equal(getComputedStyle(domButton.querySelector('svg')).width, getComputedStyle(oButton.getDomRef('img')).fontSize, `${sTheme}: icon size`);
                equal(getComputedStyle(domEditor.querySelector('.markian-toolbar')).height, getComputedStyle(oToolbar.getDomRef()).height, `${sTheme}: toolbar height`);
                equal(getComputedStyle(domSearchInput).height, getComputedStyle(oTextInput.getDomRef().querySelector('.sapMInputBaseContentWrapper')).height, `${sTheme}: search field height`);
                equal(getComputedStyle(domSearchInput).fontSize, getComputedStyle(oTextInput.getFocusDomRef()).fontSize, `${sTheme}: search field font size`);
                oEditor.getDomRef().style.width = '220px';
                equal(getComputedStyle(domButton).height, oNativeButton.height, `${sTheme}: narrow toolbar density`);
                oEditor.getDomRef().style.width = '';
                if (oEditor._oEditor !== oInitialEditor || oEditor.getValue() !== 'Editor text') throw new Error('Theme/density change rebuilt the editor or lost its draft');
                aResults.push(`${sTheme}: ${bCompact ? 'compact' : 'cozy'} matches native fields/buttons`);
            }
        }
        await oInitialEditor.instance.execute('fullscreen');
        equal(getComputedStyle(domEditor.querySelector('.markian-button')).height, getComputedStyle(oButton.getDomRef('inner')).height, 'Fullscreen lost compact density');
        oEditor.setValueStateText('Invalid Markdown').setValueState('Error');
        equal(domEditor.getAttribute('aria-invalid'), 'true', 'Fullscreen lost validation state');
        await oInitialEditor.instance.execute('fullscreen');
        equal(String(domEditor.classList.contains('sapUiSizeCompact')), 'false', 'Fullscreen leaked a density class');
        oInitialEditor.instance.setMode('raw');
        await oInitialEditor.instance.ready;
        const domSource = domEditor.querySelector('.cm-scroller');
        if (!domSource) throw new Error('Source editor was not created');
        equal(getComputedStyle(domSource).fontSize, getComputedStyle(oInput.getFocusDomRef()).fontSize, 'Source font size differs from UI5');
        aResults.push('fullscreen density/validation and source font size');
        return aResults;
    } finally {
        oEditor.destroy();
        oCodeEditor.destroy();
        oViewer.destroy();
        oText.destroy();
        oToolbar.destroy();
        oInput.destroy();
        oTextInput.destroy();
        domFixture.classList.toggle('sapUiSizeCompact', bOriginalCompact);
        await theme(sOriginalTheme);
    }
}
