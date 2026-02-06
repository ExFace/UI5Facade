/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */

// Provides the design-time metadata for the sap.ui.webc.main.Title control
sap.ui.define([],
	function () {
		"use strict";

		return {
			name: {
				singular: "TITLE_NAME",
				plural: "TITLE_NAME_PLURAL"
			},
			actions: {
				remove: {
					changeType: "hideControl"
				},
				rename: {
					changeType: "rename",
					domRef: function (oControl) {
						return oControl.getDomRef().shadowRoot.querySelector(".ui5-title-root");
					}
				},
				reveal: {
					changeType: "unhideControl"
				}
			}
		};
	});