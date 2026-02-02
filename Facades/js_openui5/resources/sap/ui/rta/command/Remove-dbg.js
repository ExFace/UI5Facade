/*!
 * OpenUI5
 * (c) Copyright 2025 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/rta/command/FlexCommand"], function(FlexCommand) {
	"use strict";

	/**
	 * Remove a control/element
	 *
	 * @class
	 * @extends sap.ui.rta.command.FlexCommand
	 * @author SAP SE
	 * @version 1.136.0
	 * @constructor
	 * @private
	 * @since 1.42
	 * @alias sap.ui.rta.command.Remove
	 */
	var Remove = FlexCommand.extend("sap.ui.rta.command.Remove", {
		metadata: {
			library: "sap.ui.rta",
			properties: {
				removedElement: {
					type: "any",
					group: "content"
				}
			},
			associations: {},
			events: {}
		}
	});

	Remove.prototype._getChangeSpecificData = function() {
		var oElement = this.getRemovedElement() || this.getElement();

		var mSpecificInfo = {
			changeType: this.getChangeType(),
			removedElement: {
				id: oElement.getId()
			}
		};
		return mSpecificInfo;
	};

	return Remove;
});
