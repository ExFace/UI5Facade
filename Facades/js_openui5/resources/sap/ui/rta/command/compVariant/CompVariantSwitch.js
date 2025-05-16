/*!
 * OpenUI5
 * (c) Copyright 2025 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/rta/command/BaseCommand","sap/ui/fl/write/api/SmartVariantManagementWriteAPI"],function(t,e){"use strict";var a=t.extend("sap.ui.rta.command.compVariant.CompVariantSwitch",{metadata:{library:"sap.ui.rta",properties:{sourceVariantId:{type:"string"},targetVariantId:{type:"string"},discardVariantContent:{type:"boolean"}}},constructor:function(...e){t.apply(this,e);this.setRelevantForSave(false)}});a.prototype.execute=function(){this.getElement().activateVariant(this.getTargetVariantId());if(this.getDiscardVariantContent()){this.getElement().setModified(false);e.discardVariantContent({control:this.getElement(),id:this.getSourceVariantId()})}return Promise.resolve()};a.prototype.undo=function(){if(this.getDiscardVariantContent()){e.revert({control:this.getElement(),id:this.getSourceVariantId()})}this.getElement().activateVariant(this.getSourceVariantId());if(this.getDiscardVariantContent()){this.getElement().setModified(true)}return Promise.resolve()};return a});
//# sourceMappingURL=CompVariantSwitch.js.map