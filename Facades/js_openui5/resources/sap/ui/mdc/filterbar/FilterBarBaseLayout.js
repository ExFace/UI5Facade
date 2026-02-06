/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/core/Control","sap/ui/mdc/mixin/FilterBarLayoutMixin","./FilterBarBaseLayoutRenderer"],(t,e,i)=>{"use strict";const n=t.extend("sap.ui.mdc.filterbar.FilterBarBaseLayout",{metadata:{library:"sap.ui.mdc",defaultAggregation:"content",aggregations:{content:{type:"sap.ui.core.Control",multiple:true},buttons:{type:"sap.ui.core.Control",multiple:true}}},renderer:i,init:function(){t.prototype.init.apply(this,arguments);e.call(n.prototype,{_getFilterItems:function(){return this.getParent()?.getFilterFields?.()??this.getContent()},_getButtons:function(){return this.getButtons()}})},onBeforeRendering:function(){this._deregisterResizeHandlers()},onAfterRendering:function(){this._registerResizeHandlers();this._onResize()},exit:function(){t.prototype.exit.apply(this,arguments)}});return n});
//# sourceMappingURL=FilterBarBaseLayout.js.map