/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/fl/apply/_internal/changes/descriptor/ApplyStrategyFactory","sap/ui/fl/apply/_internal/changes/descriptor/RawApplier","sap/ui/fl/apply/_internal/changes/Utils","sap/ui/fl/apply/_internal/flexObjects/FlexObjectFactory","sap/ui/fl/apply/_internal/flexState/FlexState","sap/ui/fl/initial/_internal/ManifestUtils"],function(e,t,a,n,p,s){"use strict";const l={async applyChanges(n,l,r){const i=s.getFlexReference({manifest:n});const c=l||p.getAppDescriptorChanges(i);const f=r||e.getRuntimeStrategy();const g=[];for(const e of c){g.push(await a.getChangeHandler({flexObject:e,strategy:f}))}return t.applyChanges(g,n,c,f)},applyInlineChanges(e,t){const a=t.map(function(e){return n.createAppDescriptorChange(e)});return l.applyChanges(e,a)}};return l});
//# sourceMappingURL=Applier.js.map