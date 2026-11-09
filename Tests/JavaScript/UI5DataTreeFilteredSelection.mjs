// Browser regression for the real UI5 TreeBinding on the Terminübersicht page.

/**
 * Waits for the search request and deferred selection restoration to finish.
 */
async function reload(oTable, oSearchButton) {
    oSearchButton.firePress();
    const fStart = performance.now();
    while (oTable.getBusy()) {
        if (performance.now() - fStart > 25000) {
            throw new Error('Search did not finish within 25 seconds.');
        }
        await new Promise(fnResolve => setTimeout(fnResolve, 50));
    }
    await new Promise(fnResolve => setTimeout(fnResolve, 100));
}

/**
 * Reports failed browser assertions without swallowing selection or loading errors.
 */
function check(bCondition, sMessage) {
    if (!bCondition) {
        throw new Error(sMessage);
    }
}

/**
 * Selects six rows, filters to 04.11.2021, and exercises repeated header toggles.
 *
 * Pass the page's actual TreeTable, Ende Zeitraum DatePicker and Suchen button.
 * The initial date filter must be empty. The filter is restored after the test.
 */
export async function runFilteredSelectionRegression(oTable, oEndDate, oSearchButton) {
    check(!oTable.getBusy(), 'The initial table must have finished loading.');
    check(oEndDate.getValue() === '', 'The initial Ende Zeitraum filter must be empty.');
    check(oTable.getBinding('rows').getLength() >= 6, 'The initial table must contain at least six rows.');
    const aResults = [];
    oTable.clearSelection();
    oTable.addSelectionInterval(0, 5);
    check(oTable.getSelectedIndices().length === 6, 'The first six rows must be selected.');
    try {
        oEndDate.setDateValue(new Date(2021, 10, 4));
        oEndDate.fireChange({value: oEndDate.getValue(), valid: true});
        await reload(oTable, oSearchButton);
        const oBinding = oTable.getBinding('rows');
        const iRows = oBinding.getLength();
        check(iRows === 2, `The regression dataset must contain two filtered rows, got ${iRows}.`);
        check(oTable.getSelectedIndices().length === 2, 'The two remaining selected rows must be restored.');
        check(oBinding.getSelectedNodesCount() === 2, 'The binding must not retain four removed selected nodes.');
        aResults.push({phase: 'filtered', visibleSelected: 2, bindingSelected: oBinding.getSelectedNodesCount()});
        for (let i = 0; i < 6; i++) {
            // Exercise the same UI5 handler used by mouse and keyboard header selection.
            oTable._getSelectionPlugin().onHeaderSelectorPress();
            sap.ui.getCore().applyChanges();
            const iExpected = i % 2 === 0 ? 0 : iRows;
            const iSelected = oTable.getSelectedIndices().length;
            const iSaved = oTable.getModel('selections').getProperty('/rows').length;
            const iBindingSelected = oBinding.getSelectedNodesCount();
            const sChecked = document.getElementById(oTable.getId() + '-selall').getAttribute('aria-checked');
            check(iSelected === iExpected, `Header click ${i + 1}: expected ${iExpected} selected rows, got ${iSelected}.`);
            check(iSaved === iExpected, `Header click ${i + 1}: saved selection is inconsistent.`);
            check(iBindingSelected === iExpected, `Header click ${i + 1}: binding selection is inconsistent.`);
            check(sChecked === String(iExpected === iRows), `Header click ${i + 1}: header checkbox is inconsistent.`);
            aResults.push({phase: `click ${i + 1}`, visibleSelected: iSelected, bindingSelected: iBindingSelected, saved: iSaved});
        }
        return aResults;
    } finally {
        oEndDate.setValue('');
        oEndDate.fireChange({value: '', valid: true});
        await reload(oTable, oSearchButton);
    }
}
