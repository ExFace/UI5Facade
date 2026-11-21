/**
 * Ensures opaque editing surfaces cannot cover UI5's inset border or focus ring.
 */
export async function runUI5MarkdownBorderRegression() {
    const oCore = sap.ui.getCore();
    await new Promise(fnResolve => oCore.attachInit(fnResolve));
    const [Markdown] = await new Promise((fnResolve, fnReject) => {
        sap.ui.require(['exface/ui5Custom/Markdown', 'libs/exface/markian/editor'],
            (...aModules) => fnResolve(aModules), fnReject);
    });
    const sOriginalTheme = oCore.getConfiguration().getTheme();
    const oEditor = new Markdown({height: '220px', width: '650px'}).setEditorConfig({
        options: {initialMode: 'visual', sidebarVisible: false}
    });
    const aResults = [];
    /**
     * Waits for actual theme loading before testing its border tokens.
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
     * Fail explicitly if a border is missing or can intercept editor interaction.
     */
    function check(bCondition, sMessage) {
        if (!bCondition) throw new Error(sMessage);
    }
    try {
        oEditor.placeAt('fixtures');
        oCore.applyChanges();
        await oEditor._oEditor.ready;
        const domEditor = oEditor._oEditor.instance.element;
        const domProbe = document.createElement('div');
        oEditor.getDomRef().append(domProbe);
        for (const sTheme of ['sap_horizon', 'sap_horizon_dark', 'sap_fiori_3', 'sap_fiori_3_dark']) {
            await theme(sTheme);
            for (const sMode of ['visual', 'raw']) {
                oEditor._oEditor.instance.setMode(sMode);
                oEditor.focus();
                const oOverlay = getComputedStyle(domEditor, '::after');
                domProbe.style.boxShadow = 'var(--sapField_Shadow)';
                check(oOverlay.content !== 'none', `${sTheme}: border layer missing`);
                check(oOverlay.boxShadow === getComputedStyle(domProbe).boxShadow, `${sTheme}: field shadow missing`);
                check(oOverlay.position === 'absolute' && oOverlay.inset === '0px', 'Border does not enclose the entire editor');
                check(Number(oOverlay.zIndex) > Number(getComputedStyle(domEditor.querySelector('.markian-toolbar')).zIndex), 'Border is behind editor surfaces');
                check(oOverlay.pointerEvents === 'none', 'Border layer intercepts editor clicks');
                check(oOverlay.outlineStyle !== 'none', `${sTheme}: focus indicator missing`);
                if (sTheme.startsWith('sap_horizon')) check(oOverlay.boxShadow !== 'none', 'Horizon inset border is absent');
                else check(getComputedStyle(domEditor).borderLeftStyle === 'solid', 'Fiori border was removed');
                oEditor.setValueStateText('Invalid Markdown').setValueState('Error');
                domProbe.style.boxShadow = 'var(--sapField_InvalidShadow)';
                check(getComputedStyle(domEditor, '::after').boxShadow === getComputedStyle(domProbe).boxShadow, `${sTheme}: invalid border missing`);
                oEditor.setValueState('None');
                aResults.push(`${sTheme}: ${sMode} border/focus/validation layer`);
            }
        }
        return aResults;
    } finally {
        oEditor.destroy();
        await theme(sOriginalTheme);
    }
}
