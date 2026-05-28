/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define([],function(){"use strict";return{actions:{remove:{changeType:"hideControl"},rename:function(){return{changeType:"rename",domRef:function(e){return e.getDomRef().getDomRef().querySelector("span>bdi")},isEnabled:function(e){return e.getText().length>0},validators:["noEmptyText"]}},reveal:{changeType:"unhideControl"}}}});
//# sourceMappingURL=Button.designtime.js.map