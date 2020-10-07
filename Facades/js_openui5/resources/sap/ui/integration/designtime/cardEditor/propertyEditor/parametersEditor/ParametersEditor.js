/*!
 * OpenUI5
 * (c) Copyright 2009-2020 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/integration/designtime/baseEditor/propertyEditor/BasePropertyEditor","sap/ui/integration/designtime/baseEditor/propertyEditor/mapEditor/MapEditor","sap/base/util/includes","sap/base/util/restricted/_merge"],function(B,M,i,_){"use strict";var P=M.extend("sap.ui.integration.designtime.cardEditor.propertyEditor.parametersEditor.ParametersEditor",{renderer:B.getMetadata().getRenderer().render});P.configMetadata=Object.assign({},M.configMetadata,{allowLabelChange:{defaultValue:true,mergeStrategy:"mostRestrictiveWins"}});P.prototype.formatItemConfig=function(c){var m=M.prototype.formatItemConfig.apply(this,arguments);var k=c.key;var I=this.getNestedDesigntimeMetadataValue(k);var l=I.label;m.splice(1,0,{label:this.getI18nProperty("CARD_EDITOR.LABEL"),path:"label",value:l,placeholder:l?undefined:k,type:"string",enabled:this.getConfig().allowLabelChange,itemKey:k});return m;};P.prototype.processInputValue=function(v){return v;};P.prototype.processOutputValue=function(v){return v;};P.prototype._configItemsFormatter=function(I){return Array.isArray(I)?I.map(function(o){var a=this.getNestedDesigntimeMetadataValue(o.key);var c=_({},o.value,a);if(!c.label){c.label=o.key;}c.itemKey=o.key;c.path="value";c.designtime=this.getNestedDesigntimeMetadata(o.key);return c;}.bind(this)):[];};P.prototype.getItemChangeHandlers=function(){return Object.assign({},M.prototype.getItemChangeHandlers.apply(this,arguments),{label:this._onDesigntimeChange});};P.prototype._onDesigntimeChange=function(k,e){var d=_({},this.getConfig().designtime);var n={__value:{}};n.__value[e.getParameter("path")]=e.getParameter("value");d[k]=_({},d[k],n);this.setDesigntimeMetadata(d);this.setValue(this.getValue());};P.prototype.onBeforeConfigChange=function(c){if(!c.allowTypeChange&&!c.allowKeyChange){this.setFragment("sap.ui.integration.designtime.cardEditor.propertyEditor.parametersEditor.ParametersConfigurationEditor",function(){return 1;});}return c;};P.prototype._isValidItem=function(I,o){var t=o.type;var v=o.value;var a=this._getAllowedTypes();return(t&&i(a,t)||typeof v==="string"&&i(a,"string"));};return P;});