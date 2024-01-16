sap.ui.jsview("[#error_view_name#]", {

	getControllerName : function() {
		return "[#app_id#].controller.Error";
	},

	createContent : function(oController) {
	    
	    return new sap.m.MessagePage({
			title: [#error_title#],
			icon: "sap-icon://error",
			text: [#error_text#],
			description: [#error_description#],
			showNavButton: true,
			navButtonPress: [oController.navBack, oController]
		});
	}
});