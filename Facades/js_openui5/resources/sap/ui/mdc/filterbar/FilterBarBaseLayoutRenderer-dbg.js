/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */

sap.ui.define([
    "sap/ui/core/Renderer",
    "./FilterBarLayoutRenderer"
], (
    Renderer,
    FilterBarLayoutRenderer
) => {
    "use strict";

    const FilterBarBaseLayoutRenderer = Renderer.extend(FilterBarLayoutRenderer);

    FilterBarBaseLayoutRenderer.apiVersion = 2;

    FilterBarBaseLayoutRenderer.renderToolbar = function (oRm, oFilterBarLayout) {
        oRm.renderControl(oFilterBarLayout._oToolbar);
    };

    FilterBarBaseLayoutRenderer.renderItems = function (oRm, oFilterBarLayout) {
        this.renderFilterItems(oRm, oFilterBarLayout);
    };

    FilterBarBaseLayoutRenderer.renderLabel = function (oRm, oFilterBarLayout, oFilterGroupItem) {
        oRm.renderControl(oFilterGroupItem._oLabel);
    };

    FilterBarBaseLayoutRenderer.renderControl = function(oRm, oFilterBarLayout, oFilterGroupItem) {
        oRm.renderControl(oFilterGroupItem._oFilterField);
    };

    return FilterBarBaseLayoutRenderer;
});