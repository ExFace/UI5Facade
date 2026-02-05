/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/fl/write/_internal/fieldExtensibility/ABAPAccess","sap/ui/fl/write/_internal/init"],function(e){"use strict";var t={};var n;function i(){n||=e;return n}function r(...e){const t=e.shift();const n=i();if(!n){return Promise.reject("Could not determine field extensibility scenario")}return Promise.resolve(n[t].apply(null,e))}t.onControlSelected=function(e){return r("onControlSelected",e)};t.isExtensibilityEnabled=function(e){return r("isExtensibilityEnabled",e)};t.isServiceOutdated=function(e){return r("isServiceOutdated",e)};t.setServiceValid=function(e){return r("setServiceValid",e)};t.getTexts=function(){return r("getTexts")};t.getExtensionData=function(){return r("getExtensionData")};t.onTriggerCreateExtensionData=function(e,t,n){return r("onTriggerCreateExtensionData",e,t,n)};t._resetCurrentScenario=function(){n=null};return t});
//# sourceMappingURL=FieldExtensibility.js.map