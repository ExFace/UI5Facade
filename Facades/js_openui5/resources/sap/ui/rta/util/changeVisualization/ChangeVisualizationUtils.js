/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define([],function(){"use strict";const n={};n.shortenString=function(n){if(!n){return null}const t=n.length;if(t>60){const s=n.substring(0,27);const r=n.substring(t-27);n=`${s}(...)${r}`}return n};return n});
//# sourceMappingURL=ChangeVisualizationUtils.js.map