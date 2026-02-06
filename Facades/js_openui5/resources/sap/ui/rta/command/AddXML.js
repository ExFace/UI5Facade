/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/rta/command/FlexCommand"],function(t){"use strict";const e=t.extend("sap.ui.rta.command.AddXML",{metadata:{library:"sap.ui.rta",properties:{fragment:{type:"string",group:"content"},fragmentPath:{type:"string",group:"content"},targetAggregation:{type:"string",group:"content"},index:{type:"int",group:"content"},changeType:{type:"string",defaultValue:"addXML"}},associations:{},events:{}}});e.prototype.bindProperty=function(...e){const[n,r]=e;if(n==="fragment"){return this.setFragment(r.bindingString)}return t.prototype.bindProperty.apply(this,e)};e.prototype._applyChange=function(...e){const n=e[0];const r={};r[n.getFlexObjectMetadata().moduleName]=this.getFragment();sap.ui.require.preload(r);return t.prototype._applyChange.apply(this,e)};return e},true);
//# sourceMappingURL=AddXML.js.map