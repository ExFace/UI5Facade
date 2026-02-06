/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/rta/command/FlexCommand"],function(t){"use strict";const e=t.extend("sap.ui.rta.command.BindProperty",{metadata:{library:"sap.ui.rta",properties:{propertyName:{type:"string",group:"content"},newBinding:{type:"string",group:"content"},changeType:{type:"string",defaultValue:"propertyBindingChange"}},associations:{},events:{}}});e.prototype.applySettings=function(...e){const[n]=e;t.prototype.applySettings.apply(this,e);this.setNewBinding(n.newBinding)};e.prototype._getChangeSpecificData=function(){const t=this.getElement();const e={changeType:this.getChangeType(),selector:{id:t.getId(),type:t.getMetadata().getName()},content:{property:this.getPropertyName(),newBinding:this.getNewBinding()}};return e};return e});
//# sourceMappingURL=BindProperty.js.map