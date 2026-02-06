/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */

// Provides control sap.ui.commons.FileUploaderParameter.
sap.ui.define(['sap/ui/core/Element', './library'],
	function(Element, library) {
	"use strict";



	/**
	 * Constructor for a new FileUploaderParameter.
	 *
	 * @param {string} [sId] id for the new control, generated automatically if no id is given
	 * @param {object} [mSettings] initial settings for the new control
	 *
	 * @class
	 * Represents a parameter for the FileUploader which is rendered as a hidden inputfield.
	 * @extends sap.ui.core.Element
	 *
	 * @author SAP SE
	 * @version 1.144.0
	 *
	 * @constructor
	 * @public
	 * @deprecated Since version 1.21.0.
	 * Please use the control sap.ui.unified.FileUploaderParameter of the library sap.ui.unified instead.
	 * @alias sap.ui.commons.FileUploaderParameter
	 */
	var FileUploaderParameter = Element.extend("sap.ui.commons.FileUploaderParameter", /** @lends sap.ui.commons.FileUploaderParameter.prototype */ { metadata : {

		library : "sap.ui.commons",
		properties : {

			/**
			 * The name of the hidden inputfield.
			 * @since 1.12.2
			 */
			name : {type : "string", group : "Data", defaultValue : null},

			/**
			 * The value of the hidden inputfield.
			 * @since 1.12.2
			 */
			value : {type : "string", group : "Data", defaultValue : null}
		}
	}});



	return FileUploaderParameter;

});
