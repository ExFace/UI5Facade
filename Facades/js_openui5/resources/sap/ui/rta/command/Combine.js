/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/rta/command/FlexCommand"],function(e){"use strict";const t=e.extend("sap.ui.rta.command.Combine",{metadata:{library:"sap.ui.rta",properties:{newElementId:{type:"string",group:"content"},source:{type:"any",group:"content"},combineElements:{type:"any[]",group:"content"}},associations:{},events:{}}});t.prototype._getChangeSpecificData=function(){const e=[];this.getCombineElements().forEach(function(t){e.push(t.getId())});const t={changeType:this.getChangeType(),content:{newElementId:this.getNewElementId(),sourceControlId:this.getSource().getId(),combineElementIds:e}};return t};return t});
//# sourceMappingURL=Combine.js.map