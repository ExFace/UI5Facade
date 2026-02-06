/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define([],function(){"use strict";return{name:{singular:"PANEL_NAME",plural:"PANEL_NAME_PLURAL"},getLabel:function(e){return e.getDomRef().shadowRoot.querySelector(".ui5-panel-header-title").textContent},actions:{remove:{changeType:"hideControl"},reveal:{changeType:"unhideControl",getLabel:function(e){return e.getHeaderText()||e.getId()}},rename:{changeType:"rename",domRef:function(e){return e.getDomRef().shadowRoot.querySelector(".ui5-panel-header-title")}}},aggregations:{header:{domRef:function(e){return e.getDomRef().shadowRoot.querySelector(".ui5-panel-header")}},content:{domRef:function(e){return e.getDomRef().shadowRoot.querySelector(".ui5-panel-content")},actions:{move:"moveControls"}}}}});
//# sourceMappingURL=Panel.designtime.js.map