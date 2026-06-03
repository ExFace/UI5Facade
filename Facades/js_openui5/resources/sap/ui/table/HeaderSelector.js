/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["./HeaderSelectorRenderer","sap/ui/core/Control"],function(e,t){"use strict";const r=t.extend("sap.ui.table.HeaderSelector",{metadata:{library:"sap.ui.table",properties:{type:{type:"string",group:"Appearance",defaultValue:"CheckBox"},visible:{type:"boolean",group:"Appearance",defaultValue:false},enabled:{type:"boolean",group:"Behavior",defaultValue:true},checkBoxSelected:{type:"boolean",group:"Appearance",defaultValue:false},icon:{type:"sap.ui.core.URI",group:"Appearance",defaultValue:""}}},renderer:e});r.prototype.resetSettings=function(){this.resetProperty("type");this.resetProperty("visible");this.resetProperty("enabled");this.resetProperty("checkBoxSelected");this.resetProperty("icon");this.destroyTooltip()};return r});
//# sourceMappingURL=HeaderSelector.js.map