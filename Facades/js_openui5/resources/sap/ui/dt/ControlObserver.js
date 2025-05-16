/*!
 * OpenUI5
 * (c) Copyright 2025 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/dt/ManagedObjectObserver"],function(e){"use strict";var t=e.extend("sap.ui.dt.ControlObserver",{metadata:{library:"sap.ui.dt",properties:{},associations:{target:{type:"sap.ui.core.Control"}}}});t.prototype.init=function(...t){e.prototype.init.apply(this,t);this._oControlDelegate={onAfterRendering:this._onAfterRendering}};t.prototype.observe=function(...t){const[o]=t;e.prototype.observe.apply(this,t);o.addEventDelegate(this._oControlDelegate,this)};t.prototype.unobserve=function(...t){var o=this.getTargetInstance();if(o){o.removeDelegate(this._oControlDelegate,this)}e.prototype.unobserve.apply(this,t)};t.prototype._onAfterRendering=function(){this.fireModified({type:"afterRendering"})};return t});
//# sourceMappingURL=ControlObserver.js.map