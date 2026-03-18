/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/fl/apply/api/FlexRuntimeInfoAPI","sap/ui/fl/variants/VariantManager","sap/ui/rta/command/BaseCommand"],function(e,a,t){"use strict";const n=t.extend("sap.ui.rta.command.ControlVariantSave",{metadata:{library:"sap.ui.rta",associations:{},events:{}}});n.prototype.execute=function(){const t=this.getElement();this.sFlexReference=e.getFlexReference({element:t});this._aControlChanges=a.getControlChangesForVariant(this.sFlexReference,t.getVariantManagementReference(),t.getCurrentVariantReference());this._aDirtyChanges=a.getDirtyChangesFromVariantChanges(this._aControlChanges,this.sFlexReference);this._aDirtyChanges.forEach(e=>{if(e.getFileType()==="change"){e.setSavedToVariant(true)}});a.updateVariantManagementMap(this.sFlexReference);return Promise.resolve()};n.prototype.undo=function(){this._aDirtyChanges.forEach(function(e){if(e.getFileType()==="change"){e.setSavedToVariant(false)}});a.updateVariantManagementMap(this.sFlexReference);return Promise.resolve()};return n});
//# sourceMappingURL=ControlVariantSave.js.map