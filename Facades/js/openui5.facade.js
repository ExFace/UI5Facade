//ignoredErrorPatterns

// Store the original console.error function
const originalConsoleError = console.error;

// Array to store captured errors
window.capturedErrors = [];
let capturedErrors = [];

/**
 * Error Pattern Management System
 * 
 * A comprehensive system for managing and persisting error filtering patterns.
 * This implementation provides:
 * - Configurable error patterns with persistence
 * - User interface for pattern management
 * - Automatic state restoration on page load
 * 
 * Usage:
 * - Patterns can be enabled/disabled through UI
 * - States persist across page reloads
 * - Active patterns filter out matching errors from the error log
 */

/**
 * Storage utility for error pattern configuration
 * Provides methods to save and load pattern states using localStorage
 * 
 * Implementation details:
 * - Uses localStorage for persistence
 * - Only stores essential data (description and active state)
 * - Handles storage errors gracefully
 * - Provides debug logging for troubleshooting
 */
const oErrorPatternStorage = {
	// Key used for localStorage entries
	STORAGE_KEY: 'errorPatternConfig',

	/**
	 * Saves current pattern configurations to localStorage
	 * 
	 * @description
	 * Converts pattern objects to a simplified format for storage:
	 * - Excludes RegExp objects which can't be serialized
	 * - Only stores description and active state
	 * - Provides error handling and debug logging
	 */
	savePatternStates: function () {
		try {
			// Create simplified objects for storage
			const aPatternStates = aConfigurableErrorPatterns.map(oPattern => ({
				description: oPattern.description,
				active: oPattern.active
			}));

			// Store in localStorage
			localStorage.setItem(this.STORAGE_KEY, JSON.stringify(aPatternStates));
			console.debug('Error pattern states saved:', {
				timestamp: new Date().toISOString(),
				patterns: aPatternStates
			});
		} catch (oError) {
			console.warn('Failed to save error pattern states:', {
				error: oError,
				timestamp: new Date().toISOString()
			});
		}
	},

	/**
	 * Loads and applies saved pattern states from localStorage
	 * 
	 * @description
	 * - Retrieves saved states from localStorage
	 * - Matches saved states to existing patterns by description
	 * - Updates active states of matching patterns
	 * - Maintains pattern integrity by only updating existing patterns
	 */
	loadPatternStates: function () {
		try {
			const sSavedStates = localStorage.getItem(this.STORAGE_KEY);
			if (sSavedStates) {
				const aSavedStates = JSON.parse(sSavedStates);

				// Update existing patterns with saved states
				aSavedStates.forEach((oSavedPattern) => {
					const oExistingPattern = aConfigurableErrorPatterns.find(
						oPattern => oPattern.description === oSavedPattern.description
					);
					if (oExistingPattern) {
						oExistingPattern.active = oSavedPattern.active;
					}
				});

				console.debug('Error pattern states loaded:', {
					timestamp: new Date().toISOString(),
					loadedStates: aSavedStates,
					activePatterns: aConfigurableErrorPatterns.filter(p => p.active).length
				});
			}
		} catch (oError) {
			console.warn('Failed to load error pattern states:', {
				error: oError,
				timestamp: new Date().toISOString()
			});
		}
	}
};

/**
 * Error Pattern Management System
 * 
 * A system to configure which types of errors should be collected in the error log.
 * Each pattern has:
 * - pattern: RegExp to match error messages
 * - description: Human readable description of what errors to collect
 * - active: When true, matching errors will be collected. When false, they will be ignored
 */
const aConfigurableErrorPatterns = [
	{
		pattern: /Assertion failed: could not find any translatable text for key/i,
		description: "Translation key errors",
		active: true,
		category: "UI5"
	},
	{
		pattern: /The target you tried to get .* does not exist!/i,
		description: "Target not found errors",
		active: true,
		category: "Navigation"
	},
	{
		pattern: /EventProvider sap\.m\.routing\.Targets/i,
		description: "Event provider errors",
		active: true,
		category: "Events"
	},
	{
		pattern: /Modules that use an anonymous define\(\) call must be loaded with a require\(\) call.*/i,
		description: "Module loading errors",
		active: true,
		category: "Module Loading"
	}
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

				// Decrease the number of background API calls - context bar oContextBar.load()
				// Refresh context bar when transitioning to online
				if (oChanges.browserOnline) {
					console.debug('Refreshing context bar after going online');
					exfLauncher.contextBar.load();
				}
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

			//Decrease the number of background API calls - context bar oContextBar.load()
			// Refresh context bar when transitioning to online state
			// This covers both browser online state and virtual offline states
			if (oNetState.isOnline()) {
				const previouslyOffline = oChanges.browserOnline === true ||
					oChanges.forcedOffline === false ||
					(oChanges.autoOffline === false || oChanges.slowNetwork === false);

				if (previouslyOffline) {
					console.debug('Refreshing context bar after network state change:', {
						changes: oChanges,
						currentState: oNetState.toString()
					});
					exfLauncher.contextBar.load();
				}
			}

		} catch (modelError) {
			console.error('Failed to update network model:', modelError);
		}

		// Handle online-specific actions
		if (oNetState.isOnline && oNetState.isOnline()) {
			// Update error counters
			/**
			 * Update queue and error counts for PWA status
			 * I was getting this error : openui5.facade.js?v20241209112552:2522 Failed to update queue or error counts: 
			 * 		ReferenceError: _oContextBar is not defined
			 * 
			 * Problem we had:
			 * - The code was trying to use _oContextBar when it might not exist
			 * - This caused an error when trying to update queue counts
			 * 
			 * Why we use Promise.all():
			 * 1. Run both updates (queue and error counts) at the same time
			 * 2. More efficient than running them one after another
			 * 3. Can catch errors from both updates in one place
			 * 4. If one update fails, we still get results from the other one
			 * 
			 * Example:
			 * Without Promise.all: Update queue (wait) -> Update errors (wait) => Takes longer
			 * With Promise.all:    Update queue + Update errors (wait once) => Faster
			 */
			const pwa = exfLauncher?.contextBar?.getComponent()?.getPWA();
			if (pwa) {
				Promise.all([
					typeof pwa.updateQueueCount === 'function' ? pwa.updateQueueCount() : Promise.resolve(),
					typeof pwa.updateErrorCount === 'function' ? pwa.updateErrorCount() : Promise.resolve()
				])
					.catch(error => {
						console.warn('Failed to update queue or error counts:', error);
					});
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

			// Extract relative URL
			let relativeUrl = options.url;
			try {
				// Remove any absolute URL parts if present
				const urlObject = new URL(options.url, window.location.origin);
				relativeUrl = urlObject.pathname + urlObject.search;
			} catch (e) {
				// URL was already relative, use as is
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

					// Calculate total duration (includes server processing time)
					// Calculate network-only duration (excludes server processing) 
					// Timing calculation with validation - changed the logic because at old version i saw - values a few times
					let totalDuration = (endTime - startTime) / 1000;
					let networkDuration = 0;

					if (serverTimingValue && serverTimingValue > 0) {
						networkDuration = Math.max(0, (totalDuration * 1000 - serverTimingValue) / 1000);
					} else {
						networkDuration = totalDuration; // If no valid server timing, use total duration
					}

					// Get response sizes
					let responseContentLength = parseInt(jqXHR.getResponseHeader('Content-Length')) || 0;
					let responseHeaders = jqXHR.getAllResponseHeaders();
					let responseHeadersLength = new Blob([responseHeaders]).size * 8;

					// Calculate total data size in bits
					let totalDataSize = (requestHeadersLength + requestContentLength + responseHeadersLength + responseContentLength * 8);

					// Calculate network speed in Mbps 
					// Using network duration (excluding server time) for more accurate speed calculation
					let speedMbps = totalDataSize / (networkDuration * 1000000); // Convert to Mbps

					// Get request MIME type
					let requestMimeType = options.contentType ||
						(options.headers && options.headers['Content-Type']) ||
						'application/x-www-form-urlencoded; charset=UTF-8';

					// Save network stats if exfPWA is available
					if (typeof exfPWA !== 'undefined') {
						exfPWA.network.saveStat(
							new Date(endTime),          // timestamp
							speedMbps,                  // speed in Mbps
							requestMimeType,            // MIME type
							totalDataSize,              // total size in bits
							networkDuration,            // network duration in seconds
							totalDuration,              // total duration including server time
							serverTimingValue,          // server processing time
							options.type || 'GET',      // HTTP method
							relativeUrl,                // relative URL
							requestContentLength + requestHeadersLength,    // request size in bits (totalDataSize)
							responseContentLength * 8 + responseHeadersLength // response size in bits
						).then(() => {
							console.debug("Network Stat Saved", {
								url: relativeUrl,
								method: options.type || 'GET',
								totalTime: totalDuration.toFixed(3) + 's',
								networkTime: networkDuration.toFixed(3) + 's',
								serverTime: (serverTimingValue / 1000).toFixed(3) + 's',
								speed: speedMbps.toFixed(2) + ' Mbps'
							});
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
	// Store interval ID for network speed status updates
	// This allows us to properly cleanup when dialog closes


	/**
	 * Global configuration object for application settings
	 * 
	 * @type {Object}
	 * @property {Object} contextBar - Settings for context bar functionality
	 * @property {Object} network - Network-related configurations
	 */
	var _oConfig = {
		contextBar: {
			refreshWaitSeconds: 5,         // Wait time between context refreshes
			autoloadIntervalSeconds: 30    // Interval for automatic context loading
		},
		network: {
			polling: exfPWA.getConfig().network.polling, // Reference network polling settings
			monitoring: {
				performanceInterval: 3 * 60 * 1000,  // 3-minute interval for performance monitoring
				historyWindow: 10 * 60 * 1000,      // 10-minute window for historical data,
				refreshChartInterval: 5 * 1000,          // 5-second interval for graph updates
				chart: {
					displayMaxSeconds: 10,           // Maximum value shown on chart Y-axis
					normalDurationSeconds: 1.5,      // Upper limit for normal performance
					dataPointsCount: 600,            // Number of data points to show (10 minutes)
					historyLength: 10 * 60          // Moved SPEED_HISTORY_ARRAY_LENGTH here (10 minutes in seconds) 10 min * 60 sec = 600
					// dataPointsCount determines the number of points to show on the graph and historyLength determines the amount of data to store in memory
					// seperating these 2 variable allowing you to store 30 minutes of data but only show the last 10 minutes with different values
					// a single variable could be used if both will always have the same value.
				}
			}
		},
		storageQuota: {
			speedHistoryLength: 3 * 60 * 1000,  // 3-minute for performance average duration value
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
			/**
			 * Calculates various network speed metrics
			 * Combines browser API data with actual API call performance
			 * 
			 * @returns {Promise<Object>} Speed metrics including browser and custom measurements
			 */
			calculateSpeed: async function () {
				// Get theoretical speed from browser's Network API
				const avarageSpeed = navigator?.connection?.downlink ?
					`${navigator?.connection?.downlink} Mbps` : '-';
				const speedTier = navigator?.connection?.effectiveType ?
					navigator?.connection?.effectiveType.toUpperCase() : '-';

				// Calculate actual average speed from recent API calls
				let fCustomSpeed = await updateSpeedHistory();
				const customSpeedAvarageLabel = fCustomSpeed ?
					`${fCustomSpeed.toFixed(2)} Second` : '-';
				// const customSpeedTier = oCalculator.calculateSpeedTier(fCustomSpeed);

				return {
					avarageSpeed,
					speedTier,
					customSpeed: fCustomSpeed,
					customSpeedAvarageLabel
					// ,					customSpeedTier
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

		/**
		* _oSpeedStatusDialogInterval is used to:
		* - Periodically update network speed metrics and chart in the dialog (every 5s)
		* - Store the interval ID so it can be cleared when dialog closes
		* - Prevent memory leaks by stopping updates when data isn't visible
		*/
		var _oSpeedStatusDialogInterval;
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
		// Display items for network speed
		/**
		 * Creates display items for network speed information
		 * These will be updated in real-time while dialog is open
		 */
		const {
			avarageSpeed,
			speedTier,
			customSpeed,
			customSpeedAvarageLabel
			// ,			customSpeedTier
		} = oCalculator.calculateSpeed();

		const oBrowserCurrentSpeedTierItem = new sap.m.DisplayListItem('browser_speed_tier_display', {
			label: "{i18n>WEBAPP.SHELL.NETWORK_SPEED_TIER}",
			value: speedTier,
		});

		const oBrowserCurrentSpeedItem = new sap.m.DisplayListItem('browser_speed_display', {
			label: "{i18n>WEBAPP.SHELL.NETWORK_SPEED}",
			value: avarageSpeed,
		});

		// const oCustomCurrentSpeedTierItem = new sap.m.DisplayListItem('custom_speed_tier_display', {
		// 	label: "{i18n>WEBAPP.SHELL.NETWORK_SPEED_TIER_CUSTOM}",
		// 	value: customSpeedTier,
		// });

		const oCustomCurrentSpeedItem = new sap.m.DisplayListItem('custom_speed_display', {
			label: "{i18n>WEBAPP.SHELL.NETWORK_SPEED_CUSTOM}",
			value: customSpeedAvarageLabel,
		});

		//
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


		// Clearing the interval, because of this error :   browser_speed_tier_display was openui5.facade.js?v20241209112552:1088 
		// Uncaught TypeError: Cannot read properties of undefined (reading 'setValue')
		// We cam clear the interval or we can check if the element is exist, then we can set values 
		/**
		 * Setup real-time updates for network speed display
		 * Updates will occur every second while dialog is open
		 */
		// Clear any existing interval before creating a new one
		if (_oSpeedStatusDialogInterval) {
			clearInterval(_oSpeedStatusDialogInterval);
		}

		/**
		 * Sets up periodic updates for network speed display
		 * Updates both browser API and custom calculated speeds every second
		 * 
		 * Safety features:
		 * - Null checks for UI elements
		 * - Error handling for speed calculations
		 * - Cleanup on dialog close
		 */
		_oSpeedStatusDialogInterval = setInterval(async () => {
			// Get references to display elements
			const oBrowserSpeedTierDisplay = sap.ui.getCore().byId('browser_speed_tier_display');
			const oBrowserSpeedDisplay = sap.ui.getCore().byId('browser_speed_display');
			// const oCustomSpeedTierDisplay = sap.ui.getCore().byId('custom_speed_tier_display');
			const oCustomSpeedDisplay = sap.ui.getCore().byId('custom_speed_display');

			// Calculate new speed values
			const {
				avarageSpeed,
				speedTier,
				customSpeedAvarageLabel
				// ,				customSpeedTier
			} = await oCalculator.calculateSpeed();

			// Update UI elements with null checks
			if (oBrowserSpeedTierDisplay) {
				oBrowserSpeedTierDisplay.setValue(speedTier);
			}
			if (oBrowserSpeedDisplay) {
				oBrowserSpeedDisplay.setValue(avarageSpeed);
			}
			// if (oCustomSpeedTierDisplay) {
			// 	oCustomSpeedTierDisplay.setValue(customSpeedTier);
			// }
			if (oCustomSpeedDisplay) {
				oCustomSpeedDisplay.setValue(customSpeedAvarageLabel);
			}
		}, 5000);

		//2
		[
			new sap.m.GroupHeaderListItem({
				title: "{i18n>WEBAPP.SHELL.NETWORK_SPEED_TITLE}",
				upperCase: false
			}),
			oBrowserCurrentSpeedTierItem,
			oBrowserCurrentSpeedItem,
			// oCustomCurrentSpeedTierItem,
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
							const _speedHistory = new Array(_oConfig.network.monitoring.chart.historyLength).fill(null);

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
								displayNetworkPerformance();
							}
						}, _oConfig.network.monitoring.refreshChartInterval);

						// Initial data load
						displayNetworkPerformance();
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
	 * Updates _speedHistory array with average network speed from last 3 minutes
	 * 
	 * This function:
	 * 1. Gets network stats from last 3 minutes
	 * 2. Filters for context API calls only
	 * 3. Calculates average speed
	 * //4. Updates _speedHistory array
	 * 
	 * @returns {Promise<number>} Average speed in Mbps
	 */
	function updateSpeedHistory() {

		const iSpeedHistoryLength = _oConfig.storageQuota.speedHistoryLength;
		// Calculate timestamp for "iSpeedHistoryLength" minutes ago
		const iThreeMinutesAgo = Date.now() - iSpeedHistoryLength;
		

		return exfPWA.network.getAllStats()
			.then(aStats => {
				// Filter stats for last 3 minutes context API calls only
				const aRecentStats = aStats.filter(oStat =>
					oStat.time >= iThreeMinutesAgo &&
					oStat.relative_url &&
					oStat.relative_url.includes('/context')
				);

				// Calculate average speed if we have data
				if (aRecentStats.length > 0) {
					const fAverageSpeed = aRecentStats.reduce((fSum, oStat) =>
						fSum + (oStat.network_duration || 0), 0) / aRecentStats.length;

					// // Update speed history array
					// _speedHistory.push(fAverageSpeed);
					// // Maintain fixed array length
					// if (_speedHistory.length > _oConfig.network.monitoring.chart.historyLength) {
					// 	_speedHistory.shift();
					// }

					return fAverageSpeed;
				}
				return 0;
			});
	};

	/**
 * Network Health Performance Monitor
 * 
 * Real-time visualization of network performance metrics focusing on context API calls.
 * Uses configuration from _oConfig.network.monitoring.chart for display parameters.
 * 
 * Features:
 * - Dynamic line chart showing network performance over time
 * - Color-coded performance bands based on configured thresholds
 * - Intelligent value capping with tooltip preservation
 * - Memory-efficient data processing
 * 
 * @returns {void}
 * @throws {Error} Throws and logs errors during data processing or visualization
 */
	function displayNetworkPerformance() {
		// Get chart configuration values
		const DISPLAY_MAX = _oConfig.network.monitoring.chart.displayMaxSeconds;
		const NORMAL_DURATION = _oConfig.network.monitoring.chart.normalDurationSeconds;
		const DATA_POINTS = _oConfig.network.monitoring.chart.dataPointsCount;


		// Performance optimization: Skip processing if chart isn't visible
		// This check prevents unnecessary data processing when nobody is looking
		const oChartElement = document.getElementById('network_speed_chart');
		if (!oChartElement || !oChartElement.offsetParent) {
			return;
		}

		// Fetch all network statistics from IndexedDB storage
		exfPWA.network.getAllStats()
			.then(aStats => {
				// Early exit conditions
				if (!exfPWA.isAvailable() || aStats.length === 0) {
					console.debug('Network stats not available or empty');
					return;
				}

				// Ensure chronological order for processing
				aStats.sort((a, b) => a.time - b.time);

				// Initialize data structures
				const oAverageDurations = {};   // Holds durations grouped by second
				const oRealDurations = {};      // Stores actual values (including those >2s)
				const iCurrentSecond = Math.floor(Date.now() / 1000);  // Current time in seconds
				const iEarliestSecond = Math.floor(aStats[0].time / 1000);
				const iLatestSecond = Math.floor(aStats[aStats.length - 1].time / 1000);

				// Log analysis range for monitoring
				console.debug('Network Stats Analysis Range:', {
					start: new Date(iEarliestSecond * 1000).toISOString(),
					end: new Date(iLatestSecond * 1000).toISOString(),
					duration: `${((iLatestSecond - iEarliestSecond) / 60).toFixed(1)} minutes`,
					totalDataPoints: aStats.length,
					displayMax: DISPLAY_MAX + 's',
					normalThreshold: NORMAL_DURATION + 's'
				});

				// First data pass: Group durations by second
				// We only process context API calls as they're our performance indicators
				aStats.forEach(oStat => {
					// Skip non-context requests - they're not relevant for this analysis
					if (!oStat.relative_url || !oStat.relative_url.includes('/context')) {
						return;
					}

					// Group durations by their second timestamp
					const iRequestSecond = Math.floor(oStat.time / 1000);
					if (!oAverageDurations[iRequestSecond]) {
						oAverageDurations[iRequestSecond] = [];
					}

					// Store the network_duration (excludes server processing time)
					if (oStat.network_duration !== undefined) {
						oAverageDurations[iRequestSecond].push(oStat.network_duration);
					}
				});

				// Prepare arrays for chart rendering
				const aSparklineData = [];      // Display values (capped at DISPLAY_MAX)
				const aSparklineTimes = [];     // Corresponding timestamps
				const aRealValues = [];         // Actual values for tooltips
				let fLastKnownDuration = 0;     // Used to fill gaps in data

				// Second data pass: Process each second for the last 10 minutes
				// This creates a consistent array of "DATA_POINTS.lenght" data points
				for (let i = 0; i < DATA_POINTS; i++) {
					const iSecond = iCurrentSecond - (DATA_POINTS - 1 - i);
					let fDuration;          // Display value (capped)
					let fRealDuration;      // Actual value (uncapped)

					if (oAverageDurations[iSecond]) {
						// Calculate average duration for this second
						const aValidDurations = oAverageDurations[iSecond].filter(d => !isNaN(d));
						if (aValidDurations.length > 0) {
							// Calculate the true average duration
							fRealDuration = aValidDurations.reduce((a, b) => a + b, 0) / aValidDurations.length;

							// Log high values for monitoring
							if (fRealDuration > DISPLAY_MAX) {
								console.debug('High Network Duration:', {
									actualValue: fRealDuration.toFixed(2) + 's',
									displayValue: DISPLAY_MAX + 's',
									timestamp: new Date(iSecond * 1000).toISOString(),
									requestCount: aValidDurations.length
								});
							}

							// Cap the display value but keep real value for tooltip
							fDuration = Math.min(fRealDuration, DISPLAY_MAX);
							fLastKnownDuration = fDuration;
						} else {
							// No valid measurements - use last known values
							fDuration = fLastKnownDuration;
							fRealDuration = fDuration;
						}
					} else {
						// Fill gaps with last known values for smooth visualization
						fDuration = fLastKnownDuration;
						fRealDuration = fDuration;
					}

					// Store values and timestamp
					aSparklineData.push(fDuration);
					aRealValues.push(fRealDuration);
					aSparklineTimes.push(new Date(iSecond * 1000));
				}

				// Render the sparkline chart
				$("#network_speed_chart").sparkline(aSparklineData, {
					type: 'line',                    // Line chart type
					width: '100%',                   // Use full container width
					height: '100px',                 // Fixed height
					chartRangeMin: 0,                // Y-axis starts at 0
					chartRangeMax: DISPLAY_MAX,      // Y-axis capped at display maximum
					normalRangeMin: 0,               // Normal range start
					normalRangeMax: NORMAL_DURATION, // Normal range end
					normalRangeColor: 'rgb(206, 246, 184)', // for gray -> sap.ui.core.IconColor.Default,

					// Remove distracting elements
					spotColor: undefined,
					minSpotColor: undefined,
					maxSpotColor: undefined,

					// Custom tooltip showing both real duration and time
					tooltipFormatter: function (spark, options, fields) {
						const fRealDuration = aRealValues[fields.x].toFixed(2);
						const sTime = aSparklineTimes[fields.x].toLocaleTimeString();
						return `${fRealDuration}s (${sTime})`; // Example: "2.73s (15:45:23)"
					}
				});
			})
			.catch(oError => {
				console.error("Network Performance Monitor Error:", {
					error: oError,
					message: oError.message,
					stack: oError.stack,
					timestamp: new Date().toISOString()
				});
			});
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
													// return exfTools.date.format(sTime, 'YYYY-MM-DD HH:mm:ss.SSS');--> 2025-01-00606 13:53:48.627  day is in wrong format
													return sap.ui.core.format.DateFormat.getDateTimeInstance({pattern: 'yyyy-MM-dd HH:mm'}).format(new Date(sTime));
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
								new sap.m.Column({ header: new sap.m.Label({ text: "Method" }) }),
								new sap.m.Column({
									header: new sap.m.Label({ text: "URL" }),
									minScreenWidth: "Tablet",
									demandPopin: true
								}),
								new sap.m.Column({ header: new sap.m.Label({ text: "Network Time (s)" }) }),
								new sap.m.Column({ header: new sap.m.Label({ text: "Server Time (ms)" }) }),
								new sap.m.Column({ header: new sap.m.Label({ text: "Total Time (s)" }) }),
								new sap.m.Column({
									header: new sap.m.Label({ text: "Request Size" }),
									minScreenWidth: "Tablet",
									demandPopin: true
								}),
								new sap.m.Column({
									header: new sap.m.Label({ text: "Response Size" }),
									minScreenWidth: "Tablet",
									demandPopin: true
								}),
								new sap.m.Column({
									header: new sap.m.Label({ text: "MIME Type" }),
									minScreenWidth: "Desktop",
									demandPopin: true
								})
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
													// return exfTools.date.format(sTime, 'YYYY-MM-DD HH:mm:ss.SSS');--> 2025-01-00606 13:53:48.627  day is in wrong format
													return sap.ui.core.format.DateFormat.getDateTimeInstance({pattern: 'yyyy-MM-dd HH:mm:ss.SSS'}).format(new Date(sTime));
												}
											}
										}),
										new sap.m.Text({
											text: {
												path: "speed",
												formatter: function (fSpeed) {
													return fSpeed ? fSpeed.toFixed(2) : '-';
												}
											}
										}),
										new sap.m.Text({
											text: {
												path: "method",
												formatter: function (sMethod) {
													return sMethod || '-';
												}
											}
										}),
										new sap.m.Text({
											text: {
												path: "relative_url",
												formatter: function (sUrl) {
													return sUrl || '-';
												}
											}
										}),
										new sap.m.ObjectStatus({
											text: {
												path: "network_duration",
												formatter: function (fDuration) {
													return fDuration ? fDuration.toFixed(3) : '-';
												}
											},
											state: {
												path: "network_duration",
												formatter: function (fDuration) {
													if (!fDuration) return "None";             // No duration - Default color
													if (fDuration > 1.5) return "Error";         // Over 1.5s - Red (Critical)
													if (fDuration > 0.1) return "Warning";     // 0s to 1s - Orange/Yellow (Warning) 
													return "Success";                          // Under 0.1s - Green (Good)
												}
											}
										}),
										new sap.m.Text({
											text: {
												path: "server_time",
												formatter: function (fTime) {
													return fTime ? Math.round(fTime) : '-';
												}
											}
										}),
										new sap.m.Text({
											text: {
												path: "total_duration",
												formatter: function (fDuration) {
													return fDuration ? fDuration.toFixed(3) : '-';
												}
											}
										}),
										new sap.m.Text({
											text: {
												path: "request_size",
												formatter: function (iSize) {
													return iSize ? iSize.toLocaleString() : '-';
												}
											}
										}),
										new sap.m.Text({
											text: {
												path: "response_size",
												formatter: function (iSize) {
													return iSize ? iSize.toLocaleString() : '-';
												}
											}
										}),
										new sap.m.Text({
											text: {
												path: "mime_type",
												formatter: function (sMime) {
													return sMime || '-';
												}
											}
										})
									]
								})
							}
						})
					]
				}),

				/**
				* Network Performance History Tab
				* 
				* Displays network performance statistics over time intervals
				* Provides insights into network request durations and performance categorization
				*/
				new sap.m.IconTabFilter({
					key: "networkhistory",
					text: "Network Performance History",
					content: [
						// Main performance history table
						new sap.m.Table({
							id: "networkPerformanceHistoryTable",
							growing: true,
							growingThreshold: 20,
							columns: [
								// Time interval column
								new sap.m.Column({
									header: new sap.m.Label({ text: "Time Interval" }),
									width: "200px"
								}),
								// Request count column
								new sap.m.Column({
									header: new sap.m.Label({ text: "Request Count" }),
									width: "120px"
								}),
								// Average network duration column
								new sap.m.Column({
									header: new sap.m.Label({ text: "Avg Network Duration (s)" }),
									width: "150px"
								}),
								// Performance status column
								new sap.m.Column({
									header: new sap.m.Label({ text: "Performance Status" }),
									width: "120px"
								})
							],
							// Data binding for network performance averages
							items: {
								path: "networkModel>/networkAverages",
								template: new sap.m.ColumnListItem({
									cells: [
										// Time interval cell
										new sap.m.Text({
											text: "{networkModel>interval}"
										}),
										// Request count cell
										new sap.m.Text({
											text: "{networkModel>count}"
										}),
										// Average duration cell with color-coded status
										new sap.m.ObjectStatus({
											text: {
												path: "networkModel>averageDuration",
												formatter: function (duration) {
													return duration ? duration.toFixed(3) + " s" : "-";
												}
											},
											state: {
												path: "networkModel>averageDuration",
												formatter: function (duration) {
													if (!duration) return "None";
													if (duration > 2) return "Error";
													if (duration > 1) return "Warning";
													return "Success";
												}
											}
										}),
										// Performance status cell
										new sap.m.ObjectStatus({
											text: {
												path: "networkModel>averageDuration",
												formatter: function (duration) {
													if (!duration) return "No Data";
													if (duration > 2) return "Very Slow";
													if (duration > 1) return "Slow";
													if (duration > 0.5) return "Medium";
													return "Fast";
												}
											},
											state: {
												path: "networkModel>averageDuration",
												formatter: function (duration) {
													if (!duration) return "None";
													if (duration > 2) return "Error";
													if (duration > 1) return "Warning";
													if (duration > 0.5) return "Information";
													return "Success";
												}
											}
										})
									]
								})
							}
						}),
						// Refresh button for network performance data
						new sap.m.Button({
							text: "Refresh Data",
							icon: "sap-icon://refresh",
							press: function () {
								var oTable = sap.ui.getCore().byId("networkPerformanceHistoryTable");
								if (oTable) {
									prepareNetworkAverages(oTable);
								} else {
									console.error("Performance table not found");
								}
							}
						}).addStyleClass("sapUiSmallMargin")
					]
				}),

				/**
			   * Error Pattern Configuration Tab
			   * 
			   * UI Component for managing error pattern configurations.
			   * Provides a user-friendly interface for:
			   * - Viewing all available error patterns
			   * - Enabling/disabling individual patterns
			   * - Immediate feedback on changes
			   * - Visual status of pattern states
			   */
				new sap.m.IconTabFilter({
					key: "errorPatterns",
					text: "Error Patterns",
					content: [
						new sap.m.VBox({
							items: [
								/**
								 * Information Strip
								 * Provides context and usage instructions to users
								 * Appears at the top of the configuration panel
								 */
								new sap.m.MessageStrip({
									text: "Configure which types of errors should be filtered from the error log.",
									type: "Information",
									showIcon: true,
									showCloseButton: false
								}).addStyleClass("sapUiSmallMarginBottom"),

								/**
								 * Pattern Configuration Table
								 * Main interface for pattern management
								 * 
								 * Features:
								 * - Growing behavior for large pattern lists
								 * - Responsive layout with popin for small screens
								 * - Real-time state updates
								 * - Visual feedback on changes
								 */
								new sap.m.Table({
									growing: true,                // Enable dynamic loading
									growingThreshold: 20,         // Items per page
									growingScrollToLoad: true,    // Load more on scroll
									columns: [
										new sap.m.Column({
											header: new sap.m.Label({ text: "Error Type" }),
											demandPopin: true,    // Responsive behavior
											minScreenWidth: "Tablet",
											popinDisplay: "Block"
										}),
										new sap.m.Column({
											header: new sap.m.Label({ text: "Active" }),
											hAlign: "Center",
											width: "100px",
											demandPopin: false    // Keep switch visible
										})
									],
									items: {
										path: "/patterns",        // Bind to pattern model
										template: new sap.m.ColumnListItem({
											cells: [
												/**
												 * Pattern Description Cell
												 * Shows human-readable pattern description
												 */
												new sap.m.Text({
													text: "{description}",
													wrapping: true
												}),

												/**
												 * Pattern State Control
												 * Toggle switch for enabling/disabling patterns
												 * 
												 * Features:
												 * - Immediate state change
												 * - Automatic persistence
												 * - Visual feedback
												 */
												new sap.m.Switch({
													state: "{active}",
													change: function (oEvent) {
														// Get pattern context and new state
														var oContext = oEvent.getSource().getBindingContext();
														var oPattern = oContext.getObject();
														var bNewState = oEvent.getParameter("state");

														// Update pattern state
														oPattern.active = bNewState;

														// Persist changes immediately
														oErrorPatternStorage.savePatternStates();

														// Provide user feedback
														sap.m.MessageToast.show(
															bNewState ?
																"Error pattern activated: " + oPattern.description :
																"Error pattern deactivated: " + oPattern.description,
															{ duration: 2000 }
														);

														// Log state change for debugging
														console.debug('Pattern state changed:', {
															pattern: oPattern.description,
															newState: bNewState,
															timestamp: new Date().toISOString()
														});
													}
												})
											]
										})
									}
								}).setModel(new sap.ui.model.json.JSONModel({
									patterns: aConfigurableErrorPatterns
								}))
							]
						}).addStyleClass("sapUiSmallMargin")
					]
				})

			]
		});


		/**
	 * Processes network stats into time intervals for historical analysis
	 * Uses configuration settings for interval duration and monitoring window
	 * 
	 * @param {Array} aStats - Raw network statistics array
	 * @returns {Array} Processed history records grouped by time intervals
	 */
		function processNetworkHistory(aStats) {
			// Sort stats chronologically
			aStats.sort((a, b) => a.time - b.time);

			if (aStats.length === 0) return [];

			// Get interval duration from config
			const iIntervalMs = _oConfig.network.monitoring.performanceInterval;
			const aHistory = [];

			// Calculate start time (rounded down to nearest interval)
			let iStartTime = Math.floor(aStats[0].time / iIntervalMs) * iIntervalMs;
			const iEndTime = aStats[aStats.length - 1].time;

			// Process each interval period
			for (let iTime = iStartTime; iTime <= iEndTime; iTime += iIntervalMs) {
				const iPeriodEnd = iTime + iIntervalMs;

				// Filter stats for this period
				const aPeriodStats = aStats.filter(oStat =>
					oStat.time >= iTime &&
					oStat.time < iPeriodEnd &&
					oStat.relative_url &&
					oStat.relative_url.includes('/context')
				);

				if (aPeriodStats.length > 0) {
					// Calculate metrics for the period
					const fAvgNetworkTime = aPeriodStats.reduce((fSum, oStat) =>
						fSum + oStat.network_duration, 0) / aPeriodStats.length;

					const iSlowCalls = aPeriodStats.filter(oStat =>
						oStat.network_duration > 1.5).length;

					const fSlowPercentage = (iSlowCalls / aPeriodStats.length) * 100;

					// Add period data to history
					aHistory.push({
						period: {
							start: iTime,
							end: iPeriodEnd
						},
						totalCalls: aPeriodStats.length,
						avgNetworkTime: fAvgNetworkTime,
						slowPercentage: fSlowPercentage,
						status: fSlowPercentage >= 50 ? "SLOW" : "NORMAL"
					});
				}
			}

			return aHistory;
		};

		/**
		 * Prepares network performance averages for display
		 * Groups statistics by configured time intervals
		 * 
		 * @param {sap.m.Table} oTable - Table to populate with performance data
		 * @returns {Promise} Promise resolving with network performance averages
		 */
		function prepareNetworkAverages(oTable) {
			// Validate table reference
			if (!oTable || !oTable.setModel) {
				console.error('Invalid table reference');
				return Promise.reject('Invalid table reference');
			}

			// Fetch all network statistics
			return exfPWA.network.getAllStats()
				.then(function (aStats) {
					// Sort statistics by time chronologically
					aStats.sort(function (a, b) {
						return a.time - b.time;
					});

					// Get interval duration from config
					const iIntervalMs = _oConfig.network.monitoring.performanceInterval;

					// Calculate earliest timestamp for data analysis
					const iEarliestTimestamp = aStats[0]?.time || Date.now();

					// Group statistics by intervals
					var oAverages = {};
					aStats.forEach(function (oStat) {
						// Round down to nearest interval start
						var iIntervalStart = Math.floor(oStat.time / iIntervalMs) * iIntervalMs;

						if (!oAverages[iIntervalStart]) {
							// Create new interval entry with timestamps
							oAverages[iIntervalStart] = {
								intervalStart: new Date(iIntervalStart).toLocaleTimeString(),
								intervalEnd: new Date(iIntervalStart + iIntervalMs).toLocaleTimeString(),
								intervalFullStart: new Date(iIntervalStart),
								intervalFullEnd: new Date(iIntervalStart + iIntervalMs),
								totalDuration: 0,
								count: 0
							};
						}

						// Accumulate statistics for this interval
						oAverages[iIntervalStart].totalDuration += oStat.network_duration;
						oAverages[iIntervalStart].count++;
					});

					// Calculate final averages and format intervals
					var aAverages = Object.values(oAverages).map(function (oInterval) {
						oInterval.averageDuration = oInterval.totalDuration / oInterval.count;
						oInterval.interval = `${oInterval.intervalStart} - ${oInterval.intervalEnd}`;
						return oInterval;
					});

					// Sort chronologically
					aAverages.sort((a, b) => a.intervalFullStart - b.intervalFullStart);

					// Create and set model for the table
					var oModel = new sap.ui.model.json.JSONModel({
						networkAverages: aAverages
					});

					oTable.setModel(oModel, "networkModel");
					return { averages: aAverages };
				})
				.catch(function (error) {
					console.error('Error retrieving network statistics:', error);
					return { averages: [] };
				});
		};


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
					networkstats: aStats.slice(-50), // Last 50 records
					networkhistory: processNetworkHistory(aStats)
				});
				oDialog.setModel(oModel);
			}).catch(function (oError) {
				console.error('Failed to load network data:', oError);
				oDialog.setModel(new sap.ui.model.json.JSONModel({
					connections: [],
					networkstats: []
				}));
			});

			// Network Performance History tablosunu hemen yükle
			var oTable = sap.ui.getCore().byId("networkPerformanceHistoryTable");
			if (oTable) {
				prepareNetworkAverages(oTable);
			}

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
											deviceId: exfPWA.getDeviceId(),
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
		const oButton = oEvent.getSource();
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
		const oButton = oEvent.getSource();
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

	/**
	 * Error List with SAPUI5 Model Binding
	 * 
	 * - Fixed: Undefined error issues on click events
	 * - Uses: Model binding instead of direct object access
	 * - Better: Memory management and maintainability
	 */
	var errorList = new sap.m.List({
		items: {
			// Using root path for model binding to access all error objects
			path: "/",
			template: new sap.m.StandardListItem({
				title: "{message}",
				description: {
					path: "timestamp",
					formatter: function (timestamp) {
						return new Date(timestamp).toLocaleString();
					}
				},
				type: "Active",
				wrapping: true,
				// Get error details from binding context to avoid undefined errors
				press: function (oEvent) {
					var oItem = oEvent.getSource();
					var oContext = oItem.getBindingContext();
					var error = oContext.getObject();

					// Call tracer's existing showErrorLog with single error
					if (window.exfTracer && typeof window.exfTracer.showErrorLog === 'function') {
						window.exfTracer.showErrorLog([error]);
					} else {
						// Load tracer.js if not available
						var currentScriptPath = $('script[src*="openui5.facade.js"]').attr('src');
						var tracerPath = currentScriptPath.replace('facade.js', 'tracer.js');

						$.getScript(tracerPath)
							.done(function () {
								window.exfTracer.showErrorLog([error]);
							})
							.fail(function (jqxhr, settings, exception) {
								console.error('Failed to load tracer script:', error);
								// Fallback to simple error display
								sap.m.MessageBox.error(error.stack || error.message, {
									title: "Error Details"
								});
							});
					}
				}
			})
		}
	});

	// Set the model with active errors data
	errorList.setModel(new sap.ui.model.json.JSONModel(activeErrors));

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
 * Error collection and management functionality
 * Modified console.error to manage error collection based on pattern configuration
 * 
 * Flow:
 * 1. Original error is logged normally
 * 2. Error message is checked against active patterns
 * 3. If any active pattern matches, error is collected (unless dismissed)
 * 4. Collected errors are displayed in error popover
 */
console.error = function (...args) {
	// Keep original console.error functionality
	originalConsoleError.apply(console, args);

	// Initialize tracking arrays
	window.capturedErrors = window.capturedErrors || [];
	exfLauncher.dismissedErrors = exfLauncher.dismissedErrors || new Set();

	// Format error message from arguments
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

	/**
	 * Check if error should be collected
	 * Only collect errors that match active patterns
	 * Active patterns = true -> collect matching errors
	 * Active patterns = false -> ignore matching errors
	 */
	const bShouldCollect = aConfigurableErrorPatterns
		.filter(oPattern => oPattern.active)  // Only check active patterns
		.some(oPattern => oPattern.pattern.test(errorMessage));

	// Collect error if:
	// 1. JS tracing is enabled
	// 2. Error matches an active pattern
	// 3. Error hasn't been dismissed
	if (exfLauncher.contextBar.traceJs && bShouldCollect &&
		!exfLauncher.dismissedErrors.has(errorMessage)) {

		// Create error details object
		const errorDetails = {
			message: errorMessage,
			timestamp: new Date().toISOString(),
			url: window.location.href,
			stack: new Error().stack
		};

		// Log error capture for debugging
		console.debug('Error captured:', {
			message: errorMessage,
			matchedPattern: true,
			timestamp: new Date().toISOString()
		});

		// Store error and show in popover
		window.capturedErrors.push(errorDetails);
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

/**
 * Enhanced Window Load Handler
 * 
 * Responsible for:
 * 1. Initializing the error tracking system
 * 2. Loading saved pattern configurations
 * 3. Setting up error notifications
 * 
 * @extends {Window.onload}
 */
window.onload = function () {
	// Maintain existing functionality
	if (typeof existingOnload === 'function') {
		existingOnload();
	}

	/**
	* Error Tracking Initialization
	* Reset error collection for new session
	*/
	window.capturedErrors = [];

	/**
		* Delayed Error Notification
		* Checks for accumulated errors after page load
		*/
	setTimeout(function () {
		if (window.capturedErrors.length > 0) {
			// Notify user of captured errors
			exfLauncher.showMessageToast(
				window.capturedErrors.length + ' errors occurred. Check the error log for details.',
				3000
			);

			// Log error summary for debugging
			console.debug('Initial error summary:', {
				errorCount: window.capturedErrors.length,
				timestamp: new Date().toISOString(),
				activePatterns: aConfigurableErrorPatterns.filter(p => p.active).length
			});
		}
	}, 1000);  // Delay to allow for initial page operations
};

window['exfLauncher'] = exfLauncher;
// v1
// v2
//    v3    

