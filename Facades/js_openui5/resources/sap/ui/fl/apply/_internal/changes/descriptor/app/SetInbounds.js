/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
*/
sap.ui.define(["sap/ui/fl/util/DescriptorChangeCheck"],function(e){"use strict";const t=["semanticObject","action"];const n=[...t,"hideLauncher","icon","title","shortTitle","subTitle","info","indicatorDataSource","deviceTypes","displayMode","signature"];const a={semanticObject:"^[\\w\\*]{0,30}$",action:"^[\\w\\*]{0,60}$"};const o={applyChange(o,s){o["sap.app"].crossNavigation||={};o["sap.app"].crossNavigation.inbounds||={};const i=s.getContent();const c=e.getAndCheckContentObject(i,{sKey:"inbounds",sChangeType:s.getChangeType(),iMaxNumberOfKeys:-1,aMandatoryProperties:t,aSupportedProperties:n,oSupportedPropertyPattern:a});c.forEach(t=>{e.checkIdNamespaceCompliance(t,s)});o["sap.app"].crossNavigation.inbounds=i.inbounds;return o}};return o});
//# sourceMappingURL=SetInbounds.js.map