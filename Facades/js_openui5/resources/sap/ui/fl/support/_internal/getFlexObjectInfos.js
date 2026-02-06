/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/fl/apply/_internal/flexState/changes/UIChangesState","sap/ui/fl/apply/_internal/flexState/controlVariants/VariantManagementState","sap/ui/fl/apply/_internal/flexState/FlexObjectState","sap/ui/fl/apply/_internal/flexState/FlexState","sap/ui/fl/initial/_internal/ManifestUtils"],function(e,t,n,a,l){"use strict";function i(i){const r=i.oContainer.getComponentInstance();const p=l.getFlexReferenceForControl(r);return{allUIChanges:e.getAllUIChanges(p),allFlexObjects:a.getFlexObjectsDataSelector().get({reference:p}),dirtyFlexObjects:n.getDirtyFlexObjects(p),completeDependencyMap:n.getCompleteDependencyMap(p),liveDependencyMap:n.getLiveDependencyMap(p),variantManagementMap:t.getVariantManagementMap().get({reference:p})}}return function(e){return Promise.resolve(i(e))}});
//# sourceMappingURL=getFlexObjectInfos.js.map