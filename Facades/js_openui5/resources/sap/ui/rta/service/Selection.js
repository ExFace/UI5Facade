/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(function(){"use strict";return function(e,n){var t=e._oDesignTime.getSelectionManager();function r(e){return e.map(function(e){return e.getElement().getId()})}t.attachEvent("change",function(e){n("change",r(e.getParameter("selection")))});return{events:["change"],exports:{get(){return r(t.get())},set:t.set.bind(t),add:t.add.bind(t),remove:t.remove.bind(t),reset:t.reset.bind(t),addHover:t.addHover.bind(t),removeHover:t.removeHover.bind(t)}}}});
//# sourceMappingURL=Selection.js.map