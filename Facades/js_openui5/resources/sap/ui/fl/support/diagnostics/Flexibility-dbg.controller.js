/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define([
	"sap/ui/core/mvc/Controller"
], function(
	Controller
) {
	"use strict";

	/**
	 * Controller for the flexibility diagnostics view.
	 *
	 * @constructor
	 * @alias sap.ui.fl.support.diagnostics.Flexibility
	 * @author SAP SE
	 * @version 1.144.0
	 */
	return Controller.extend("sap.ui.fl.support.diagnostics.Flexibility", {
		onDownloadPress() {
			const bAnonymizeData = this.getView().getModel("flexToolSettings").getProperty("/anonymizeData");
			const oFlexibilityPlugin = this.getView().getViewData().plugin;
			// The download is handled by the plugin
			oFlexibilityPlugin.sendGetDataEvent(bAnonymizeData);
		}
	});
});