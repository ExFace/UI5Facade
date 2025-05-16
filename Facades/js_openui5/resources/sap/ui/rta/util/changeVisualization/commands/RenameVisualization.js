/*!
 * OpenUI5
 * (c) Copyright 2025 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/core/Lib","sap/ui/rta/util/changeVisualization/ChangeVisualizationUtils"],function(i,e){"use strict";var t={};t.getDescription=function(t,a){var r=i.getResourceBundleFor("sap.ui.rta");var n=t.originalLabel?"TXT_CHANGEVISUALIZATION_CHANGE_RENAME_FROM_TO":"TXT_CHANGEVISUALIZATION_CHANGE_RENAME_TO";var o=r.getText(n,[e.shortenString(t.newLabel)||a,e.shortenString(t.originalLabel)]);var s=r.getText(n,[t.newLabel||a,t.originalLabel]);return{descriptionText:o,descriptionTooltip:s}};return t});
//# sourceMappingURL=RenameVisualization.js.map