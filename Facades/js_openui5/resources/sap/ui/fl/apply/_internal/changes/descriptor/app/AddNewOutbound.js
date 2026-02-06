/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
*/
sap.ui.define(["sap/ui/fl/util/DescriptorChangeCheck"],function(t){"use strict";const e=["semanticObject","action"];const a=[...e,"additionalParameters","parameters"];const o={semanticObject:"^[\\w\\*]{0,30}$",action:"^[\\w\\*]{0,60}$",additionalParameters:"^(ignored|allowed|notallowed)$"};const n={applyChange(n,s){n["sap.app"].crossNavigation||={};n["sap.app"].crossNavigation.outbounds||={};const r=s.getContent();const i=t.getAndCheckContentObject(r,{sKey:"outbound",sChangeType:s.getChangeType(),iMaxNumberOfKeys:1,aMandatoryProperties:e,aSupportedProperties:a,oSupportedPropertyPattern:o});const p=n["sap.app"].crossNavigation.outbounds[i];if(!p){t.checkIdNamespaceCompliance(i,s);n["sap.app"].crossNavigation.outbounds[i]=r.outbound[i]}else{throw new Error(`Outbound with ID "${i}" already exist.`)}return n}};return n});
//# sourceMappingURL=AddNewOutbound.js.map