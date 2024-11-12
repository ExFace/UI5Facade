sap.ui.define([
	"[#component_path#]/controller/NotFound.controller",
	"sap/ui/core/routing/HashChanger"
], function (NotFoundController, HashChanger, History) {
	"use strict";

	return NotFoundController.extend("[#app_id#].controller.Offline", {
		onInit: function () {
			var oRouter, oTarget;
			oRouter = this.getRouter();
			oTarget = oRouter.getTarget("offline");
			oTarget.attachDisplay(function (oEvent) {
				var oHashChanger = HashChanger.getInstance();
				this._oData = oEvent.getParameter("data"); // store the data
				// If the event has not data, make sure to at least the fromHash is filled
				// with the hash of the view, that initiated the navigation to the offline
				// view
				if (this._oData === undefined) {
					this._oData = {
						fromHash: oHashChanger.getHash()
					};
				}
			}, this);
		}
	});

});

