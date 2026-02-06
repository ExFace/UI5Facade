/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/base/BindingParser","sap/ui/core/Lib"],function(e,r){"use strict";const t=" ";const o={noEmptyText:{validatorFunction(e){return e!==t},errorMessage:r.getResourceBundleFor("sap.ui.rta").getText("RENAME_EMPTY_ERROR_TEXT")}};function n(t,o){if(o===t){throw Error("sameTextError")}let n;let i;try{n=e.complexParser(t,undefined,true)}catch(e){i=true}if(n&&typeof n==="object"||i){throw Error(r.getResourceBundleFor("sap.ui.rta").getText("RENAME_BINDING_ERROR_TEXT"))}}return function(e,r,t){n(e,r);let i;const s=t&&t.validators||[];s.some(function(r){let t;if(typeof r==="string"&&o[r]){t=o[r]}else{t=r}if(!t.validatorFunction(e)){i=t.errorMessage;return true}return false});if(i){throw Error(i)}}});
//# sourceMappingURL=validateText.js.map