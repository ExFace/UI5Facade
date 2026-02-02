/*!
 * OpenUI5
 * (c) Copyright 2025 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */
sap.ui.define(["sap/ui/rta/command/FlexCommand"], function(FlexCommand) {
	"use strict";

	/**
	 * Reveal controls by setting visible to true or unstash them
	 *
	 * @class
	 * @extends sap.ui.rta.command.FlexCommand
	 * @author SAP SE
	 * @version 1.136.0
	 * @constructor
	 * @private
	 * @since 1.44
	 * @alias sap.ui.rta.command.Reveal
	 */
	var Reveal = FlexCommand.extend("sap.ui.rta.command.Reveal", {
		metadata: {
			library: "sap.ui.rta",
			properties: {
				revealedElementId: {
					type: "string",
					group: "content"
				},
				directParent: "object"
			}
		}
	});

	Reveal.prototype._getChangeSpecificData = function() {
		var mSpecificChangeInfo = {
			changeType: this.getChangeType(),
			content: {}
		};
		if (this.getRevealedElementId()) {
			mSpecificChangeInfo.content.revealedElementId = this.getRevealedElementId();
		}
		return mSpecificChangeInfo;
	};

	return Reveal;
});
