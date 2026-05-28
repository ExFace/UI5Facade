/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/fl/Utils"],function(t){"use strict";function e(e){if(!e){return false}const n=t.getAppComponentForControl(e);if(!n){return false}const s=e.getManifest();const r=n.getManifest();const o=s?.["sap.app"]?.id;const i=r?.["sap.ui5"]?.componentUsages;return Object.values(i||{}).some(t=>{if(t.name===o){return true}return false})}return e});
//# sourceMappingURL=isReuseComponent.js.map