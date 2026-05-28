sap.ui.define([
	"sap/ui/model/SimpleType",
], function (SimpleType) {
	"use strict";


    return SimpleType.extend("exface.ui5Custom.dataTypes.MomentDateType", {		
		
		constructor: function(data) {
			if (data) {
				this.options = data;
			} else {
				this.options = {};
			}
		},
		
		parseValue: function (date) {
			var ParseParams = undefined;
			var dateFormat = undefined;
			if (this.options.ParseParams) ParseParams = this.options.ParseParams;
			if (this.options.dateFormat) dateFormat = this.options.dateFormat;
			if (this.options.valueFormat) {
				return exfTools.date.format(exfTools.date.parse(date, dateFormat, ParseParams), this.options.valueFormat, this.options.emptyText);
			}
			return exfTools.date.parse(date, dateFormat, ParseParams);
		},			
		
		formatValue: function (sDate) {
			var dateFormat = this.options.dateFormat ? dateFormat = this.options.dateFormat : undefined;
			return exfTools.date.format(sDate, dateFormat, this.options.emptyText);			
		},
		
		validateValue: function (sInternalValue) {			
			return exfTools.date.validate(sInternalValue);
		},
	});
});