// Store the original console.error function
const originalConsoleError = console.error;

// Array to store captured errors
window.capturedErrors = [];
let capturedErrors = [];

// Regex patterns for errors to ignore
const ignoredErrorPatterns = [
	// /Assertion failed: could not find any translatable text for key/i,
	// /The target you tried to get .* does not exist!/i,
	// /EventProvider sap\.m\.routing\.Targets/i,
	// /Modules that use an anonymous define\(\) call must be loaded with a require\(\) call.*/i
];


/**
 * Network State Change Event Handler
 * 
 * This event handler manages application-wide responses to network state changes.
 * It updates UI components, handles offline queue synchronization, and manages
 * network-dependent features.
 * 
 * @param {jQuery.Event} oEvent The jQuery event object
 * @param {Object} oData The event data containing current network state
 */

$(window).on('networkchanged', function (oEvent, oData) {
	try {
		//oData value was coming undefined, thats why i modified below codes
		// Safety check for oData
		if (!oData) {
			console.warn('Network state change event received without data');
			return;
		}

		// Get current network state  
		const oNetState = oData.currentState;
		if (!oNetState) {
			console.warn('Network state change event received without current state');
			return;
		}

		// Safety check for changes object
		const oChanges = oData.changes;
		const i18nModel = exfLauncher.contextBar.getComponent().getModel('i18n');

		// Safely check forcedOffline changes
		if (oChanges && oChanges.forcedOffline !== undefined) {
			const messageKey = oChanges.forcedOffline ?
				"WEBAPP.SHELL.PWA.FORCE_OFFLINE_ON" :
				"WEBAPP.SHELL.PWA.FORCE_OFFLINE_OFF";
			try {
				exfLauncher.showMessageToast(i18nModel.getProperty(messageKey));
			} catch (toastError) {
				console.warn('Failed to show message toast:', toastError);
			}
		}

		// Safely check auto offline changes
		if (oChanges && oChanges.autoOffline !== undefined) {
			const messageKey = oChanges.autoOffline ?
				"WEBAPP.SHELL.PWA.AUTOMATIC_OFFLINE_ON" :
				"WEBAPP.SHELL.PWA.AUTOMATIC_OFFLINE_OFF";
			try {
				exfLauncher.showMessageToast(i18nModel.getProperty(messageKey));
			} catch (toastError) {
				console.warn('Failed to show message toast:', toastError);
			}
		}

		// Safely check auto offline and slow network changes 
		if (oChanges && oChanges.autoOffline !== undefined && oData.currentState._bSlowNetwork) {
			const messageKey = oChanges.autoOffline ?
				"WEBAPP.SHELL.PWA.AUTOMATIC_OFFLINE_SLOW_INTERNET" :
				"WEBAPP.SHELL.PWA.AUTOMATIC_OFFLINE_STABLE_INTERNET";
			try {
				exfLauncher.showMessageToast(i18nModel.getProperty(messageKey));
			} catch (toastError) {
				console.warn('Failed to show message toast:', toastError);
			}
		}

		// Handle browser online/offline state changes
		if (oChanges && oChanges.browserOnline !== undefined) {
			const messageKey = oChanges.browserOnline ?
				"WEBAPP.SHELL.NETWORK.ONLINE" :
				"WEBAPP.SHELL.NETWORK.OFFLINE";
			try {
				exfLauncher.showMessageToast(i18nModel.getProperty(messageKey));
			} catch (toastError) {
				console.warn('Failed to show browser online/offline toast:', toastError);
			}
		}

		// //https://home.unicode.org/
		// console.debug(
		// 	"%c 😀⚠️ Failed to cleanup network stats:",
		// 	"color: white; background-color: red; padding: 4px; border-radius: 4px;",
		// 	"Some error Text ...."
		// );

		// console.log(
		// 	"%c ❌ Failed to save or cleanup network stats:",
		// 	"color: white; background-color: orange; padding: 4px; border-radius: 4px;",
		// 	oError
		// );

		// // Log state change with safety checks
		// console.debug("%c ⚠️ Network State Changed:",
		// 	"color: white; background-color: red; padding: 4px; border-radius: 4px;",
		// 	{
		// 		timestamp: new Date().toISOString(),
		// 		state: oNetState.toString ? oNetState.toString() : 'Unknown State',
		// 		isOnline: typeof oNetState.isOnline === 'function' ? oNetState.isOnline() : 'Unknown',
		// 		isOfflineVirtually: typeof oNetState.isOfflineVirtually === 'function' ?
		// 			oNetState.isOfflineVirtually() : 'Unknown'
		// 	});

		// Log state change with safety checks
		console.debug('Network State Changed:', {
			timestamp: new Date().toISOString(),
			state: oNetState.toString ? oNetState.toString() : 'Unknown State',
			flags: oNetState.serialize()
		});


		// Update UI components with new state 
		try {
			exfLauncher.updateNetworkModel(oNetState);
		} catch (modelError) {
			console.error('Failed to update network model:', modelError);
		}

		// Handle online-specific actions
		if (oNetState.isOnline && oNetState.isOnline()) {
			// Update error counters
			const pwa = exfLauncher.contextBar.getComponent().getPWA();
			if (pwa && typeof pwa.updateQueueCount === 'function') {
				pwa.updateQueueCount()
					.then(() => {
						_oContextBar.load();
					})
					.catch(error => {
						console.error('Failed to update queue or error counts:', error);
					});
			} else {
				_oContextBar.load();
			}

			// Sync offline items if no ServiceWorker available
			if (!navigator.serviceWorker) {
				syncOfflineItems();
			}
		}
	} catch (error) {
		console.error('Network State Change Handler Error:', {
			message: error.message,
			stack: error.stack,
			timestamp: new Date().toISOString()
		});
	}
});

function syncOfflineItems() {
	if (exfPWA.network.getState().isOfflineVirtually()) return;

	exfPWA.actionQueue.getIds('offline')
		.then(function (ids) {
			var count = ids.length;
			if (count > 0) {
				var shell = exfLauncher.getShell();
				shell.setBusy(true);
				exfPWA.actionQueue.syncIds(ids)
					.then(function () {
						exfLauncher.contextBar.getComponent().getPWA().updateQueueCount();
						exfLauncher.contextBar.getComponent().getPWA().updateErrorCount();
						return;
					})
					.then(function () {
						shell.setBusy(false);
						var text = exfLauncher.contextBar.getComponent().getModel('i18n').getProperty("WEBAPP.SHELL.NETWORK.SYNC_ACTIONS_COMPLETE");
						exfLauncher.showMessageToast(text);
						return;
					})
			}
		})
		.catch(function (error) {
			shell.setBusy(false);
			exfLauncher.showMessageToast("Cannot synchronize offline actions: " + error);
		})
};

//this definition is using by network speed graph
const SPEED_HISTORY_ARRAY_LENGTH = 10 * 60; // seconds for 10 minutes 

const exfLauncher = {};
(function () {

	/**
	 * Save network stats on every AJAX request in order to use these stats
	 * to determin slow network.
	 * 
	 * @return void
	 */
	var registerAjaxSpeedLogging = function () {
		var originalAjax = $.ajax;
		$.ajax = function (options) {
			var startTime = new Date().getTime();
			// Calculate the request headers length
			let requestHeadersLength = 0;
			if (options.headers) {
				for (let header in options.headers) {
					if (options.headers.hasOwnProperty(header)) {
						requestHeadersLength += new Blob([header + ": " + options.headers[header] + "\r\n"]).size * 8;
					}
				}
			}

			// Calculate the request content length (if any)
			let requestContentLength = 0;
			if (options.data) {
				requestContentLength = new Blob([JSON.stringify(options.data)]).size * 8;
			}

			var newOptions = $.extend({}, options, {
				success: function (data, textStatus, jqXHR) {
					// Record the response end time
					let endTime = new Date().getTime();

					// Check if the response is from cache; skip measurement if true
					if (jqXHR.getResponseHeader('X-Cache') === 'HIT') {
						return; // Cancel measurement
					}

					// Retrieve the 'Server-Timing' header
					let serverTimingHeader = jqXHR.getResponseHeader('Server-Timing');
					let serverTimingValue = 0;

					// Extract the 'dur' value from the Server-Timing header
					if (serverTimingHeader) {
						let durMatch = serverTimingHeader.match(/dur=([\d\.]+)/);
						if (durMatch) {
							serverTimingValue = parseFloat(durMatch[1]);
						}
					}

					// Calculate the duration, adjusting for server processing time
					let duration = (endTime - startTime - serverTimingValue) / 1000; // Convert to seconds

					// Retrieve the Content-Length (size) of the response
					let responseContentLength = parseInt(jqXHR.getResponseHeader('Content-Length')) || 0;

					// Calculate the length of response headers
					let responseHeaders = jqXHR.getAllResponseHeaders(); // Retrieves all response headers as a string
					let responseHeadersLength = new Blob([responseHeaders]).size * 8; // Calculate in bits

					// Calculate the total data size (request headers + request body + response headers + response body) in bits
					let totalDataSize = (requestHeadersLength + requestContentLength + responseHeadersLength + responseContentLength * 8);

					// Calculate internet speed in Mbps
					let speedMbps = totalDataSize / (duration * 1000000);

					// Retrieve the Content-Type from the headers or from the contentType property
					let requestMimeType = options.contentType || (options.headers && options.headers['Content-Type']) || 'application/x-www-form-urlencoded; charset=UTF-8';

					// check exfPWA library is exists
					if (typeof exfPWA !== 'undefined') {
						exfPWA.network.saveStat(
							new Date(endTime),
							speedMbps,
							requestMimeType,
							totalDataSize
						).then(() => {
							//This delete code moved to exfPWA : The current cleanup in openui5 depends on the browser tab being open, which is unreliable. 
							// Moving this process to exfPWA ensures consistent background cleanup independent of the UI state.
							console.log("Network Stat Saved");
						});
					} else {
						console.error("exfPWA is not defined");
					}


					if (options.success) {
						options.success.apply(this, arguments);
					}
				},
				complete: function (jqXHR, textStatus) {
					if (options.complete) {
						options.complete.apply(this, arguments);
					}
				}
			});

			// // Function to delete old network stats
			// function deleteOldNetworkStats() {
			// 	if (typeof exfPWA !== 'undefined') {
			// 		var tenMinutesAgo = new Date(Date.now() - 10 * 60 * 1000);
			// 		exfPWA.network.deleteStatsBefore(tenMinutesAgo)
			// 			.then(function () {

			// 			})
			// 			.catch(function (error) {
			// 				console.error("Error deleting old network stats:", error);
			// 			});
			// 	}
			// }

			return originalAjax.call(this, newOptions);
		};
	}

	exfPWA.actionQueue.setTopics(['offline', 'ui5']);

	// Initialize network state management and event listeners 
	// This is required because browser's online/offline events weren't being captured properly
	// Without this initialization, the application couldn't detect network status changes
	exfPWA.network.init();
	registerAjaxSpeedLogging();



	var _oShell = {};
	var _oLauncher = this;
	var _bBusy = false;
	var _oSpeedStatusDialogInterval

	const _speedHistory = new Array(SPEED_HISTORY_ARRAY_LENGTH).fill(null);
	var _oConfig = {
		contextBar: {
			refreshWaitSeconds: 5,
			autoloadIntervalSeconds: 30
		},
		network: {
			slowNetworkPollIntervalSeconds: 30,
			fastNetworkPollIntervalSeconds: 30,
		}
	};

	// Reload context bar every X seconds
	setInterval(function () {
		exfLauncher.contextBar.load();
	}, _oConfig.contextBar.autoloadIntervalSeconds * 1000);

	/**
	 * 
	 * @returns {boolean}
	 */
	this.isOfflineVirtually = function () {
		return exfPWA.network.getState().isOfflineVirtually();
	};

	/**
	 * 
	 * @returns {boolean}
	 */
	this.isOnline = function () {
		return exfPWA.network.getState().isOnline();
	};

	this.getShell = function () {
		return _oShell;
	};

	this.initShell = function () {

		// Save global busy indicator state to be able to determine when the app
		// is busy - e.g. for UI testing.
		sap.ui.core.BusyIndicator.attachOpen(function (Event) {
			_bBusy = true;
		});
		sap.ui.core.BusyIndicator.attachClose(function (Event) {
			_bBusy = false;
		});

		_oShell = new sap.ui.unified.Shell({
			header: [
				new sap.m.OverflowToolbar({
					design: "Transparent",
					content: [
						new sap.m.Button({
							icon: "sap-icon://menu2",
							layoutData: new sap.m.OverflowToolbarLayoutData({ priority: "NeverOverflow" }),
							press: function () {
								_oShell.setShowPane(!_oShell.getShowPane());
							}
						}),
						new sap.m.OverflowToolbarButton("exf-home", {
							text: "{i18n>WEBAPP.SHELL.HOME.TITLE}",
							icon: "sap-icon://home",
							press: function (oEvent) {
								var oBtn = oEvent.getSource();
								sap.ui.core.BusyIndicator.show(0);
								window.location.href = oBtn.getModel().getProperty('/_app/home_url');
							}
						}),
						new sap.m.ToolbarSpacer(),
						new sap.m.Button("exf-pagetitle", {
							text: "{/_app/home_title}",
							//icon: "sap-icon://navigation-down-arrow",
							iconFirst: false,
							layoutData: new sap.m.OverflowToolbarLayoutData({ priority: "NeverOverflow" }),
							press: function (oEvent) {
								var oBtn = oEvent.getSource();
								sap.ui.core.BusyIndicator.show(0);
								window.location.href = oBtn.getModel().getProperty('/_app/app_url');
								/*
								if (_oAppMenu !== undefined) {
									var oButton = oEvent.getSource();
									var eDock = sap.ui.core.Popup.Dock;
									_oAppMenu.open(this._bKeyboard, oButton, eDock.BeginTop, eDock.BeginBottom, oButton);
								}*/
							}
						}),
						new sap.m.ToolbarSpacer(),
						new sap.m.Button("exf-network-indicator", {
							icon: "{= ${/_network/online} > 0 ? 'sap-icon://connected' : 'sap-icon://disconnected'}",
							text: "{/_network/queueCnt} / {/_network/syncErrorCnt}",
							layoutData: new sap.m.OverflowToolbarLayoutData({ priority: "NeverOverflow" }),
							press: _oLauncher.showOfflineMenu
						}),
					]
				})
			],
			content: [

			]
		})
		.setModel(new sap.ui.model.json.JSONModel({
			_network: {
				online: true,
				queueCnt: 0,
				syncErrorCnt: 0,
				deviceId: exfPWA.getDeviceId(),
				state: {}
			}
		}, true));

		// Initialize the network model by reading the current network state from the
		// PWA. This will put all the toggles in their correct positions.
		// Now, every time the user changes things like the auto-offline-toggle, we will get a change
		// in the model here. Just propagate this change to exfPWA. It will decide if this really
		// is a network change or not.
		exfLauncher.updateNetworkModel(exfPWA.network.getState(), _oShell.getModel());

		/**
		 * Network state change handler
		 * Manages partial updates to network state based on UI model changes
		 * 
		 * This handler:
		 * 1. Listens for changes to network state properties in the UI model
		 * 2. Creates targeted update objects containing only changed properties (Partial)
		 * 3. Updates network state through exfPWA state management system
		 * 4. Triggers networkchanged event for UI updates
		 * 
		 * We pass the model change to exfPWA
		 * exfPWA handles the rest - triggering the networkchanged event if there's a real change
		 * 
		 * @param {sap.ui.base.Event} oEvent The property change event
		 */
		_oShell.getModel().attachPropertyChange(function (oEvent) {
			// Extract changed property details from event
			var oParams = oEvent.getParameters();
			var sPath = oParams.path; // Property path that changed
			var bValue = oParams.value; // New value

			// Initialize partial update object
			var oUpdateObj = {};

			// Map changed property to corresponding state update
			// Only the changed property will be included in update object
			if (sPath.endsWith('forcedOffline')) {
				oUpdateObj.forcedOffline = bValue; // Manual offline toggle
			} else if (sPath.endsWith('autoOffline')) {
				oUpdateObj.autoOffline = bValue; // Automatic offline mode toggle
			} else if (sPath.endsWith('slowNetwork')) {
				oUpdateObj.slowNetwork = bValue; // Network speed status
			}

			// Only proceed with update if we have changes to apply
			if (Object.keys(oUpdateObj).length > 0) {
				console.debug('Network State Partial Update:', {
					path: sPath,
					update: oUpdateObj,
					timestamp: exfTools.date.format(new Date(), 'YYYY-MM-DD HH:mm:ss.SSS')
				});

				// Update network state through PWA manager
				// This will:
				// 1. Apply changes to internal state
				// 2. Persist state changes in IndexedDB
				// 3. Trigger networkchanged event if state actually changed
				exfPWA.network.setState(oUpdateObj)
					.catch(function (oError) {
						console.error('Failed to update network state:', {
							error: oError,
							update: oUpdateObj,
							timestamp: exfTools.date.format(new Date(), 'YYYY-MM-DD HH:mm:ss.SSS')
						});
					});
			}
		});

		return _oShell;
	};

	/**
	 * Returns TRUE if the global busy indicator is shown and FALSE otherwise
	 * 
	 * @returns {boolean}
	 */
	this.isBusy = function () {
		return _bBusy && $('#exf-loader').is(':visible') === false;
	};

	this.setAppMenu = function (oControl) {
		_oAppMenu = oControl;
	};

	this.contextBar = function () {
		var _oComponent = {};
		var _oContextBar = {
			traceJs: false,
			lastContextRefresh: null,
			init: function (oComponent) {
				_oComponent = oComponent;

				// Give the shell the translation model of the component
				_oShell.setModel(oComponent.getModel('i18n'), 'i18n');

				oComponent.getRouter().attachRouteMatched(function (oEvent) {
					_oContextBar.load();
				});

				$(document).ajaxSuccess(function (event, jqXHR, ajaxOptions, data) {
					var extras = {};
					if (jqXHR.responseJSON) {
						extras = jqXHR.responseJSON.extras;
					} else {
						try {
							extras = $.parseJSON(jqXHR.responseText).extras;
						} catch (err) {
							extras = {};
						}
					}
					if (extras && extras.ContextBar) {
						_oContextBar.refresh(extras.ContextBar);
					} else {
						_oContextBar.load();
					}
				});
				oComponent.getPWA().updateQueueCount();

				$(document).on('debugShowJsTrace', function (oEvent) {
					_oLauncher.showErrorLog();
					oEvent.preventDefault();
				});
			},

			getComponent: function () {
				return _oComponent;
			},

			load: function (delay) {
				var oNetState = exfPWA.network.getState();
				if (delay === undefined) delay = 100;

				// Don't refresh if configured wait-time not passed yet
				if (_oContextBar.lastContextRefresh !== null && (Math.abs((new Date()) - _oContextBar.lastContextRefresh)) < _oConfig.contextBar.refreshWaitSeconds * 1000) {
					return;
				}
				_oContextBar.lastContextRefresh = new Date();

				// Do not really refresh if offline or semi-offline
				if (oNetState.isBrowserOnline() === false || oNetState.isOfflineVirtually()) {
					_oContextBar.refresh({});
					return;
				}

				setTimeout(function () {
					// IDEA had to disable adding context bar extras to every request due to
					// performance issues. This will be needed for asynchronous contexts like
					// user messaging, external task management, etc. So put the line back in
					// place to fetch context data with every request instead of a dedicated one.
					// if ($.active == 0 && $('#contextBar .context-bar-spinner').length > 0){
					//if ($('#contextBar .context-bar-spinner').length > 0){

					$.ajax({
						type: 'GET',
						url: 'api/ui5/' + _oLauncher.getPageId() + '/context',
						dataType: 'json',
						success: function (data, textStatus, jqXHR) {
							_oContextBar.refresh(data);
						},
						error: function (jqXHR, textStatus, errorThrown) {
							_oContextBar.refresh({});
						}
					});
					/*} else {
						_oContextBar.load(delay*3);
					}*/
				}, delay);
			},

			refresh: function (data) {
				var oToolbar = _oShell.getHeader();
				var aItemsOld = _oShell.getHeader().getContent();
				var iItemsIndex = 5;
				var oControl = {};
				var oCtxtData = {};
				var sColor;

				_oContextBar.data = data;
				oToolbar.removeAllContent();

				for (var i = 0; i < aItemsOld.length; i++) {
					oControl = aItemsOld[i];
					if (i < iItemsIndex || oControl.getId() == 'exf-network-indicator' || oControl.getId() == 'exf-pagetitle' || oControl.getId() == 'exf-user-icon') {
						oToolbar.addContent(oControl);
					} else {
						oControl.destroy();
					}
				}

				for (var id in data) {
					oCtxtData = data[id];
					sColor = oCtxtData.color ? 'background-color:' + oCtxtData.color + ' !important;' : '';
					if (oCtxtData.context_alias === 'exface.Core.PWAContext') {
						_oShell.getModel().setProperty("/_network/syncErrorCnt", parseInt(oCtxtData.indicator));
						continue;
					}
					if (oCtxtData.context_alias === 'exface.Core.NotificationContext') {
						_oContextBar.hideAnnouncement();
						(oCtxtData.announcements || []).forEach(function (oMsg) {
							_oContextBar.showAnnouncement(oMsg.text, oMsg.type, oMsg.icon);
						});
					}
					if (oCtxtData.visibility === 'hide_allways') {
						continue;
					}
					oToolbar.insertContent(
						new sap.m.Button(id, {
							icon: oCtxtData.icon,
							tooltip: oCtxtData.hint,
							text: oCtxtData.indicator,
							press: function (oEvent) {
								var oButton = oEvent.getSource();
								_oContextBar.showMenu(oButton);
							}
						})
							.data('widget', oCtxtData.bar_widget_id, true)
							.data('context', oCtxtData.context_alias, true),
						iItemsIndex
					);

					// Handle JS tracer if it is enabled in the DebugContext
					if (id.endsWith('CoreDebugContext')) {
						_oContextBar._setupTracer(oCtxtData);
					}
				}
				_oLauncher.contextBar.getComponent().getPWA().updateQueueCount();
			},

			/**
			 * 
			 * @param {string} sText 
			 * @param {string} sType 
			 * @param {string} sIcon 
			 * @return void
			 */
			showAnnouncement: function (sText, sType, sIcon) {
				var sHeight = '1.75rem';
				var iHeightTotal = '0';
				var sClass = 'sapMMsgStripInformation';
				var sIconCls = sIcon ? (sIcon.startsWith('fa-') ? 'fa ' + sIcon : sIcon) : 'fa fa-info-circle';
				var jqStrip;
				switch (sType.toLowerCase()) {
					case 'warning':
						sClass = 'sapMMsgStripWarning';
						sIconCls = 'fa fa-exclamation-triangle';
						break;
					case 'error':
						sClass = 'sapMMsgStripError';
						sIconCls = 'fa fa-times-circle';
						break;
					case 'success':
						sClass = 'sapMMsgStripSuccess';
						sIconCls = 'fa fa-check-circle-o';
						break;
					case 'hint':
						sClass = 'sapMMsgStripInformation';
						sIconCls = 'fa fa-exclamation-circle';
						break;
					case 'info':
					default:
						break;
				}
				jqStrip = $('<div class="exf-announcement sapMTB-Transparent-CTX ' + sClass + ' style="height: ' + sHeight + '""><div class="sapMLabel" style="line-height: ' + sHeight + '"><i class="' + sIconCls + '"></i> ' + sText + '</div></div>');
				iHeightTotal = $('#exf-announcements').append(jqStrip).outerHeight();
				$('.exf-launcher').css({ 'height': 'calc(100% - ' + iHeightTotal + 'px)' });
				$('.sapUiUfdShell.sapUiUfdShellCurtainHidden .sapUiUfdShellCurtain').hide();
			},

			/**
			 * @return void
			 */
			hideAnnouncement: function () {
				$('#exf-announcements').empty();
				$('.exf-launcher').css({ 'height': '100%' });
				$('.sapUiUfdShell.sapUiUfdShellCurtainHidden .sapUiUfdShellCurtain').show();
			},

			_setupTracer: function (oCtxtData) {
				if (oCtxtData.indicator !== 'OFF' && oCtxtData.indicator.includes('F')) {
					if (_oContextBar.traceJs !== true) {
						_oContextBar.traceJs = true;
					}
				} else {
					if (_oContextBar.traceJs === true) {
						_oContextBar.traceJs = false;
					}
				}
			},

			showMenu: function (oButton) {
				var sPopoverId = oButton.data('widget') + "_popover";
				var iPopoverWidth = sPopoverId === 'ContextBar_UserExfaceCoreNotificationContext' ? "500px" : "350px";
				var iPopoverHeight = "300px";
				var oPopover = sap.ui.getCore().byId(sPopoverId);
				if (oPopover) {
					return;
				} else {
					oPopover = new sap.m.ResponsivePopover(sPopoverId, {
						title: oButton.getTooltip(),
						placement: "Bottom",
						busy: true,
						contentWidth: iPopoverWidth,
						contentHeight: iPopoverHeight,
						horizontalScrolling: false,
						afterClose: function (oEvent) {
							oEvent.getSource().destroy();
						},
						content: [
							new sap.m.NavContainer({
								pages: [
									new sap.m.Page({
										showHeader: false,
										content: [

										]
									})
								]
							})
						],
						endButton: [
							new sap.m.Button({
								icon: 'sap-icon://font-awesome/close',
								text: "{i18n>CONTEXT.BUTTON.CLOSE}",
								press: function () { oPopover.close(); },
							})

						]

					})
						.setModel(oButton.getModel())
						.setModel(oButton.getModel('i18n'), 'i18n')
						.setBusyIndicatorDelay(0);
					oPopover.addStyleClass('exf-context-popup');

					jQuery.sap.delayedCall(0, this, function () {
						oPopover.openBy(oButton);
					});
				}

				$.ajax({
					type: 'GET',
					url: 'api/ui5',
					dataType: 'script',
					data: {
						action: 'exface.Core.ShowContextPopup',
						resource: _oLauncher.getPageId(),
						element: oButton.data('widget')
					},
					success: function (data, textStatus, jqXHR) {
						var viewMatch = data.match(/sap.ui.jsview\("(.*)"/i);
						if (viewMatch !== null) {
							var view = viewMatch[1];
						} else {
							_oComponent.showAjaxErrorDialog(jqXHR);
						}

						var oPopoverPage = oPopover.getContent()[0].getPages()[0];
						var oView = _oComponent.runAsOwner(function () {
							return sap.ui.view({ type: sap.ui.core.mvc.ViewType.JS, viewName: view });
						});
						var oEvent;

						var oNavInfoOpen = {
							from: null,
							fromId: null,
							to: oView || null,
							toId: (oView ? oView.getId() : null),
							firstTime: true,
							isTo: false,
							isBack: false,
							isBackToTop: false,
							isBackToPage: false,
							direction: "initial"
						};

						oPopoverPage.removeAllContent();

						// Before-open events
						oNavInfoOpen.to = oView;
						oNavInfoOpen.toId = oView.getId();

						oEvent = jQuery.Event("BeforeShow", oNavInfoOpen);
						oEvent.srcControl = oPopover.getContent()[0];
						oEvent.data = {};
						oEvent.backData = {};
						oView._handleEvent(oEvent);

						oView.fireBeforeRendering();

						// Populate the popover
						oPopoverPage.addContent(oView);

						// After-open events
						oEvent = jQuery.Event("AfterShow", oNavInfoOpen);
						oEvent.srcControl = oPopover.getContent()[0];
						oEvent.data = {};
						oEvent.backData = {};
						oView._handleEvent(oEvent);

						oView.fireAfterRendering();

						// TODO need close-events here?
						/*CLOSE-events
						 * Why We Need Close Events:
						* 1. If we have open events, Without proper close events, vievs might stay in memory even after closing
						* we might need to remove the event handlers 
						* 2. Resource cleanup
						* 3. Following proper view lifecycle improves stability and performance
						*/
						oPopover.setBusy(false);

					},
					error: function (jqXHR, textStatus, errorThrown) {
						oButton.setBusy(false);
						_oComponent.showAjaxErrorDialog(jqXHR);
					}
				});
			}
		};
		return _oContextBar;
	}();

	this.getPageId = function () {
		return $("meta[name='page_id']").attr("content");
	};

	this.registerNetworkSpeed = function (speedMbps) {
		const minusOneIndex = _speedHistory.indexOf(null);
		if (minusOneIndex !== -1) {
			_speedHistory[minusOneIndex] = speedMbps;
		} else {
			_speedHistory.shift();
			_speedHistory.push(speedMbps);
		}
	};

	this.showMessageToast = function (message, duration) {
		// Set default duration to 3000 milliseconds (3 seconds)

		var defaultDuration = 3000;

		// If a duration is provided, use it; otherwise, use the default duration
		var toastDuration = duration || defaultDuration;

		// Show the MessageToast with the customized duration
		sap.m.MessageToast.show(message, {
			duration: toastDuration,
			width: "20em"  // Increase the width of the message (optional)
		});
	};

	/**
	* Shows a dialog with offline storage info (quota, preload data summary, etc.)
	* 
	* @return void
	*
	* * Key features for performance:
	* 1. Interval Management 
	*    - Clears interval when dialog closes  
	* 2. Resource Cleanup
	*    - All intervals are cleaned up in afterClose event 
	* 3. Chart Updates
	*    - Only updates when chart is visible
	*    - Uses visibility check before each update 
	*/
	this.showStorage = async function (oEvent) {
		const oCalculator = {
			calculateSpeed: function () {
				const avarageSpeed = navigator?.connection?.downlink ? `${navigator?.connection?.downlink} Mbps` : '-';
				const speedTier = navigator?.connection?.effectiveType ? navigator?.connection?.effectiveType.toUpperCase() : '-';

				let customSpeed;
				if (_speedHistory.indexOf(null) === -1) {
					customSpeed = _speedHistory[_speedHistory.length - 1];
				} else if (_speedHistory.indexOf(null) === 0) {
					customSpeed = 0;
				} else {
					customSpeed = _speedHistory[_speedHistory.indexOf(null) - 1];
				}

				const customSpeedAvarageLabel = customSpeed ? `${customSpeed} Mbps` : '-';
				const customSpeedTier = oCalculator.calculateSpeedTier(customSpeed);

				return {
					avarageSpeed,
					speedTier,
					customSpeed,
					customSpeedAvarageLabel,
					customSpeedTier
				};
			},
			calculateSpeedTier: function (speedMbps) {
				let speedClass;
				switch (true) {
					case speedMbps == '-':
					case speedClass == 0:
						speedClass = '-';
						break;
					case speedMbps < 0.3:
						speedClass = '2G';
						break;
					case speedMbps < 5:
						speedClass = '3G';
						break;
					case speedMbps < 50:
						speedClass = '4G';
						break;
					default:
						speedClass = '5G';
						break;
				}
				return speedClass;
			}
		};

		var dialog = new sap.m.Dialog({
			title: "{i18n>WEBAPP.SHELL.NETWORK.STORAGE_HEADER}",
			icon: "sap-icon://unwired",
			// Cleanup handler ensures all intervals stop when dialog closes
			afterClose: function (oEvent) {
				// Clear all intervals to stop background processing
				if (_oSpeedStatusDialogInterval) {
					clearInterval(_oSpeedStatusDialogInterval);
				}
				oEvent.getSource().destroy();
			}
		});
		var oButton = oEvent.getSource();
		var button = new sap.m.Button({
			icon: 'sap-icon://font-awesome/close',
			text: "{i18n>WEBAPP.SHELL.NETWORK.STORAGE_CLOSE}",
			press: function () { dialog.close(); },
		});
		dialog.addButton(button);
		let list = new sap.m.List({});
		//check if possible to acces storage (means https connection)
		if (navigator.storage && navigator.storage.estimate) {
			var promise = navigator.storage.estimate()
				.then(function (estimate) {
					list = new sap.m.List({
						items: [
							new sap.m.GroupHeaderListItem({
								title: "{i18n>WEBAPP.SHELL.NETWORK.STORAGE_OVERVIEW}",
								upperCase: false
							}),
							new sap.m.DisplayListItem({
								label: "{i18n>WEBAPP.SHELL.NETWORK.STORAGE_TOTAL}",
								value: Number.parseFloat(estimate.quota / 1024 / 1024).toFixed(2) + ' MB'
							}),
							new sap.m.DisplayListItem({
								label: "{i18n>WEBAPP.SHELL.NETWORK.STORAGE_USED}",
								value: Number.parseFloat(estimate.usage / 1024 / 1024).toFixed(2) + ' MB'
							}),
							new sap.m.DisplayListItem({
								label: "{i18n>WEBAPP.SHELL.NETWORK.STORAGE_PERCENTAGE}",
								value: Number.parseFloat(100 / estimate.quota * estimate.usage).toFixed(2) + ' %'
							})
						]
					});
					if (estimate.usageDetails) {
						list.addItem(new sap.m.GroupHeaderListItem({
							title: "{i18n>WEBAPP.SHELL.NETWORK.STORAGE_DETAILS}",
							upperCase: false
						}));
						Object.keys(estimate.usageDetails).forEach(function (key) {
							list.addItem(new sap.m.DisplayListItem({
								label: key,
								value: Number.parseFloat(estimate.usageDetails[key] / 1024 / 1024).toFixed(2) + ' MB'
							})
							);
						});
					}
				})
				.catch(function (error) {
					console.error(error);
					list.addItem(new sap.m.GroupHeaderListItem({
						title: "{i18n>WEBAPP.SHELL.NETWORK.STORAGE_ERROR}",
						upperCase: false
					}))
				});
			//wait for the promise to resolve
			await promise;
		}

		const {
			avarageSpeed,
			speedTier,
			customSpeedAvarageLabel,
			customSpeedTier
		} = oCalculator.calculateSpeed();

		/* $("#sparkline").sparkline([10.4,3,6,12,], {
			type: 'line',
			width: '200px',
			height: '100px',
			chartRangeMin: 0,
			drawNormalOnTop: false}); */

		const oBrowserCurrentSpeedTierItem = new sap.m.DisplayListItem('browser_speed_tier_display', {
			label: "{i18n>WEBAPP.SHELL.NETWORK_SPEED_TIER}",
			value: speedTier,
		});

		const oBrowserCurrentSpeedItem = new sap.m.DisplayListItem('browser_speed_display', {
			label: "{i18n>WEBAPP.SHELL.NETWORK_SPEED}",
			value: avarageSpeed,
		});

		const oCustomCurrentSpeedTierItem = new sap.m.DisplayListItem('custom_speed_tier_display', {
			label: "{i18n>WEBAPP.SHELL.NETWORK_SPEED_TIER_CUSTOM}",
			value: customSpeedTier,
		});

		const oCustomCurrentSpeedItem = new sap.m.DisplayListItem('custom_speed_display', {
			label: "{i18n>WEBAPP.SHELL.NETWORK_SPEED_CUSTOM}",
			value: customSpeedAvarageLabel,
		});

		// Clearing the interval, because of this error :   browser_speed_tier_display was openui5.facade.js?v20241209112552:1088 
		// Uncaught TypeError: Cannot read properties of undefined (reading 'setValue')
		// We cam clear the interval or we can check if the element is exist, then we can set values 
		if (_oSpeedStatusDialogInterval) {
			clearInterval(_oSpeedStatusDialogInterval);
		}
		_oSpeedStatusDialogInterval = setInterval(() => {
			const {
				avarageSpeed,
				speedTier,
				customSpeedAvarageLabel,
				customSpeedTier
			} = oCalculator.calculateSpeed();

			sap.ui.getCore().byId('browser_speed_tier_display').setValue(speedTier);
			sap.ui.getCore().byId('browser_speed_display').setValue(avarageSpeed);
			sap.ui.getCore().byId('custom_speed_tier_display').setValue(customSpeedTier);
			sap.ui.getCore().byId('custom_speed_display').setValue(customSpeedAvarageLabel);
		}, 1000);


		[
			new sap.m.GroupHeaderListItem({
				title: "{i18n>WEBAPP.SHELL.NETWORK_SPEED_TITLE}",
				upperCase: false
			}),
			oBrowserCurrentSpeedTierItem,
			oBrowserCurrentSpeedItem,
			oCustomCurrentSpeedTierItem,
			oCustomCurrentSpeedItem,
			new sap.m.GroupHeaderListItem({
				title: "{i18n>WEBAPP.SHELL.NETWORK_HEALTH}",
				upperCase: false,
			}),
			new sap.m.CustomListItem({
				content: new sap.ui.core.HTML('network_speed_chart_wrapper', {
					content: '<div id="network_speed_chart"></div>',
					afterRendering: function () {
						// Initial chart update with sparkline
						// Setup interval that includes visibility check
						_oSpeedStatusDialogInterval = setInterval(function () {
							const chartDiv = document.getElementById('network_speed_chart');
							// Only update if chart is visible
							//check element is on DOM && check element is visible
							if (chartDiv && chartDiv.offsetParent) {
								$("#network_speed_chart").sparkline(_speedHistory, {
									type: 'line',
									width: '100%',
									height: '100px',
									chartRangeMin: 0,
									chartRangeMax: 10,
									drawNormalOnTop: false
								});
								// Update network stats only when visible
								listNetworkStats();
							}
						}, 5000);

						// Initial data load
						listNetworkStats();
					}
				})
			})

		].forEach(item => list.addItem(item));


		list.addItem(new sap.m.GroupHeaderListItem({
			title: "{i18n>WEBAPP.SHELL.NETWORK.STORAGE_SYNCED}",
			upperCase: false
		}));

		var oTable = new sap.m.Table({
			autoPopinMode: true,
			fixedLayout: false,
			headerToolbar: [
				new sap.m.OverflowToolbar({
					design: "Transparent",
					content: [
						new sap.m.ToolbarSpacer(),
						new sap.m.Button({
							text: "{i18n>WEBAPP.SHELL.PWA.MENU_SYNC}",
							tooltip: "{i18n>WEBAPP.SHELL.PWA.MENU_SYNC_TOOLTIP}",
							icon: "sap-icon://synchronize",
							enabled: "{/_network/online}",
							press: function (oEvent) {
								var oButton = oEvent.getSource();
								var oTable = oButton.getParent().getParent();
								oTable.setBusy(true);
								_oLauncher.syncOffline(oEvent)
									.then(function () {
										_oLauncher.loadPreloadInfo(oTable);
										oTable.setBusy(false);
									})
									.catch(function () {
										oTable.setBusy(false);
									})
							},
						}),
						new sap.m.Button({
							text: "{i18n>WEBAPP.SHELL.PWA.MENU_RE_SYNC}",
							tooltip: "{i18n>WEBAPP.SHELL.PWA.MENU_RE_SYNC_TOOLTIP}",
							icon: "sap-icon://synchronize",
							enabled: "{/_network/online}",
							press: function (oEvent) {
								var oButton = oEvent.getSource();
								var oTable = oButton.getParent().getParent();
								oTable.setBusy(true);
								_oLauncher.reSyncOffline(oEvent)
									.then(function () {
										_oLauncher.loadPreloadInfo(oTable);
										oTable.setBusy(false);
									})
									.catch(function () {
										oTable.setBusy(false);
									})
							},
						}),
						new sap.m.Button({
							text: "{i18n>WEBAPP.SHELL.PWA.MENU_RESET}",
							tooltip: "{i18n>WEBAPP.SHELL.PWA.MENU_RESET_TOOLTIP}",
							icon: "sap-icon://sys-cancel",
							press: function (oEvent) {
								var oButton = oEvent.getSource();
								var oTable = oButton.getParent().getParent();
								oTable.setBusy(true);
								_oLauncher.clearPreload(oEvent)
									.then(function () {
										_oLauncher.loadPreloadInfo(oTable);
										oTable.setBusy(false);
									})
									.catch(function () {
										oTable.setBusy(false);
									})
							},
						}),
					]
				})
			],
			columns: [
				new sap.m.Column({
					header: new sap.m.Label({
						text: "{i18n>WEBAPP.SHELL.NETWORK.STORAGE_OBJECT}"
					}),
					popinDisplay: sap.m.PopinDisplay.Inline,
					demandPopin: true,
				}),
				new sap.m.Column({
					header: new sap.m.Label({
						text: "{i18n>WEBAPP.SHELL.NETWORK.STORAGE_DATASETS}"
					}),
					popinDisplay: sap.m.PopinDisplay.Inline,
					demandPopin: true,
				}),
				,
				new sap.m.Column({
					header: new sap.m.Label({
						text: "{i18n>WEBAPP.SHELL.NETWORK.STORAGE_LAST_SYNC}"
					}),
					popinDisplay: sap.m.PopinDisplay.Inline,
					demandPopin: true,
				})
			]
		}).setBusyIndicatorDelay(0);
		dialog.addContent(list);
		dialog.addContent(oTable);

		promise = _oLauncher.loadPreloadInfo(oTable)
			.catch(function (error) {
				console.error(error);
				list.addItem(new sap.m.GroupHeaderListItem({
					title: "{i18n>WEBAPP.SHELL.NETWORK.STORAGE_ERROR}",
					upperCase: false
				}))
				dialog.addContent(list);
			})
		//wait for the promise to resolve
		await promise;
		dialog.setModel(oButton.getModel())
		dialog.setModel(oButton.getModel('i18n'), 'i18n');
		dialog.open();
		return;
	};

	/**
	 * Displays a testing menu for network-related data and statistics.
	 * Creates a dialog containing network state changes and performance metrics.
	 * 
	 * @param {sap.ui.base.Event} oEvent The event object from the triggering action
	 * @return {void}
	 */
	this.showTesterMenu = function (oEvent) {
		// Create the main dialog container for the testing interface
		var oDialog = new sap.m.Dialog({
			title: "Network Testing Data",
			icon: "sap-icon://performance",
			contentWidth: "90%",
			contentHeight: "90%",
			// Cleanup on dialog close to prevent memory leaks
			afterClose: function (oEvent) {
				oEvent.getSource().destroy();
			}
		});

		// Create a formatter function to convert boolean values to Yes/No
		// Utility function to convert boolean values to Yes/No for better readability
		var formatBoolean = function (value) {
			if (value === true || value === "true") return "Yes";
			if (value === false || value === "false") return "No";
			return "Unknown";
		};

		// Create tab container to organize different types of network data
		var oTabContainer = new sap.m.IconTabBar({
			items: [
				// First tab: Network Connection States
				new sap.m.IconTabFilter({
					key: "connection",
					text: "Connection States",
					content: [
						new sap.m.Table({
							growing: true, //This a property used in SAP UI5 tables for performance optimization and pagination
							growingThreshold: 20, // Show 20 items initially, then allow growing
							columns: [
								// Timestamp column showing when the state was recorded
								new sap.m.Column({
									header: new sap.m.Label({ text: "Time" }),
									width: "180px"
								}),
								new sap.m.Column({
									header: new sap.m.Label({ text: "Auto Offline" })
								}),
								new sap.m.Column({
									header: new sap.m.Label({ text: "Browser Online" })
								}),
								new sap.m.Column({
									header: new sap.m.Label({ text: "Force Offline" })
								}),
								new sap.m.Column({
									header: new sap.m.Label({ text: "Slow Network" })
								}),
								// Overall connection status with visual indicator
								new sap.m.Column({
									header: new sap.m.Label({ text: "Status" }),
									hAlign: "Center"
								})
							],
							// Bind the table rows to the connection data
							items: {
								path: "/connections",
								template: new sap.m.ColumnListItem({
									cells: [
										// Time column
										// Format and display timestamp
										new sap.m.Text({
											text: {
												path: "time",
												formatter: function (sTime) {
													return exfTools.date.format(sTime, 'YYYY-MM-DD HH:mm:ss.SSS');
												}
											}
										}),
										// Auto Offline column
										new sap.m.Text({
											text: {
												path: "state/bAutoOffline",
												formatter: formatBoolean
											}
										}),
										// Browser Online column
										new sap.m.Text({
											text: {
												path: "state/bBrowserOnline",
												formatter: formatBoolean
											}
										}),
										// Forced Offline column
										new sap.m.Text({
											text: {
												path: "state/bForcedOffline",
												formatter: formatBoolean
											}
										}),
										// Slow Network column
										new sap.m.Text({
											text: {
												path: "state/bSlowNetwork",
												formatter: formatBoolean
											}
										}),
										// Status column with icon
										// HBox is a horizontal layout control where we can place controls next to each other.
										// Displays overall status with icon and text
										new sap.m.HBox({
											justifyContent: "Center",
											items: [
												// Status icon that changes based on connection state
												new sap.ui.core.Icon({
													src: {
														path: "state",
														formatter: function (state) {
															if (!state) return "sap-icon://disconnected";
															// Online durumunda forced offline veya auto offline+slow network yoksa
															if (state.bBrowserOnline && !state.bForcedOffline &&
																!(state.bAutoOffline && state.bSlowNetwork)) {
																return "sap-icon://connected";
															}
															return "sap-icon://disconnected";
														}
													},
													// Color coding for status (green/red)
													color: {
														path: "state",
														formatter: function (state) {
															if (!state) return sap.ui.core.IconColor.Negative; //enum sap.ui.core.IconColor
															if (state.bBrowserOnline && !state.bForcedOffline &&
																!(state.bAutoOffline && state.bSlowNetwork)) {
																return sap.ui.core.IconColor.Positive;
															}
															return sap.ui.core.IconColor.Negative;
														}
													}
												}),
												// Status text (Online/Offline)
												new sap.m.Text({
													text: {
														path: "state",
														formatter: function (state) {
															if (!state) return " Offline";
															if (state.bBrowserOnline && !state.bForcedOffline &&
																!(state.bAutoOffline && state.bSlowNetwork)) {
																return " Online";
															}
															return " Offline";
														}
													}
												}).addStyleClass("sapUiTinyMarginBegin")
											]
										})
									]
								})
							}
						})
					]
				}),
				// Second tab: Network Performance Statistics
				new sap.m.IconTabFilter({
					key: "networkstats",
					text: "Network Statistics",
					content: [
						new sap.m.Table({
							growing: true,
							growingThreshold: 20,
							columns: [
								new sap.m.Column({ header: new sap.m.Label({ text: "Time" }) }),
								new sap.m.Column({ header: new sap.m.Label({ text: "Speed (Mbps)" }) }),
								new sap.m.Column({ header: new sap.m.Label({ text: "Size (bytes)" }) })
							],
							// Bind network statistics data
							items: {
								path: "/networkstats",
								template: new sap.m.ColumnListItem({
									cells: [
										new sap.m.Text({
											text: {
												path: "time",
												formatter: function (sTime) {
													return exfTools.date.format(sTime, 'YYYY-MM-DD HH:mm:ss.SSS');
												}
											}
										}),
										new sap.m.Text({
											text: {
												path: "speed",
												formatter: function (fSpeed) {
													return fSpeed.toFixed(2);
												}
											}
										}),
										new sap.m.Text({ text: "{size}" })
									]
								})
							}
						})
					]
				})
			]
		});

		// Add close button to dialog
		oDialog.addButton(new sap.m.Button({
			text: "Close",
			press: function () { oDialog.close(); }
		}));

		// Add the tab container to the dialog
		oDialog.addContent(oTabContainer);

		if (exfPWA && exfPWA.isAvailable()) {
			Promise.all([
				exfPWA.network.getAllStates(),
				exfPWA.network.getAllStats()
			]).then(function ([aConnections, aStats]) {
				// For model binding, prepare the data
				var oModel = new sap.ui.model.json.JSONModel({
					connections: aConnections,
					networkstats: aStats.slice(-50) // Last 50 records
				});
				oDialog.setModel(oModel);
			}).catch(function (oError) {
				console.error('Failed to load network data:', oError);
				oDialog.setModel(new sap.ui.model.json.JSONModel({
					connections: [],
					networkstats: []
				}));
			});
		}
		else {
			console.warn('PWA functionality is not available - offline data cannot be displayed');
		}
		// Display the dialog
		oDialog.open();
	};

	/**
	 * Loads information about the preload data (number of items, sync time) into
	 * the passed sap.m.Table
	 * 
	 * @reutn void
	 */
	this.loadPreloadInfo = function (oTable) {
		return exfPWA.data.getTable().toArray()
			.then(function (dbContent) {
				oTable.removeAllItems();
				dbContent.forEach(function (element) {
					var oRow = new sap.m.ColumnListItem();
					oRow.addCell(new sap.m.Text({ text: element.object_name }));
					if (element.rows) {
						oRow.addCell(new sap.m.Text({ text: element.rows.length }));
						oRow.addCell(new sap.m.Text({ text: new Date(element.last_sync).toLocaleString() }));
					} else {
						oRow.addCell(new sap.m.Text({ text: '0' }));

						oRow.addCell(new sap.m.Text({ text: '{i18n>WEBAPP.SHELL.NETWORK.STORAGE_NOT_SYNCED}' }));
					}
					oTable.addItem(oRow);
				});
			})
	}

	/**
	 * Shows a popover with pending offline actions for a data item
	 * 
	 * @return void
	 */
	this.showOfflineQueuePopoverForItem = function (sObjectAlias, sUidColumn, sUidValue, oTrigger) {
		var oPopover = new sap.m.Popover({
			title: "{i18n>WEBAPP.SHELL.NETWORK.QUEUE_TABLE_WAITING_ACTIONS}",
			placement: "Right",
			afterClose: function (oEvent) {
				oEvent.getSource().destroy();
			},
			content: [
				new sap.m.Table({
					autoPopinMode: true,
					fixedLayout: false,
					columns: [
						new sap.m.Column({
							header: [
								new sap.m.Label({
									text: "{i18n>WEBAPP.SHELL.NETWORK.QUEUE_TABLE_ACTION}"
								})
							],
							popinDisplay: sap.m.PopinDisplay.Inline,
							demandPopin: true,
						}),
						new sap.m.Column({
							header: [
								new sap.m.Label({
									text: '{i18n>WEBAPP.SHELL.NETWORK.QUEUE_TABLE_TRIGGERED}'
								})
							],
							popinDisplay: sap.m.PopinDisplay.Inline,
							demandPopin: true,
						}),
						new sap.m.Column({
							header: [
								new sap.m.Label({
									text: '{i18n>WEBAPP.SHELL.NETWORK.QUEUE_TABLE_STATUS}'
								}),
							],
							popinDisplay: sap.m.PopinDisplay.Inline,
							demandPopin: true,
						}),
						new sap.m.Column({
							header: [
								new sap.m.Label({
									text: '{i18n>WEBAPP.SHELL.NETWORK.QUEUE_TABLE_TRIES}'
								}),
							],
							popinDisplay: sap.m.PopinDisplay.Inline,
							demandPopin: true,
						})
					],
					items: {
						path: "queueModel>/rows",
						template: new sap.m.ColumnListItem({
							cells: [new sap.m.Text({
								text: "{queueModel>effect_name}"
							}),
							new sap.m.Text({
								text: "{queueModel>triggered}"
							}),
							new sap.m.Text({
								text: "{queueModel>status}"
							}),
							new sap.m.Text({
								text: "{queueModel>tries}"
							})
							]
						})
					}
				})
			]
		})
			.setModel(oTrigger.getModel())
			.setModel(oTrigger.getModel('i18n'), 'i18n');

		exfPWA.actionQueue.getEffects(sObjectAlias)
			.then(function (aEffects) {
				var oData = {
					rows: []
				};
				aEffects.forEach(function (oEffect) {
					var oRow = oEffect.offline_queue_item;
					// TODO filter over sUidColumn, sUidValue passed to the method here! Otherwise
					// it shows all actions for the object, not only those effecting the row!
					oRow.effect_name = oEffect.name;
					oData.rows.push(oRow);
				});
				oPopover.setModel(function () { return new sap.ui.model.json.JSONModel(oData) }(), 'queueModel');
			})
			.catch(function (data) {
				// TODO
			});

		jQuery.sap.delayedCall(0, this, function () {
			oPopover.openBy(oTrigger);
		});

		return;
	};

	/**
	 * Shows a dialog with a table showing currently queued offline actions (not yet sent
	 * to the server).
	 * 
	 * @param {sap.ui.base.Event} [oEvent]
	 * 
	 * @return void
	 */
	this.showOfflineQueue = function (oEvent) {
		var oButton = oEvent.getSource();
		var oTable = new sap.m.Table({
			fixedLayout: false,
			autoPopinMode: true,
			mode: sap.m.ListMode.MultiSelect,
			headerToolbar: [
				new sap.m.OverflowToolbar({
					design: "Transparent",
					content: [
						new sap.m.Label({
							text: "{i18n>WEBAPP.SHELL.NETWORK.QUEUE_TABLE_WAITING_ACTIONS}"
						}),
						new sap.m.ToolbarSpacer(),
						new sap.m.Button({
							text: "{i18n>WEBAPP.SHELL.NETWORK.QUEUE_TABLE_DELETE}",
							icon: "sap-icon://delete",
							press: function (oEvent) {
								var oButton = oEvent.getSource();
								var table = oButton.getParent().getParent()
								var selectedItems = table.getSelectedItems();
								if (selectedItems.length === 0) {
									var text = exfLauncher.contextBar.getComponent().getModel('i18n').getProperty("WEBAPP.SHELL.NETWORK.NO_SELECTION");
									_oLauncher.showMessageToast(text);
									return;
								}
								oButton.setBusyIndicatorDelay(0).setBusy(true);
								var selectedIds = [];
								selectedItems.forEach(function (item) {
									var bindingObj = item.getBindingContext('queueModel').getObject()
									selectedIds.push(bindingObj.id);
								})

								var confirmDialog = new sap.m.Dialog({
									title: "{i18n>WEBAPP.SHELL.NETWORK.CONFIRM_HEADER}",
									stretch: false,
									type: sap.m.DialogType.Message,
									content: [
										new sap.m.Text({
											text: '{i18n>WEBAPP.SHELL.NETWORK.CONFIRM_TEXT}'
										})
									],
									beginButton: new sap.m.Button({
										text: "{i18n>WEBAPP.SHELL.NETWORK.CONFIRM_YES}",
										type: sap.m.ButtonType.Emphasized,
										press: function (oEvent) {
											exfPWA.actionQueue.deleteAll(selectedIds)
												.then(function () {
													_oLauncher.contextBar.getComponent().getPWA().updateQueueCount()
												})
												.then(function () {
													confirmDialog.close();
													oButton.setBusy(false);
													var text = exfLauncher.contextBar.getComponent().getModel('i18n').getProperty("WEBAPP.SHELL.NETWORK.ENTRIES_DELETED");
													_oLauncher.showMessageToast(text);
													return exfPWA.actionQueue.get('offline')
												})
												.then(function (data) {
													var oData = {};
													oData.data = data;
													oTable.setModel(function () { return new sap.ui.model.json.JSONModel(oData) }(), 'queueModel');
													return;
												})
										}
									}),
									endButton: new sap.m.Button({
										text: "{i18n>WEBAPP.SHELL.NETWORK.CONFIRM_NO}",
										type: sap.m.ButtonType.Default,
										press: function (oEvent) {
											oButton.setBusy(false);
											confirmDialog.close();
										}
									})
								})
									.setModel(oButton.getModel('i18n'), 'i18n');

								confirmDialog.open();
							}
						}),
						new sap.m.Button('exf-queue-sync', {
							text: "{i18n>WEBAPP.SHELL.NETWORK.QUEUE_TABLE_SYNC}",
							icon: "sap-icon://synchronize",
							enabled: "{= ${/_network/online} > 0 ? true : false }",
							press: function (oEvent) {
								var oButton = oEvent.getSource();
								var table = oButton.getParent().getParent()
								var selectedItems = table.getSelectedItems();
								if (selectedItems.length === 0) {
									var text = exfLauncher.contextBar.getComponent().getModel('i18n').getProperty("WEBAPP.SHELL.NETWORK.NO_SELECTION");
									_oLauncher.showMessageToast(text);
									return;
								}
								oButton.setBusyIndicatorDelay(0).setBusy(true);
								var selectedIds = [];
								selectedItems.forEach(function (item) {
									var bindingObj = item.getBindingContext('queueModel').getObject()
									selectedIds.push(bindingObj.id);
								})
								exfPWA.actionQueue.syncIds(selectedIds)
									.then(function () {
										_oLauncher.contextBar.getComponent().getPWA().updateQueueCount();
										_oLauncher.contextBar.getComponent().getPWA().updateErrorCount();
									})
									.then(function () {
										oButton.setBusy(false);
										var text = exfLauncher.contextBar.getComponent().getModel('i18n').getProperty("WEBAPP.SHELL.NETWORK.SYNC_ACTIONS_COMPLETE");
										_oLauncher.showMessageToast(text);
										return exfPWA.actionQueue.get('offline')
									})
									.then(function (data) {
										var oData = {};
										oData.data = data;
										oTable.setModel(function () { return new sap.ui.model.json.JSONModel(oData) }(), 'queueModel');
										return;
									})
									.catch(function (error) {
										console.error('Offline action sync error: ', error);
										_oLauncher.contextBar.getComponent().getPWA().updateQueueCount()
											.then(function () {
												_oLauncher.contextBar.getComponent().getPWA().updateErrorCount();
												oButton.setBusy(false);
												_oLauncher.contextBar.getComponent().showErrorDialog(error, '{i18n>WEBAPP.SHELL.NETWORK.QUEUE_TABLE_HEADER}');
												return exfPWA.actionQueue.get('offline')
											})
											.then(function (data) {
												var oData = {};
												oData.data = data;
												oTable.setModel(function () { return new sap.ui.model.json.JSONModel(oData) }(), 'queueModel');
												return;
											})
										return;
									})
							},
						}),
						new sap.m.Button({
							text: "{i18n>WEBAPP.SHELL.NETWORK.QUEUE_TABLE_EXPORT}",
							icon: "sap-icon://download",
							press: function (oEvent) {
								var oButton = oEvent.getSource();
								var table = oButton.getParent().getParent()
								var selectedItems = table.getSelectedItems();
								if (selectedItems.length === 0) {
									var text = exfLauncher.contextBar.getComponent().getModel('i18n').getProperty("WEBAPP.SHELL.NETWORK.NO_SELECTION");
									_oLauncher.showMessageToast(text);
									return;
								}
								oButton.setBusyIndicatorDelay(0).setBusy(true);
								var selectedIds = [];
								selectedItems.forEach(function (item) {
									var bindingObj = item.getBindingContext('queueModel').getObject()
									selectedIds.push(bindingObj.id);
								})
								exfPWA.actionQueue.getByIds(selectedIds)
									.then(function (aQItems) {
										var oData = {
											deviceId: _pwa.getDeviceId(),
											actions: aQItems
										};
										var sJson = JSON.stringify(oData);
										var date = new Date();
										var dateString = date.toISOString();
										dateString = dateString.substr(0, 16);
										dateString = dateString.replace(/-/gi, "");
										dateString = dateString.replace("T", "_");
										dateString = dateString.replace(":", "");
										oButton.setBusyIndicatorDelay(0).setBusy(false);
										exfPWA.download(sJson, 'offlineActions_' + dateString, 'application/json')
										var text = exfLauncher.contextBar.getComponent().getModel('i18n').getProperty("WEBAPP.SHELL.NETWORK.ENTRIES_EXPORTED");
										_oLauncher.showMessageToast(text);
										return;
									})
									.catch(function (error) {
										console.error(error);
										oButton.setBusyIndicatorDelay(0).setBusy(false);
										_oLauncher.contextBar.getComponent().showErrorDialog('{i18n>WEBAPP.SHELL.NETWORK.CONSOLE}', '{i18n>WEBAPP.SHELL.NETWORK.ENTRIES_EXPORTED_FAILED}');
										return;
									})
							}
						})
					]
				})
			],
			footerText: '{i18n>WEBAPP.SHELL.NETWORK.QUEUE_TABLE_DEVICE}: {/_network/deviceId}',
			columns: [
				new sap.m.Column({
					header: [
						new sap.m.Label({
							text: "{i18n>WEBAPP.SHELL.NETWORK.QUEUE_TABLE_OBJECT}"
						})
					],
					popinDisplay: sap.m.PopinDisplay.Inline,
					demandPopin: true,
				}),
				new sap.m.Column({
					header: [
						new sap.m.Label({
							text: "{i18n>WEBAPP.SHELL.NETWORK.QUEUE_TABLE_ACTION}"
						})
					],
					popinDisplay: sap.m.PopinDisplay.Inline,
					demandPopin: true,
				}),
				new sap.m.Column({
					header: [
						new sap.m.Label({
							text: '{i18n>WEBAPP.SHELL.NETWORK.QUEUE_TABLE_TRIGGERED}'
						})
					],
					popinDisplay: sap.m.PopinDisplay.Inline,
					demandPopin: true,
				}),
				new sap.m.Column({
					header: [
						new sap.m.Label({
							text: '{i18n>WEBAPP.SHELL.NETWORK.QUEUE_TABLE_STATUS}'
						})
					],
					popinDisplay: sap.m.PopinDisplay.Inline,
					demandPopin: true,
				}),
				new sap.m.Column({
					header: [
						new sap.m.Label({
							text: '{i18n>WEBAPP.SHELL.NETWORK.QUEUE_TABLE_TRIES}'
						})
					],
					popinDisplay: sap.m.PopinDisplay.Inline,
					demandPopin: true,
				}),
				new sap.m.Column({
					header: [
						new sap.m.Label({
							text: '{i18n>WEBAPP.SHELL.NETWORK.QUEUE_TABLE_ID}'
						})
					],
					popinDisplay: sap.m.PopinDisplay.Inline,
					demandPopin: true,
				}),
			],
			items: {
				path: "queueModel>/data",
				template: new sap.m.ColumnListItem({
					cells: [
						new sap.m.Text({
							text: "{queueModel>object_name}"
						}),
						new sap.m.Text({
							text: "{queueModel>action_name}"
						}),
						new sap.m.Text({
							text: "{queueModel>triggered}"
						}),
						new sap.m.Text({
							text: "{queueModel>status}"
						}),
						new sap.m.Text({
							text: "{queueModel>tries}"
						}),
						new sap.m.Text({
							text: "{queueModel>id}"
						}),
					]
				})
			}
		})
			.setModel(oButton.getModel())
			.setModel(oButton.getModel('i18n'), 'i18n');

		exfPWA.actionQueue.get('offline')
			.then(function (data) {
				var oData = {};
				oData.data = data;
				oTable.setModel(function () { return new sap.ui.model.json.JSONModel(oData) }(), 'queueModel');
				_oLauncher.contextBar.getComponent().showDialog('{i18n>WEBAPP.SHELL.NETWORK.QUEUE_TABLE_HEADER}', oTable, undefined, undefined, true);
			})
			.catch(function (data) {
				var oData = {};
				oData.data = data;
				oTable.setModel(function () { return new sap.ui.model.json.JSONModel(oData) }());
				_oLauncher.contextBar.getComponent().showDialog('{i18n>WEBAPP.SHELL.NETWORK.QUEUE_TABLE_HEADER}', oTable, undefined, undefined, true);
			})
	};

	/**
	 * Shows a dialog with a table with offline actions server errors
	 * 
	 * @param {sap.ui.base.Event} [oEvent]
	 * 
	 * @return void
	 */
	this.showOfflineErrors = function (oEvent) {
		var oButton = oEvent.getSource();
		var oTable = new sap.m.Table({
			autoPopinMode: true,
			fixedLayout: false,
			/*headerToolbar: [
				new sap.m.OverflowToolbar({
					design: "Transparent",
					content: [
						new sap.m.Label({
							text: "{i18n>WEBAPP.SHELL.NETWORK.ERROR_TABLE_ERRORS}"
						})
					]
				})
			],*/
			footerText: '{i18n>WEBAPP.SHELL.NETWORK.QUEUE_TABLE_DEVICE}: {/_network/deviceId}',
			columns: [
				new sap.m.Column({
					header: [
						new sap.m.Label({
							text: '{i18n>WEBAPP.SHELL.NETWORK.QUEUE_TABLE_ID}'
						})
					],
					popinDisplay: sap.m.PopinDisplay.Inline,
					demandPopin: true,
				}),
				new sap.m.Column({
					header: [
						new sap.m.Label({
							text: "{i18n>WEBAPP.SHELL.NETWORK.QUEUE_TABLE_OBJECT}"
						})
					],
					popinDisplay: sap.m.PopinDisplay.Inline,
					demandPopin: true,
				}),
				new sap.m.Column({
					header: [
						new sap.m.Label({
							text: "{i18n>WEBAPP.SHELL.NETWORK.QUEUE_TABLE_ACTION}"
						})
					],
					popinDisplay: sap.m.PopinDisplay.Inline,
					demandPopin: true,
				}),
				new sap.m.Column({
					header: [
						new sap.m.Label({
							text: '{i18n>WEBAPP.SHELL.NETWORK.QUEUE_TABLE_TRIGGERED}'
						})
					],
					popinDisplay: sap.m.PopinDisplay.Inline,
					demandPopin: true,
				}),
				new sap.m.Column({
					header: [
						new sap.m.Label({
							text: '{i18n>WEBAPP.SHELL.NETWORK.ERROR_TABLE_LOGID}'
						})
					],
					popinDisplay: sap.m.PopinDisplay.Inline,
					demandPopin: true,
				}),
				new sap.m.Column({
					header: [
						new sap.m.Label({
							text: '{i18n>WEBAPP.SHELL.NETWORK.ERROR_MESSAGE}'
						})
					],
					popinDisplay: sap.m.PopinDisplay.Inline,
					demandPopin: true,
				})
			],
			items: {
				path: "errorModel>/data",
				template: new sap.m.ColumnListItem({
					cells: [
						new sap.m.Text({
							text: "{errorModel>MESSAGE_ID}"
						}),
						new sap.m.Text({
							text: "{errorModel>OBJECT_ALIAS}"
						}),
						new sap.m.Text({
							text: "{errorModel>ACTION_ALIAS}"
						}),
						new sap.m.Text({
							text: "{errorModel>TASK_ASSIGNED_ON}"
						}),
						new sap.m.Text({
							text: "{errorModel>ERROR_LOGID}"
						}),
						new sap.m.Text({
							text: "{errorModel>ERROR_MESSAGE}"
						})
					]
				})
			}
		})
			.setModel(oButton.getModel())
			.setModel(oButton.getModel('i18n'), 'i18n');

		if (_oLauncher.isOnline()) {
			exfPWA.errors.sync()
				.then(function (data) {
					var oData = {};
					if (data.rows !== undefined) {
						var rows = data.rows;
						for (var i = 0; i < rows.length; i++) {
							if (rows[i].TASK_ASSIGNED_ON !== undefined) {
								rows[i].TASK_ASSIGNED_ON = new Date(rows[i].TASK_ASSIGNED_ON).toLocaleString();
							}
						}
						oData.data = rows;
					}
					oTable.setModel(function () { return new sap.ui.model.json.JSONModel(oData) }(), 'errorModel');
					_oLauncher.contextBar.getComponent().showDialog('{i18n>WEBAPP.SHELL.NETWORK.ERROR_TABLE_ERRORS}', oTable, undefined, undefined, true);
				})
		}
	};

	/**
	 * Loads all preload data from the server since the last increment
	 * 
	 * @param {sap.ui.base.Event} [oEvent]
	 * 
	 * @return Promise
	 */
	this.syncOffline = function (oEvent) {
		oButton = oEvent.getSource();
		oButton.setBusyIndicatorDelay(0).setBusy(true);
		var oI18nModel = oButton.getModel('i18n');
		return exfPWA.syncAll()
			.then(function () {
				oButton.setBusy(false);
				exfLauncher.showMessageToast(oI18nModel.getProperty('WEBAPP.SHELL.NETWORK.SYNC_COMPLETE'));
			})
			.catch(error => {
				console.error(error);
				exfLauncher.showMessageToast(oI18nModel.getProperty('WEBAPP.SHELL.NETWORK.SYNC_FAILED'));
				oButton.setBusy(false);
			});
	};

	/**
	 * Loads all preload data from the server
	 *
	 * @param {sap.ui.base.Event} [oEvent]
	 *
	 * @return Promise
	 */
	this.reSyncOffline = function (oEvent) {
		oButton = oEvent.getSource();
		oButton.setBusyIndicatorDelay(0).setBusy(true);
		var oI18nModel = oButton.getModel('i18n');
		return exfPWA.syncAll({ doReSync: true })
			.then(function () {
				oButton.setBusy(false);
				exfLauncher.showMessageToast(oI18nModel.getProperty('WEBAPP.SHELL.NETWORK.SYNC_COMPLETE'));
			})
			.catch(error => {
				console.error(error);
				exfLauncher.showMessageToast(oI18nModel.getProperty('WEBAPP.SHELL.NETWORK.SYNC_FAILED'));
				oButton.setBusy(false);
			});
	};

	/**
	 * Removes all preload data
	 * 
	 * @param {sap.ui.base.Event} [oEvent]
	 * 
	 * @return Promise
	 */
	this.clearPreload = function (oEvent) {
		var oButton = oEvent.getSource();
		var oI18nModel = oButton.getModel('i18n');
		oButton.setBusyIndicatorDelay(0).setBusy(true);
		return exfPWA
			.reset()
			.then(() => {
				oButton.setBusy(false);
				exfLauncher.showMessageToast(oI18nModel.getProperty('WEBAPP.SHELL.PWA.CLEARED'));
			}).catch(() => {
				oButton.setBusy(false);
				exfLauncher.showMessageToast(oI18nModel.getProperty('WEBAPP.SHELL.PWA.CLEARED_ERROR}'));
			})
	};

	/**
	 * Shows the offline menu
	 * 
	 * @param {sap.ui.base.Event} [oEvent]
	 * 
	 * @return void
	 */
	this.showOfflineMenu = function (oEvent) {
		_oLauncher.contextBar.getComponent().getPWA().updateQueueCount();
		_oLauncher.contextBar.getComponent().getPWA().updateErrorCount();

		var oButton = oEvent.getSource();
		var oPopover = sap.ui.getCore().byId('exf-network-menu');
		const titleInterval = setInterval(function () {
			oPopover.setTitle(exfPWA.network.getState().toString());
		}, 1000);


		if (oPopover === undefined) {
			oPopover = new sap.m.ResponsivePopover("exf-network-menu", {
				title: "{/_network/title}",
				placement: "Bottom",
				content: [
					new sap.m.MessageStrip({
						text: "Offline sync not available.",
						type: "Warning",
						showIcon: true,
						visible: (!exfPWA.isAvailable())
					}).addStyleClass('sapUiSmallMargin'),
					new sap.m.List({
						items: [
							new sap.m.GroupHeaderListItem({
								title: '{i18n>WEBAPP.SHELL.NETWORK.SYNC_MENU}',
								upperCase: false
							}),
							new sap.m.StandardListItem({
								title: "{i18n>WEBAPP.SHELL.NETWORK.SYNC_MENU_QUEUE} ({/_network/queueCnt})",
								type: "Active",
								icon: "sap-icon://time-entry-request",
								press: _oLauncher.showOfflineQueue,
							}),
							new sap.m.StandardListItem({
								title: "{i18n>WEBAPP.SHELL.NETWORK.SYNC_MENU_ERRORS} ({/_network/syncErrorCnt})",
								type: "{= ${/_network/online} > 0 ? 'Active' : 'Inactive' }",
								icon: "sap-icon://alert",
								//blocked: "{= ${/_network/online} > 0 ? false : true }", //Deprecated as of version 1.69.
								press: _oLauncher.showOfflineErrors,
							}),
							new sap.m.GroupHeaderListItem({
								title: '{i18n>WEBAPP.SHELL.PWA.MENU}',
								upperCase: false
							}),
							new sap.m.StandardListItem({
								title: "{i18n>WEBAPP.SHELL.PWA.MENU_SYNC}",
								tooltip: "{i18n>WEBAPP.SHELL.PWA.MENU_SYNC_TOOLTIP}",
								icon: "sap-icon://synchronize",
								type: "{= ${/_network/online} > 0 ? 'Active' : 'Inactive' }",
								press: _oLauncher.syncOffline,
							}),
							new sap.m.StandardListItem({
								title: "{i18n>WEBAPP.SHELL.PWA.MENU_RE_SYNC}",
								tooltip: "{i18n>WEBAPP.SHELL.PWA.MENU_SYNC_RE_TOOLTIP}",
								icon: "sap-icon://synchronize",
								type: "{= ${/_network/online} > 0 ? 'Active' : 'Inactive' }",
								press: _oLauncher.reSyncOffline,
							}),
							new sap.m.StandardListItem({
								title: "{i18n>WEBAPP.SHELL.NETWORK.STORAGE_HEADER}",
								icon: "sap-icon://unwired",
								type: "Active",
								press: _oLauncher.showStorage,
							}),
							new sap.m.StandardListItem({
								title: "Tester Menu",  // Not using i18n as this is a development tool
								icon: "sap-icon://performance",
								type: "Active",
								press: _oLauncher.showTesterMenu,  // New function we'll create
							}),
							new sap.m.StandardListItem({
								title: "{i18n>WEBAPP.SHELL.PWA.MENU_RESET}",
								tooltip: "{i18n>WEBAPP.SHELL.PWA.MENU_RESET_TOOLTIP}",
								icon: "sap-icon://sys-cancel",
								type: "Active",
								press: _oLauncher.clearPreload,
							}),
							new sap.m.GroupHeaderListItem({
								title: "{i18n>WEBAPP.SHELL.NETWORK.OFFLINE_HEADER}",
								upperCase: false
							}),
							new sap.m.CustomListItem({
								content: new sap.m.FlexBox({
									direction: "Row",
									alignItems: "Center",
									customData: new sap.ui.core.CustomData({
										key: "style",
										value: "gap: 1rem;"
									}),
									items: [
										new sap.m.Switch({
											state: "{/_network/state/autoOffline}",
										}),
										new sap.m.Text({
											text: "{i18n>WEBAPP.SHELL.NETWORK_AUTOMATIC_OFFLINE}"
										}),
									],
								}),
							}).addStyleClass("sapUiResponsivePadding"),
							new sap.m.CustomListItem({
								content: new sap.m.FlexBox({
									direction: "Row",
									alignItems: "Center",
									customData: new sap.ui.core.CustomData({
										key: "style",
										value: "gap: 1rem;"
									}),
									items: [
										new sap.m.Switch({
											//Instead of a custom change handler, we can use a model binding and
											//let the property change listener in the shell do the work like auto_offline_toggle
											state: "{/_network/state/forcedOffline}"
										}),
										new sap.m.Text({
											text: "{i18n>WEBAPP.SHELL.NETWORK_FORCE_OFFLINE}"
										}),
									],
								}).addStyleClass("sapUiResponsivePadding"),
							}),
						]
					})
				],
				endButton: [
					new sap.m.Button({
						icon: 'sap-icon://font-awesome/close',
						text: "{i18n>CONTEXT.BUTTON.CLOSE}",
						press: function () { oPopover.close(); },
					})

				],
				afterClose: function (oEvent) {
					clearInterval(titleInterval);
				}
			})
				.setModel(oButton.getModel())
				.setModel(oButton.getModel('i18n'), 'i18n');

			// Get the initial network state from exfPWA and populate the UI5 model
			// this will put the switche in their initial position.
			exfPWA.network.checkState().then(state => {
				exfLauncher.updateNetworkModel(state);
			});
		}

		jQuery.sap.delayedCall(0, this, function () {
			oPopover.openBy(oButton);
		});
	};

	this.showErrorLog = function (oEvent) {
		if (window.exfTracer) {
			// Global capturedErrors'u güvenli bir şekilde geç
			var errors = Array.isArray(window.capturedErrors) ? window.capturedErrors : [];
			window.exfTracer.showErrorLog(errors);
		} else {
			var currentScriptPath = $('script[src*="openui5.facade.js"]').attr('src');
			var tracerPath = currentScriptPath.replace('facade.js', 'tracer.js');

			$.getScript(tracerPath)
				.done(function () {
					var errors = Array.isArray(window.capturedErrors) ? window.capturedErrors : [];
					window.exfTracer.showErrorLog(errors);
				})
				.fail(function (jqxhr, settings, exception) {
					console.error('Failed to load tracer script:', {
						error: exception,
						path: tracerPath,
						status: jqxhr.status
					});
				});
		}
	};


	this.updateNetworkModel = function (oNetStat, oModel) {
		console.log('set network model', oNetStat);
		var oModel = oModel === undefined ? exfLauncher.getShell().getModel() : oModel;
		var oNetStat = exfPWA.network.getState();
		oModel.setProperty('/_network/state', {
			autoOffline: oNetStat.hasAutoffline(),
			slowNetwork: oNetStat.isNetworkSlow(),
			forcedOffline: oNetStat.isOfflineForced()
		});
		oModel.setProperty('/_network/title', oNetStat.toString());
		oModel.setProperty('/_network/online', oNetStat.isOnline());
	}


}).apply(exfLauncher);



/**
 * Collects and processes network stats for chart visualization.
 * Only runs when chart is visible to optimize performance.
 * This prevents unnecessary processing and memory consumption when stats aren't being displayed 
 * Gets data from IndexedDB, processes it, and updates speed history chart.
 * Shows only last 10 records and refreshes the chart completely on each update.
 * 
 * @returns {void} No return value
 * @throws {Error} Logs error if stats collection fails
 */
function listNetworkStats() {
	// Skip if chart is not visible
	// Critical visibility check to prevent unnecessary processing
	// offsetParent will be null if element is not visible or not in DOM
	// This ensures we only process data when user can actually see the chart
	const chartElement = document.getElementById('network_speed_chart');
	if (!chartElement || !chartElement.offsetParent) {
		return;
	}
	// Get and process network stats
	exfPWA.network.getAllStats()
		.then(stats => {
			if (exfPWA.isAvailable() === false) {
				return;
			}
			// Check if there are any statistics available
			if (stats.length === 0) {
				return; // Exit if there are no stats
			}

			// Sort the statistics by time (oldest to newest)
			stats.sort((a, b) => a.time - b.time);

			const averageSpeeds = {};
			const currentSecond = Math.floor(Date.now() / 1000); // Get current time in seconds
			const earliestSecond = Math.floor(stats[0].time / 1000); // Earliest timestamp (first element)
			const latestSecond = Math.floor(stats[stats.length - 1].time / 1000); // Latest timestamp (last element)

			// Group speeds by second
			stats.forEach(stat => {
				const requestTimeInSeconds = Math.floor(stat.time / 1000); // Convert timestamp to seconds
				if (!averageSpeeds[requestTimeInSeconds]) {
					averageSpeeds[requestTimeInSeconds] = []; // Initialize array if it doesn't exist
				}
				averageSpeeds[requestTimeInSeconds].push(stat.speed); // Collect speeds for this second
			});

			const secondsToProcess = latestSecond - earliestSecond; // Calculate the number of seconds to process

			const result = {};
			let lastKnownSpeed = 0; // Variable to store the last known speed

			// Iterate over each second from earliest to latest
			for (let i = 0; i <= secondsToProcess; i++) {
				const second = earliestSecond + i; // Get the second to process

				if (averageSpeeds[second]) {
					// Filter out any NaN values from the speeds and convert to doubles
					const validSpeeds = averageSpeeds[second].filter(speed => {
						const isValid = !isNaN(speed); // Check if speed is not NaN
						if (!isValid) {
							console.warn(`Invalid speed detected: ${speed} at second ${second}`);
						}
						return isValid; // Return valid speeds
					}).map(speed => parseFloat(speed)); // Convert to floating-point numbers

					if (validSpeeds.length > 0) {
						// Calculate the average speed from valid speeds
						const avgSpeed = validSpeeds.reduce((a, b) => a + b, 0) / validSpeeds.length;
						result[second] = avgSpeed; // Store the average speed for this second
						lastKnownSpeed = avgSpeed; // Update the last known speed
					} else {
						result[second] = lastKnownSpeed; // Use the last known speed if no valid speeds
					}
				} else {
					result[second] = lastKnownSpeed; // If no data for this second, use last known speed
				}
			}

			// Register each calculated speed
			// Update speed history for chart
			Object.keys(result).forEach(second => {
				exfLauncher.registerNetworkSpeed(result[second]);
			});
		})
		.catch(error => {
			console.error("An error occurred while listing network statistics:", error);
		});
}

// Set to keep track of dismissed errors
exfLauncher.dismissedErrors = new Set();

/**
 * Displays an error popover with a list of captured errors, excluding dismissed ones.
 * This function creates or updates a popover to show error details.
 * @param {string} errorMessage - The most recent error message to display.
 */
exfLauncher.showErrorPopover = function (errorMessage) {
	// Using window.capturedErrors explicitly indicates the variable's global scope
	// Initialize arrays if undefined
	window.capturedErrors = window.capturedErrors || [];
	this.dismissedErrors = this.dismissedErrors || new Set();

	// Filter out dismissed errors 
	var activeErrors = window.capturedErrors.filter(error => !this.dismissedErrors.has(error.message));

	// Prepare error messages as a list  
	var errorList = new sap.m.List({
		items: activeErrors.map(function (error) {
			return new sap.m.StandardListItem({
				title: error.message,
				description: new Date(error.timestamp).toLocaleString(),
				type: "Active",
				wrapping: true,
				press: function () {
					// Show error details 
					sap.m.MessageBox.error(error.stack || error.message, {
						title: "Error Details"
					});
				}
			});
		})
	});

	this.errorPopover = new sap.m.Popover({
		placement: sap.m.PlacementType.Bottom,
		showHeader: true,
		title: "Errors Occurred",
		content: [
			new sap.m.VBox({
				items: [
					new sap.m.Text({
						text: activeErrors.length + " error(s) occurred. Tap for details.",
						wrapping: true // Wrapping the message
					}).addStyleClass("sapUiSmallMargin"),
					errorList
				],
				justifyContent: sap.m.FlexJustifyContent.SpaceBetween,
				alignItems: sap.m.FlexAlignItems.Stretch,
				height: "100%"
			})
		],
		footer: new sap.m.Toolbar({
			content: [
				new sap.m.ToolbarSpacer(),
				new sap.m.Button({
					text: "Details",
					icon: "sap-icon://detail-view",
					press: function () {
						console.log('Details button pressed');

						// Get current active errors
						window.capturedErrors = window.capturedErrors || [];
						var activeErrors = window.capturedErrors.filter(error =>
							!exfLauncher.dismissedErrors.has(error.message)
						);

						console.log('Active errors:', activeErrors);

						// Close the popover
						exfLauncher.errorPopover.close();

						// Ensure tracer.js is loaded and show error log
						if (window.exfTracer) {
							console.log('Showing error log with tracer...');
							window.exfTracer.showErrorLog(activeErrors);
						} else {
							console.log('Loading tracer.js...');
							var currentScriptPath = $('script[src*="openui5.facade.js"]').attr('src');
							var tracerPath = currentScriptPath.replace('facade.js', 'tracer.js');

							$.getScript(tracerPath)
								.done(function () {
									console.log('Tracer.js loaded successfully');
									window.exfTracer.showErrorLog(activeErrors);
								})
								.fail(function (jqxhr, settings, exception) {
									console.error('Failed to load tracer script:', {
										error: exception,
										path: tracerPath,
										status: jqxhr.status
									});
								});
						}
					}
				}),
				new sap.m.Button({
					text: "Dismiss All",
					press: function () {
						activeErrors.forEach(error => exfLauncher.dismissedErrors.add(error.message));
						exfLauncher.errorPopover.close();
					}
				}),
				new sap.m.Button({
					text: "Close",
					press: function () {
						exfLauncher.errorPopover.close();
					}
				})
			]
		}),
		afterClose: function () {
			// Don't destroy the popover after closing, keep it for reuse
			exfLauncher.errorPopover.close();
		}
	});

	// Make the popover more visible
	this.errorPopover.addStyleClass("sapUiContentPadding");
	this.errorPopover.addStyleClass("sapUiResponsivePadding");

	// Keep the popover above other page elements
	this.errorPopover.setInitialFocus(this.errorPopover.getContent()[0]);

	// Show Popover only if there are active errors
	if (activeErrors.length > 0) {
		var oNetworkIndicator = sap.ui.getCore().byId("exf-network-indicator");
		jQuery.sap.delayedCall(0, this, function () {
			this.errorPopover.openBy(oNetworkIndicator);
		});
	}
};

/**
 * Overrides the default console.error function to capture and log errors.
 * This function logs the original error, captures error details, and displays them in a popover.
 */
console.error = function (...args) {
	originalConsoleError.apply(console, args);

	// Initialize arrays if undefined
	window.capturedErrors = window.capturedErrors || [];
	exfLauncher.dismissedErrors = exfLauncher.dismissedErrors || new Set();

	let errorMessage = args.map(arg => {
		if (arg instanceof Error) {
			return arg.stack || arg.message;
		} else if (typeof arg === 'object') {
			try {
				return JSON.stringify(arg);
			} catch (e) {
				return String(arg);
			}
		} else {
			return String(arg);
		}
	}).join(' ');

	const shouldIgnore = ignoredErrorPatterns.some(pattern => pattern.test(errorMessage));

	if (exfLauncher.contextBar.traceJs && !shouldIgnore &&
		!exfLauncher.dismissedErrors.has(errorMessage)) {

		const errorDetails = {
			message: errorMessage,
			timestamp: new Date().toISOString(),
			url: window.location.href,
			stack: new Error().stack
		};

		console.log('Capturing error:', errorDetails); // Debug için
		window.capturedErrors.push(errorDetails);

		// Show error popover
		if (typeof exfLauncher !== 'undefined' &&
			typeof exfLauncher.showErrorPopover === 'function') {
			try {
				exfLauncher.showErrorPopover(errorMessage);
			} catch (e) {
				console.error('Error showing popover:', e);
			}
		}
	}
};



// Store the existing window.onload function (if it exists)
var existingOnload = window.onload;

// Define the new window.onload function
window.onload = function () {
	if (typeof existingOnload === 'function') {
		existingOnload();
	}

	// Initialize/clear error list
	window.capturedErrors = [];

	setTimeout(function () {
		if (window.capturedErrors.length > 0) {
			exfLauncher.showMessageToast(window.capturedErrors.length + ' errors occurred. Check the error log for details.', 3000);
		}
	}, 1000);
};
window['exfLauncher'] = exfLauncher;
//v1 