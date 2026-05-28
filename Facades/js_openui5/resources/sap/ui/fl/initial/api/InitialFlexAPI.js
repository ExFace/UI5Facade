/*
 * ! OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/fl/initial/_internal/FlexInfoSession","sap/ui/fl/initial/_internal/Loader","sap/ui/fl/initial/_internal/ManifestUtils","sap/ui/fl/initial/_internal/Settings","sap/ui/fl/initial/_internal/StorageUtils","sap/ui/fl/requireAsync","sap/ui/fl/Utils"],function(e,t,n,i,s,l,r){"use strict";var a={};a.isKeyUser=async function(){const e=await i.getInstance();return e.getIsKeyUser()};a.getFlexVersion=function(t){return e.getByReference(t.reference)?.version};a.waitForChanges=async function(e){let i;if(e.element){i=[{selector:e.element}]}else if(e.selectors){i=e.selectors.map(function(e){return{selector:e}})}else if(e.complexSelectors){i=e.complexSelectors}const a=r.getAppComponentForSelector(i[0].selector);const o=n.getFlexReferenceForControl(a);const c=t.getCachedFlexData(o);if(!s.isStorageResponseFilled(c.changes)){return undefined}const f=await l("sap/ui/fl/apply/_internal/flexState/FlexObjectState");return f.waitForFlexObjectsToBeApplied(i,a)};return a});
//# sourceMappingURL=InitialFlexAPI.js.map