/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/m/p13n/Engine","sap/ui/core/Lib"],(e,t)=>{"use strict";return{actions:{settings:{"sap.ui.mdc":function(n){const r=e.getInstance()._getKeyUserPersistence(n);return{name:function(){return t.getResourceBundleFor("sap.ui.mdc").getText("filterbar.ADAPT_TITLE")},handler:function(t,n){return t.initializedWithMetadata().then(()=>e.getInstance().getRTASettingsActionHandler(t,n,"Item"))},CAUTION_variantIndependent:r}}}},aggregations:{layout:{ignore:true},basicSearchField:{ignore:true},filterItems:{ignore:true}},properties:{showAdaptFiltersButton:{ignore:false},showClearButton:{ignore:false},p13nMode:{ignore:false},enableLegacyUI:{ignore:true},adaptFiltersText:{ignore:true},adaptFiltersTextNonZero:{ignore:true}}}});
//# sourceMappingURL=FilterBar.designtime.js.map