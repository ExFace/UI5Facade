/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/rta/command/FlexCommand"], function(FlexCommand) {
	"use strict";

	/**
	 * Basic implementation for the command pattern.
	 *
	 * @class
	 * @extends sap.ui.rta.command.FlexCommand
	 * @author SAP SE
	 * @version 1.144.0
	 * @constructor
	 * @private
	 * @since 1.44
	 * @alias sap.ui.rta.command.Settings
	 */
	const Settings = FlexCommand.extend("sap.ui.rta.command.Settings", {
		metadata: {
			library: "sap.ui.rta",
			properties: {
				content: {
					type: "any"
				}
			},
			associations: {},
			events: {}
		}
	});

	/**
	 * @override
	 */
	Settings.prototype.execute = function(...aArgs) {
		if (this.getElement()) {
			return FlexCommand.prototype.execute.apply(this, aArgs);
		}
		return Promise.resolve();
	};

	/**
	 * @override
	 */
	Settings.prototype.undo = function(...aArgs) {
		if (this.getElement()) {
			return FlexCommand.prototype.undo.apply(this, aArgs);
		}
		return Promise.resolve();
	};

	return Settings;
});
