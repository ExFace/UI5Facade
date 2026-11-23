/**
 * Uses genuine pointer hover to compare Markian against native toolbar buttons.
 *
 * @param {function(string): Promise<void>} fnHover Browser automation's selector hover callback.
 */
export async function runUI5MarkdownHoverRegression(fnHover) {
    const oCore = sap.ui.getCore();
    await new Promise(fnResolve => oCore.attachInit(fnResolve));
    const [Markdown, Button, Toolbar] = await new Promise((fnResolve, fnReject) => {
        sap.ui.require(['exface/ui5Custom/Markdown', 'sap/m/Button', 'sap/m/Toolbar', 'libs/exface/markian/editor'],
            (...aModules) => fnResolve(aModules), fnReject);
    });
    const sOriginalTheme = oCore.getConfiguration().getTheme();
    const oButton = new Button('native-markian-hover', {icon: 'sap-icon://edit', type: 'Transparent'});
    const oDisabled = new Button('native-markian-disabled', {icon: 'sap-icon://undo', type: 'Transparent', enabled: false});
    const oToolbar = new Toolbar({content: [oButton, oDisabled]});
    const oEditor = new Markdown({height: '220px', width: '650px'}).setEditorConfig({
        options: {initialMode: 'visual', sidebarVisible: false}
    });
    const aResults = [];
    /**
     * Waits until UI5 has applied the selected theme's CSS.
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
     * Compares the painted hover state including the theme-specific border.
     */
    function appearance(domElement) {
        const oStyle = getComputedStyle(domElement);
        return JSON.stringify([oStyle.backgroundColor, oStyle.border, oStyle.color, oStyle.boxShadow]);
    }
    try {
        oToolbar.placeAt('fixtures');
        oEditor.placeAt('fixtures');
        oCore.applyChanges();
        await oEditor._oEditor.ready;
        const domRoot = oEditor._oEditor.instance.element;
        const aSelectors = [
            '.markian-toolbar > button[title="Find and replace"]',
            '.markian-toolbar > .markian-menu:first-of-type > summary'
        ];
        const sDisabledSelector = '.markian-toolbar > button[title="Undo"]';
        for (const sTheme of ['sap_horizon', 'sap_fiori_3', 'sap_horizon_dark', 'sap_fiori_3_dark']) {
            await theme(sTheme);
            await fnHover('#native-markian-hover');
            const sNativeHover = appearance(oButton.getDomRef('inner'));
            for (const sSelector of aSelectors) {
                const domTarget = domRoot.querySelector(sSelector);
                if (!domTarget) throw new Error('Toolbar hover fixture is missing: ' + sSelector);
                const fWidth = domTarget.getBoundingClientRect().width;
                await fnHover(sSelector);
                const domHovered = domRoot.querySelector(sSelector);
                if (appearance(domHovered) !== sNativeHover) throw new Error(`${sTheme}: ${sSelector} differs from native hover: ${appearance(domHovered)} != ${sNativeHover}`);
                if (domHovered.getBoundingClientRect().width !== fWidth) throw new Error('Hover changed toolbar layout');
            }
            await fnHover('#native-markian-disabled');
            const sNativeDisabled = appearance(oDisabled.getDomRef('inner'));
            await fnHover(sDisabledSelector);
            const domDisabled = domRoot.querySelector(sDisabledSelector);
            if (appearance(domDisabled) !== sNativeDisabled) throw new Error(`${sTheme}: disabled hover differs from UI5`);
            aResults.push(`${sTheme}: button/dropdown/disabled hover matches native toolbar`);
        }
        return aResults;
    } finally {
        oEditor.destroy();
        oToolbar.destroy();
        await theme(sOriginalTheme);
    }
}
