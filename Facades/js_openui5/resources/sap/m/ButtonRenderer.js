/*!
 * OpenUI5
 * (c) Copyright 2009-2020 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(['sap/ui/Device','sap/ui/core/library','sap/ui/core/IconPool','sap/ui/core/ShortcutHintsMixin','sap/m/library','sap/ui/core/InvisibleText'],function(D,c,I,S,l,a){"use strict";var B=l.ButtonType;var b=l.ButtonAccessibilityType;var T=c.TextDirection;var d=l.BadgeState;var e={apiVersion:2};e.render=function(R,o){var s=o.getId();var t=o.getType();var E=o.getEnabled();var w=o.getWidth();var f=o._getTooltip();var g=o._getText();var h=o.getTextDirection();var i=D.browser.internet_explorer||D.browser.edge;var j=(h===T.Inherit)&&!i;var k=I.getIconURI("nav-back");var m;R.openStart("button",o);R.class("sapMBtnBase");if(!o._isUnstyled()){R.class("sapMBtn");if((t===B.Back||t===B.Up)&&o._getAppliedIcon()&&!g){R.class("sapMBtnBack");}}var n=e.generateAccProps(o);if(this.renderAccessibilityAttributes){this.renderAccessibilityAttributes(R,o,n);}R.accessibilityState(o,n);if(!E){R.attr("disabled","disabled");if(!o._isUnstyled()){R.class("sapMBtnDisabled");}}else{switch(t){case B.Accept:case B.Reject:case B.Emphasized:case B.Attention:R.class("sapMBtnInverted");break;default:break;}}if(f&&!S.isDOMIDRegistered(s)){R.attr("title",f);}if(w!=""||w.toLowerCase()==="auto"){R.style("width",w);if(o._getAppliedIcon()&&g){m="4rem";}else{m="2.25rem";}R.style("min-width",m);}r(o,R);R.openEnd();R.openStart("span",s+"-inner");if(!o._isUnstyled()){R.class("sapMBtnInner");}if(o._isHoverable()){R.class("sapMBtnHoverable");}if(E){R.class("sapMFocusable");if(i){R.class("sapMIE");}}if(!o._isUnstyled()){if(g){R.class("sapMBtnText");}if(t===B.Back||t===B.Up){R.class("sapMBtnBack");}if(o._getAppliedIcon()){if(o.getIconFirst()){R.class("sapMBtnIconFirst");}else{R.class("sapMBtnIconLast");}}}if(this.renderButtonAttributes){this.renderButtonAttributes(R,o);}if(!o._isUnstyled()&&t!==""){R.class("sapMBtn"+t);}r(o,R);R.openEnd();if(t===B.Back||t===B.Up){this.writeInternalIconPoolHtml(R,o,k);}if(o.getIconFirst()&&o._getAppliedIcon()){this.writeImgHtml(R,o);}if(g){R.openStart("span",s+"-content");R.class("sapMBtnContent");if(h!==T.Inherit){R.attr("dir",h.toLowerCase());}R.openEnd();if(j){R.openStart("bdi",s+"-BDI-content");R.openEnd();}R.text(g);if(j){R.close("bdi");}R.close("span");}if(!o.getIconFirst()&&o._getAppliedIcon()){this.writeImgHtml(R,o);}if(i&&E){R.openStart("span");R.class("sapMBtnFocusDiv");R.openEnd();R.close("span");}R.close("span");if(f){R.openStart("span",s+"-tooltip");R.class("sapUiInvisibleText");R.openEnd();R.text(f);R.close("span");}R.close("button");};e.writeImgHtml=function(R,o){R.renderControl(o._getImage(o.getId()+"-img",o._getAppliedIcon(),o.getActiveIcon(),o.getIconDensityAware()));};e.writeInternalIconPoolHtml=function(R,o,u){R.renderControl(o._getInternalIconBtn((o.getId()+"-iconBtn"),u));};function r(o,R){if(o._bExcludeFromTabChain){R.attr("tabindex",-1);}}var A={Accept:"BUTTON_ARIA_TYPE_ACCEPT",Reject:"BUTTON_ARIA_TYPE_REJECT",Attention:"BUTTON_ARIA_TYPE_ATTENTION",Emphasized:"BUTTON_ARIA_TYPE_EMPHASIZED",Critical:"BUTTON_ARIA_TYPE_CRITICAL",Negative:"BUTTON_ARIA_TYPE_NEGATIVE",Success:"BUTTON_ARIA_TYPE_SUCCESS"};e.getButtonTypeAriaLabelId=function(t){return a.getStaticId("sap.m",A[t]);};e.getBadgeTextId=function(o){return o._oBadgeData&&o._oBadgeData.value!==""&&o._oBadgeData.state!==d.Disappear?o._getBadgeInvisibleText().getId():"";};e.generateAccProps=function(o){var t=o._getText(),m;if(t){m=e.generateTextButtonAccProps(o);}else{m=e.generateIconOnlyButtonAccProps(o);}m["disabled"]=null;return m;};e.generateIconOnlyButtonAccProps=function(o){var t=e.getButtonTypeAriaLabelId(o.getType()),s=this.getBadgeTextId(o),f=o._getTooltip(),g=o.getId()+"-tooltip",h=o._determineAccessibilityType(),m={};switch(h){case b.Default:m["label"]={value:f,append:true};break;case b.Described:m["label"]={value:f,append:true};m["describedby"]={value:(g+" "+t+" "+s).trim(),append:true};break;case b.Labelled:m["describedby"]={value:g,append:true};break;case b.Combined:m["describedby"]={value:(g+" "+t+" "+s).trim(),append:true};break;default:break;}return m;};e.generateTextButtonAccProps=function(o){var s=o.getId(),t=e.getButtonTypeAriaLabelId(o.getType()),f=this.getBadgeTextId(o),g=o._getTooltip()?s+"-tooltip":"",i=s+"-content",h=o._determineAccessibilityType(),p=o._determineSelfReferencePresence(),m={},j;switch(h){case b.Default:g&&(m["describedby"]={value:g,append:true});break;case b.Described:j=(g+" "+t+" "+f).trim();j&&(m["describedby"]={value:j,append:true});break;case b.Labelled:p&&(m["labelledby"]={value:i,append:true});g&&(m["describedby"]={value:g,append:true});break;case b.Combined:j=(g+" "+t+" "+f).trim();j&&(m["describedby"]={value:j,append:true});p&&(m["labelledby"]={value:i,append:true});break;default:break;}return m;};return e;},true);
