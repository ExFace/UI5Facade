/*
 * ! OpenUI5
 * (c) Copyright 2009-2020 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define([], function () {
	"use strict";
	/**
	 * P13n/Settings helper class for sap.ui.mdc.Chart.
	 *
	 * @author SAP SE
	 * @private
	 * @experimental
	 * @since 1.60
	 * @alias sap.ui.mdc.chart.ChartSettings
	 */

	var ChartSettings = {

		showPanel: function (oControl, sP13nType, oSource, aProperties) {
			ChartSettings["showP13n" + sP13nType](oControl, oSource);
		},

		showP13nChart: function (oControl, oSource) {
			var oAdaptationController = oControl.getAdaptationController();
			oAdaptationController.showP13n(oSource, "Item");
		},

		showP13nSort: function(oControl, oSource){
			var oAdaptationController = oControl.getAdaptationController();
			oAdaptationController.showP13n(oSource, "Sort");
		}

	};
	return ChartSettings;
});
