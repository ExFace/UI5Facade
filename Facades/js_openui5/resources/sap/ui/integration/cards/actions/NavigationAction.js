/*!
 * OpenUI5
 * (c) Copyright 2025 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["./BaseAction","sap/ui/util/openWindow"],function(t,e){"use strict";var i=t.extend("sap.ui.integration.cards.actions.NavigationAction",{metadata:{library:"sap.ui.integration"}});i.prototype.execute=function(){var t=this.getResolvedConfig();if(t.service){return}var e=this.getParameters(),n,r,a,o;if(e){a=e.url;o=e.target}n=t.url||a;r=t.target||o||i.DEFAULT_TARGET;if(n){this._openUrl(n,r)}};i.prototype._openUrl=function(t,i){e(t,i)};i.DEFAULT_TARGET="_blank";return i});
//# sourceMappingURL=NavigationAction.js.map