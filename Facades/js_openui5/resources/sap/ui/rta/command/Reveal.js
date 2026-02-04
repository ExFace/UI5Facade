/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/rta/command/FlexCommand"],function(e){"use strict";var t=e.extend("sap.ui.rta.command.Reveal",{metadata:{library:"sap.ui.rta",properties:{revealedElementId:{type:"string",group:"content"},directParent:"object"}}});t.prototype._getChangeSpecificData=function(){var e={changeType:this.getChangeType(),content:{}};if(this.getRevealedElementId()){e.content.revealedElementId=this.getRevealedElementId()}return e};return t});
//# sourceMappingURL=Reveal.js.map