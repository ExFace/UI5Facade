/*!
 * OpenUI5
 * (c) Copyright 2025 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/core/Lib","sap/ui/rta/command/ManifestCommand"],function(e,t){"use strict";const a=t.extend("sap.ui.rta.command.manifest.AddLibrary",{metadata:{library:"sap.ui.rta",events:{}}});a.prototype.init=function(){this.setChangeType("appdescr_ui5_addLibraries")};a.prototype.execute=function(){const t=[];if(this.getParameters().libraries){const a=Object.keys(this.getParameters().libraries);a.forEach(function(a){t.push(e.load({name:a}))})}return Promise.all(t)};return a},true);
//# sourceMappingURL=AddLibrary.js.map