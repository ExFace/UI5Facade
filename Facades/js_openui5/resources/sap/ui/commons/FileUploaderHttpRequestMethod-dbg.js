/*!
 * OpenUI5
 * (c) Copyright 2026 SAP SE or an SAP affiliate company.
 * Licensed under the Apache License, Version 2.0 - see LICENSE.txt.
 */

// Provides type sap.ui.commons.FileUploaderHttpRequestMethod
sap.ui.define(["sap/ui/base/DataType"], function(DataType) {
	"use strict";

	/**
	 * Types of HTTP request methods.
	 *
	 * @enum {string}
	 * @alias sap.ui.commons.FileUploaderHttpRequestMethod
	 * @public
     * @deprecated Since version 1.144.0.
	 * Please use the control sap.ui.unified.FileUploaderHttpRequestMethod of the library sap.ui.unified instead.
	 * @since 1.144.0
	 */
	var FileUploaderHttpRequestMethod = {

		/**
		 * HTTP request POST method.
		 * @public
		 */
		Post : "POST",

		/**
		 * HTTP request PUT method.
		 * @public
		 */
		Put : "PUT"

	};

	DataType.registerEnum("sap.ui.commons.FileUploaderHttpRequestMethod", FileUploaderHttpRequestMethod);

	return FileUploaderHttpRequestMethod;

});