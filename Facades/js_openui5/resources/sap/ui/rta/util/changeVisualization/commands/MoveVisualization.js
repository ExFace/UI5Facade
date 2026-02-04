/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/core/Lib","sap/ui/rta/util/changeVisualization/ChangeVisualizationUtils","sap/ui/core/util/reflection/JsControlTreeModifier"],function(t,e,r){"use strict";var i={};i.getDescription=function(i,n,o){var a=t.getResourceBundleFor("sap.ui.rta");var T=a.getText("TXT_CHANGEVISUALIZATION_CHANGE_MOVE_WITHIN",[e.shortenString(n)]);var I=a.getText("TXT_CHANGEVISUALIZATION_CHANGE_MOVE_WITHIN",[n]);var N;var A=o.appComponent;var s=i.sourceContainer&&r.getControlIdBySelector(i.sourceContainer,A);var u=i.targetContainer&&r.getControlIdBySelector(i.targetContainer,A);if(s!==u){T=a.getText("TXT_CHANGEVISUALIZATION_CHANGE_MOVE",[e.shortenString(n)]);I=s&&a.getText("TXT_CHANGEVISUALIZATION_CHANGE_MOVE",[n])||"";N=s&&a.getText("BTN_CHANGEVISUALIZATION_SHOW_DEPENDENT_CONTAINER_MOVE")}return{descriptionText:T,descriptionTooltip:I,buttonText:N}};return i});
//# sourceMappingURL=MoveVisualization.js.map