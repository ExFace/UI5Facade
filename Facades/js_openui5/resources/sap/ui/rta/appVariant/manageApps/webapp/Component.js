/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/core/UIComponent"],function(e){"use strict";var t;var r;var i;return e.extend("sap.ui.rta.appVariant.manageApps.webapp.Component",{metadata:{manifest:"json",library:"sap.ui.rta",version:"0.9",properties:{idRunningApp:"string",isOverviewForKeyUser:{type:"boolean"},layer:"string"}},constructor:function(...n){t=n[1].idRunningApp;r=n[1].isOverviewForKeyUser;i=n[1].layer;e.prototype.constructor.apply(this,n)},init(...n){this.setIdRunningApp(t);this.setIsOverviewForKeyUser(r);this.setLayer(i);e.prototype.init.apply(this,n)}})});
//# sourceMappingURL=Component.js.map