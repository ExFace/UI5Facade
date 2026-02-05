//@ui5-bundle sap/ui/webc/main/designtime/library-preload.designtime.js
/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.predefine("sap/ui/webc/main/designtime/Avatar.designtime", [],function(){"use strict";return{name:{singular:"AVATAR_NAME",plural:"AVATAR_NAME_PLURAL"},actions:{remove:{changeType:"hideControl"},reveal:{changeType:"unhideControl"}}}});
/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.predefine("sap/ui/webc/main/designtime/Badge.designtime", [],function(){"use strict";return{name:{singular:"BADGE_NAME",plural:"BADGE_NAME_PLURAL"},actions:{remove:{changeType:"hideControl"},rename:{changeType:"rename",domRef:function(e){return e.getDomRef()}},reveal:{changeType:"unhideControl"}}}});
/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.predefine("sap/ui/webc/main/designtime/BreadcrumbsItem.designtime", [],function(){"use strict";return{domRef:function(e){return e.getDomRef().getDomRef()},actions:{remove:{changeType:"hideControl"},rename:{changeType:"rename",domRef:function(e){return e.getDomRef().getDomRef()},isEnabled:function(e){return e.getText().length>0},validators:["noEmptyText"]},reveal:{changeType:"unhideControl"}}}});
/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.predefine("sap/ui/webc/main/designtime/BusyIndicator.designtime", [],function(){"use strict";return{name:{singular:"BUSYINDICATOR_NAME",plural:"BUSYINDICATOR_NAME_PLURAL"},actions:{remove:{changeType:"hideControl"},reveal:{changeType:"unhideControl"}}}});
/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.predefine("sap/ui/webc/main/designtime/Button.designtime", [],function(){"use strict";return{actions:{remove:{changeType:"hideControl"},rename:function(){return{changeType:"rename",domRef:function(e){return e.getDomRef().getDomRef().querySelector("span>bdi")},isEnabled:function(e){return e.getText().length>0},validators:["noEmptyText"]}},reveal:{changeType:"unhideControl"}}}});
/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.predefine("sap/ui/webc/main/designtime/Card.designtime", [],function(){"use strict";return{name:{singular:"CARD_NAME",plural:"CARD_NAME_PLURAL"},actions:{remove:{changeType:"hideControl"},reveal:{changeType:"unhideControl"}}}});
/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.predefine("sap/ui/webc/main/designtime/Carousel.designtime", [],function(){"use strict";return{name:{singular:"CAROUSEL_NAME",plural:"CAROUSEL_NAME_PLURAL"},actions:{remove:{changeType:"hideControl"},reveal:{changeType:"unhideControl"}}}});
/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.predefine("sap/ui/webc/main/designtime/CheckBox.designtime", [],function(){"use strict";return{name:{singular:"CHECKBOX_NAME",plural:"CHECKBOX_NAME_PLURAL"},actions:{remove:{changeType:"hideControl"},rename:{changeType:"rename",domRef:function(e){return e.getDomRef().getDomRef().querySelector(".ui5-checkbox-label")},isEnabled:function(e){return e.getText().length>0},validators:["noEmptyText"]},reveal:{changeType:"unhideControl"}}}});
/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.predefine("sap/ui/webc/main/designtime/CustomListItem.designtime", [],function(){"use strict";return{actions:{remove:{changeType:"hideControl"},reveal:{changeType:"unhideControl"}},aggregations:{content:{actions:{move:{changeType:"moveControls"}}}}}});
/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.predefine("sap/ui/webc/main/designtime/DatePicker.designtime", [],function(){"use strict";return{name:{singular:"DATEPICKER_NAME",plural:"DATEPICKER_NAME_PLURAL"},actions:{remove:{changeType:"hideControl"},reveal:{changeType:"unhideControl"}}}});
/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.predefine("sap/ui/webc/main/designtime/DateTimePicker.designtime", [],function(){"use strict";return{name:{singular:"DATETIMEPICKER_NAME",plural:"DATETIMEPICKER_NAME_PLURAL"},actions:{remove:{changeType:"hideControl"},reveal:{changeType:"unhideControl"}}}});
/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.predefine("sap/ui/webc/main/designtime/Dialog.designtime", [],function(){"use strict";return{name:{singular:"DIALOG_NAME",plural:"DIALOG_NAME_PLURAL"},getLabel:function(e){return e.getDomRef().getAttribute("header-text")},actions:{remove:{changeType:"hideControl"},reveal:{changeType:"unhideControl"},rename:{changeType:"rename",domRef:function(e){return e.getDomRef().shadowRoot.querySelector(".ui5-popup-header-text")}}}}});
/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.predefine("sap/ui/webc/main/designtime/Input.designtime", [],function(){"use strict";return{name:{singular:"INPUT_NAME",plural:"INPUT_NAME_PLURAL"},actions:{remove:{changeType:"hideControl"},reveal:{changeType:"unhideControl"}}}});
/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.predefine("sap/ui/webc/main/designtime/Label.designtime", [],function(){"use strict";return{name:{singular:"LABEL_NAME",plural:"LABEL_NAME_PLURAL"},actions:{remove:{changeType:"hideControl"},reveal:{changeType:"unhideControl"},rename:{changeType:"rename",domRef:function(e){return e.getDomRef().shadowRoot.querySelector(".ui5-title-root")}}}}});
/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.predefine("sap/ui/webc/main/designtime/Link.designtime", ["sap/ui/core/Element","sap/ui/model/json/JSONModel","sap/ui/core/Core","sap/ui/core/Fragment"],function(e,t,n,a){"use strict";var r=function(r,o){var i=n.getLibraryResourceBundle("sap.ui.webc.main.designtime");return new Promise(function(n){var c={selectedKey:r.getTarget(),titleText:i.getText("LINK_DIALOG_TITLE_CHANGE_TARGET"),cancelBtn:i.getText("LINK_DIALOG_CANCEL_BTN"),okBtn:i.getText("LINK_DIALOG_OK_BTN")};var s=new t;s.setData(c);a.load({name:"sap.m.designtime.LinkTargetSelectDialog",controller:this}).then(function(t){t.setModel(s);t.getBeginButton().attachPress(function(a){var r=e.getElementById("targetCombo").getValue();n(r);t.close()});t.getEndButton().attachPress(function(e){t.close()});t.attachEventOnce("afterClose",function(e){t.destroy()});t.addStyleClass(o.styleClass);t.open()})}).then(function(e){return[{selectorControl:r,changeSpecificData:{changeType:"changeLinkTarget",content:e}}]})};return{name:{singular:"LINK_NAME",plural:"LINK_NAME_PLURAL"},actions:{remove:{changeType:"hideControl"},reveal:{changeType:"unhideControl"},rename:function(){return{changeType:"rename",domRef:function(e){return e.getDomRef()}}},settings:function(){return{changeLinkTarget:{name:"LINK_CHANGE_TARGET",isEnabled:function(e){return!!e.getHref()},handler:r}}}}}});
/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.predefine("sap/ui/webc/main/designtime/List.designtime", [],function(){"use strict";function e(e){var n=e;while(n){if(n.isA("sap.ui.webc.main.List")){var t=n.getBinding("items");if(t){return true}return false}n=n.getParent()}return false}return{name:{singular:"LIST_NAME",plural:"LIST_NAME_PLURAL"},getLabel:function(e){return e.getHeaderText()},aggregations:{items:{propagateMetadata:function(n){if(e(n)){return{actions:{remove:null,rename:null}}}},actions:{move:"moveControls"}}},actions:{remove:{changeType:"hideControl"},reveal:{changeType:"unhideControl"},rename:function(){return{changeType:"rename",domRef:function(e){return e.getDomRef().getDomRef().querySelector(".ui5-list-header")},isEnabled:function(e){return e.getHeaderText().length>0},validators:["noEmptyText"]}}}}});
/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.predefine("sap/ui/webc/main/designtime/MultiInput.designtime", [],function(){"use strict";return{name:{singular:"MULTIINPUT_NAME",plural:"MULTIINPUT_NAME_PLURAL"},actions:{remove:{changeType:"hideControl"},reveal:{changeType:"unhideControl"}}}});
/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.predefine("sap/ui/webc/main/designtime/Panel.designtime", [],function(){"use strict";return{name:{singular:"PANEL_NAME",plural:"PANEL_NAME_PLURAL"},getLabel:function(e){return e.getDomRef().shadowRoot.querySelector(".ui5-panel-header-title").textContent},actions:{remove:{changeType:"hideControl"},reveal:{changeType:"unhideControl",getLabel:function(e){return e.getHeaderText()||e.getId()}},rename:{changeType:"rename",domRef:function(e){return e.getDomRef().shadowRoot.querySelector(".ui5-panel-header-title")}}},aggregations:{header:{domRef:function(e){return e.getDomRef().shadowRoot.querySelector(".ui5-panel-header")}},content:{domRef:function(e){return e.getDomRef().shadowRoot.querySelector(".ui5-panel-content")},actions:{move:"moveControls"}}}}});
/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.predefine("sap/ui/webc/main/designtime/Popover.designtime", [],function(){"use strict";return{name:{singular:"POPOVER_NAME",plural:"POPOVER_NAME_PLURAL"},getLabel:function(e){return e.getDomRef().getAttribute("header-text")},actions:{remove:{changeType:"hideControl"},rename:{changeType:"rename",domRef:function(e){return e.getDomRef().shadowRoot.querySelector(".ui5-popup-header-text")}},reveal:{changeType:"unhideControl"}}}});
/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.predefine("sap/ui/webc/main/designtime/RadioButton.designtime", [],function(){"use strict";return{name:{singular:"RADIO_BUTTON_NAME",plural:"RADIO_BUTTON_NAME_PLURAL"},actions:{remove:{changeType:"hideControl"},rename:{changeType:"rename",domRef:function(e){return e.getDomRef().shadowRoot.querySelector(".ui5-radio-label")}},reveal:{changeType:"unhideControl"}}}});
/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.predefine("sap/ui/webc/main/designtime/RangeSlider.designtime", [],function(){"use strict";return{name:{singular:"RANGESLIDER_NAME",plural:"RANGESLIDER_NAME_PLURAL"},actions:{remove:{changeType:"hideControl"},reveal:{changeType:"unhideControl"}}}});
/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.predefine("sap/ui/webc/main/designtime/RatingIndicator.designtime", [],function(){"use strict";return{name:{singular:"RATINGINDICATOR_NAME",plural:"RATINGINDICATOR_NAME_PLURAL"},actions:{remove:{changeType:"hideControl"},reveal:{changeType:"unhideControl"}}}});
/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.predefine("sap/ui/webc/main/designtime/ResponsivePopover.designtime", [],function(){"use strict";return{name:{singular:"RESPONSIVE_POPOVER_NAME",plural:"RESPONSIVE_POPOVER_NAME_PLURAL"},getLabel:function(e){return e.getDomRef().getAttribute("header-text")},actions:{remove:{changeType:"hideControl"},rename:{changeType:"rename",domRef:function(e){return e.getDomRef().shadowRoot.querySelector(".ui5-popup-header-text")}},reveal:{changeType:"unhideControl"}}}});
/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.predefine("sap/ui/webc/main/designtime/Slider.designtime", [],function(){"use strict";return{name:{singular:"SLIDER_NAME",plural:"SLIDER_NAME_PLURAL"},actions:{remove:{changeType:"hideControl"},reveal:{changeType:"unhideControl"}}}},true);
/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.predefine("sap/ui/webc/main/designtime/StandardListItem.designtime", [],function(){"use strict";return{actions:{remove:{changeType:"hideControl"},rename:{changeType:"rename",domRef:function(e){return e.getDomRef().getDomRef().querySelector(".ui5-li-title")}},reveal:{changeType:"unhideControl"}}}});
/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.predefine("sap/ui/webc/main/designtime/Tab.designtime", [],function(){"use strict";return{name:{singular:"TAB_NAME",plural:"TAB_NAME_PLURAL"},domRef:function(e){return e.getParent().getItems().find(function(t){return t.sId===e.sId}).getDomRef()._getRealDomRef()},actions:{rename:{changeType:"rename",domRef:function(e){return e.getDomRef()._getRealDomRef().querySelector(".ui5-tab-strip-itemText")}}}}});
/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.predefine("sap/ui/webc/main/designtime/TabContainer.designtime", [],function(){"use strict";return{name:{singular:"TABCONTAINER_NAME",plural:"TABCONTAINER_NAME_PLURAL"},actions:{remove:{changeType:"hideControl"},reveal:{changeType:"unhideControl"}}}});
/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.predefine("sap/ui/webc/main/designtime/Table.designtime", [],function(){"use strict";return{name:{singular:"TABLE_NAME",plural:"TABLE_NAME_PLURAL"},aggregations:{columns:{domRef:function(e){return e.getDomRef().shadowRoot.querySelector(".ui5-table-header-row")}},rows:{domRef:function(e){return e.getDomRef().shadowRoot.querySelector("tbody")}}},actions:{remove:{changeType:"hideControl"},reveal:{changeType:"unhideControl"}}}});
/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.predefine("sap/ui/webc/main/designtime/TableColumn.designtime", ["sap/ui/dt/ElementUtil"],function(e){"use strict";return{domRef:function(e){return e.getDomRef().shadowRoot.querySelector("th")},aggregations:{content:{domRef:function(e){return e.getDomRef().shadowRoot.querySelector("th")},actions:{remove:{removeLastElement:true}}}}}});
/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.predefine("sap/ui/webc/main/designtime/Title.designtime", [],function(){"use strict";return{name:{singular:"TITLE_NAME",plural:"TITLE_NAME_PLURAL"},actions:{remove:{changeType:"hideControl"},rename:{changeType:"rename",domRef:function(e){return e.getDomRef().shadowRoot.querySelector(".ui5-title-root")}},reveal:{changeType:"unhideControl"}}}});
/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.predefine("sap/ui/webc/main/designtime/library.designtime", [],function(){"use strict";return{}});
//# sourceMappingURL=library-preload.designtime.js.map
