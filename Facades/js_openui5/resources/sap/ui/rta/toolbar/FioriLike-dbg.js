/*!
 * OpenUI5
 * (c) Copyright 2025 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */

sap.ui.define([
	"./Adaptation",
	"sap/ui/rta/toolbar/AdaptationRenderer"
],
function(
	Adaptation,
	AdaptationRenderer
) {
	"use strict";

	/**
	 * Constructor for a new sap.ui.rta.toolbar.FioriLike control
	 *
	 * @class
	 * Contains implementation of Fiori specific toolbar
	 * @extends sap.ui.rta.toolbar.Adaptation
	 *
	 * @author SAP SE
	 * @version 1.136.0
	 *
	 * @constructor
	 * @private
	 * @since 1.84
	 * @alias sap.ui.rta.toolbar.FioriLike
	 */
	var FioriLike = Adaptation.extend("sap.ui.rta.toolbar.FioriLike", {
		metadata: {
			library: "sap.ui.rta"
		},
		renderer: AdaptationRenderer,
		type: "fiori"
	});

	return FioriLike;
});