/*!
 * OpenUI5
 * (c) Copyright 2025 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
(function(i){"use strict";var e;var r;function u(){if(i.sap.ui.require("sap/ui/core/Core")&&i.sap.ui.require("sap/ui/core/Lib")){e=i.sap.ui.require("sap/ui/core/Core");r=i.sap.ui.require("sap/ui/core/Lib");o();return}i.sap.ui.require(["sap/ui/core/Core","sap/ui/core/Lib"],function(i,u){e=i;r=u;e.boot();e.ready().then(o)})}function n(e){var r=e.extensions["sap.ui.integration"].customElements,u=Object.keys(r);i.sap.ui.require(u.map(function(i,e){return r[u[e]]}))}function o(){r.load({name:"sap.ui.integration"}).then(function(i){n(i)})}u()})(window);
//# sourceMappingURL=library-bootstrap.js.map