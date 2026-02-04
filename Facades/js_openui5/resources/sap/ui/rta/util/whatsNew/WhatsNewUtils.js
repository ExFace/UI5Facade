/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/fl/apply/api/FlexRuntimeInfoAPI","sap/ui/rta/Utils","sap/ui/rta/util/whatsNew/whatsNewContent/WhatsNewFeatures"],function(t,e,n){"use strict";function r(t,e){return t.filter(t=>!e?.includes(t.featureId))}async function a(t,e){const n=await Promise.all(t.map(t=>{if(typeof t.isFeatureApplicable==="function"){return t.isFeatureApplicable(e)}return true}));const r=t.filter((t,e)=>n[e]);return r}const s={getLearnMoreURL(n,r){const a=n.slice(-1);const s=!!t.getSystem();if(s){if(e.isS4HanaCloud()){return r[a].documentationUrls.s4HanaCloudUrl}return r[a].documentationUrls.s4HanaOnPremUrl}return r[a].documentationUrls.btpUrl},getFilteredFeatures(t,e){const s=n.getAllFeatures();const u=r(s,t);return a(u,e)}};return s});
//# sourceMappingURL=WhatsNewUtils.js.map