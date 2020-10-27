/*
 * ! OpenUI5
 * (c) Copyright 2009-2020 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(['sap/ui/mdc/field/FieldValueHelpContentWrapperBase','sap/ui/model/ChangeReason','sap/ui/model/Filter','sap/ui/model/FormatException','sap/ui/model/ParseException','sap/ui/model/FilterType','sap/ui/base/ManagedObjectObserver','sap/base/strings/capitalize','sap/m/library','sap/base/util/deepEqual','sap/base/Log'],function(F,C,a,b,P,c,M,d,l,e,L){"use strict";var f=l.ListMode;var S=l.Sticky;var g;var h=F.extend("sap.ui.mdc.field.FieldValueHelpMTableWrapper",{metadata:{library:"sap.ui.mdc",aggregations:{table:{type:"sap.m.Table",multiple:false}},defaultAggregation:"table"}});h._init=function(){g=undefined;};h.prototype.init=function(){F.prototype.init.apply(this,arguments);this._oObserver=new M(n.bind(this));this._oObserver.observe(this,{properties:["selectedItems"],aggregations:["table"]});this._oTablePromise=new Promise(function(R){this._oTablePromiseResolve=R;}.bind(this));this._oResourceBundle=sap.ui.getCore().getLibraryResourceBundle("sap.ui.mdc");this._oPromises={};this._oTableDelegate={onsapprevious:z};};h.prototype.exit=function(){F.prototype.exit.apply(this,arguments);if(this._oScrollContainer){this._oScrollContainer.destroy();delete this._oScrollContainer;}this._oObserver.disconnect();this._oObserver=undefined;delete this._oTablePromise;delete this._oTablePromiseResolve;};h.prototype.invalidate=function(O){if(O){var T=this.getTable();if(T&&O===T){if(O.bOutput&&!this._bIsBeingDestroyed){var i=this.getParent();if(i){i.invalidate(this);}}return;}}F.prototype.invalidate.apply(this,arguments);};h.prototype.initialize=function(i){if(i||this._oScrollContainer){return this;}if(!g&&!this._bScrollContainerRequested){g=sap.ui.require("sap/m/ScrollContainer");if(!g){sap.ui.require(["sap/m/ScrollContainer"],_.bind(this));this._bScrollContainerRequested=true;}}if(g&&!this._bScrollContainerRequested){this._oScrollContainer=new g(this.getId()+"-SC",{height:"100%",width:"100%",vertical:true});this._oScrollContainer._oWrapper=this;this._oScrollContainer.getContent=function(){var j=[];var T=this._oWrapper&&this._oWrapper.getTable();if(T){j.push(T);}return j;};}return this;};function _(i){g=i;this._bScrollContainerRequested=false;if(!this._bIsBeingDestroyed){this.initialize();this.fireDataUpdate({contentChange:true});}}h.prototype.getDialogContent=function(){return this._oScrollContainer;};h.prototype.getSuggestionContent=function(){return this.getTable();};h.prototype.fieldHelpOpen=function(i){F.prototype.fieldHelpOpen.apply(this,arguments);var T=this.getTable();if(T){q.call(this,T,i);v.call(this);if(i){var j=T.getSelectedItem();T.scrollToIndex(T.indexOfItem(j));}}return this;};h.prototype.navigate=function(i){var T=this.getTable();if(!y(T)){this._bNavigate=true;this._iStep=i;return;}if(this._getMaxConditions()!==1){T.focus();this.fireNavigate();return;}var j=T.getSelectedItem();var I=T.getItems();var A=I.length;var B=0;if(j){B=T.indexOfItem(j);B=B+i;}else if(i>=0){B=i-1;}else{B=A+i;}if(B<0){B=0;}else if(B>=A-1){B=A-1;}var D=I[B];if(D&&D!==j){D.setSelected(true);var V=w.call(this,D);T.scrollToIndex(B);this._bNoTableUpdate=true;this.setSelectedItems([{key:V.key,description:V.description,inParameters:V.inParameters,outParameters:V.outParameters}]);this._bNoTableUpdate=false;this.fireNavigate({key:V.key,description:V.description,inParameters:V.inParameters,outParameters:V.outParameters,itemId:D.getId()});}};h.prototype.getTextForKey=function(K,I,O,N){if(K===null||K===undefined){return null;}var T=this.getTable();if(y(T)){var R={key:K,description:""};var j=T.getItems();var A;var B;var D=false;if(I){A=[];for(var E in I){A.push(E);}}if(O){B=[];for(var G in O){B.push(G);}}for(var i=0;i<j.length;i++){var H=j[i];var V=w.call(this,H,A,B);if(V.key===K&&(!V.inParameters||!I||e(I,V.inParameters))&&(!V.outParameters||!O||e(O,V.outParameters))){R.description=V.description;R.inParameters=V.inParameters;R.outParameters=V.outParameters;D=true;break;}}if(D){return R;}}if(N){throw new b(this._oResourceBundle.getText("valuehelp.VALUE_NOT_EXIST",[K]));}else{return k.call(this,this._getKeyPath,K,"description",I,O,true);}};h.prototype.getKeyForText=function(T,I,N){if(!T){return null;}var j=this.getTable();if(y(j)){var R={key:undefined,description:T};var A=j.getItems();var B;var D=false;if(I){B=[];for(var E in I){B.push(E);}}for(var i=0;i<A.length;i++){var G=A[i];var V=w.call(this,G,B);if(V.description===T&&(!V.inParameters||!I||e(I,V.inParameters))){R.key=V.key;R.inParameters=V.inParameters;R.outParameters=V.outParameters;D=true;break;}}if(D){return R;}}if(N){throw new P(this._oResourceBundle.getText("valuehelp.VALUE_NOT_EXIST",[T]));}else{return k.call(this,this._getDescriptionPath,T,"key",I,undefined,false);}};function k(i,V,R,I,O,U){var j=i.call(this);if(this._oPromises[j]&&this._oPromises[j][V]){return this._oPromises[j][V];}if(!this._oPromises[j]){this._oPromises[j]={};}this._oPromises[j][V]=new Promise(function(A,B){this._oTablePromise.then(function(T){var D=j;j=i.call(this);if(!j){B(new Error("missing FieldPath"));return;}if(j!==D){if(!this._oPromises[j]){this._oPromises[j]={};}this._oPromises[j][V]=this._oPromises[D][V];delete this._oPromises[D][V];}var E=this.getListBinding();var G=E.getModel();var H=E.getPath();var J=new a(j,"EQ",V);var K=E.getContext();var N=[];if(I){for(var Q in I){N.push(new a(Q,"EQ",I[Q]));}}if(O){for(var W in O){if(!I||!I.hasOwnProperty(W)||I[W]!==O[W]){N.push(new a(W,"EQ",O[W]));}}}if(N.length>0){N.push(J);J=new a({filters:N,and:true});}try{var X=G.bindList(H,K);var Y=function(){var a1=X.getContexts();if(a1.length===1){var b1=x.call(this,a1[0]);var c1={key:b1.key,description:b1.description,inParameters:b1.inParameters,outParameters:b1.outParameters};A(c1);}else if(V===""&&a1.length===0){A(null);}else{var d1;var e1;var f1=false;if(a1.length>1){d1=this._oResourceBundle.getText("valuehelp.VALUE_NOT_UNIQUE",[V]);f1=true;}else{d1=this._oResourceBundle.getText("valuehelp.VALUE_NOT_EXIST",[V]);}if(U){e1=new b(d1);}else{e1=new P(d1);}e1._bNotUnique=f1;B(e1);}setTimeout(function(){X.destroy();},0);delete this._oPromises[j][V];};var Z=this._getDelegate();if(Z.delegate){Z.delegate.executeFilter(Z.payload,X,J,Y.bind(this),2);}}catch($){B($);}}.bind(this));}.bind(this));return this._oPromises[j][V];}h.prototype.getListBinding=function(){var T=this.getTable();var i;if(T){i=T.getBinding("items");}return i;};h.prototype.getAsyncKeyText=function(){return true;};h.prototype.applyFilters=function(i,j){var A=this.getListBinding();if(!A){this._oTablePromise.then(function(T){if(!this._bIsBeingDestroyed){this.applyFilters(i,j);}}.bind(this));return;}var D=this._getDelegate();var U=true;var B=A.getFilterInfo();if(!i){i=[];}if(i.length===0&&!B){U=false;}if(D.delegate&&D.delegate.isSearchSupported(D.payload,A)){if(!A.isSuspended()&&U){A.suspend();}D.delegate.executeSearch(D.payload,A,j);L.info("ValueHelp-Search: "+j);}if(U){A.filter(i,c.Application);L.info("ValueHelp-Filter: "+m.call(this,i));}if(A.isSuspended()){A.resume();}};h.prototype.isSuspended=function(){var i=this.getListBinding();if(!i){return true;}return i.isSuspended();};function m(i){var R;if(!i){return"";}if(Array.isArray(i)){R="";i.forEach(function(i,I,j){R+=m.call(this,i);if(j.length-1!=I){R+=" or ";}},this);return"("+R+")";}else if(i._bMultiFilter){R="";var A=i.bAnd;i.aFilters.forEach(function(i,I,j){R+=m.call(this,i);if(j.length-1!=I){R+=A?" and ":" or ";}},this);return"("+R+")";}else{R=i.sPath+" "+i.sOperator+" '"+i.oValue1+"'";if(i.sOperator==="BT"){R+="...'"+i.oValue2+"'";}return R;}}h.prototype.clone=function(i,j){var T=this.getTable();if(T){T.detachEvent("itemPress",r,this);T.detachEvent("selectionChange",s,this);T.detachEvent("updateFinished",u,this);}var A=F.prototype.clone.apply(this,arguments);if(T){T.attachEvent("itemPress",r,this);T.attachEvent("selectionChange",s,this);T.attachEvent("updateFinished",u,this);}return A;};function n(i){if(i.name==="table"){o.call(this,i.mutation,i.child);}if(i.name==="selectedItems"){v.call(this);}}function o(i,T){if(i==="remove"){T.detachEvent("itemPress",r,this);T.detachEvent("selectionChange",s,this);T.detachEvent("updateFinished",u,this);T.detachEvent("modelContextChange",p,this);T.removeDelegate(this._oTableDelegate);T=undefined;this._oTablePromise=new Promise(function(R){this._oTablePromiseResolve=R;}.bind(this));}else{T.setMode(f.SingleSelectMaster);T.setRememberSelections(false);T.attachEvent("itemPress",r,this);T.attachEvent("selectionChange",s,this);T.attachEvent("updateFinished",u,this);T.addDelegate(this._oTableDelegate,true,this);q.call(this,T,this._bSuggestion);v.call(this);if(this._bNavigate){this._bNavigate=false;this.navigate(this._iStep);}if(this.getListBinding()){this._oTablePromiseResolve(T);}else{T.attachEvent("modelContextChange",p,this);}}this.fireDataUpdate({contentChange:true});}function p(E){if(this.getListBinding()){var T=E.getSource();this._oTablePromiseResolve(T);T.detachEvent("modelContextChange",p,this);}}function q(T,i){if(T&&this.getParent()){if(i){if(this._sTableWidth){T.setWidth(this._sTableWidth);}if(this._getMaxConditions()===1){T.setMode(f.SingleSelectMaster);}else{T.setMode(f.MultiSelect);}}else{if(T.getWidth()!=="100%"){this._sTableWidth=T.getWidth();T.setWidth("100%");}if(this._getMaxConditions()===1){T.setMode(f.SingleSelectLeft);}else{T.setMode(f.MultiSelect);}}var j=T.getSticky();if(!j||j.length===0){T.setSticky([S.ColumnHeaders]);}}}function r(E){var i=E.getParameter("listItem");if(!this._bSuggestion||this._getMaxConditions()!==1){i.setSelected(!i.getSelected());}t.call(this,true);}function s(E){if(!this._bSuggestion||this._getMaxConditions()!==1){t.call(this,false);}}function t(I){var A=[];var T=this.getTable();if(T){var B=this.getSelectedItems();var D=T.getItems();var i=0;var E;var V;if(B.length>0){for(i=0;i<D.length;i++){E=D[i];V=w.call(this,E);if(!V){throw new Error("Key of item cannot be determined"+this);}for(var j=B.length-1;j>=0;j--){var G=B[j];if(G.key===V.key&&(!V.inParameters||!G.inParameters||e(G.inParameters,V.inParameters))&&(!V.outParameters||!G.outParameters||e(G.outParameters,V.outParameters))){B.splice(j,1);break;}}}}if(B.length>0){A=B;}B=T.getSelectedItems();for(i=0;i<B.length;i++){E=B[i];V=w.call(this,E);if(!V){throw new Error("Key of item cannot be determined"+this);}A.push({key:V.key,description:V.description,inParameters:V.inParameters,outParameters:V.outParameters});}}this._bNoTableUpdate=true;this.setSelectedItems(A);this._bNoTableUpdate=false;this.fireSelectionChange({selectedItems:A,itemPress:I});}function u(E){if(!this.getParent()){return;}v.call(this);if(this._bNavigate){this._bNavigate=false;this.navigate(this._iStep);}if(E.getParameter("reason")!==d(C.Filter)){this.fireDataUpdate({contentChange:false});}}function v(){if(this._bNoTableUpdate){return;}var T=this.getTable();if(y(T)){var A=this.getSelectedItems();var I=T.getItems();var U=false;for(var j=0;j<I.length;j++){var B=I[j];var D=false;if(A.length>0){var V=w.call(this,B);for(var i=0;i<A.length;i++){var E=A[i];if(V.key===E.key&&(!V.inParameters||!E.inParameters||e(E.inParameters,V.inParameters))&&(!V.outParameters||!E.outParameters||e(E.outParameters,V.outParameters))){D=true;if(V.description!==E.description){E.description=V.description;U=true;}break;}}}if(B.getSelected()!==D){B.setSelected(D);}}}if(U){this._bNoTableUpdate=true;this.setSelectedItems(A);this._bNoTableUpdate=false;}}function w(i,I,O){var V;var B=i.getBindingContext();if(B){V=x.call(this,B,I,O);}if(!V){var K=this._getKeyPath();var j;var D;if(!K&&i.getCells){var A=i.getCells();if(A.length>0&&A[0].getText){j=A[0].getText();}if(A.length>1&&A[1].getText){D=A[1].getText();}if(j!==undefined){V={key:j,description:D};}}}if(!V){throw new Error("Key could not be determined from item "+this);}return V;}function x(B,I,O){var K=this._getKeyPath();var D=this._getDescriptionPath();var j=B.getObject();var A;var E;if(!I){I=this._getInParameters();}if(!O){O=this._getOutParameters();}var G=I.length>0?{}:null;var H=O.length>0?{}:null;var J;if(j){if(K&&j.hasOwnProperty(K)){A=j[K];}if(D&&j.hasOwnProperty(D)){E=j[D];}var i=0;for(i=0;i<I.length;i++){J=I[i];if(j.hasOwnProperty(J)){G[J]=j[J];}}for(i=0;i<O.length;i++){J=O[i];if(j.hasOwnProperty(J)){H[J]=j[J];}else{L.error("FieldValueHelpMTableWrapper","cannot find out-parameter '"+J+"' in item data!");}}}if(A===null||A===undefined){return false;}return{key:A,description:E,inParameters:G,outParameters:H};}function y(T){if(!T){return false;}var B=T.getBinding("items");if(B&&(B.isSuspended()||B.getLength()===0)){return false;}return true;}function z(E){var T=this.getTable();var i=jQuery(E.target).control(0);switch(E.type){case"sapprevious":if(i.isA("sap.m.ListItemBase")){if(T.indexOfItem(i)===0){this.fireNavigate({key:undefined,description:undefined,leave:true});E.preventDefault();E.stopPropagation();E.stopImmediatePropagation(true);}}break;default:break;}}return h;});
