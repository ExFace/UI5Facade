/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/base/util/merge","sap/ui/fl/apply/_internal/flexObjects/FlexObjectFactory","sap/ui/fl/write/_internal/flexState/changes/UIChangeManager","sap/ui/fl/write/_internal/flexState/FlexObjectManager"],function(e,t,n,i){"use strict";const a=function(e,t,n){this._mChangeFile=e;this._mChangeFile.packageName="";this._oInlineChange=t;this._oAppComponent=n};a.prototype.submit=function(e){this.store(e);return i.saveFlexObjects({reference:this._mChangeFile.reference,selector:this._mChangeFile.selector})};a.prototype.store=function(e){const t=this._mChangeFile.reference;const i=this._getChangeToSubmit();n.addDirtyChanges(t,[i],e||this._oAppComponent);return i};a.prototype._getChangeToSubmit=function(){return t.createAppDescriptorChange(this._getMap())};a.prototype._getMap=function(){var e=this._oInlineChange.getMap();this._mChangeFile.content=e.content;this._mChangeFile.texts=e.texts;return this._mChangeFile};a.prototype.getJson=function(){return e({},this._getMap())};return a});
//# sourceMappingURL=DescriptorChange.js.map