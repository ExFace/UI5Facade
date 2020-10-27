/*!
 * OpenUI5
 * (c) Copyright 2009-2020 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/fl/changeHandler/BaseAddViaDelegate"],function(B){"use strict";var t="sap.ui.core.Title";var T="sap.m.Toolbar";var s="sap.m.Label";var a="sap.ui.comp.smartfield.SmartLabel";function g(c,p){var C=p.change;var m=p.modifier;var d=C.getContent();var i=d.newFieldIndex;var o=C.getDependentControl("targetContainerHeader",p);var I=c.indexOf(o);var n=0;var f=0;if(c.length===1||c.length===I+1){n=c.length;}else{var j=0;for(j=I+1;j<c.length;j++){var e=m.getControlType(c[j]);if(e===s||e===a){if(f==i){n=j;break;}f++;}if(e===t||e===T){n=j;break;}if(j===(c.length-1)){n=c.length;}}}return n;}function b(c,n,i){var C=c.slice();C.splice(n,0,i.label,i.control);return C;}function r(S,c,m,p){m.removeAllAggregation(S,"content");for(var i=0;i<c.length;++i){m.insertAggregation(S,"content",c[i],i,p.view);}}var A=B.createAddViaDelegateChangeHandler({addProperty:function(p){var S=p.control;var i=p.innerControls;var m=p.modifier;var o=p.appComponent;var c=p.change;var R=c.getRevertData();R.labelSelector=m.getSelector(i.label,o);var C=m.getAggregation(S,"content");var n=g(C,p);var d=b(C,n,i);r(S,d,m,p);if(i.valueHelp){m.insertAggregation(S,"dependents",i.valueHelp,0,p.view);}},revertAdditionalControls:function(p){var S=p.control;var c=p.change;var m=p.modifier;var o=p.appComponent;var l=c.getRevertData().labelSelector;if(l){var L=m.bySelector(l,o);m.removeAggregation(S,"content",L);m.destroy(L);}},aggregationName:"content",mapParentIdIntoChange:function(c,S,p){var o=p.appComponent;var v=p.view;var f=p.modifier.bySelector(S.parentId,o,v);var d=f.getTitle()||f.getToolbar();if(d){c.addDependentControl(d.getId(),"targetContainerHeader",p);}},parentAlias:"_",fieldSuffix:"",skipCreateLayout:true,supportsDefault:true});return A;},true);
