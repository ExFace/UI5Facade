// Store the original console.error function
const originalConsoleError = console.error;

// Array to store captured errors
let capturedErrors = [];

// Regex patterns for errors to ignore
const ignoredErrorPatterns = [
	/Assertion failed: could not find any translatable text for key/i,
	/The target you tried to get .* does not exist!/i,
	/EventProvider sap\.m\.routing\.Targets/i,
	/Modules that use an anonymous define\(\) call must be loaded with a require\(\) call.*/i
];

// Toggle online/offlie icon
window.addEventListener('online', async function () {
	try {
		const state = await exfPWA.network.getState();
		await exfLauncher.updateNetworkState(state._bSlowNetwork, state._bAutoOffline);

		if (exfLauncher.isOnline()) {
			exfLauncher.toggleOnlineIndicator();
		}
		exfLauncher.contextBar.getComponent().getPWA().updateErrorCount();
		if (!navigator.serviceWorker) {
			syncOfflineItems();
		}
	} catch (error) {
		console.error('Error handling online event:', error);
	}
});

window.addEventListener('offline', async function () {
	try {
		const state = await exfPWA.network.getState();
		await exfLauncher.updateNetworkState(state._bSlowNetwork, state._bAutoOffline);
		exfLauncher.toggleOnlineIndicator();
	} catch (error) {
		console.error('Error handling offline event:', error);
	}
});

//
//
//


window.addEventListener('load', function () {
	exfLauncher.initPoorNetworkPoller();

	//ServiceWorker automatic update
	checkForServiceWorkerUpdate();
	if ('serviceWorker' in navigator) {
		navigator.serviceWorker.getRegistration().then(reg => {
			if (reg && reg.waiting) {
				reg.waiting.postMessage({ type: 'SKIP_WAITING' });
			}
		});
	}
});

if (navigator.serviceWorker) {
	navigator.serviceWorker.addEventListener('message', function (event) {
		exfLauncher.contextBar.getComponent().getPWA().updateQueueCount()
			.then(function () {
				exfLauncher.contextBar.getComponent().getPWA().updateErrorCount();
				exfLauncher.showMessageToast(event.data);
			})
	});
}

function syncOfflineItems() {
	if (exfPWA.network.isOfflineVirtually()) return;

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

	exfPWA.actionQueue.setTopics(['offline', 'ui5']);

	const SPEED_HISTORY_ARRAY_LENGTH = 10 * 60; // seconds for 10 minutes 

	var _oShell = {};
	var _oLauncher = this;
	var _oNetworkSpeedPoller;
	var _oSpeedStatusDialogInterval
	var _forceOffline = false;

	const _speedHistory = new Array(SPEED_HISTORY_ARRAY_LENGTH).fill(null);
	var _oConfig = {
		contextBar: {
			refreshWaitSeconds: 5
		}
	};

	// Reload context bar every 30 seconds
	setInterval(function () {
		exfLauncher.contextBar.load();
	}, 30 * 1000);



	this.initPoorNetworkPoller = function () {
		// FIXME #auto-offline
		return;
		clearInterval(_oNetworkSpeedPoller);
		_oNetworkSpeedPoller = setInterval(async function () {
			try {
				//take the network state
				const state = await exfPWA.network.getState();

				// If we are forced offline mode, stop polling
				if (state._bForcedOffline) {
					clearInterval(_oNetworkSpeedPoller);
					return;
				}

				// Check Network Speed
				const isNetworkSlow = await exfPWA.network.isNetworkSlow();

				// If auto offline is true and network is slow
				if (isNetworkSlow && state._bAutoOffline) {
					await exfLauncher.updateNetworkState(isNetworkSlow, state._bAutoOffline);
					clearInterval(_oNetworkSpeedPoller);
					exfLauncher.initFastNetworkPoller();
				}

			} catch (error) {
				console.error('Error in poor network poller:', error); 
			}
		}, 5 * 1000); // Check every 5 seconds
	};

	this.initFastNetworkPoller = function () {
		// FIXME #auto-offline
		return;
		clearInterval(_oNetworkSpeedPoller);
		_oNetworkSpeedPoller = setInterval(async function () {
			try {
				//take the network state
				const state = await exfPWA.network.getState();

				// If forced offline true, stop polling
				if (state._bForcedOffline) {
					clearInterval(_oNetworkSpeedPoller);
					return;
				}

				// check network speed
				const isNetworkSlow = await exfPWA.network.isNetworkSlow();

				// Auto offline true ve network fast 
				// or auto offline off, get back to poller
				if (!isNetworkSlow || !state._bAutoOffline) {
					await exfLauncher.updateNetworkState(isNetworkSlow, state._bAutoOffline);
					clearInterval(_oNetworkSpeedPoller);
					exfLauncher.initPoorNetworkPoller();
				} else {
					// Network hala yavaş ve auto offline açıksa state'i güncelle
					await exfLauncher.updateNetworkState(isNetworkSlow, state._bAutoOffline);
				}
			} catch (error) {
				console.error('Error in fast network poller:', error);
			}
		}, 5000); // Check every 5 seconds
	};


	/**
	 * 
	 * @returns {boolean}
	 */
	this.isOfflineVirtually = function () {
		return exfPWA.network.isOfflineVirtually();
	};


	this.isSemiOffline = async function () {
		return exfPWA.network.isOfflineVirtually();
	};

	this.isOnline = function () {
		return exfPWA.network.isOnline();
	};

	this.getShell = function () {
		return _oShell;
	};

	this.initShell = function () {
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
							icon: function () { return exfLauncher.isOnline() ? "sap-icon://connected" : "sap-icon://disconnected" }(),
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
					online: navigator.onLine,
					queueCnt: 0,
					syncErrorCnt: 0,
					deviceId: exfPWA.getDeviceId()
				}
			}));

		return _oShell;
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
				oComponent.getPWA().updateErrorCount();

				$(document).on('debugShowJsTrace', function (oEvent) {
					_oLauncher.showErrorLog();
					oEvent.preventDefault();
				});
			},

			getComponent: function () {
				return _oComponent;
			},

			load: function (delay) {
				if (delay === undefined) delay = 100;

				// Don't refresh if configured wait-time not passed yet
				if (_oContextBar.lastContextRefresh !== null && (Math.abs((new Date()) - _oContextBar.lastContextRefresh)) < _oConfig.contextBar.refreshWaitSeconds * 1000) {
					return;
				}
				_oContextBar.lastContextRefresh = new Date();

				// Do not really refresh if offline or semi-offline
				if (navigator.onLine === false || exfPWA.network.isOfflineVirtually()) {
					_oContextBar.refresh({});
					return;
				}

				window._oNetworkSpeedPoller = setInterval(function () {
					// IDEA: Measure network speed every 5 seconds 
					listNetworkStats();
				}, 1000 * 5);

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
				_oLauncher.contextBar.getComponent().getPWA().updateErrorCount();
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

	this.calculateSpeedTier = function (speedMbps) {
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
	};

	this.toggleOnlineIndicator = async function ({ lowSpeed = false } = {}) {
		try {
			// Use existing exfPWA functions
			const isOnline = exfPWA.network.isOnline();

			// Update UI
			const networkIndicator = sap.ui.getCore().byId('exf-network-indicator');
			if (networkIndicator) {
				networkIndicator.setIcon(isOnline ? 'sap-icon://connected' : 'sap-icon://disconnected');
			}

			_oShell.getModel().setProperty("/_network/online", isOnline);

			if (isOnline) {
				_oLauncher.contextBar.load();
				if (exfPWA) {
					exfPWA.actionQueue.syncOffline();
				}
			}

		} catch (error) {
			console.error('Error in toggleOnlineIndicator:', error);
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

	this.calculateSpeed = function () {
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
		const customSpeedTier = exfLauncher.calculateSpeedTier(customSpeed);

		return {
			avarageSpeed,
			speedTier,
			customSpeed,
			customSpeedAvarageLabel,
			customSpeedTier
		};
	}

	/**
	 * Shows a dialog with offline storage info (quota, preload data summary, etc.)
	 * 
	 * @return void
	 */
	this.showStorage = async function (oEvent) {

		var dialog = new sap.m.Dialog({
			title: "{i18n>WEBAPP.SHELL.NETWORK.STORAGE_HEADER}",
			icon: "sap-icon://unwired",
			afterClose: function (oEvent) {
				oEvent.getSource().destroy();
				if (_oSpeedStatusDialogInterval) {
					clearInterval(_oSpeedStatusDialogInterval);
				}
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
		} = exfLauncher.calculateSpeed();

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

		_oSpeedStatusDialogInterval = setInterval(() => {
			const {
				avarageSpeed,
				speedTier,
				customSpeedAvarageLabel,
				customSpeedTier
			} = exfLauncher.calculateSpeed();

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
						setInterval(function () {
							$("#network_speed_chart").sparkline(_speedHistory, {
								type: 'line',
								width: '100%',
								height: '100px',
								chartRangeMin: 0,
								chartRangeMax: 10,
								drawNormalOnTop: false,
							});
						}, 1000);
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



	this.getTitle = function () {
		return exfPWA.network.getStateString();
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
			oPopover.setTitle(exfPWA.network.getStateString());
		}, 1000);


		if (oPopover === undefined) {
			oPopover = new sap.m.ResponsivePopover("exf-network-menu", {
				title: exfLauncher.getTitle(),
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
										new sap.m.Switch('auto_offline_toggle', {
											state: true,
											change: function (oEvent) {
												var oSwitch = oEvent.getSource();
												if (oSwitch.getState()) {
													exfLauncher.toggleAutoOfflineOn();
												} else {
													exfLauncher.toggleAutoOfflineOff();
												}
											}
										}),
										new sap.m.Text({
											// FIXME #auto-offline
											visible: false,
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
										new sap.m.Switch('force_offline_toggle', {
											state: _forceOffline,
											//enabled: navigator.onLine, // Changed from disabled to enabled
											change: function (oEvent) {
												var oSwitch = oEvent.getSource();
												if (oSwitch.getState()) {
													exfLauncher.toggleForceOfflineOn();
												} else {
													exfLauncher.toggleForceOfflineOff();
												}
											}
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

			// Get and set initial toggle states after popover creation
			exfPWA.network.getState().then(state => {
				var autoOfflineSwitch = sap.ui.getCore().byId('auto_offline_toggle');
				var forceOfflineSwitch = sap.ui.getCore().byId('force_offline_toggle');

				if (autoOfflineSwitch) {
					autoOfflineSwitch.setState(state._bAutoOffline);
				}

				if (forceOfflineSwitch) {
					forceOfflineSwitch.setState(state._bForcedOffline);
				}
			});
		}

		jQuery.sap.delayedCall(0, this, function () {
			oPopover.openBy(oButton);
		});
	};

	this.showErrorLog = function (oEvent) {
		var oTable = new sap.m.Table({
			autoPopinMode: true,
			fixedLayout: false,
			columns: [
				new sap.m.Column({
					header: new sap.m.Label({ text: "Timestamp" }),
					width: "200px",
					hAlign: "Begin"
				}),
				new sap.m.Column({
					header: new sap.m.Label({ text: "Message" })
				}),
				new sap.m.Column({
					header: new sap.m.Label({ text: "URL" })
				}),
				new sap.m.Column({
					header: new sap.m.Label({ text: "Stack" })
				}),
				new sap.m.Column({
					header: new sap.m.Label({ text: "Network Status" }),
					width: "150px"
				}),
				new sap.m.Column({
					header: new sap.m.Label({ text: "Connection Status" }),
					width: "150px"
				})
			],
			items: {
				path: "/errors",
				template: new sap.m.ColumnListItem({
					cells: [
						new sap.m.Text({
							text: {
								path: "timestamp",
								type: new sap.ui.model.type.DateTime({
									pattern: "yyyy-MM-dd HH:mm:ss",
									UTC: true
								})
							}
						}),
						new sap.m.VBox({
							items: [
								new sap.m.Text({
									text: {
										path: "message",
										formatter: function (text) {
											return text && text.length > 100 ? text.substring(0, 100) + "..." : text;
										}
									}
								}).addStyleClass("sapUiTinyMarginBottom"),
								new sap.m.Link({
									text: {
										path: "message",
										formatter: function (text) {
											return text && text.length > 100 ? "More" : "";
										}
									},
									press: function (oEvent) {
										var oLink = oEvent.getSource();
										var oVBox = oLink.getParent();
										var oText = oVBox.getItems()[0];
										var sFullText = oEvent.getSource().getBindingContext().getObject().message;

										if (oLink.getText() === "More") {
											oText.setText(sFullText);
											oLink.setText("Less");
										} else {
											oText.setText(sFullText.substring(0, 100) + "...");
											oLink.setText("More");
										}
									}
								})
							]
						}),
						new sap.m.VBox({
							items: [
								new sap.m.Text({
									text: {
										path: "url",
										formatter: function (text) {
											return text && text.length > 100 ? text.substring(0, 100) + "..." : text;
										}
									}
								}).addStyleClass("sapUiTinyMarginBottom"),
								new sap.m.Link({
									text: {
										path: "url",
										formatter: function (text) {
											return text && text.length > 100 ? "More" : "";
										}
									},
									press: function (oEvent) {
										var oLink = oEvent.getSource();
										var oVBox = oLink.getParent();
										var oText = oVBox.getItems()[0];
										var sFullText = oEvent.getSource().getBindingContext().getObject().url;

										if (oLink.getText() === "More") {
											oText.setText(sFullText);
											oLink.setText("Less");
										} else {
											oText.setText(sFullText.substring(0, 100) + "...");
											oLink.setText("More");
										}
									}
								})
							]
						}),
						new sap.m.VBox({
							items: [
								new sap.m.Text({
									text: {
										path: "stack",
										formatter: function (text) {
											return text && text.length > 100 ? text.substring(0, 100) + "..." : text;
										}
									}
								}).addStyleClass("sapUiTinyMarginBottom"),
								new sap.m.Link({
									text: {
										path: "stack",
										formatter: function (text) {
											return text && text.length > 100 ? "More" : "";
										}
									},
									press: function (oEvent) {
										var oLink = oEvent.getSource();
										var oVBox = oLink.getParent();
										var oText = oVBox.getItems()[0];
										var sFullText = oEvent.getSource().getBindingContext().getObject().stack;

										if (oLink.getText() === "More") {
											oText.setText(sFullText);
											oLink.setText("Less");
										} else {
											oText.setText(sFullText.substring(0, 100) + "...");
											oLink.setText("More");
										}
									}
								})
							]
						}),
						new sap.m.Text({ text: "{networkStatus}" }),
						new sap.m.Text({ text: "{connectionStatus}" })
					]
				})
			}
		});

		// Update error logs and add network status information 
		var updatedErrors = capturedErrors.map(function (error) {
			return exfPWA.getConnectionStatus()
				.then(function (oStatus) {
					return {
						timestamp: new Date(error.timestamp),
						message: error.message,
						url: error.url,
						stack: error.stack,
						networkStatus: navigator.connection ? navigator.connection.effectiveType : 'Unknown',
						connectionStatus: oStatus.toString()
					};
				});
		});

		Promise.all(updatedErrors).then(function (resolvedErrors) {
			var oModel = new sap.ui.model.json.JSONModel({
				errors: resolvedErrors
			});
			oTable.setModel(oModel);

			var dialog = new sap.m.Dialog({
				title: 'Error Log',
				contentWidth: "90%",
				contentHeight: "80%",
				content: [oTable],
				endButton: new sap.m.Button({
					text: "Close",
					type: "Emphasized",
					press: function () {
						dialog.close();
					}
				}),
				beginButton: new sap.m.Button({
					text: "Dismiss All",
					press: function () {
						// Clear Error List
						capturedErrors = [];

						// Update Model 
						oTable.getModel().setProperty("/errors", []);
						exfLauncher.showMessageToast("Error log cleared");
					}
				})
			});

			dialog.open();
		});
	};

	this.toggleForceOfflineOn = function () {
		exfPWA.network.getState()
			.then(state => {
				// Only change force offline, preserve auto offline state
				return exfPWA.network.setState(true, state._bAutoOffline, state._bSlowNetwork);
			})
			.then(() => {
				_oLauncher.showMessageToast(_oLauncher.contextBar.getComponent().getModel('i18n')
					.getProperty("WEBAPP.SHELL.PWA.FORCE_OFFLINE_ON"));

				clearInterval(_oNetworkSpeedPoller);
				_oLauncher.toggleOnlineIndicator({ lowSpeed: true });

				// Update only force offline switch state
				var forceOfflineSwitch = sap.ui.getCore().byId('force_offline_toggle');
				if (forceOfflineSwitch) {
					forceOfflineSwitch.setState(true);
				}
			});
	};

	this.toggleForceOfflineOff = function () {
		// First get the current state
		exfPWA.network.getState()
			.then(state => {
				// Only change force offline, preserve auto offline state
				return exfPWA.network.setState(false, state._bAutoOffline, state._bSlowNetwork);
			})
			.then(() => {
				_oLauncher.showMessageToast(_oLauncher.contextBar.getComponent().getModel('i18n')
					.getProperty("WEBAPP.SHELL.PWA.FORCE_OFFLINE_OFF"));
	
				// Get state again to determine polling behavior
				return exfPWA.network.getState();
			})
			.then(state => {
				if (state._bAutoOffline) {
					_oLauncher.initPoorNetworkPoller();
				} else {
					_oLauncher.toggleOnlineIndicator({ lowSpeed: false });
				}
	
				// Update only force offline switch state
				var forceOfflineSwitch = sap.ui.getCore().byId('force_offline_toggle');
				if (forceOfflineSwitch) {
					forceOfflineSwitch.setState(false);
				}
			})
			.catch(error => {
				console.error('Error toggling force offline off:', error);
				_oLauncher.showMessageToast("Error toggling force offline mode off");
			});
	};

	this.toggleAutoOfflineOn = function () {
		exfPWA.network.getState()
			.then(state => {
				if (state._bForcedOffline) {
					exfLauncher.showMessageToast(exfLauncher.contextBar.getComponent().getModel('i18n')
						.getProperty("WEBAPP.SHELL.PWA.AUTO_OFFLINE_DISABLED_FORCE_OFFLINE"));
					return;
				}

				return exfPWA.network.setState(false, true, false);
			})
			.then(() => {
				return exfPWA.network.isNetworkSlow();
			})
			.then(isNetworkSlow => {
				if (isNetworkSlow) {
					exfLauncher.initFastNetworkPoller();
				} else {
					exfLauncher.initPoorNetworkPoller();
				}

				exfLauncher.toggleOnlineIndicator({ lowSpeed: isNetworkSlow });

				var i18nModel = exfLauncher.contextBar.getComponent().getModel('i18n');
				exfLauncher.showMessageToast(i18nModel.getProperty("WEBAPP.SHELL.PWA.AUTOMATIC_OFFLINE_ON"));
			})
			.catch(error => {
				console.error("Error turning on auto offline mode:", error);
				exfLauncher.showMessageToast("Error turning on auto offline mode");
			});
	};

	this.updateNetworkState = async function (isLowSpeed, isAutoOffline) {
		try {
			const currentState = await exfPWA.network.getState();

			await exfPWA.network.setState(
				currentState._bForcedOffline,
				isAutoOffline,
				isLowSpeed
			);

			// Update UI indicators
			this.toggleOnlineIndicator({
				lowSpeed: isLowSpeed
			});

			// Update network menu title if open
			const oPopover = sap.ui.getCore().byId('exf-network-menu');
			if (oPopover) {
				oPopover.setTitle(exfPWA.network.getStateString());
			}

			// Update polling behavior
			if (!exfPWA.network.isOnline()) {
				if (!currentState._bForcedOffline) {
					clearInterval(_oNetworkSpeedPoller);
					this.initFastNetworkPoller();
				}
			} else {
				clearInterval(_oNetworkSpeedPoller);
				this.initPoorNetworkPoller();
			}

			return true;

		} catch (error) {
			console.error('Failed to update network state:', error);
			return false;
		}
	};

	this.toggleAutoOfflineOff = function () {
		exfPWA.network.setState(false, false, false)
			.then(() => {
				clearInterval(_oNetworkSpeedPoller);
				exfLauncher.initPoorNetworkPoller();
				exfLauncher.toggleOnlineIndicator({ lowSpeed: false });

				var i18nModel = exfLauncher.contextBar.getComponent().getModel('i18n');
				exfLauncher.showMessageToast(i18nModel.getProperty("WEBAPP.SHELL.PWA.AUTOMATIC_OFFLINE_OFF"));
			})
			.catch(error => {
				console.error("Error turning off auto offline mode:", error);
				exfLauncher.showMessageToast("Error turning off auto offline mode");
			});
	};
}).apply(exfLauncher);


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
					// Set Cleanup interval
					if (!window.networkStatCleanupInterval) {
						window.networkStatCleanupInterval = setInterval(function () {
							const tenMinutesAgo = new Date(Date.now() - 10 * 60 * 1000);
							exfPWA.network.deleteStatsBefore(tenMinutesAgo)
								.then(() => exfPWA.network.listNetworkStats());
						}, 10 * 60 * 1000);
					}
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

	// Function to delete old network stats
	function deleteOldNetworkStats() {
		if (typeof exfPWA !== 'undefined') {
			var tenMinutesAgo = new Date(Date.now() - 10 * 60 * 1000);
			exfPWA.data.deleteNetworkStatsBefore(tenMinutesAgo)
				.then(function () {

				})
				.catch(function (error) {
					console.error("Error deleting old network stats:", error);
				});
		}
	}

	return originalAjax.call(this, newOptions);
};


function listNetworkStats() {
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
	// Close and destroy the existing popover if it's open 
	if (this.errorPopover) {
		this.errorPopover.close();
		this.errorPopover.destroy();
	}

	// Filter out dismissed errors 
	var activeErrors = capturedErrors.filter(error => !this.dismissedErrors.has(error.message));

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
						// Close the popover before showing the  log
						exfLauncher.errorPopover.close();
						// Create a dummy event object since showErrorLog  expects one
						var dummyEvent = {
							getSource: function () {
								return sap.ui.getCore().byId("exf-network-indicator");
							}
						};
						// Show the detailed error log
						exfLauncher.showErrorLog(dummyEvent);
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

	let errorMessage = args.map(arg => {
		if (arg instanceof Error) {
			return arg.stack || arg.message;
		} else if (typeof arg === 'object') {
			return JSON.stringify(arg);
		} else {
			return String(arg);
		}
	}).join(' ');

	const shouldIgnore = ignoredErrorPatterns.some(pattern => pattern.test(errorMessage));

	if (exfLauncher.contextBar.traceJs && !shouldIgnore && !exfLauncher.dismissedErrors.has(errorMessage)) {
		const errorDetails = {
			message: errorMessage,
			timestamp: new Date().toISOString(),
			url: window.location.href,
			stack: new Error().stack
		};

		capturedErrors.push(errorDetails);

		// Show or update the popover
		if (typeof exfLauncher !== 'undefined' && typeof exfLauncher.showErrorPopover === 'function') {
			exfLauncher.showErrorPopover(errorMessage);
		}
	}
};



// Store the existing window.onload function (if it exists)
var existingOnload = window.onload;

// Define the new window.onload function
window.onload = function () {
	// If there is an existing onload function, call it
	if (typeof existingOnload === 'function') {
		existingOnload();
	}

	// Clear the error list (Step 5)
	capturedErrors = [];

	// After the page has finished loading, check the error count and show toast message (Step 6)
	setTimeout(function () {
		if (capturedErrors.length > 0) {
			exfLauncher.showMessageToast(capturedErrors.length + ' errors occurred. Check the error log for details.', 3000);
		}
	}, 1000); // 1 second delay to ensure the page has fully loaded
};
window['exfLauncher'] = exfLauncher;


//ServiceWorker automatic update
function checkForServiceWorkerUpdate() {
	if ('serviceWorker' in navigator) {
		navigator.serviceWorker.getRegistration().then(reg => {
			if (reg) {
				reg.update();
			}
		});
	}
}
//v1
//v2
//v3