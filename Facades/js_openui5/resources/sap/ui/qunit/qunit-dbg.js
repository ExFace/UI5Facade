/**
 * @fileoverview
 * @deprecated As of version 1.6, use sap/ui/thirdparty/qunit.js or sap/ui/thirdparty/qunit-2.js instead
 */
(function(jQuery){

	var MESSAGE = "The file qunit.js has been moved from sap.ui.qunit to sap.ui.thirdparty! Please update the dependencies accordingly.";

	if (jQuery && jQuery.sap && jQuery.sap.require){
		jQuery.sap.require("sap.ui.thirdparty.qunit");
		jQuery.sap.log.warning(MESSAGE);
	} else {
		throw new Error(MESSAGE);
	}

})(window.jQuery);