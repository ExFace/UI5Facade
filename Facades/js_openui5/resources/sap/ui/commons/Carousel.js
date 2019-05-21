/*!
 * UI development toolkit for HTML5 (OpenUI5)
 * (c) Copyright 2009-2018 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(['sap/ui/thirdparty/jquery','sap/base/Log','sap/base/strings/capitalize','sap/ui/dom/containsOrEquals','./library','sap/ui/core/Control','sap/ui/core/ResizeHandler','sap/ui/core/delegate/ItemNavigation','./CarouselRenderer','sap/ui/Device','sap/ui/events/KeyCodes'],function(q,L,c,a,b,C,R,I,d,D,K){"use strict";var O=b.enums.Orientation;var f=C.extend("sap.ui.commons.Carousel",{metadata:{library:"sap.ui.commons",properties:{orientation:{type:"sap.ui.commons.enums.Orientation",group:"Misc",defaultValue:O.horizontal},width:{type:"sap.ui.core.CSSSize",group:"Misc",defaultValue:null},height:{type:"sap.ui.core.CSSSize",group:"Misc",defaultValue:null},defaultItemHeight:{type:"int",group:"Misc",defaultValue:150},defaultItemWidth:{type:"int",group:"Misc",defaultValue:150},animationDuration:{type:"int",group:"Misc",defaultValue:500},visibleItems:{type:"int",group:"Misc",defaultValue:null},handleSize:{type:"int",group:"Misc",defaultValue:22},firstVisibleIndex:{type:"int",group:"Appearance",defaultValue:0}},defaultAggregation:"content",aggregations:{content:{type:"sap.ui.core.Control",multiple:true,singularName:"content",bindable:"bindable"}}}});f.prototype.init=function(){this._visibleItems=0;this.data("sap-ui-fastnavgroup","true",true);};f.prototype.exit=function(){if(this.sResizeListenerId){R.deregister(this.sResizeListenerId);this.sResizeListenerId=null;}this._destroyItemNavigation();};f.prototype.onclick=function(e){switch(e.target){case this.getDomRef('prevbutton'):this.showPrevious();break;case this.getDomRef('nextbutton'):this.showNext();break;default:return;}};f.prototype.onBeforeRendering=function(){if(this.sResizeListenerId){R.deregister(this.sResizeListenerId);this.sResizeListenerId=null;}};f.prototype.onAfterRendering=function(){if(this.getOrientation()=="vertical"){this._sAnimationAttribute='margin-top';}else{if(sap.ui.getCore().getConfiguration().getRTL()){this._sAnimationAttribute='margin-right';}else{this._sAnimationAttribute='margin-left';}}this.showElementWithId(this._getItemIdByIndex(this.getFirstVisibleIndex()));this.calculateAndSetSize();this.oDomRef=this.getDomRef();this.sResizeListenerId=R.register(this.oDomRef,q.proxy(this.onresize,this));this._initItemNavigation();};f.prototype._initItemNavigation=function(){var $=this.$("scrolllist");if(!this._oItemNavigation){this._oItemNavigation=new I();this._oItemNavigation.setCycling(true);this.addDelegate(this._oItemNavigation);this._oItemNavigation.attachEvent(I.Events.AfterFocus,function(e){var g=this.$("contentarea"),s=this.$("scrolllist");var o=e.getParameter("event");if(o&&o.type=="mousedown"){var h=false;for(var i=0;i<s.children().length;i++){var j=s.children()[i];if(o.target.id==j.id){h=true;break;}}if(!h){o.target.focus();}}if(sap.ui.getCore().getConfiguration().getRTL()){g.scrollLeft(s.width()-g.width());}else{g.scrollLeft(0);}},this);}this._oItemNavigation.setRootDomRef($[0]);this._oItemNavigation.setItemDomRefs($.children());};f.prototype._destroyItemNavigation=function(){if(this._oItemNavigation){this._oItemNavigation.destroy();this._oItemNavigation=undefined;}};f.prototype.onThemeChanged=function(e){this.calculateAndSetSize();};f.prototype.onfocusin=function(e){var $=q(e.target);if(!this._bIgnoreFocusIn&&($.hasClass("sapUiCrslBefore")||$.hasClass("sapUiCrslAfter"))){this._leaveActionMode();q(this._oItemNavigation.getFocusedDomRef()||this._oItemNavigation.getRootDomRef()).focus();}};f.prototype.onsaptabnext=function(e){var $=this.$();if(this._bActionMode){if($.find(".sapUiCrslScl").lastFocusableDomRef()===e.target){$.find(".sapUiCrslScl").firstFocusableDomRef().focus();e.preventDefault();e.stopPropagation();}}else{if(this._oItemNavigation.getFocusedDomRef()===e.target){this._bIgnoreFocusIn=true;$.find(".sapUiCrslAfter").focus();this._bIgnoreFocusIn=false;}}};f.prototype.onsaptabprevious=function(e){var $=this.$();if(this._bActionMode){if($.find(".sapUiCrslScl").firstFocusableDomRef()===e.target){$.find(".sapUiCrslScl").lastFocusableDomRef().focus();e.preventDefault();e.stopPropagation();}}else{if(this._oItemNavigation.getFocusedDomRef()===e.target&&a($.find(".sapUiCrslScl").get(0),e.target)){this._bIgnoreFocusIn=true;$.find(".sapUiCrslBefore").focus();this._bIgnoreFocusIn=false;}}};f.prototype.onsapescape=function(e){this._leaveActionMode(e);};f.prototype.onsapnext=function(e){var $=q(e.target);var s=this.$("scrolllist");s.stop(true,true);if($.hasClass('sapUiCrslItm')&&$.nextAll(':visible').length<2){this.showNext();e.preventDefault();}};f.prototype.onsapprevious=function(e){var $=q(e.target);var s=this.$("scrolllist");s.stop(true,true);if($.hasClass('sapUiCrslItm')&&$.prevAll(':visible').length<2){this.showPrevious();e.preventDefault();}};f.prototype.onkeydown=function(e){var $=this.$();if(!this._bActionMode&&e.keyCode==K.F2||e.keyCode==K.ENTER){if($.find(".sapUiCrslScl li:focus").length>0){this._enterActionMode($.find(".sapUiCrslScl li:focus :sapFocusable").get(0));e.preventDefault();e.stopPropagation();}}else if(this._bActionMode&&e.keyCode==K.F2){this._leaveActionMode(e);}};f.prototype.onmouseup=function(e){if(this.$().find(".sapUiCrslScl li :focus").length>0){this._enterActionMode(this.$().find(".sapUiCrslScl li :focus").get(0));}else{this._leaveActionMode(e);}};if(D.support.touch){f.prototype.onswipeleft=function(e){this.showNext();};f.prototype.onswiperight=function(e){this.showPrevious();};}f.prototype._enterActionMode=function(o){if(o&&!this._bActionMode){this._bActionMode=true;this.removeDelegate(this._oItemNavigation);q(this._oItemNavigation.getFocusedDomRef()).attr("tabindex","-1");this.$("scrolllist").attr("aria-activedescendant",q(this._oItemNavigation.getFocusedDomRef()).attr("id"));q(o).focus();}};f.prototype._leaveActionMode=function(e){if(this._bActionMode){this._bActionMode=false;this.addDelegate(this._oItemNavigation);q(this._oItemNavigation.getFocusedDomRef()).attr("tabindex","0");this.$("scrolllist").removeAttr("aria-activedescendant");if(e){if(q(e.target).closest("li[tabindex=-1]").length>0){var i=q(this._oItemNavigation.aItemDomRefs).index(q(e.target).closest("li[tabindex=-1]").get(0));this._oItemNavigation.focusItem(i,null);}else{if(a(this.$().find(".sapUiCrslScl").get(0),e.target)){this._oItemNavigation.focusItem(this._oItemNavigation.getFocusedIndex(),null);}}}else{this._oItemNavigation.focusItem(this._oItemNavigation.getFocusedIndex(),null);}}};f.prototype.onresize=function(e){if(!this.getDomRef()){if(this.sResizeListenerId){R.deregister(this.sResizeListenerId);this.sResizeListenerId=null;}return;}this.calculateAndSetSize();};f.prototype.showPrevious=function(){var t=this,A={},s=this.$("scrolllist");var $,e;A[this._sAnimationAttribute]=0;if(s.children('li').length<2){return;}s.stop(true,true);s.css(this._sAnimationAttribute,-this._iMaxWidth);$=s.children('li:last');e=s.children('li:first');this._showAllItems();$.insertBefore(e);s.append($.sapExtendedClone(true));s.animate(A,this.getAnimationDuration(),function(){s.children('li:last').remove();t.setProperty("firstVisibleIndex",t._getContentIndex(s.children('li:first').attr('id')),true);t._hideInvisibleItems();});};f.prototype.showNext=function(){var t=this,A={},s=this._sAnimationAttribute,S=this.$("scrolllist");var $;A[this._sAnimationAttribute]=-this._iMaxWidth;if(S.children('li').length<2){return;}S.stop(true,true);this._showAllItems();$=S.children('li:first');$.appendTo(S);$.sapExtendedClone(true).insertBefore(S.children('li:first'));S.animate(A,this.getAnimationDuration(),function(){S.children('li:first').remove();q(this).css(s,'0px');t.setProperty("firstVisibleIndex",t._getContentIndex(S.children('li:first').attr('id')),true);t._hideInvisibleItems();});};f.prototype.showElementWithId=function(e){this._showAllItems();var s=this.$("scrolllist"),i;i=s.children('li').index(this.getDomRef("item-"+e));s.children('li:lt('+i+')').appendTo(s);this._hideInvisibleItems();};f.prototype.calculateAndSetSize=function(){var o=this._getDimensions();var m=o.maxWidth;var e=o.maxHeight;var g;var v=this.getVisibleItems();var M=this.$();var n=this.$('nextbutton');var p=this.$('prevbutton');var $=this.$('contentarea');this._showAllItems();if(this.getContent().length<=0){return;}if(this.getWidth()&&this.getOrientation()=="vertical"){m=M.width();}if(this.getHeight()&&this.getOrientation()=="horizontal"){e=M.height();}this.$().addClass('sapUiCrsl'+c(this.getOrientation()));if(this.getOrientation()=="horizontal"){g=M.width()-this.getHandleSize()*2-1;$.css('left',this.getHandleSize()+"px").css('right',this.getHandleSize()+"px");if(v==0){v=Math.floor(g/m);}m=g/v;this._iMaxWidth=m;var h=e+"px";$.find('.sapUiCrslItm').css("width",m+"px").css("height",e+"px").css("display","inline-block");p.css("height",e).css("line-height",h);n.css("height",e).css("line-height",h);$.height(e);M.height(e);var V=this.getContent().length<v?this.getContent().length:v;if(this.getWidth()){M.width(this.getWidth());}else{var i=M.width()-(m*V+(this.getHandleSize()*2-1));if(i>5){M.width(m*V+(this.getHandleSize()*2-1));}}}else{g=M.height()-this.getHandleSize()*2-1;$.css('top',this.getHandleSize()+"px").css('bottom',this.getHandleSize()+"px");if(v==0){v=Math.floor(g/e);}e=g/v;this._iMaxWidth=e;$.find('.sapUiCrslItm').css("width",m+"px").css("height",e+"px").css("display","block");p.width(m).after($);n.width(m);$.width(m);M.width(m);}this._visibleItems=v;this._hideInvisibleItems();};f.prototype._getDimensions=function(){var g=this.getContent();var m=0;var h=0;for(var i=0;i<g.length;i++){var j,k;try{j=g[i].getWidth();if(j.substr(-1)=="%"){j=this.getDefaultItemWidth();}}catch(e){j=this.getDefaultItemWidth();}try{k=g[i].getHeight();if(k.substr(-1)=="%"){k=this.getDefaultItemHeight();}}catch(e){k=this.getDefaultItemHeight();}m=Math.max(m,parseInt(j));h=Math.max(h,parseInt(k));}if(m==0||isNaN(m)){m=this.getDefaultItemWidth();}if(h==0||isNaN(h)){h=this.getDefaultItemHeight();}return{maxWidth:m,maxHeight:h};};f.prototype.getFocusDomRef=function(){return this.$("scrolllist");};f.prototype._showAllItems=function(){var $=this.$("contentarea");$.find('.sapUiCrslItm').show().css("display","inline-block");};f.prototype._hideInvisibleItems=function(){var $=this.$("contentarea");$.find('.sapUiCrslItm:gt('+(this._visibleItems-1)+')').hide();};f.prototype._getContentIndex=function(i){var e=i.split("-item-");return q.inArray(sap.ui.getCore().byId(e[1]),this.getContent());};f.prototype._getItemIdByIndex=function(i){var o=this.getContent()[i];if(!o){return null;}return o.getId();};f.prototype.setFirstVisibleIndex=function(F){if(F>this.getContent().length-1){L.warning("The index is invalid. There are less items available in the carousel.");return this;}this.setProperty("firstVisibleIndex",F,true);this.showElementWithId(this._getItemIdByIndex(F));if(this._oItemNavigation){this._oItemNavigation.focusItem(F);}return this;};
//Licensed under the terms of the MIT source code license
(function(o){q.fn.sapExtendedClone=function(){var r=o.apply(this,arguments);var m=this.find('textarea').add(this.filter('textarea'));var e=r.find('textarea').add(r.filter('textarea'));var g=this.find('select').add(this.filter('select'));var h=r.find('select').add(r.filter('select'));for(var i=0,l=m.length;i<l;++i){q(e[i]).val(q(m[i]).val());}for(var i=0,l=g.length;i<l;++i){h[i].selectedIndex=g[i].selectedIndex;}return r;};})(q.fn.clone);return f;});