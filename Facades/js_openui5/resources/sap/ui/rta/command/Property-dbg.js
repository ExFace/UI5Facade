/*!
 * OpenUI5
 * (c) Copyright 2025 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define([
	"sap/ui/rta/command/FlexCommand"
], function(
	FlexCommand
) {
	"use strict";

	/**
	 * Basic implementation for the command pattern.
	 *
	 * @class
	 * @extends sap.ui.rta.command.FlexCommand
	 * @author SAP SE
	 * @version 1.136.0
	 * @constructor
	 * @private
	 * @since 1.34
	 * @alias sap.ui.rta.command.Property
	 */
	var Property = FlexCommand.extend("sap.ui.rta.command.Property", {
		metadata: {
			library: "sap.ui.rta",
			properties: {
				propertyName: {
					type: "string",
					group: "content"
				},
				newValue: {
					type: "any",
					group: "content"
				},
				semanticMeaning: {
					type: "string",
					group: "content"
				},
				changeType: {
					type: "string",
					defaultValue: "propertyChange"
				}
			},
			associations: {},
			events: {}
		}
	});

	Property.prototype._getChangeSpecificData = function() {
		var oElement = this.getElement();
		// general format
		return {
			changeType: this.getChangeType(),
			selector: {
				id: oElement.getId(),
				type: oElement.getMetadata().getName()
			},
			content: {
				property: this.getPropertyName(),
				newValue: this.getNewValue(),
				semantic: this.getSemanticMeaning()
			}
		};
	};

	return Property;
});
