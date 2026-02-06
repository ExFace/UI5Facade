/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/rta/command/FlexCommand"],function(e){"use strict";const t=e.extend("sap.ui.rta.command.Split",{metadata:{library:"sap.ui.rta",properties:{newElementIds:{type:"string[]",group:"content"},source:{type:"any",group:"content"},parentElement:{type:"any",group:"content"}},associations:{},events:{}}});t.prototype._getChangeSpecificData=function(){const e={changeType:this.getChangeType(),content:{newElementIds:this.getNewElementIds(),sourceControlId:this.getSource().getId(),parentId:this.getParentElement().getId()}};return e};return t});
//# sourceMappingURL=Split.js.map