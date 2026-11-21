/**
 * Removes final Markdown group spacing without changing other form groups.
 */
export async function runUI5MarkdownSpacingRegression() {
    const oCore = sap.ui.getCore();
    await new Promise(fnResolve => oCore.attachInit(fnResolve));
    const [Markdown, Form, FormContainer, FormElement, ResponsiveGridLayout, Input, GridData] = await new Promise((fnResolve, fnReject) => {
        sap.ui.require([
            'exface/ui5Custom/Markdown', 'sap/ui/layout/form/Form',
            'sap/ui/layout/form/FormContainer', 'sap/ui/layout/form/FormElement',
            'sap/ui/layout/form/ResponsiveGridLayout', 'sap/m/Input',
            'sap/ui/layout/GridData',
            'libs/exface/markian/editor'
        ], (...aModules) => fnResolve(aModules), fnReject);
    });
    const oFirstEditor = new Markdown({height: '220px', value: 'First'});
    const oLastEditor = new Markdown({height: '220px', value: 'Last'});
    const oLastElement = new FormElement({fields: [oLastEditor]});
    const oForm = new Form({
        editable: true,
        layout: new ResponsiveGridLayout({columnsL: 1, columnsM: 1}),
        formContainers: [
            new FormContainer({
                layoutData: new GridData({span: 'XL12 L12 M12 S12'}),
                formElements: [new FormElement({fields: [oFirstEditor]})]
            }),
            new FormContainer({
                layoutData: new GridData({span: 'XL12 L12 M12 S12'}),
                formElements: [oLastElement]
            })
        ]
    });
    /**
     * Compare the final group's padding to the unchanged non-final group.
     */
    function check(bCondition, sMessage) {
        if (!bCondition) throw new Error(sMessage);
    }
    try {
        oForm.placeAt('fixtures');
        oCore.applyChanges();
        await Promise.all([oFirstEditor._oEditor.ready, oLastEditor._oEditor.ready]);
        const domFirstGroup = oFirstEditor.getDomRef().closest('.sapUiFormResGridCont');
        const domLastGroup = oLastEditor.getDomRef().closest('.sapUiFormResGridCont');
        const fNormalPadding = parseFloat(getComputedStyle(domFirstGroup).paddingBottom);
        check(fNormalPadding > 0, 'Non-final Markdown group lost its spacing');
        check(getComputedStyle(domLastGroup).paddingBottom === '0px', 'Final Markdown group still has extra spacing');
        const fEditorHeight = oLastEditor.getDomRef().getBoundingClientRect().height;
        const fGroupHeight = domLastGroup.getBoundingClientRect().height;
        oLastElement.addField(new Input({value: 'Following field'}));
        oCore.applyChanges();
        await oLastEditor._oEditor.ready;
        const domExpandedGroup = oLastEditor.getDomRef().closest('.sapUiFormResGridCont');
        check(parseFloat(getComputedStyle(domExpandedGroup).paddingBottom) === fNormalPadding, 'Spacing was removed when Markdown was not the last field');
        check(domExpandedGroup.getBoundingClientRect().height >= fGroupHeight + fNormalPadding, 'Normal form spacing was not restored');
        check(oLastEditor.getDomRef().getBoundingClientRect().height === fEditorHeight, 'Changing form spacing resized the editor');
        check(oFirstEditor.getValue() === 'First' && oLastEditor.getValue() === 'Last', 'Changing form spacing lost editor values');
        return ['final Markdown group removes extra spacing', 'non-final groups and following fields retain normal spacing', 'editor height and values unchanged'];
    } finally {
        oForm.destroy();
    }
}
