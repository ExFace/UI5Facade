/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/fl/Utils","sap/ui/dt/ElementUtil","sap/base/util/isPlainObject"],function(t,e,n){"use strict";function a(t){const{sParentAggregationName:e}=t;const n=t.getParent();if(n&&e){const s=n.getBindingInfo(e);if(s){if(s.template&&t.isA(s.template.getMetadata().getClass())){return s.template}return false}return a(n)}return false}return function s(i){if(!i||i._bIsBeingDestroyed){return false}if(typeof i.data("hasStableId")!=="boolean"){const s=i.getDesignTimeMetadata().getStableElements(i);let o=false;if(s.length>0){if(a(i.getElement())){o=s.some(function(a){const s=n(a)?a.id:a;const i=n(a)&&a.appComponent;o=!t.checkControlId(s,i);if(o){const n=e.getElementInstance(s);if(e.getElementInstance(s)){const a=e.getAggregationInformation(n);o=!t.checkControlId(e.extractTemplateId(a),i)}}return o})}else{o=s.some(function(e){const n=e.id||e;return!t.checkControlId(n,e.appComponent)})}}i.data("hasStableId",!o)}return i.data("hasStableId")}});
//# sourceMappingURL=hasStableId.js.map