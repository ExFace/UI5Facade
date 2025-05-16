/*!
 * OpenUI5
 * (c) Copyright 2025 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */

/**
 * Initialization Code and shared classes of library sap.ui.rta.
 */
sap.ui.define([
	"sap/ui/core/Lib",
	"sap/ui/core/library",
	"sap/m/library",
	"sap/ui/fl/library",
	"sap/ui/dt/library"
], function(Lib) {
	"use strict";

	/**
	 * SAPUI5 library with RTA controls.
	 *
	 * @namespace
	 * @alias sap.ui.rta
	 * @author SAP SE
	 * @version 1.136.0
	 * @since 1.50
	 * @private
	 */
	var thisLib = Lib.init({
		name: "sap.ui.rta",
		apiVersion: 2,
		version: "1.136.0",
		dependencies: ["sap.ui.core", "sap.m", "sap.ui.fl", "sap.ui.dt"],
		types: [],
		interfaces: [],
		controls: [],
		elements: []
	});

	thisLib.GENERATOR_NAME = "sap.ui.rta.command";

	return thisLib;
});