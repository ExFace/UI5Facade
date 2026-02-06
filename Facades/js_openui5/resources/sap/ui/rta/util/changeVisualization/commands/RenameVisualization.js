/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/core/Lib","sap/ui/rta/util/changeVisualization/ChangeVisualizationUtils"],function(i,t){"use strict";const e={};e.getDescription=function(e,n){const o=i.getResourceBundleFor("sap.ui.rta");const a=e.originalLabel?"TXT_CHANGEVISUALIZATION_CHANGE_RENAME_FROM_TO":"TXT_CHANGEVISUALIZATION_CHANGE_RENAME_TO";const r=o.getText(a,[t.shortenString(e.newLabel)||n,t.shortenString(e.originalLabel)]);const s=o.getText(a,[e.newLabel||n,e.originalLabel]);return{descriptionText:r,descriptionTooltip:s}};return e});
//# sourceMappingURL=RenameVisualization.js.map