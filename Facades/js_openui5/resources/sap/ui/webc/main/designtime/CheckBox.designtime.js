/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define([],function(){"use strict";return{name:{singular:"CHECKBOX_NAME",plural:"CHECKBOX_NAME_PLURAL"},actions:{remove:{changeType:"hideControl"},rename:{changeType:"rename",domRef:function(e){return e.getDomRef().getDomRef().querySelector(".ui5-checkbox-label")},isEnabled:function(e){return e.getText().length>0},validators:["noEmptyText"]},reveal:{changeType:"unhideControl"}}}});
//# sourceMappingURL=CheckBox.designtime.js.map