# Modifications to UI5 libraries

As of version 1.82

## Global modifications

### Removed `PasteEventFix`

By default UI5 includes a "fix" to standardize paste events in `sap/ui/events/PasteEventFix.js`. This cancelles the original event a replaces it by a new one. 
This causes problems with some Browsers however, as they do not allow programmatically generated paste events for security reasons.

This "fix" was removed because it prevented pasting into jSpreadsheet/jExcel in Firefox. Also commented out the contents of the closure in various preload 
files - see `sap/ui/events/PasteEventFix.js`.

Also commented out the contents of the closure in the preload files
- sap-ui-core.js
- sap-ui-core-nojQuery.js
- sap-ui-integration.js
- sap-ui-integration-nojQuery.js

Commenting out the contents instead of the entire predefine statement is important as otherwise UI5 would attempt to load the file separately resulting in 
a sync request breaking offline startup of the entire app.

## sap.ui.table

### Prevent column focus on auto-column-width in `sap.ui.table.Table`

`oTable.autoResizeColumn()` sets the focus to the column, so it is scrolled into view. After a number of workaround attempts, a hack of UI5 solves the problem now. Previous attempts included 

- cancelling the focus event (did not work because focus is given programmatically), 
- applying focusInfo with preventScroll:true (did not work at all),
- scrolling back to previous positions inside the table scroller and eventually a page container - worked, but cause flickering due to quick down and up scrolling.
- currently the focus() call is simply commented out in `sap/ui/table/library-preload.js` and `sap/ui/table/pointer-dbg.js`

