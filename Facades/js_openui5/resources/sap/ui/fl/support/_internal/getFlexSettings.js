/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/fl/initial/_internal/Settings"],function(t){"use strict";async function e(){const e=await t.getInstance();return Object.entries(e.getMetadata().getProperties()).map(function([t,n]){let i=e[n._sGetter]();if(t==="versioning"){i=i.CUSTOMER||i.ALL}return{key:t,value:i}})}return function(t){return e(t)}});
//# sourceMappingURL=getFlexSettings.js.map