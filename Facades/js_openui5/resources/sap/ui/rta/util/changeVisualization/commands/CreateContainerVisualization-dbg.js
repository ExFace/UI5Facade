/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */

sap.ui.define([
	"sap/ui/core/Lib",
	"sap/ui/rta/util/changeVisualization/ChangeVisualizationUtils"
], function(
	Lib,
	ChangeVisualizationUtils
) {
	"use strict";

	var CreateContainerVisualization = {};

	/**
	 * Creates a localized description for create container, based on the provided
	 * payload containing the original label.
	 *
	 * @param {object} mPayload - Change visualization description payload from the change handler
	 * @param {string} mPayload.originalLabel - Original element label from create container change
	 * @param {string} sLabel - Current element label
	 * @returns {object} Map containing localized description text and tooltip
	 */
	CreateContainerVisualization.getDescription = function(mPayload, sLabel) {
		var oRtaResourceBundle = Lib.getResourceBundleFor("sap.ui.rta");
		var sElementLabel = mPayload.originalLabel || sLabel;
		var sShortenedElementLabel = ChangeVisualizationUtils.shortenString(sElementLabel);
		var sTextKey = "TXT_CHANGEVISUALIZATION_CHANGE_CREATECONTAINER";
		return {
			descriptionText: oRtaResourceBundle.getText(sTextKey, [sShortenedElementLabel]),
			descriptionTooltip: oRtaResourceBundle.getText(sTextKey, [sElementLabel])
		};
	};

	return CreateContainerVisualization;
});