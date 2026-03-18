/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["./utils/TableUtils"],function(e){"use strict";const t={apiVersion:2};t.render=function(t,l){const i=l.getType();const s=l.getVisible();let a=l.getTooltip_AsString();t.openStart("div",l);t.class("sapUiTableHeaderSelector");t.class("sapUiTableCell");t.class("sapUiTableHeaderCell");t.class("sapUiTableRowSelectionHeaderCell");t.attr("tabindex","-1");if(s){if(!l.getEnabled()){t.class("sapUiTableHeaderSelectorDisabled")}if(i==="CheckBox"){if(l.getCheckBoxSelected()){a??=e.getResourceText("TBL_DESELECT_ALL")}else{a??=e.getResourceText("TBL_SELECT_ALL")}}if(a){t.attr("title",a)}const s=l.getParent();s._getAccRenderExtension().writeAriaAttributesFor(t,s,"ColumnRowHeader",l)}t.openEnd();if(s){if(i==="Icon"){t.icon(l.getIcon(),["sapUiTableHeaderSelectorIcon"],{title:null})}else if(i==="CheckBox"){t.openStart("div");t.class("sapUiTableCheckBox");if(l.getCheckBoxSelected()){t.class("sapUiTableCheckBoxSelected")}t.openEnd();t.close("div")}}t.close("div")};return t});
//# sourceMappingURL=HeaderSelectorRenderer.js.map