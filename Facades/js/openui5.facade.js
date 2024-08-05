// Toggle online/offlie icon
window.addEventListener('online', function(){
	if (exfLauncher.isOnline()) {
		exfLauncher.toggleOnlineIndicator();
	}
	exfLauncher.contextBar.getComponent().getPWA().updateErrorCount();
	if(!navigator.serviceWorker){
		exfPWA.actionQueue.getIds('offline')
		.then(function(ids) {
			var count = ids.length;
			if (count > 0){
				var shell = exfLauncher.getShell();
				shell.setBusy(true);				
				exfPWA.actionQueue.syncIds(ids)
				.then(function(){
					exfLauncher.contextBar.getComponent().getPWA().updateQueueCount();
					exfLauncher.contextBar.getComponent().getPWA().updateErrorCount();
					return;
				})
				.then(function(){
					shell.setBusy(false);
					var text = exfLauncher.contextBar.getComponent().getModel('i18n').getProperty("WEBAPP.SHELL.NETWORK.SYNC_ACTIONS_COMPLETE");
					exfLauncher.showMessageToast(text);
					return;
				})
			}
		})
		.catch(function(error){
			shell.setBusy(false);
			exfLauncher.showMessageToast("Cannot synchronize offline actions: " + error);
		})
	}
});
window.addEventListener('offline', function(){
	exfLauncher.toggleOnlineIndicator();
});

window.addEventListener('load', function() {
	exfLauncher.initPoorNetworkPoller();
});

if (navigator.serviceWorker) {
	navigator.serviceWorker.addEventListener('message', function(event) {
		exfLauncher.contextBar.getComponent().getPWA().updateQueueCount()
		.then(function(){
			exfLauncher.contextBar.getComponent().getPWA().updateErrorCount();
			exfLauncher.showMessageToast(event.data);
		})
	});
}

const exfLauncher = {};
(function() {
	
	exfPWA.actionQueue.setTopics(['offline', 'ui5']);
	
	var _oShell = {};
	var _oAppMenu;
	var _oLauncher = this;
	var _oNetworkSpeedPoller;
	var _oSpeedStatusDialogInterval
	var _bLowSpeed = false;
	var _forceOffline = false;
	var _autoOffline = true;
	var _speedHistory = [null, null, null, null, null, null, null, null, null, null, null, null, null, null];
	var _oConfig = {
		contextBar: {
			refreshWaitSeconds: 5
		}
	};

	const SPEEDTEST_DUMMY_DATA_NAME = 'speedtest.data.json';

	this.initFastNetworkPoller = function() {
		clearInterval(_oNetworkSpeedPoller);
		_oNetworkSpeedPoller = setInterval(function(){
			if(!exfLauncher.isNetworkSlow() && _bLowSpeed) {
				exfLauncher.showMessageToast(exfLauncher.contextBar.getComponent().getModel('i18n').getProperty("WEBAPP.SHELL.PWA.AUTOMATIC_OFFLINE_STABLE_INTERNET"));
				exfLauncher.toggleOnlineIndicator();
				exfLauncher.revertMockNetworkError();
				_bLowSpeed = false;
				clearInterval(_oNetworkSpeedPoller);
				exfLauncher.initPoorNetworkPoller();
			}
		}, 5000);
	}

	this.initPoorNetworkPoller = function() {
		clearInterval(_oNetworkSpeedPoller);
		_oNetworkSpeedPoller = setInterval(function(){
			if(exfLauncher.isNetworkSlow()){
				exfLauncher.showMessageToast(exfLauncher.contextBar.getComponent().getModel('i18n').getProperty("WEBAPP.SHELL.PWA.AUTOMATIC_OFFLINE_SLOW_INTERNET"));
				exfLauncher.toggleOnlineIndicator({ lowSpeed: true});
				exfLauncher.mockNetworkError();
				_bLowSpeed = true;
				clearInterval(_oNetworkSpeedPoller);
				exfLauncher.initFastNetworkPoller();
			}
		}, 5000);
	};


	this.isNetworkSlow = function() {
		// Check if the network speed is slow via browser API (Chrome, Opera, Edge)
		if (navigator?.connection?.effectiveType) {
			return ['2g', 'slow-2g'].includes(navigator.connection.effectiveType);
		} 
		// Check if the network speed is slow via network speed history (iOS, Android, Firefox)
		else {
			let customSpeed;
			if (_speedHistory.indexOf(null) === -1) {
				customSpeed = _speedHistory[_speedHistory.length - 1];
			} else if (_speedHistory.indexOf(null) === 0) {
				customSpeed = 100;
			} else {
				customSpeed = _speedHistory[_speedHistory.indexOf(null) - 1];
			}
			return customSpeed < 0.5;
		}
	}



	this.isOnline = function() {
		return !_bLowSpeed && !_forceOffline && navigator.onLine;
	};

	this.sendMessageToServiceWorker = function (message) {
		if (navigator?.serviceWorker?.controller) {
			navigator.serviceWorker.controller.postMessage(message);
		} else {
			navigator.serviceWorker.ready.then((registration) => {
				if (registration.active) {
					registration.active.postMessage(message);
				}
			});
		}
	}	

	// Revert network functions to original
	this.revertMockNetworkError = function() {
		window.fetch = window._originalFetch;
		window.XMLHttpRequest = window._originalXMLHttpRequest;
		exfLauncher.sendMessageToServiceWorker({ action: 'virtuallyOfflineDisabled' });
	};

	// Simulate network error in poor network speeds, except for specific URLs
	this.mockNetworkError = function() {
		// Store original network functions
		window._originalFetch = window.fetch;
		window._originalXMLHttpRequest = window.XMLHttpRequest;

		function MockFetch (input, init) {
			// Check if the URL contains dummy data name
			if (typeof input === "string" && input.includes(SPEEDTEST_DUMMY_DATA_NAME)) {
				return window._originalFetch(input, init);
			}
			return new Promise((resolve, reject) => {
				reject(new Error('Network error'));
			});
		};


		function MockXHR() {
			const xhr = new window._originalXMLHttpRequest();
			const self = this;
	
			// Manually copy event handler properties
			const eventHandlers = [
				"onreadystatechange", "onload", "onerror", "onprogress", 
				"onabort", "ontimeout", "onloadstart", "onloadend"
			];
			eventHandlers.forEach(handler => {
				Object.defineProperty(self, handler, {
					get: function() { return xhr[handler]; },
					set: function(val) { xhr[handler] = val; }
				});
			});
	
			// Manually copy response properties
			const responseProperties = [
				"readyState", "responseText", "response", "status", "statusText", 
				"responseType", "responseURL", "responseXML"
			];
			responseProperties.forEach(prop => {
				Object.defineProperty(self, prop, {
					get: function() { return xhr[prop]; }
				});
			});
	
			// Bind the methods
			for (const attr in xhr) {
				if (typeof xhr[attr] === 'function') {
					this[attr] = xhr[attr].bind(xhr);
				}
			}
	
			this.open = function(method, url) {
				if (url.includes(SPEEDTEST_DUMMY_DATA_NAME)) {
					this._url = url;
					xhr.open.apply(xhr, arguments);
				}
			};
	
			this.send = function(...args) {
				// Check if the URL contains dummy data name
				if (this._url && this._url.includes(SPEEDTEST_DUMMY_DATA_NAME)) {
					xhr.send.apply(xhr, args);
				} else {
					setTimeout(() => {
						if (self.onreadystatechange) {
							self.readyState = 4;
							self.status = 0;
							self.onreadystatechange();
						}
					}, 0);
				}
			};
		}
	

		// Replace network functions with mock functions
		window.XMLHttpRequest = MockXHR;
		window.fetch = MockFetch;
		exfLauncher.sendMessageToServiceWorker({ action: 'virtuallyOfflineEnabled' });
	};

	this.getShell = function() {
		return _oShell;
	};
	
	this.initShell = function() {
		_oShell = new sap.ui.unified.Shell({
			header: [
				new sap.m.OverflowToolbar({
		            design: "Transparent",
					content: [
						new sap.m.Button({
		                    icon: "sap-icon://menu2",
		                    layoutData: new sap.m.OverflowToolbarLayoutData({priority: "NeverOverflow"}),
		                    press: function() {
		                    	_oShell.setShowPane(! _oShell.getShowPane());
		            		}
		                }),
		                new sap.m.OverflowToolbarButton("exf-home", {
		                	text: "{i18n>WEBAPP.SHELL.HOME.TITLE}",
							icon: "sap-icon://home",
		                	press: function(oEvent){
		                		oBtn = oEvent.getSource();
		                		sap.ui.core.BusyIndicator.show(0); 
		                		window.location.href = oBtn.getModel().getProperty('/_app/home_url');
                			}
		                }),
		                new sap.m.ToolbarSpacer(),
		                new sap.m.Button("exf-pagetitle", {
		                    text: "{/_app/home_title}",
		                    //icon: "sap-icon://navigation-down-arrow",
		                    iconFirst: false,
		                    layoutData: new sap.m.OverflowToolbarLayoutData({priority: "NeverOverflow"}),
		                    press: function(oEvent) {
		                    	oBtn = oEvent.getSource();
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
		                    icon: function(){return exfLauncher.isOnline() ? "sap-icon://connected" : "sap-icon://disconnected"}(),
		                    text: "{/_network/queueCnt} / {/_network/syncErrorCnt}",
		                    layoutData: new sap.m.OverflowToolbarLayoutData({priority: "NeverOverflow"}),
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
	
	this.contextBar = function(){
		var _oComponent = {};
		var _oContextBar = {
			lastContextRefresh : null,
			init : function (oComponent) {
				_oComponent = oComponent;
				
				// Give the shell the translation model of the component
				_oShell.setModel(oComponent.getModel('i18n'), 'i18n');
				
				oComponent.getRouter().attachRouteMatched(function (oEvent){
					_oContextBar.load();
				});
				
				$(document).ajaxSuccess(function(event, jqXHR, ajaxOptions, data){
					var extras = {};
					if (jqXHR.responseJSON){
						extras = jqXHR.responseJSON.extras;
					} else {
						try {
							extras = $.parseJSON(jqXHR.responseText).extras;
						} catch (err) {
							extras = {};
						}
					}
					if (extras && extras.ContextBar){
						_oContextBar.refresh(extras.ContextBar);
					} else {
						_oContextBar.load();
					}
				});
				oComponent.getPWA().updateQueueCount();
				oComponent.getPWA().updateErrorCount();
			},
		
			getComponent : function() {
				return _oComponent;
			},

			load : function(delay){
				if (delay === undefined) delay = 100;
				
				// Don't refresh if configured wait-time not passed yet
				if (_oContextBar.lastContextRefresh !== null && (Math.abs((new Date()) - _oContextBar.lastContextRefresh)) < _oConfig.contextBar.refreshWaitSeconds * 1000) {
					return;
				}
				_oContextBar.lastContextRefresh = new Date();
				
				if (navigator.onLine === false) {
					_oContextBar.refresh({});
					return;
				}
				
				exfLauncher.measureNetworkSpeed();

				if (window._oNetworkSpeedPoller) {
					clearInterval(window._oNetworkSpeedPoller);
				}

 				window._oNetworkSpeedPoller = setInterval(function(){
					// IDEA: Measure network speed every 15 seconds
					exfLauncher.measureNetworkSpeed();
				}, 1000 * 60);

			setTimeout(function(){
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
							success: function(data, textStatus, jqXHR) {
								_oContextBar.refresh(data);
							},
							error: function(jqXHR, textStatus, errorThrown){
								_oContextBar.refresh({});
							}
						});
					/*} else {
						_oContextBar.load(delay*3);
					}*/
				}, delay);
			},

			refresh : function(data){
				var oToolbar = _oShell.getHeader();
				var aItemsOld = _oShell.getHeader().getContent();
				var iItemsIndex = 5;
				var oControl = {};
				var oCtxtData = {};
				var sColor;
				
				oToolbar.removeAllContent();
				
				for (var i=0; i<aItemsOld.length; i++) {
					oControl = aItemsOld[i];
					if (i < iItemsIndex || oControl.getId() == 'exf-network-indicator' || oControl.getId() == 'exf-pagetitle' || oControl.getId() == 'exf-user-icon') {
						oToolbar.addContent(oControl);
					} else {
						oControl.destroy();
					}
				}
				
				for (var id in data) {
					oCtxtData = data[id];
					sColor = oCtxtData.color ? 'background-color:'+oCtxtData.color+' !important;' : '';
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
							press: function(oEvent) {
								var oButton = oEvent.getSource();
								_oContextBar.showMenu(oButton);
							}
						}).data('widget', oCtxtData.bar_widget_id, true), 
						iItemsIndex
					);
				}
				_oLauncher.contextBar.getComponent().getPWA().updateQueueCount();
				_oLauncher.contextBar.getComponent().getPWA().updateErrorCount();
			},

			showMenu : function (oButton){
				var sPopoverId = oButton.data('widget')+"_popover";
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
						afterClose: function(oEvent) {
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
					            press: function() {oPopover.close();},
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
					success: function(data, textStatus, jqXHR) {			
						var viewMatch = data.match(/sap.ui.jsview\("(.*)"/i);
			            if (viewMatch !== null) {
			                var view = viewMatch[1];
			            } else {
			            	_oComponent.showAjaxErrorDialog(jqXHR);
			            }
			            
			            var oPopoverPage = oPopover.getContent()[0].getPages()[0];			            
			            var oView = _oComponent.runAsOwner(function() {
		            		return sap.ui.view({type:sap.ui.core.mvc.ViewType.JS, viewName:view});
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
	            		
	            		// After-open events
		            	oPopoverPage.addContent(oView);
            			
            			oEvent = jQuery.Event("AfterShow", oNavInfoOpen);
            			oEvent.srcControl = oPopover.getContent()[0];
            			oEvent.data = {};
            			oEvent.backData = {};
            			oView._handleEvent(oEvent);
            			
                        oView.fireAfterRendering();
                        
                        // TODO need close-events here?

			        	oPopover.setBusy(false);
						
					},
					error: function(jqXHR, textStatus, errorThrown){
						oButton.setBusy(false);
						_oComponent.showAjaxErrorDialog(jqXHR);
					}
				});
			}
		};
		return _oContextBar;
	}();

	this.getPageId = function(){
		return $("meta[name='page_id']").attr("content");
	};

	this.registerNetworkSpeed = function(speedMbps) {
		const minusOneIndex = _speedHistory.indexOf(null);
		if (minusOneIndex !== -1) {
			_speedHistory[minusOneIndex] = speedMbps;
		} else {
			_speedHistory.shift();
			_speedHistory.push(speedMbps);
		}
	};

	this.calculateSpeedTier = function(speedMbps) {
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

	this.measureNetworkSpeed = function() {
		const startTime = new Date();
		let endTime;
		$.ajax({
			type: 'GET',
			url: `vendor/exface/UI5Facade/Facades/js/${SPEEDTEST_DUMMY_DATA_NAME}`,
			dataType: 'json',
			success: function(data, textStatus, jqXHR) {
				endTime = new Date();
				const contentLength = 50 * 1024; // 50KB
				const durationMs = endTime - startTime; // Duration in milliseconds
				const durationSeconds = durationMs / 1000; // Convert duration to seconds
				const contentLengthBits = contentLength * 8; // Convert content length to bits
				const speedMbps = (contentLengthBits / (durationSeconds * 1024 * 1024)).toFixed(1); // Calculate speed in Megabits per second (Mbit/s)
				let speedClass;
				exfLauncher.registerNetworkSpeed(speedMbps);
			},
			error: function(jqXHR, textStatus, errorThrown) {
				console.error(errorThrown);
			}
		});
	};

	this.toggleOnlineIndicator = function({ lowSpeed = false } = {}) {
		const isOnline = navigator.onLine && !lowSpeed;

		sap.ui.getCore().byId('exf-network-indicator').setIcon(isOnline ? 'sap-icon://connected' : 'sap-icon://disconnected');
		_oShell.getModel().setProperty("/_network/online", isOnline);
		if (exfLauncher.isOnline()) {
			_oLauncher.contextBar.load();
			if (exfPWA) {
				exfPWA.actionQueue.syncOffline();
			}
		}
	};
	
	this.showMessageToast = function(message){
		sap.m.MessageToast.show(message);
		return;
	};

	this.calculateSpeed = function() {
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
	this.showStorage = async function(oEvent) {
		
		var dialog = new sap.m.Dialog({
			title: "{i18n>WEBAPP.SHELL.NETWORK.STORAGE_HEADER}",
			icon: "sap-icon://unwired",
			afterClose: function(oEvent) {
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
            press: function() {dialog.close();},
        });
		dialog.addButton(button);
		list = new sap.m.List({});
		//check if possible to acces storage (means https connection)
		if (navigator.storage && navigator.storage.estimate) {
			var promise = navigator.storage.estimate()
			.then(function(estimate) {
				list = new sap.m.List({
					items: [
						new sap.m.GroupHeaderListItem({
							title: "{i18n>WEBAPP.SHELL.NETWORK.STORAGE_OVERVIEW}",
							upperCase: false
						}),
						new sap.m.DisplayListItem({
							label: "{i18n>WEBAPP.SHELL.NETWORK.STORAGE_TOTAL}",
							value: Number.parseFloat(estimate.quota/1024/1024).toFixed(2) + ' MB'
						}),
						new sap.m.DisplayListItem({
							label: "{i18n>WEBAPP.SHELL.NETWORK.STORAGE_USED}",
							value: Number.parseFloat(estimate.usage/1024/1024).toFixed(2) + ' MB'
						}),
						new sap.m.DisplayListItem({
							label: "{i18n>WEBAPP.SHELL.NETWORK.STORAGE_PERCENTAGE}",
							value: Number.parseFloat(100/estimate.quota*estimate.usage).toFixed(2) + ' %'
						})
					]
				});
				if (estimate.usageDetails) {
					list.addItem(new sap.m.GroupHeaderListItem({
							title: "{i18n>WEBAPP.SHELL.NETWORK.STORAGE_DETAILS}",
							upperCase: false
					}));
					Object.keys(estimate.usageDetails).forEach(function(key) {
						list.addItem(new sap.m.DisplayListItem({
								label: key,
								value: Number.parseFloat(estimate.usageDetails[key]/1024/1024).toFixed(2) + ' MB'
							})
						);
					});
				}				
			})
			.catch(function(error) {
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

		const oBrowserCurrentSpeedTierItem = new sap.m.DisplayListItem('browser_speed_tier_display',{
			label: "{i18n>WEBAPP.SHELL.NETWORK_SPEED_TIER}",
			value: speedTier,
		});

		const oBrowserCurrentSpeedItem = new sap.m.DisplayListItem('browser_speed_display',{
			label: "{i18n>WEBAPP.SHELL.NETWORK_SPEED}",
			value: avarageSpeed,
		});

		const oCustomCurrentSpeedTierItem = new sap.m.DisplayListItem('custom_speed_tier_display', {
			label: "{i18n>WEBAPP.SHELL.NETWORK_SPEED_TIER_CUSTOM}",
			value: customSpeedTier,
		});

		const oCustomCurrentSpeedItem = new sap.m.DisplayListItem('custom_speed_display',{
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
				content: new sap.ui.core.HTML('network_speed_chart_wrapper',{
					content:'<div id="network_speed_chart"></div>',
					afterRendering: function() {
						setInterval(function(){
							$("#network_speed_chart").sparkline(_speedHistory, {
								type: 'line',
								width: '100%',
								height: '100px',
								chartRangeMin: 0,
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
							press: function(oEvent) {
								var oButton = oEvent.getSource();
								var oTable = oButton.getParent().getParent();
								oTable.setBusy(true);
								_oLauncher.syncOffline(oEvent)
									.then(function(){
										_oLauncher.loadPreloadInfo(oTable);
										oTable.setBusy(false);
									})
									.catch(function(){
										oTable.setBusy(false);
									})
							},
						}),
						new sap.m.Button({
							text: "{i18n>WEBAPP.SHELL.PWA.MENU_RE_SYNC}",
							tooltip: "{i18n>WEBAPP.SHELL.PWA.MENU_RE_SYNC_TOOLTIP}",
							icon: "sap-icon://synchronize",
							enabled: "{/_network/online}",
							press: function(oEvent) {
								var oButton = oEvent.getSource();
								var oTable = oButton.getParent().getParent();
								oTable.setBusy(true);
								_oLauncher.reSyncOffline(oEvent)
									.then(function(){
										_oLauncher.loadPreloadInfo(oTable);
										oTable.setBusy(false);
									})
									.catch(function(){
										oTable.setBusy(false);
									})
							},
						}),
						new sap.m.Button({
							text: "{i18n>WEBAPP.SHELL.PWA.MENU_RESET}",
							tooltip: "{i18n>WEBAPP.SHELL.PWA.MENU_RESET_TOOLTIP}",
							icon: "sap-icon://sys-cancel",
							press: function(oEvent) {
								var oButton = oEvent.getSource();
								var oTable = oButton.getParent().getParent();
								oTable.setBusy(true);
								_oLauncher.clearPreload(oEvent)
								.then(function(){
									_oLauncher.loadPreloadInfo(oTable);
									oTable.setBusy(false);
								})
								.catch(function(){
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
		.catch(function(error) {
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
	this.loadPreloadInfo = function(oTable) {
		return exfPWA.data.getTable().toArray()
		.then(function(dbContent){
			oTable.removeAllItems();
			dbContent.forEach(function(element) {
				oRow = new sap.m.ColumnListItem();
				oRow.addCell(new sap.m.Text({text: element.object_name}));
				if (element.rows) {
					oRow.addCell(new sap.m.Text({text: element.rows.length}));
					oRow.addCell(new sap.m.Text({text: new Date(element.last_sync).toLocaleString()}));
				} else {
					oRow.addCell(new sap.m.Text({text: '0'}));

					oRow.addCell(new sap.m.Text({text: '{i18n>WEBAPP.SHELL.NETWORK.STORAGE_NOT_SYNCED}'}));
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
	this.showOfflineQueuePopoverForItem = function(sObjectAlias, sUidColumn, sUidValue, oTrigger) {	
		var oPopover = new sap.m.Popover({
			title: "{i18n>WEBAPP.SHELL.NETWORK.QUEUE_TABLE_WAITING_ACTIONS}",
			placement: "Right",
			afterClose: function(oEvent) {
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
        .then(function(aEffects){
			var oData = {
				rows: []
			};
			aEffects.forEach(function(oEffect){
				var oRow = oEffect.offline_queue_item;
				// TODO filter over sUidColumn, sUidValue passed to the method here! Otherwise
				// it shows all actions for the object, not only those effecting the row!
				oRow.effect_name = oEffect.name;
				oData.rows.push(oRow);
			});
			oPopover.setModel(function(){return new sap.ui.model.json.JSONModel(oData)}(), 'queueModel');
		})
		.catch(function(data){
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
	this.showOfflineQueue = function(oEvent){
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
							press: function(oEvent){
								var oButton = oEvent.getSource();																					
								var table = oButton.getParent().getParent()
								var selectedItems = table.getSelectedItems();																					
								if (selectedItems.length ===0) {
									var text = exfLauncher.contextBar.getComponent().getModel('i18n').getProperty("WEBAPP.SHELL.NETWORK.NO_SELECTION");
									_oLauncher.showMessageToast(text);
									return;
								}
								oButton.setBusyIndicatorDelay(0).setBusy(true);
								var selectedIds = [];
								selectedItems.forEach(function(item){
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
										press: function(oEvent){
											exfPWA.actionQueue.deleteAll(selectedIds)
											.then(function(){
												_oLauncher.contextBar.getComponent().getPWA().updateQueueCount()
											})
											.then(function(){
												confirmDialog.close();
												oButton.setBusy(false);
												var text = exfLauncher.contextBar.getComponent().getModel('i18n').getProperty("WEBAPP.SHELL.NETWORK.ENTRIES_DELETED");
												_oLauncher.showMessageToast(text);
												return exfPWA.actionQueue.get('offline')
											})
											.then(function(data){
												var oData = {};
												oData.data = data;
												oTable.setModel(function(){return new sap.ui.model.json.JSONModel(oData)}(), 'queueModel');
												return;
											})
										}
									}),
									endButton: new sap.m.Button({
										text: "{i18n>WEBAPP.SHELL.NETWORK.CONFIRM_NO}",
										type: sap.m.ButtonType.Default,
										press: function(oEvent){
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
							press: function(oEvent){
								var oButton = oEvent.getSource();
								var table = oButton.getParent().getParent()
								var selectedItems = table.getSelectedItems();
								if (selectedItems.length ===0) {
									var text = exfLauncher.contextBar.getComponent().getModel('i18n').getProperty("WEBAPP.SHELL.NETWORK.NO_SELECTION");
									_oLauncher.showMessageToast(text);
									return;
								}
								oButton.setBusyIndicatorDelay(0).setBusy(true);
								var selectedIds = [];
								selectedItems.forEach(function(item){
									var bindingObj = item.getBindingContext('queueModel').getObject()
									selectedIds.push(bindingObj.id);
								})																					
								exfPWA.actionQueue.syncIds(selectedIds)
								.then(function(){
									_oLauncher.contextBar.getComponent().getPWA().updateQueueCount();
									_oLauncher.contextBar.getComponent().getPWA().updateErrorCount();
								})
								.then(function(){
									oButton.setBusy(false);
									var text = exfLauncher.contextBar.getComponent().getModel('i18n').getProperty("WEBAPP.SHELL.NETWORK.SYNC_ACTIONS_COMPLETE");
									_oLauncher.showMessageToast(text);
									return exfPWA.actionQueue.get('offline')
								})
								.then(function(data){
									var oData = {};
									oData.data = data;
									oTable.setModel(function(){return new sap.ui.model.json.JSONModel(oData)}(), 'queueModel');
									return;
								})
								.catch(function(error){
									console.error('Offline action sync error: ', error);
									_oLauncher.contextBar.getComponent().getPWA().updateQueueCount()
									.then(function(){
										_oLauncher.contextBar.getComponent().getPWA().updateErrorCount();
										oButton.setBusy(false);
										_oLauncher.contextBar.getComponent().showErrorDialog(error, '{i18n>WEBAPP.SHELL.NETWORK.QUEUE_TABLE_HEADER}');
										return exfPWA.actionQueue.get('offline')
									})
									.then(function(data){
										var oData = {};
										oData.data = data;
										oTable.setModel(function(){return new sap.ui.model.json.JSONModel(oData)}(), 'queueModel');
										return;
									})
									return;
								})
							},
						}),
						new sap.m.Button({
							text: "{i18n>WEBAPP.SHELL.NETWORK.QUEUE_TABLE_EXPORT}",
							icon: "sap-icon://download",
							press: function(oEvent){
								var oButton = oEvent.getSource();																					
								var table = oButton.getParent().getParent()
								var selectedItems = table.getSelectedItems();																					
								if (selectedItems.length ===0) {
									var text = exfLauncher.contextBar.getComponent().getModel('i18n').getProperty("WEBAPP.SHELL.NETWORK.NO_SELECTION");
									_oLauncher.showMessageToast(text);
									return;
								}
								oButton.setBusyIndicatorDelay(0).setBusy(true);
								var selectedIds = [];
								selectedItems.forEach(function(item){
									var bindingObj = item.getBindingContext('queueModel').getObject()
									selectedIds.push(bindingObj.id);
								})
								exfPWA.actionQueue.getByIds(selectedIds)
								.then(function(aQItems) {
									var oData = {
										deviceId: _pwa.getDeviceId(),
										actions: aQItems
									};
									var sJson = JSON.stringify(oData);
									var date = new Date();
									var dateString = date.toISOString();
									dateString = dateString.substr(0,16);
									dateString = dateString.replace(/-/gi,"");
									dateString = dateString.replace("T","_");
									dateString = dateString.replace(":","");
									oButton.setBusyIndicatorDelay(0).setBusy(false);
									exfPWA.download(sJson,'offlineActions_'+ dateString, 'application/json')
									var text = exfLauncher.contextBar.getComponent().getModel('i18n').getProperty("WEBAPP.SHELL.NETWORK.ENTRIES_EXPORTED");
									_oLauncher.showMessageToast(text);
									return;
								})
								.catch(function(error) {
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
		.then(function(data){
			var oData = {};
			oData.data = data;
			oTable.setModel(function(){return new sap.ui.model.json.JSONModel(oData)}(), 'queueModel');
			_oLauncher.contextBar.getComponent().showDialog('{i18n>WEBAPP.SHELL.NETWORK.QUEUE_TABLE_HEADER}', oTable, undefined, undefined, true);
		})
		.catch(function(data){
			var oData = {};
			oData.data = data;
			oTable.setModel(function(){return new sap.ui.model.json.JSONModel(oData)}());
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
	this.showOfflineErrors = function(oEvent){
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
		
		exfPWA.errors.sync()
		.then(function(data){
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
			oTable.setModel(function(){return new sap.ui.model.json.JSONModel(oData)}(), 'errorModel');
			_oLauncher.contextBar.getComponent().showDialog('{i18n>WEBAPP.SHELL.NETWORK.ERROR_TABLE_ERRORS}', oTable, undefined, undefined, true);
		})
	};
	
	/**
	 * Loads all preload data from the server since the last increment
	 * 
	 * @param {sap.ui.base.Event} [oEvent]
	 * 
	 * @return Promise
	 */
	this.syncOffline = function(oEvent){
		oButton = oEvent.getSource();
		oButton.setBusyIndicatorDelay(0).setBusy(true);
		var oI18nModel = oButton.getModel('i18n');
		return exfPWA.syncAll()
		.then(function(){
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
	this.reSyncOffline = function(oEvent){
		oButton = oEvent.getSource();
		oButton.setBusyIndicatorDelay(0).setBusy(true);
		var oI18nModel = oButton.getModel('i18n');
		return exfPWA.syncAll({doReSync: true})
			.then(function(){
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
	this.clearPreload = function(oEvent){
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
		switch (true) {
			case !navigator.onLine:
				return "Offline, No Internet";
			case _forceOffline:
				return "Offline, Forced";
			case _autoOffline && _bLowSpeed:
				return "Offline, Low Speed";
			default:
				return "Online";
		}
	}
	
	/**
	 * Shows the offline menu
	 * 
	 * @param {sap.ui.base.Event} [oEvent]
	 * 
	 * @return void
	 */
	this.showOfflineMenu = function(oEvent){
    	_oLauncher.contextBar.getComponent().getPWA().updateQueueCount();
    	_oLauncher.contextBar.getComponent().getPWA().updateErrorCount();
		var oButton = oEvent.getSource();
		var oPopover = sap.ui.getCore().byId('exf-network-menu');
		const titleInterval = setInterval(function(){
			oPopover.setTitle(exfLauncher.getTitle());
		}, 1000);
		if (oPopover === undefined) {
			oPopover = new sap.m.ResponsivePopover("exf-network-menu", {
				title: exfLauncher.getTitle(),
				placement: "Bottom",
				content: [
					new sap.m.MessageStrip({
						text: "Offline sync not available.",
						type:"Warning",
						showIcon: true,
						visible: (! exfPWA.isAvailable())
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
											state: _autoOffline,
											disabled: !navigator.onLine,
											change: function(oEvent){
												var oSwitch = oEvent.getSource();
												if (oSwitch.getState()) {
													exfLauncher.toggleAutoOfflineOn();
												} else {
													exfLauncher.toggleAutoOfflineOff();
												}
											}
										}),
										new sap.m.Text({
											text: "{i18n>WEBAPP.SHELL.NETWORK_AUTOMATIC_OFFLINE}"
										}),
									],
								}),
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
										new sap.m.Switch('force_offline_toggle', {
											state: _forceOffline,
											disabled: !navigator.onLine,
											change: function(oEvent){
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
									style: "padding-left: 1rem; padding-right: 1rem;",
								}),
							}),
						]
					})
				],
				endButton: [
					new sap.m.Button({
						icon: 'sap-icon://font-awesome/close',
			            text: "{i18n>CONTEXT.BUTTON.CLOSE}",
			            press: function() {oPopover.close();},
			        })
					
				],
				afterClose: function(oEvent) {
					clearInterval(titleInterval);
				}
			})
			.setModel(oButton.getModel())
			.setModel(oButton.getModel('i18n'), 'i18n');
		}
		
		jQuery.sap.delayedCall(0, this, function () {
			oPopover.openBy(oButton);
		});
	};
	this.toggleForceOfflineOn = function() {
		exfLauncher.showMessageToast(exfLauncher.contextBar.getComponent().getModel('i18n').getProperty("WEBAPP.SHELL.PWA.FORCE_OFFLINE_ON"));
		// mock api with error function, if it is not already mocked via low speed
		if (!_bLowSpeed) {
			exfLauncher.mockNetworkError();
		} else {
			_bLowSpeed = false;
		}
		// clear auto low speed poller
		clearInterval(_oNetworkSpeedPoller);
		_forceOffline = true;
		exfLauncher.toggleOnlineIndicator({ lowSpeed: true });
		sap.ui.getCore().byId('auto_offline_toggle').setEnabled(false);	
	}

	this.toggleForceOfflineOff = function() {
		exfLauncher.showMessageToast(exfLauncher.contextBar.getComponent().getModel('i18n').getProperty("WEBAPP.SHELL.PWA.FORCE_OFFLINE_OFF"));
		exfLauncher.revertMockNetworkError()
		_bLowSpeed = false;
		_forceOffline = false;
		// restart auto low speed poller
		if (_autoOffline) {
			exfLauncher.initPoorNetworkPoller()
		}
		exfLauncher.toggleOnlineIndicator();
		sap.ui.getCore().byId('auto_offline_toggle').setEnabled(true);
	}

	this.toggleAutoOfflineOn = function() {
		exfLauncher.showMessageToast(exfLauncher.contextBar.getComponent().getModel('i18n').getProperty("WEBAPP.SHELL.PWA.AUTOMATIC_OFFLINE_ON"));
		exfLauncher.initPoorNetworkPoller()
		_autoOffline = true;
	}

	this.toggleAutoOfflineOff = function() {
		exfLauncher.showMessageToast(exfLauncher.contextBar.getComponent().getModel('i18n').getProperty("WEBAPP.SHELL.PWA.AUTOMATIC_OFFLINE_OFF"));
		_autoOffline = false;
		clearInterval(_oNetworkSpeedPoller);
		if (_bLowSpeed) {
			exfLauncher.revertMockNetworkError();
			exfLauncher.toggleOnlineIndicator({ lowSpeed: false });
		}
		_bLowSpeed = false;
	}
}).apply(exfLauncher);