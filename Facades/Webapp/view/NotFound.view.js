sap.ui.jsview("[#app_id#].view.NotFound", {

	getControllerName : function() {
		return "[#app_id#].controller.NotFound";
	},

	createContent : function(oController) {
	    return new sap.m.MessagePage({
			title: "{i18n>WEBAPP.ROUTING.NOTFOUND.TITLE}",
			text: "{i18n>WEBAPP.ROUTING.NOTFOUND.TEXT}",
			description: "{i18n>WEBAPP.ROUTING.NOTFOUND.DESCRIPTION}",
			showNavButton: true,
			navButtonPress: [oController.navBack, oController],
			visible: (oController.getOwnerComponent().getManifest()['exface']['useCombinedViewControllers'] !== true)
		});
	}
});