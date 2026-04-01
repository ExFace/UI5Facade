/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/core/Lib","sap/ui/rta/util/changeVisualization/ChangeVisualizationUtils","sap/ui/core/util/reflection/JsControlTreeModifier"],function(t,e,n){"use strict";const o={};o.getDescription=function(o,i,r){const T=t.getResourceBundleFor("sap.ui.rta");let s=T.getText("TXT_CHANGEVISUALIZATION_CHANGE_MOVE_WITHIN",[e.shortenString(i)]);let I=T.getText("TXT_CHANGEVISUALIZATION_CHANGE_MOVE_WITHIN",[i]);let N;const a=r.appComponent;const c=o.sourceContainer&&n.getControlIdBySelector(o.sourceContainer,a);const A=o.targetContainer&&n.getControlIdBySelector(o.targetContainer,a);if(c!==A){s=T.getText("TXT_CHANGEVISUALIZATION_CHANGE_MOVE",[e.shortenString(i)]);I=c&&T.getText("TXT_CHANGEVISUALIZATION_CHANGE_MOVE",[i])||"";N=c&&T.getText("BTN_CHANGEVISUALIZATION_SHOW_DEPENDENT_CONTAINER_MOVE")}return{descriptionText:s,descriptionTooltip:I,buttonText:N}};return o});
//# sourceMappingURL=MoveVisualization.js.map