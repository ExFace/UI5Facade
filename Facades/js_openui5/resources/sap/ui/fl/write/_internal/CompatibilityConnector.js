/*
 * ! OpenUI5
 * (c) Copyright 2009-2019 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/fl/apply/_internal/Storage","sap/ui/fl/write/_internal/Storage","sap/ui/fl/FakeLrepConnector"],function(A,W,F){"use strict";function _(m){return!!F.prototype[m];}var C=function(){};C.loadChanges=function(c,p){p=p||{};if(_("loadChanges")){return F.prototype.loadChanges(c,p);}return A.loadFlexData({reference:c.name,appVersion:c.appVersion,componentName:c.appName,cacheKey:p.cacheKey,siteId:p.siteId,appDescriptor:p.appDescriptor}).then(function(f){return{changes:f,loadModules:false};});};C.loadSettings=function(){if(_("loadSettings")){return F.prototype.loadSettings();}return W.loadFeatures();};C.create=function(f,c,i){if(_("create")){return F.prototype.create(f,c,i);}var a=f;if(!Array.isArray(a)){a=[f];}return W.write({layer:a[0].layer,flexObjects:a,_transport:c,isLegacyVariant:i});};C.update=function(f,c){if(_("update")){return F.prototype.update(f,c);}return W.update({flexObject:f,layer:f.layer,transport:c});};C.deleteChange=function(f,c){if(_("deleteChange")){return F.prototype.deleteChange(f,c);}return W.remove({flexObject:f,layer:f.layer,transport:c});};C.getFlexInfo=function(p){if(_("getFlexInfo")){return F.prototype.getFlexInfo(p);}return W.getFlexInfo(p);};C.resetChanges=function(p){if(_("resetChanges")){return F.prototype.resetChanges(p);}return W.reset({reference:p.sReference,layer:p.sLayer,appVersion:p.sAppVersion,generator:p.sGenerator,changelist:p.sChangelist,selectorIds:p.aSelectorIds,changeTypes:p.aChangeTypes});};return C;},true);