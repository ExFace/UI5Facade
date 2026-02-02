/*!
 * OpenUI5
 * (c) Copyright 2025 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(function(){"use strict";return function(i){var n=false;var e;var r;var t;var s=new Promise(function(e,s){i(function(...i){if(!n){e.apply(this,i)}else if(r){r.apply(this,i)}},function(...i){if(!n){s.apply(this,i)}else if(t){t.apply(this,i)}})});return{promise:s,cancel(){n=true;e||=new Promise(function(i,n){r=i;t=n});return e}}}});
//# sourceMappingURL=_createPromise.js.map