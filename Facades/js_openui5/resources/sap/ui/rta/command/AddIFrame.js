/*!
 * OpenUI5
 * (c) Copyright 2025 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/rta/command/FlexCommand"],function(t){"use strict";var e=t.extend("sap.ui.rta.command.AddIFrame",{metadata:{library:"sap.ui.rta",properties:{baseId:{type:"string",group:"content"},targetAggregation:{type:"string",group:"content"},index:{type:"int",group:"content"},url:{type:"string",group:"content"},width:{type:"string",group:"content"},height:{type:"string",group:"content"},title:{type:"string",group:"content"},advancedSettings:{type:"object",defaultValue:{},group:"content"},changeType:{type:"string",defaultValue:"addIFrame"}},associations:{},events:{}}});e.prototype.applySettings=function(...e){const n=e[0];var r={};Object.keys(n).filter(function(t){return t!=="url"}).forEach(function(t){r[t]=n[t]});e[0]=r;t.prototype.applySettings.apply(this,e);this.setUrl(n.url)};e.prototype._getChangeSpecificData=function(){var e=t.prototype._getChangeSpecificData.call(this);var n=e.changeType;delete e.changeType;return{changeType:n,content:e.content}};return e},true);
//# sourceMappingURL=AddIFrame.js.map