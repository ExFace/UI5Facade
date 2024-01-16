sap.ui.define([
	"[#component_path#]/controller/BaseController",
	"sap/ui/core/routing/HashChanger"
], function (BaseController, HashChanger) {
	"use strict";

	return BaseController.extend("[#app_id#].controller.NotFound", {

		onInit: function () {
			var oRouter, oTarget;
			oRouter = this.getRouter();
			oTarget = oRouter.getTarget("notFound");
			oTarget.attachDisplay(function (oEvent) {				
				if (this.getOwnerComponent().getManifest()['exface']['useCombinedViewControllers'] === true) {
					this._loadViewFromHash();
				}
				this._oData = oEvent.getParameter("data"); //store the data
			}, this);
		},
		
		/**
		 * override the parent's navBack (inherited from BaseController)
		 * @param oEvent
		 */
		navBack : function (oEvent){
			var oHashChanger = HashChanger.getInstance();
			var sHash = oHashChanger.getHash();console.log(this._oData, sHash);
			// in some cases we could display a certain target when the back button is pressed
			if (this._oData && this._oData.fromTarget) {
				this.getRouter().getTargets().display(this._oData.fromTarget);
				delete this._oData.fromTarget;
				return;
			} else if (this._oData && this._oData.fromHash) {
				this._loadViewFromHash(this._oData.fromHash);
				delete this._oData.fromHash;
				return;
			} else if (sHash === "") {
				this.getRouter().getTargets().display('[#app_id#]');
			}
			// call the parent's navBack
			BaseController.prototype.navBack.apply(this, arguments);
		},
		
		/**
		 * Attempts to load the view/controller from server by parsing the current
		 * hash.
		 * 
		 * @return jqXHR
		 */
		_loadViewFromHash: function(sHash) {
			sHash = sHash || HashChanger.getInstance().getHash();
			var aHashParts = sHash.split('/', 3);
			var sPageAlias, sWidgetId, sRouteParams, sViewName, oViewXHR;
			var oXHRSettings = {data: {}};
			var oController = this;
			var oXHR;
			
			if (aHashParts[0]) {
				sPageAlias = decodeURIComponent(aHashParts[0]);
			}
			if (aHashParts[1]) {
				sWidgetId = decodeURIComponent(aHashParts[1]);
				if (sWidgetId.substring(0,1) === '{') {
					try {
						oXHRSettings.data = JSON.parse(sWidgetId);
						sWidgetId = undefined;
					} catch (e) {
						
					}
				}
			}
			if (aHashParts[2]) {
				sRouteParams = decodeURIComponent(aHashParts[2]);
				try {
					oXHRSettings.data = JSON.parse(sRouteParams);
				} catch (e) {
						
				}
			}
			
			oXHRSettings.data['_notFoundRecovered']++;
			oController.getView().setBusyIndicatorDelay(0).setBusy(true);
			console.log('goTo', sPageAlias, sWidgetId);
			oXHR = oController.navTo(sPageAlias, sWidgetId, oXHRSettings, true);
			if (oXHR) {
				oXHR.fail(function() {
					oController.getView().setBusy(false).getContent()[0].setVisible(true);
				});
			} else {
				oController.getView().setBusy(false).getContent()[0].setVisible(true);
			}
			return oXHR;
		}
	});
});

