/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/core/Lib","sap/ui/rta/util/changeVisualization/ChangeVisualizationUtils"],function(t,i){"use strict";const e={};e.getDescription=function(e,n){const o=t.getResourceBundleFor("sap.ui.rta");const s=e.originalLabel||n;const r=i.shortenString(s);const a="TXT_CHANGEVISUALIZATION_CHANGE_CREATECONTAINER";return{descriptionText:o.getText(a,[r]),descriptionTooltip:o.getText(a,[s])}};return e});
//# sourceMappingURL=CreateContainerVisualization.js.map