/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/rta/command/FlexCommand"],function(e){"use strict";const t=e.extend("sap.ui.rta.command.Move",{metadata:{library:"sap.ui.rta",properties:{movedElements:{type:"any[]",group:"content"},target:{type:"any",group:"content"},source:{type:"any",group:"content"}},associations:{},events:{}}});t.prototype._getChangeSpecificData=function(){const e=this.getSource();const t=this.getTarget();if(e.parent){e.id=e.parent.getId();delete e.parent}if(t.parent){t.id=t.parent.getId();delete t.parent}const n={changeType:this.getChangeType(),content:{source:e,target:t,movedElements:[]}};this.getMovedElements().forEach(function(e){n.content.movedElements.push({id:e.id||e.element&&e.element.getId(),sourceIndex:e.sourceIndex,targetIndex:e.targetIndex})});return n};return t});
//# sourceMappingURL=Move.js.map