sap.ui.jsview("[#app_id#].view.Offline", {

	getControllerName : function() {
		return "[#app_id#].controller.Offline";
	},

	createContent : function(oController) {
	    
	    return new sap.m.MessagePage({
			title: "{i18n>WEBAPP.ROUTING.OFFLINE.TITLE}",
			icon: "sap-icon://disconnected",
			text: "{i18n>WEBAPP.ROUTING.OFFLINE.TEXT}",
			description: "{i18n>WEBAPP.ROUTING.OFFLINE.DESCRIPTION}",
			showNavButton: true,
			navButtonPress: [oController.navBack, oController]
		});
	}
});