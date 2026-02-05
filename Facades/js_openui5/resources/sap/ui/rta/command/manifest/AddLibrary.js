/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/core/Lib","sap/ui/rta/command/ManifestCommand"],function(e,t){"use strict";const a=t.extend("sap.ui.rta.command.manifest.AddLibrary",{metadata:{library:"sap.ui.rta",events:{}}});a.prototype.init=function(){this.setChangeType("appdescr_ui5_addLibraries")};a.prototype.execute=function(){if(this.getParameters().libraries){const t=Object.keys(this.getParameters().libraries);return Promise.all(t.map(t=>e.load({name:t})))}return Promise.resolve()};return a},true);
//# sourceMappingURL=AddLibrary.js.map