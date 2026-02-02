/*!
 * OpenUI5
 * (c) Copyright 2025 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */

sap.ui.define([
], function(
) {
	"use strict";

	var ChangeStates = {};

	ChangeStates.ALL = "all";
	ChangeStates.DRAFT = "draft";
	ChangeStates.DIRTY = "dirty";

	/**
	 * Builds an array with the combined State of Draft and Dirty
	 *
	 * @returns {array} Array of change states.
	 */
	ChangeStates.getDraftAndDirtyStates = function() {
		return [this.DRAFT, this.DIRTY];
	};

	return ChangeStates;
});