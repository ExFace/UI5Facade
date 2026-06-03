/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */

sap.ui.define([
	"sap/ui/fl/support/_internal/extractChangeDependencies"
], function(
	extractChangeDependencies
) {
	"use strict";

	/**
	 * Provides an object with the changes for the current application as well as
	 * further information. I.e. if the changes were applied and their dependencies.
	 * WARNING: No deep clone - Returns original object references to ensure that prototype methods
	 * stay intact. Do not mutate.
	 *
	 * @namespace sap.ui.fl.support._internal.getChangeDependencies
	 * @since 1.98
	 * @version 1.144.0
	 * @private
	 * @ui5-restricted sap.ui.fl.support.api.SupportAPI
	 */

	function getChangeDependencies(oCurrentAppContainerObject) {
		var oAppComponent = oCurrentAppContainerObject.oContainer.getComponentInstance();
		return extractChangeDependencies.extract(oAppComponent);
	}

	return function(oAppComponent) {
		return Promise.resolve(getChangeDependencies(oAppComponent));
	};
});
