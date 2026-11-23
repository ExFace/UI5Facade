/**
 * Compares real editor/viewer task checkboxes with native controls and text lines.
 */
export async function runUI5MarkdownTaskRegression() {
    const oCore = sap.ui.getCore();
    await new Promise(fnResolve => oCore.attachInit(fnResolve));
    const [Markdown, CheckBox] = await new Promise((fnResolve, fnReject) => {
        sap.ui.require(['exface/ui5Custom/Markdown', 'sap/m/CheckBox',
            'libs/exface/markian/editor', 'libs/exface/markian/viewer'],
        (...aModules) => fnResolve(aModules), fnReject);
    });
    const sOriginalTheme = oCore.getConfiguration().getTheme();
    const domFixture = document.getElementById('fixtures');
    const bOriginalCompact = domFixture.classList.contains('sapUiSizeCompact');
    const sMarkdown = '- [ ] First task\n- [x] A long checked task that wraps onto several lines inside this narrow document\n  - [x] Nested task\n\n- [ ] Loose task\n\n  Another paragraph.';
    const oNative = new CheckBox({text: 'Native task'});
    const aControls = [false, true].map(bViewer => new Markdown({
        viewer: bViewer, value: sMarkdown, width: '260px', height: bViewer ? 'auto' : '400px'
    }).setEditorConfig({options: {
        initialMode: 'visual', sidebarVisible: false,
        markdownCssUrls: [new URL('../../../../npm-asset/github-markdown-css/github-markdown-light.css', location.href).href]
    }}));
    const aResults = [];
    /**
     * Waits for theme assets rather than using guessed delays.
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
     * Measures the first actual text glyph, including wrapped and nested items.
     */
    function firstTextRect(domItem) {
        const oWalker = document.createTreeWalker(domItem, NodeFilter.SHOW_TEXT);
        let domText;
        while ((domText = oWalker.nextNode())) {
            const iStart = domText.textContent.search(/\S/);
            if (iStart < 0) continue;
            const oRange = document.createRange();
            oRange.setStart(domText, iStart);
            oRange.setEnd(domText, iStart + 1);
            return oRange.getBoundingClientRect();
        }
        throw new Error('Task text was not rendered');
    }
    try {
        for (const oControl of [oNative, ...aControls]) oControl.placeAt('fixtures');
        oCore.applyChanges();
        await Promise.all(aControls.map(oControl => oControl._oEditor.ready));
        const aInitialInstances = aControls.map(oControl => oControl._oEditor);
        for (const sTheme of ['sap_fiori_3', 'sap_horizon', 'sap_fiori_3_dark', 'sap_horizon_dark']) {
            await theme(sTheme);
            for (const bCompact of [false, true]) {
                domFixture.classList.toggle('sapUiSizeCompact', bCompact);
                for (const [iControl, oControl] of aControls.entries()) {
                    const aCheckboxes = [...oControl.getDomRef().querySelectorAll('.markian-document input[type="checkbox"]')];
                    if (aCheckboxes.length !== 4) throw new Error('Wrapped/nested/loose checklist fixture is incomplete');
                    for (const domCheckbox of aCheckboxes) {
                        oNative.setSelected(domCheckbox.checked);
                        oCore.applyChanges();
                        const domNative = oNative.getDomRef().querySelector('.sapMCbBg');
                        for (const sPseudo of [null, ...(domCheckbox.checked ? ['::before'] : [])]) {
                            const oActual = getComputedStyle(domCheckbox, sPseudo);
                            const oExpected = getComputedStyle(domNative, sPseudo);
                            const aProperties = sPseudo ? ['content', 'fontFamily', 'fontSize', 'color'] :
                                ['width', 'height', 'border', 'borderRadius', 'backgroundColor'];
                            for (const sProperty of aProperties) {
                                if (oActual[sProperty] !== oExpected[sProperty]) {
                                    throw new Error(`${sTheme}: task ${sProperty}: ${oActual[sProperty]} != ${oExpected[sProperty]}`);
                                }
                            }
                        }
                        const oBox = domCheckbox.getBoundingClientRect();
                        const oText = firstTextRect(domCheckbox.closest('li'));
                        if (Math.abs((oBox.top + oBox.height / 2) - (oText.top + oText.height / 2)) > 1) {
                            throw new Error(`${sTheme}: ${oControl.getViewer() ? 'viewer' : 'editor'} task centres differ: box ${oBox.top + oBox.height / 2}, text ${oText.top + oText.height / 2}; offset ${getComputedStyle(domCheckbox).top}, font ${getComputedStyle(domCheckbox).fontSize}`);
                        }
                        if (oText.left - oBox.right < 6) throw new Error('Task checkbox overlaps its text');
                        if (domCheckbox.disabled !== oControl.getViewer()) throw new Error('Viewer/editor task permissions changed');
                        if (getComputedStyle(domCheckbox).opacity !== '1') throw new Error('Read-only tasks lost readable colours');
                    }
                    if (oControl.getValue() !== sMarkdown || oControl._oEditor !== aInitialInstances[iControl]) {
                        throw new Error('Theme/density changes replaced task source or the instance');
                    }
                }
                aResults.push(`${sTheme}: ${bCompact ? 'compact' : 'cozy'} editor/viewer task appearance and first-line alignment`);
            }
        }
        await aControls[0]._oEditor.instance.execute('preview');
        await aControls[0]._oEditor.instance.ready;
        const aPreviewCheckboxes = [...aControls[0].getDomRef().querySelectorAll('.markian-view.markian-document input[type="checkbox"]')];
        const aViewerCheckboxes = [...aControls[1].getDomRef().querySelectorAll('.markian-document input[type="checkbox"]')];
        if (aPreviewCheckboxes.length !== 4 || aPreviewCheckboxes.some(domCheckbox => !domCheckbox.disabled)) {
            throw new Error('Preview task rendering or permissions changed');
        }
        for (const [iCheckbox, domCheckbox] of aPreviewCheckboxes.entries()) {
            for (const sProperty of ['width', 'height', 'border', 'borderRadius', 'backgroundColor']) {
                if (getComputedStyle(domCheckbox)[sProperty] !== getComputedStyle(aViewerCheckboxes[iCheckbox])[sProperty]) {
                    throw new Error(`Preview checkbox ${sProperty} differs from viewer`);
                }
            }
            const oBox = domCheckbox.getBoundingClientRect();
            const oText = firstTextRect(domCheckbox.closest('li'));
            if (Math.abs((oBox.top + oBox.height / 2) - (oText.top + oText.height / 2)) > 1) {
                throw new Error('Preview checkbox is not centred on its first text line');
            }
        }
        aResults.push('Preview task styling, first-line alignment and read-only permissions match viewer');
        return aResults;
    } finally {
        for (const oControl of [oNative, ...aControls]) oControl.destroy();
        domFixture.classList.toggle('sapUiSizeCompact', bOriginalCompact);
        await theme(sOriginalTheme);
    }
}
