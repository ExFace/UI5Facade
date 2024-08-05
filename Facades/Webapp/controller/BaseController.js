sap.ui.define([
	"sap/ui/core/mvc/Controller",
	"sap/ui/core/routing/History"
], function (Controller, History) {
	"use strict";
	return Controller.extend("[#app_id#].controller.BaseController", {
		
		onInit : function() {
			[#onInit#]
			
			/* TODO Move check for unsaved changes here (see onNavBack)
			this.getNavContainer().attachNavigate(function(oEvent){
				console.log('navigate', oEvent.getSource(), oEvent.getParameters());
			});
			*/
		},
		
		getRouter : function () {
			return sap.ui.core.UIComponent.getRouterFor(this);
		},
		
		getViewId : function(sViewName) {
			return this.getOwnerComponent().createId(sViewName);
		},
		
		getViewName : function(sPageAlias, sWidgetId) {
			return sPageAlias + (sWidgetId ? '.'+sWidgetId : '');
		},
		
		getNavContainer : function() {
			return sap.ui.getCore().byId('[#app_id#].app');
		},
		
		/**
		 * Navigates to the view matching the given page and widget.
		 * 
		 * Returns the the jQuery XHR object used to load the view or nothing if no request
		 * to the server was made (view loaded from cache).
		 * 
		 * @param String sPageAlias
		 * @param String sWigetId
		 * @param Object oXHRSettings
		 * 
		 * @return jqXHR|undefined
		 */
		navTo : function(sPageAlias, sWidgetId, oXHRSettings, bReplaceHash) {
			var oRouter = this.getRouter();	
			var sViewName = this.getViewName(sPageAlias, sWidgetId);
			var sRouteParams = this._encodeRouteParams(oXHRSettings && oXHRSettings.data ? oXHRSettings.data : {});
			
			// Register page in router
			this._addRoute(oRouter, sViewName);
			
			// TODO this produces the following error: Modules that use an anonymous define() 
			// call must be loaded with a require() call; they must not be executed via script 
			// tag or nested into other modules. All other usages will fail in future releases 
			// or when standard AMD loaders are used or when ui5loader runs in async mode. 
			// Now using substitute name ~anonymous~1.js -  sap.ui.ModuleSystem
			// Obviously, we need to wrap ap.ui.jsview(...) in the view definition file in
			// something - but what???
			return this._loadView(sViewName, function() {
				oRouter.navTo(sViewName, {params: sRouteParams}, bReplaceHash)
			}, oXHRSettings);
		},
		
		/**
		 * Loads a view using async jQuery.ajax() instead of the sync-only ui5Loader.
		 * 
		 * The view is only loaded via AJAX if not found in the UI5 cache or if bForceLoad 
		 * is explicitly set to true. 
		 * 
		 * However, the callback function provided here will be executed once the view is
		 * available regardless of where it was loaded from.
		 * 
		 * Returns the the jQuery XHR object used to load the view or nothing if no request
		 * to the server was made (view loaded from cache).
		 * 
		 * @param {String} sViewName
		 * @param {callable} fnCallback
		 * @param {Object} oXHRSettings
		 * @param {boolean} bForceLoad
		 * 
		 * @return jqXHR|undefined
		 */
		_loadView : function(sViewName, fnCallback, oXHRSettings, bForceLoad) {
			var sViewId = this.getViewId(sViewName);
			var oController = this;		
			var bUseCombinedViewControllers	= oController.getOwnerComponent().getManifest()['exface']['useCombinedViewControllers']
			var bViewPreloaded = this.isPreloaded(this._getModulePath(sViewName, 'view'));
			bForceLoad = bForceLoad === undefined ? false : bForceLoad;
			// Load view and controller with a custom async AJAX if running on UI server. 
			// Reasons:
			// 1) By default, views and controllers are loaded with sync requests (not compatible with CacheAPI)
			// 2) Loading a single viewcontroller is faster, than the view and the controller separately
			if (bForceLoad || (bUseCombinedViewControllers === true && ! bViewPreloaded && ! sap.ui.getCore().byId(sViewId))) {
				if (oXHRSettings) {
					var oCallbacks = {
						success: oXHRSettings.success,
						error: oXHRSettings.error
					}
					delete oXHRSettings.success;
					delete oXHRSettings.error;
				}
				
				var oDefSettings = {
					url: this._getUrlFromRoute(sViewName, 'viewcontroller'),
					dataType: "script",
					cache: true,
					success: function(script, textStatus) {
						
						if (oCallbacks && oCallbacks.success) {
							oCallbacks.success();
						}
						
						fnCallback();
					},
					error: function(jqXHR, textStatus, errorThrown) {
						console.warn("Failed loading combined viewcontroller for " + sViewName + ": " + errorThrown + " (" + textStatus + ")");
						if (oCallbacks && oCallbacks.error) {
							oCallbacks.error();
						}
						
						oController.getOwnerComponent().getManifestEntry("/exface/useCombinedViewControllers");
						
						if (!exfLauncher.isOnline()) {
							oController.getRouter().getTargets().display("offline");
						} else {
							oController.getOwnerComponent().showAjaxErrorDialog(jqXHR);
						}
					}
				}
				
				var params = $.extend({}, oDefSettings, oXHRSettings);
				delete params.data;
				
				return $.ajax(params);
			} else {
				if(! bViewPreloaded && ! sap.ui.getCore().byId(sViewId)) {
					if (!exfLauncher.isOnline()) {
						oController.getRouter().getTargets().display("offline");
					} 
				}
				if (oXHRSettings) {
					if (oXHRSettings.success) {
						oXHRSettings.success();
					}
					if (oXHRSettings.complete) {
						oXHRSettings.complete();
					}
				}
				fnCallback();
			}
		},
		
		/**
		 * Adds a target and a corrseponding route to the given router.
		 * 
		 * @private
		 * @param sap.ui.core.routing.Router oRouter
		 * @param String sPattern
		 */
		_addRoute: function(oRouter, sName) {
			var aTargets = oRouter.getTargets();
			
			if (aTargets.getTarget(sName) === undefined) {
				jQuery.sap.log.info('Adding target ' + sName);
				aTargets.addTarget(sName, {
					"viewId": sName,
					"viewName": sName
				});
			}
			
			if (oRouter.getRoute(sName) === undefined) {
				jQuery.sap.log.info('Adding route ' + sName);
				oRouter.addRoute({
					"pattern": sName + "/:params:",
					"name": sName,
					"target": sName
				});
			}
		},
		
		/**
		 * Computes an API URL to a resource for the given page.
		 * 
		 * @private
		 * 
		 * @param String sViewName
		 * @param String sType (view|controller|viewcontroller)
		 */
		_getUrlFromRoute: function(sViewName, sType) {
			return this._getResourceRoot() + '/' + sType + '/' + sViewName.replace(/\./g, '/') + '.' + sType + '.js';
		},
		
		_getModulePath: function(sName, sType) {
			return ('[#app_id#]/' + sType + '/' + sName).replace(/\./g, '/') + '.' + sType;
		},
		
		/**
		 * @private
		 */
		_getResourceRoot: function() {
			return '[#assets_path#]';
		},
		
		/**
		 * Function to be called by all sorts of back buttons and buttons closing a dialog
		 * 
		 */
		navBack : function (oEvent) {
			var oController = this;
			var oHistory = History.getInstance();
			var sPreviousHash = oHistory.getPreviousHash();
			if (sPreviousHash !== undefined) {
				window.history.go(-1);
			} else {
				oController.getRouter().navTo("[#app_id#]", {}, true /*no history*/);
			}
		},
		
		showWarningAboutUnsavedChanges : function(fnDiscard) {
			var oDialog = new sap.m.Dialog({
				type: sap.m.DialogType.Message,
				title: "{i18n>MESSAGE.DISCARD_CHANGES.TITLE}",
				content: new sap.m.Text({ text: "{i18n>MESSAGE.DISCARD_CHANGES.TEXT}" }),
				beginButton: new sap.m.Button({
					type: sap.m.ButtonType.Emphasized,
					text: "{i18n>MESSAGE.DISCARD_CHANGES.DISCARD}",
					press: function () {
						oDialog.close().destroy();
						fnDiscard()
					}.bind(this)
				}),
				endButton: new sap.m.Button({
					text: "{i18n>COMMON.CANCEL}",
					press: function () {
						oDialog.close().destroy();
					}.bind(this)
				})
			})
			.setModel(this.getNavContainer().getModel('i18n'), 'i18n');
			oDialog.open();
		},
		
		/**
		 * Produces a string to be used as route parameter from a given object.
		 * @param Object oParams
		 * @return String
		 */
		_encodeRouteParams : function(oParams) {
			// Need URI encoding to prevent crossroads invalid value error
			return encodeURIComponent(JSON.stringify(oParams));
		},
		
		/**
		 * @param String sParams
		 * @return Object
		 */
		_decodeRouteParams : function(sParams) {
			return JSON.parse(decodeURIComponent(sParams));
		},
		
		isPreloaded: function(sModuleName, sExtension) {
			sExtension = sExtension || '.js';
			return sap.ui.loader._.getModuleState(sModuleName + sExtension) !== 0;
		}
	});
});

