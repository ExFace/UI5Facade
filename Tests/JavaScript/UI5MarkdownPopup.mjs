/**
 * Compares popup controls with native UI5 fields, checkboxes and menu buttons.
 */
export async function runUI5MarkdownPopupRegression() {
    const oCore = sap.ui.getCore();
    await new Promise(fnResolve => oCore.attachInit(fnResolve));
    const [Markdown, Input, CheckBox, Select, Item, Button, VBox] = await new Promise((fnResolve, fnReject) => {
        sap.ui.require([
            'exface/ui5Custom/Markdown', 'sap/m/Input', 'sap/m/CheckBox', 'sap/m/Select',
            'sap/ui/core/Item', 'sap/m/Button', 'sap/m/VBox', 'libs/exface/markian/editor'
        ], (...aModules) => fnResolve(aModules), fnReject);
    });
    const sOriginalTheme = oCore.getConfiguration().getTheme();
    const domFixture = document.getElementById('fixtures');
    const bOriginalCompact = domFixture.classList.contains('sapUiSizeCompact');
    const oInput = new Input({value: 'Native text'});
    const oCheckbox = new CheckBox({text: 'Match case'});
    const oSelect = new Select({items: [new Item({text: 'None', key: ''})]});
    const oButton = new Button({text: 'Heading 1', icon: 'sap-icon://edit', type: 'Transparent'});
    const oSamples = new VBox({items: [oInput, oCheckbox, oSelect, oButton]});
    const oEditor = new Markdown({height: '300px', width: '650px', value: 'Keep this draft'}).setEditorConfig({
        options: {initialMode: 'visual', sidebarVisible: false}
    });
    const aResults = [];
    /**
     * Waits for actual theme assets before comparing rendered controls.
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
     * Reports the exact visual property that differs from the native control.
     */
    function compare(domActual, domNative, aProperties, sMessage, sPseudo) {
        const oActual = getComputedStyle(domActual, sPseudo);
        const oNative = getComputedStyle(domNative, sPseudo);
        for (const sProperty of aProperties) {
            if (oActual[sProperty] !== oNative[sProperty]) throw new Error(`${sMessage} ${sProperty}: ${oActual[sProperty]} != ${oNative[sProperty]}`);
        }
    }
    try {
        oSamples.placeAt('fixtures');
        oEditor.placeAt('fixtures');
        oCore.applyChanges();
        await oEditor._oEditor.ready;
        const oInstance = oEditor._oEditor.instance;
        const domEditor = oInstance.element;
        await oInstance.execute('search');
        const domSearch = domEditor.querySelector('.markian-search');
        const domInput = domSearch.querySelector('input[type="text"]');
        const domCheckbox = domSearch.querySelector('input[type="checkbox"]');
        domInput.value = 'Keep';
        const aFieldProperties = ['height', 'border', 'borderRadius', 'boxShadow', 'backgroundColor', 'backgroundImage', 'backgroundPosition', 'backgroundSize', 'backgroundRepeat'];
        for (const sTheme of ['sap_fiori_3', 'sap_horizon', 'sap_fiori_3_dark', 'sap_horizon_dark']) {
            await theme(sTheme);
            for (const bCompact of [false, true]) {
                domFixture.classList.toggle('sapUiSizeCompact', bCompact);
                oButton.focus();
                compare(domInput, oInput.getDomRef().querySelector('.sapMInputBaseContentWrapper'), aFieldProperties, sTheme + ' input');
                compare(domInput, oInput.getFocusDomRef(), ['fontSize', 'fontFamily', 'color'], sTheme + ' input text');
                domInput.readOnly = true;
                oInput.setEditable(false);
                oCore.applyChanges();
                compare(domInput, oInput.getDomRef().querySelector('.sapMInputBaseContentWrapper'),
                    ['backgroundImage', 'backgroundPosition', 'backgroundSize', 'backgroundRepeat', 'backgroundColor'], sTheme + ' readonly input');
                domInput.readOnly = false;
                oInput.setEditable(true);
                oCore.applyChanges();
                for (const bChecked of [false, true]) {
                    domCheckbox.checked = bChecked;
                    oCheckbox.setSelected(bChecked);
                    oCore.applyChanges();
                    const domNativeBox = oCheckbox.getDomRef().querySelector('.sapMCbBg');
                    compare(domCheckbox, domNativeBox, ['width', 'height', 'border', 'borderRadius', 'backgroundColor'], sTheme + ' checkbox');
                    if (bChecked) compare(domCheckbox, domNativeBox, ['content', 'fontFamily', 'fontSize', 'color'], sTheme + ' checkmark', '::before');
                }
                domCheckbox.checked = false;
                domCheckbox.disabled = true;
                oCheckbox.setEnabled(false);
                if (getComputedStyle(domCheckbox).opacity === '1') throw new Error('Disabled checkbox has no visual feedback');
                domCheckbox.disabled = false;
                oCheckbox.setEnabled(true);
                const domMenu = domEditor.querySelector('.markian-toolbar > .markian-menu');
                domMenu.open = true;
                const domItems = domMenu.querySelector('.markian-menu-items');
                if (!domItems.matches(':popover-open')) domItems.showPopover();
                const domRow = domItems.querySelector('button');
                compare(domRow, oButton.getDomRef('inner'), ['height', 'border', 'borderRadius', 'fontFamily', 'fontSize'], sTheme + ' menu row');
                domItems.hidePopover();
                domMenu.open = false;
                aResults.push(`${sTheme}: ${bCompact ? 'compact' : 'cozy'} popup fields/checkmarks/menu rows match UI5`);
            }
        }
        await oInstance.execute('link');
        const domSelect = domEditor.querySelector('.markian-destination select');
        if (!domSelect) throw new Error('Link heading selector was not created');
        compare(domSelect, oSelect.getDomRef(), ['height', 'borderWidth', 'borderStyle', 'borderRadius', 'boxShadow', 'backgroundColor'], 'Heading select');
        if (getComputedStyle(domSelect).borderStyle !== 'none') compare(domSelect, oSelect.getDomRef(), ['borderColor'], 'Heading select');
        if (getComputedStyle(domSelect.parentElement, '::after').pointerEvents !== 'none') throw new Error('Dropdown indicator intercepts selection');
        if (domInput.value !== 'Keep' || oEditor.getValue() !== 'Keep this draft') throw new Error('Theme changes discarded popup input or editor draft');
        return aResults;
    } finally {
        oEditor.destroy();
        oSamples.destroy();
        domFixture.classList.toggle('sapUiSizeCompact', bOriginalCompact);
        await theme(sOriginalTheme);
    }
}
