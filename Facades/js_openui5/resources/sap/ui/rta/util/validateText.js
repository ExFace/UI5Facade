/*!
 * OpenUI5
 * (c) Copyright 2025 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/base/BindingParser","sap/ui/core/Lib"],function(r,e){"use strict";var t=" ";var o={noEmptyText:{validatorFunction(r){return r!==t},errorMessage:e.getResourceBundleFor("sap.ui.rta").getText("RENAME_EMPTY_ERROR_TEXT")}};function a(t,o){if(o===t){throw Error("sameTextError")}var a;var i;try{a=r.complexParser(t,undefined,true)}catch(r){i=true}if(a&&typeof a==="object"||i){throw Error(e.getResourceBundleFor("sap.ui.rta").getText("RENAME_BINDING_ERROR_TEXT"))}}return function(r,e,t){a(r,e);var i;var n=t&&t.validators||[];n.some(function(e){var t;if(typeof e==="string"&&o[e]){t=o[e]}else{t=e}if(!t.validatorFunction(r)){i=t.errorMessage;return true}return false});if(i){throw Error(i)}}});
//# sourceMappingURL=validateText.js.map