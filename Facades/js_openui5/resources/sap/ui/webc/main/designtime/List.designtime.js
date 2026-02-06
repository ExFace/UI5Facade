/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define([],function(){"use strict";function e(e){var n=e;while(n){if(n.isA("sap.ui.webc.main.List")){var t=n.getBinding("items");if(t){return true}return false}n=n.getParent()}return false}return{name:{singular:"LIST_NAME",plural:"LIST_NAME_PLURAL"},getLabel:function(e){return e.getHeaderText()},aggregations:{items:{propagateMetadata:function(n){if(e(n)){return{actions:{remove:null,rename:null}}}},actions:{move:"moveControls"}}},actions:{remove:{changeType:"hideControl"},reveal:{changeType:"unhideControl"},rename:function(){return{changeType:"rename",domRef:function(e){return e.getDomRef().getDomRef().querySelector(".ui5-list-header")},isEnabled:function(e){return e.getHeaderText().length>0},validators:["noEmptyText"]}}}}});
//# sourceMappingURL=List.designtime.js.map