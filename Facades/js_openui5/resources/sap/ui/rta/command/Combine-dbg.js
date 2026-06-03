/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define([
	"sap/ui/rta/command/FlexCommand"
], function(
	FlexCommand
) {
	"use strict";

	/**
	 * Combine fields
	 *
	 * @class
	 * @extends sap.ui.rta.command.FlexCommand
	 * @author SAP SE
	 * @version 1.144.0
	 * @constructor
	 * @private
	 * @since 1.46
	 * @alias sap.ui.rta.command.Combine
	 */
	const Combine = FlexCommand.extend("sap.ui.rta.command.Combine", {
		metadata: {
			library: "sap.ui.rta",
			properties: {
				newElementId: {
					type: "string",
					group: "content"
				},
				source: {
					type: "any",
					group: "content"
				},
				combineElements: {
					type: "any[]",
					group: "content"
				}
			},
			associations: {},
			events: {}
		}
	});

	/**
	 * @override
	 */
	Combine.prototype._getChangeSpecificData = function() {
		const aFieldIds = [];
		this.getCombineElements().forEach(function(oField) {
			aFieldIds.push(oField.getId());
		});
		const mSpecificInfo = {
			changeType: this.getChangeType(),
			content: {
				newElementId: this.getNewElementId(),
				sourceControlId: this.getSource().getId(),
				combineElementIds: aFieldIds
			}
		};
		return mSpecificInfo;
	};

	return Combine;
});
