/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/core/EventBus"],function(t){"use strict";const e=t.extend("sap.ui.rta.util.ServiceEventBus");e.prototype._callListener=function(t,e,n,i,u){t.call(e,u)};e.prototype.getChannel=function(t){return this._mChannels[t]};return e});
//# sourceMappingURL=ServiceEventBus.js.map