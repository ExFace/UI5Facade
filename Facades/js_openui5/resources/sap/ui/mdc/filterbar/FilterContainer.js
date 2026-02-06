/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/base/i18n/Localization","sap/ui/mdc/filterbar/IFilterContainer","sap/ui/layout/VerticalLayout","sap/ui/layout/HorizontalLayout","sap/m/Text","./FilterBarBaseLayout"],(t,e,i,o,a,n)=>{"use strict";const r=e.extend("sap.ui.mdc.filterbar.FilterContainer",{metadata:{library:"sap.ui.mdc",aggregations:{_layout:{type:"sap.ui.core.Element",multiple:false,visibility:"hidden"}}}});r.prototype.init=function(){this.oLayout=new n;this.setAggregation("_layout",this.oLayout,true)};r.prototype.addButton=function(t){this.oLayout.addButton(t)};r.prototype.insertFilterField=function(t,e){this.oLayout.insertContent(t,e)};r.prototype.removeFilterField=function(t){this.oLayout.removeContent(t)};r.prototype.getFilterFields=function(){return this.oLayout.getContent()};return r});
//# sourceMappingURL=FilterContainer.js.map