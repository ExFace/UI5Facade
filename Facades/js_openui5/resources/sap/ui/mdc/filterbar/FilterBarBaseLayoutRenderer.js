/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/core/Renderer","./FilterBarLayoutRenderer"],(e,r)=>{"use strict";const n=e.extend(r);n.apiVersion=2;n.renderToolbar=function(e,r){e.renderControl(r._oToolbar)};n.renderItems=function(e,r){this.renderFilterItems(e,r)};n.renderLabel=function(e,r,n){e.renderControl(n._oLabel)};n.renderControl=function(e,r,n){e.renderControl(n._oFilterField)};return n});
//# sourceMappingURL=FilterBarBaseLayoutRenderer.js.map