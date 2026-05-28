/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/fl/apply/api/FlexRuntimeInfoAPI","sap/ui/rta/Utils","sap/ui/rta/util/whatsNew/whatsNewContent/WhatsNewFeatures"],function(t,e,n){"use strict";function r(t,e){return t.filter(t=>!e?.includes(t.featureId))}async function i(t,e){const n=await Promise.all(t.map(t=>{if(typeof t.isFeatureApplicable==="function"){return t.isFeatureApplicable(e)}return true}));const r=t.filter((t,e)=>n[e]);return r}const s={getLearnMoreURL(t,n){const r=t.slice(-1);const i=n[r].documentationUrls;return e.getSystemSpecificDocumentationUrl(i)},getFilteredFeatures(t,e){const s=n.getAllFeatures();const u=r(s,t);return i(u,e)}};return s});
//# sourceMappingURL=WhatsNewUtils.js.map