/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
// FIXME removed this fix because it prevented pasting into jSpreadsheet/jExcel in Firefox
// Also commented out the contents of the closure in the preload files
// - sap-ui-core.js
// - sap-ui-core-nojQuery.js
// - sap-ui-integration.js
// - sap-ui-integration-nojQuery.js
// Commenting out the contents instead of the entire predefine statement is important as
// otherwise UI5 would attempt to load the file separately resulting in a sync request
// breaking offline startup of the entire app.
// sap.ui.define(function(){"use strict";document.documentElement.addEventListener("paste",function(e){var t=document.activeElement;if(e.isTrusted&&t instanceof HTMLElement&&!t.contains(e.target)){var a=new ClipboardEvent("paste",{bubbles:true,cancelable:true,clipboardData:e.clipboardData});t.dispatchEvent(a);e.stopImmediatePropagation();e.preventDefault()}},true)});
//# sourceMappingURL=PasteEventFix.js.map