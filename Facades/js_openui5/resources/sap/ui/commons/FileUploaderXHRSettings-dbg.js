/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */

// Provides element sap.ui.commons.FileUploaderXHRSettings.
sap.ui.define(['sap/ui/core/Element', './library'],
	function (Element) {
		"use strict";

		/**
		 * Constructor for a new FileUploaderXHRSettings.
		 *
		 * @param {string} [sId] id for the new control, generated automatically if no id is given
		 * @param {object} [mSettings] initial settings for the new control
		 *
		 * @class
		 * Properties for the <code>XMLHttpRequest</code> object used for file uploads.
		 * @extends sap.ui.core.Element
		 *
		 * @author SAP SE
		 * @version 1.144.0
		 *
		 * @constructor
		 * @since 1.144
		 * @public
         * @deprecated Since version 1.144.0.
		 * Please use the control sap.ui.unified.FileUploaderXHRSettings of the library sap.ui.unified instead.
		 * @alias sap.ui.commons.FileUploaderXHRSettings
		 */
		var FileUploaderXHRSettings = Element.extend("sap.ui.commons.FileUploaderXHRSettings", /** @lends sap.ui.commons.FileUploaderXHRSettings.prototype */ {
			metadata: {
				library: "sap.ui.commons",
				properties: {

					/**
					 * Determines the value of the <code>XMLHttpRequest.withCredentials</code> property
					 * @since 1.52
					 */
					withCredentials: {type: "boolean", group: "Data", defaultValue: false}
				}
			}
		});


		return FileUploaderXHRSettings;

	});