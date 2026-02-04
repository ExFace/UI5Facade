/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/core/ComponentRegistry","sap/ui/core/UIAreaRegistry"],function(t,e){"use strict";var n=null;function r(r){n=r;return{getMetadata:function(){return n.getMetadata()},getUIAreas:function(){return e.all()},getComponents:function(){return t.all()},getModels:function(){return n.oModels}}}return r},true);
//# sourceMappingURL=CoreFacade.js.map