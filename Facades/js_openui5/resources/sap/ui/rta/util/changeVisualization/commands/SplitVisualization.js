/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/core/Lib","sap/ui/rta/util/changeVisualization/ChangeVisualizationUtils"],function(t,e){"use strict";const i={};i.getDescription=function(i,n){const o=t.getResourceBundleFor("sap.ui.rta");const s=o.getText("TXT_CHANGEVISUALIZATION_CHANGE_SPLIT",[e.shortenString(n)]);const T=o.getText("TXT_CHANGEVISUALIZATION_CHANGE_SPLIT",[n]);const r=o.getText("BTN_CHANGEVISUALIZATION_SHOW_DEPENDENT_CONTAINER_SPLIT");return{descriptionText:s,descriptionTooltip:T,buttonText:r}};return i});
//# sourceMappingURL=SplitVisualization.js.map