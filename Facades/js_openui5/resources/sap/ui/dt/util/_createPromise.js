/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(function(){"use strict";return function(n){var e=false;var i;var r;var t;var s=new Promise(function(i,s){n(function(...n){if(!e){i.apply(this,n)}else{r.apply(this,n)}},function(...n){if(!e){s.apply(this,n)}else{t.apply(this,n)}})});return{promise:s,cancel(){e=true;i||=new Promise(function(n,e){r=n;t=e});return i}}}});
//# sourceMappingURL=_createPromise.js.map