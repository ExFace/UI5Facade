/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
*/
sap.ui.define(["sap/ui/fl/util/DescriptorChangeCheck"],function(e){"use strict";const t=["semanticObject","action"];const n=[...t,"hideLauncher","icon","title","shortTitle","subTitle","info","indicatorDataSource","deviceTypes","displayMode","signature"];const a={semanticObject:"^[\\w\\*]{0,30}$",action:"^[\\w\\*]{0,60}$"};const o={applyChange(o,i){o["sap.app"].crossNavigation||={};o["sap.app"].crossNavigation.inbounds||={};const s=i.getContent();const c=e.getAndCheckContentObject(s,{sKey:"inbound",sChangeType:i.getChangeType(),iMaxNumberOfKeys:1,aMandatoryProperties:t,aSupportedProperties:n,oSupportedPropertyPattern:a});const r=o["sap.app"].crossNavigation.inbounds[c];if(!r){e.checkIdNamespaceCompliance(c,i);o["sap.app"].crossNavigation.inbounds[c]=s.inbound[c]}else{throw new Error(`Inbound with ID "${c}" already exist.`)}return o}};return o});
//# sourceMappingURL=AddNewInbound.js.map