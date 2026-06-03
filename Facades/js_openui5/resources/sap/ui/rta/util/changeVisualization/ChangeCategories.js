/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/fl/changeHandler/common/ChangeCategories"],function(e){"use strict";const n={};n.ALL="all";const o={};o[e.ADD]=["createContainer","addDelegateProperty","reveal","addIFrame"];o[e.MOVE]=["move"];o[e.RENAME]=["rename"];o[e.COMBINESPLIT]=["combine","split"];o[e.REMOVE]=["remove"];o[e.OTHER]=[];const a={};a[n.ALL]="sap-icon://show";a[e.ADD]="sap-icon://add";a[e.MOVE]="sap-icon://move";a[e.RENAME]="sap-icon://edit";a[e.COMBINESPLIT]="sap-icon://combine";a[e.REMOVE]="sap-icon://less";a[e.OTHER]="sap-icon://key-user-settings";n.getCategories=function(){return o};n.getIconForCategory=function(e){return a[e]};return n});
//# sourceMappingURL=ChangeCategories.js.map