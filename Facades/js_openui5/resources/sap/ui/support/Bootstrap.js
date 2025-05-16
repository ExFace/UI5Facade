/*!
 * OpenUI5
 * (c) Copyright 2025 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/base/Log"],function(t){"use strict";t.setLogEntriesLimit(Infinity);var e={initSupportRules:function(e,i){sap.ui.require(["sap/ui/support/supportRules/Main"],function(n){if(e[0].toLowerCase()==="true"||e[0].toLowerCase()==="silent"){var o=i&&i.onReady&&typeof i.onReady==="function";if(!n._pluginStarted){if(o){n.attachEvent("ready",i.onReady)}n.startPlugin(e)}else{if(o){i.onReady()}}t.logSupportInfo(true)}})}};return e});
//# sourceMappingURL=Bootstrap.js.map