/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/rta/command/FlexCommand"],function(e){"use strict";const t=e.extend("sap.ui.rta.command.Rename",{metadata:{library:"sap.ui.rta",properties:{renamedElement:{type:"object",group:"content"},newValue:{type:"string",defaultValue:"new text",group:"content"}},associations:{},events:{}}});t.prototype._getChangeSpecificData=function(){const e={changeType:this.getChangeType(),content:{renamedElement:{id:this.getRenamedElement().getId()},value:this.getNewValue()}};return e};return t});
//# sourceMappingURL=Rename.js.map