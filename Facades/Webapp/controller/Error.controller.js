sap.ui.define([
	"[#component_path#]/controller/BaseController"
], function (BaseController) {
	"use strict";

	return BaseController.extend("[#app_id#].controller.Error", {

		onInit: function () {
			var oRouter, oTarget;
			oRouter = this.getRouter();
			if (oRouter) {
				oTarget = oRouter.getTarget("error");
				oTarget.attachDisplay(function (oEvent) {
					this._oData = oEvent.getParameter("data"); //store the data
				}, this);
			}
		},
		// override the parent's navBack (inherited from BaseController)
		navBack : function (oEvent){
			// in some cases we could display a certain target when the back button is pressed
			if (this._oData && this._oData.fromTarget) {
				this.getRouter().getTargets().display(this._oData.fromTarget);
				delete this._oData.fromTarget;
				return;
			}
			// call the parent's navBack
			BaseController.prototype.navBack.apply(this, arguments);
		}

	});

});