/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/base/ManagedObject","sap/ui/core/Element","sap/ui/core/IconPool"],function(t,e,r){"use strict";var a=t.extend("sap.ui.integration.formatters.IconFormatter",{metadata:{library:"sap.ui.integration",associations:{card:{type:"sap.ui.integration.widgets.Card",multiple:false}}}});a.SRC_FOR_HIDDEN_ICON="SRC_FOR_HIDDEN_ICON";a.prototype.formatSrc=function(t){if(!t||!t.trim()){return t}if(t===a.SRC_FOR_HIDDEN_ICON){return a.SRC_FOR_HIDDEN_ICON}if(t.startsWith("data:")||r.isIconURI(t)){return t}const e=this._getCardInstance();return e.getRuntimeUrl(t)};a.prototype._getCardInstance=function(){return e.getElementById(this.getCard())};return a});
//# sourceMappingURL=IconFormatter.js.map