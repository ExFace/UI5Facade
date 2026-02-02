/*!
 * OpenUI5
 * (c) Copyright 2025 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/core/Lib","sap/ui/rta/util/changeVisualization/ChangeVisualizationUtils"],function(i,t){"use strict";var e={};e.getDescription=function(e,r){var a=i.getResourceBundleFor("sap.ui.rta");var n=e.originalLabel||r;var o=t.shortenString(n);var s="TXT_CHANGEVISUALIZATION_CHANGE_CREATECONTAINER";return{descriptionText:a.getText(s,[o]),descriptionTooltip:a.getText(s,[n])}};return e});
//# sourceMappingURL=CreateContainerVisualization.js.map