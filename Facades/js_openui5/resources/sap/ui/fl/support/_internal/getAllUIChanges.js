/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/fl/apply/_internal/flexState/changes/UIChangesState","sap/ui/fl/initial/_internal/ManifestUtils"],function(e,n){"use strict";function t(t){const i=t.oContainer.getComponentInstance();const a=n.getFlexReferenceForControl(i);return e.getAllUIChanges(a)}return function(e){return Promise.resolve(t(e))}});
//# sourceMappingURL=getAllUIChanges.js.map