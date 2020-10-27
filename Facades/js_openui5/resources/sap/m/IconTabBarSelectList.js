/*!
 * OpenUI5
 * (c) Copyright 2009-2020 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(['./library','sap/ui/core/Control',"sap/ui/core/Core",'sap/ui/core/delegate/ItemNavigation','./IconTabBarDragAndDropUtil','sap/ui/core/dnd/DropPosition','./IconTabBarSelectListRenderer',"sap/ui/thirdparty/jquery"],function(l,C,a,I,b,D,c,q){"use strict";var d=C.extend("sap.m.IconTabBarSelectList",{metadata:{library:"sap.m",aggregations:{items:{type:"sap.m.IconTab",multiple:true,singularName:"item",dnd:true}},events:{selectionChange:{parameters:{selectedItem:{type:"sap.m.IconTabFilter"}}}}}});d.prototype.init=function(){this._oItemNavigation=new I();this._oItemNavigation.setCycling(false);this.addEventDelegate(this._oItemNavigation);this._oItemNavigation.setPageSize(10);this._oIconTabHeader=null;this._oTabFilter=null;};d.prototype.exit=function(){this._oItemNavigation.destroy();this._oItemNavigation=null;this._oIconTabHeader=null;this._oTabFilter=null;};d.prototype.onBeforeRendering=function(){if(!this._oIconTabHeader){return;}this.destroyDragDropConfig();this._setsDragAndConfiguration();};d.prototype.onAfterRendering=function(){this._initItemNavigation();this.getItems().forEach(function(i){if(i._onAfterParentRendering){i._onAfterParentRendering();}});};d.prototype._setsDragAndConfiguration=function(){if(this._oIconTabHeader.getEnableTabReordering()&&!this.getDragDropConfig().length){b.setDragDropAggregations(this,"Vertical",this._oIconTabHeader._getDropPosition());}};d.prototype._initItemNavigation=function(){var e=this.getItems(),f=[],p=this._oIconTabHeader.oSelectedItem,s=-1,o,i;for(i=0;i<e.length;i++){o=e[i];if(o.isA("sap.m.IconTabFilter")){var g=o._getAllSubFiltersDomRefs();f=f.concat(o.getDomRef(),g);}if(p&&this.getSelectedItem()&&this.getSelectedItem()._getRealTab()===p){s=i;}}if(p&&f.indexOf(p.getDomRef())!==-1){s=f.indexOf(p.getDomRef());}this._oItemNavigation.setRootDomRef(this.getDomRef()).setItemDomRefs(f).setSelectedIndex(s);};d.prototype.getVisibleItems=function(){return this.getItems().filter(function(i){return i.getVisible();});};d.prototype.getVisibleTabFilters=function(){return this.getVisibleItems().filter(function(i){return i.isA("sap.m.IconTabFilter");});};d.prototype.setSelectedItem=function(i){this._selectedItem=i;};d.prototype.getSelectedItem=function(){return this._selectedItem;};d.prototype.ontap=function(e){var t=e.srcControl;if(!t){return;}if(!t.isA("sap.m.IconTabFilter")){return;}if(this._oIconTabHeader._isUnselectable(t)){return;}e.preventDefault();if(t!=this.getSelectedItem()){this.fireSelectionChange({selectedItem:t});}};d.prototype.onsapenter=d.prototype.ontap;d.prototype.onsapspace=d.prototype.ontap;d.prototype.checkIconOnly=function(){this._bIconOnly=this.getVisibleTabFilters().every(function(i){return!i.getText()&&!i.getCount();});return this._bIconOnly;};d.prototype._handleDragAndDrop=function(e){var s=e.getParameter("dropPosition"),o=e.getParameter("draggedControl"),f=e.getParameter("droppedControl"),g=f._getRealTab().getParent(),h=this._oIconTabHeader.getMaxNestingLevel();if(this._oTabFilter._bIsOverflow){g=this._oIconTabHeader;}if(s===D.On){g=f._getRealTab();}b.handleDrop(g,s,o._getRealTab(),f._getRealTab(),true,h);this._oIconTabHeader._setItemsForStrip();this._oIconTabHeader._initItemNavigation();this._oTabFilter._setSelectListItems();this._initItemNavigation();f._getRealTab().getParent().$().trigger("focus");};d.prototype.ondragrearranging=function(e){if(!this._oIconTabHeader.getEnableTabReordering()){return;}var t=e.srcControl,k=e.keyCode,i=this.indexOfItem(t),o=this;b.moveItem.call(o,t,k,o.getItems().length-1);this._initItemNavigation();t.$().trigger("focus");if(i===this.indexOfItem(t)){return;}o=t._getRealTab().getParent();if(this._oTabFilter._bIsOverflow&&t._getRealTab()._getNestedLevel()===1){this._oIconTabHeader._moveTab(t._getRealTab(),k,this._oIconTabHeader.getItems().length-1);}else{b.moveItem.call(o,t._getRealTab(),k,o.getItems().length-1);}};d.prototype.onsaphomemodifiers=d.prototype.ondragrearranging;d.prototype.onsapendmodifiers=d.prototype.ondragrearranging;d.prototype.onsapincreasemodifiers=d.prototype.ondragrearranging;d.prototype.onsapdecreasemodifiers=d.prototype.ondragrearranging;return d;});
