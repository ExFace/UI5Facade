/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/rta/command/FlexCommand"],function(t){"use strict";const e=t.extend("sap.ui.rta.command.AddIFrame",{metadata:{library:"sap.ui.rta",properties:{baseId:{type:"string",group:"content"},targetAggregation:{type:"string",group:"content"},index:{type:"int",group:"content"},url:{type:"string",group:"content"},width:{type:"string",group:"content"},height:{type:"string",group:"content"},title:{type:"string",group:"content"},advancedSettings:{type:"object",defaultValue:{},group:"content"},changeType:{type:"string",defaultValue:"addIFrame"}},associations:{},events:{}}});e.prototype.applySettings=function(...e){const n=e[0];const a={};Object.keys(n).filter(t=>t!=="url").forEach(t=>{a[t]=n[t]});e[0]=a;t.prototype.applySettings.apply(this,e);this.setUrl(n.url)};e.prototype._getChangeSpecificData=function(){const e=t.prototype._getChangeSpecificData.call(this);const{title:n,...a}=e.content;return{changeType:e.changeType,content:a,texts:n?{title:{value:n,type:"XTIT"}}:{}}};return e},true);
//# sourceMappingURL=AddIFrame.js.map