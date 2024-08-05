sap.ui.define([
	"sap/ui/core/UIComponent"
], function (UIComponent) {
	"use strict";

	return UIComponent.extend("[#app_id#].Component", {

		metadata: {
			manifest: "json"
		},

        init: function () {
            // call the init function of the parent
            UIComponent.prototype.init.apply(this, arguments);

            // create the views based on the url/hash
            this.getRouter().initialize();
        },
		
        /**
         * Returns the view, that the given control belongs to
         * 
         * @param sap.ui.core.Control oControl
         * 
         * @return sap.ui.core.mvc.View
         */
		findViewOfControl: function(oControl) {
			while (oControl && oControl.getParent) {
				oControl = oControl.getParent();
				if (oControl instanceof sap.ui.core.mvc.View){
					return oControl;
				}
		    }
		},
        
        convertConditionOperationToConditionGroupOperator: function(operation) {
			var map = {
				//Ascending
				//Average
				//BT
				Contains: '=',
				//Descending
				//Empty
				//EndsWith
				EQ: '==',
				GE: '>=',
				//GroupAscending
				//GroupDescending
				GT: '>',
				//Initial
				LE: '<=',
				LT: '<',
				//Maximum
				//Minimum
				//NotEmpty
				//StartsWith
				//Total
			}; 
			if (map[operation] !== undefined) {
				return map[operation];
			} else {
				throw 'UI5 Condintion operation "'+operation+'" cannot be mapped to a condition group operator!';
			}
        },
        
        /**
		 * Convenience method to create and open a dialog.
		 * 
		 * The dialog is automatically destroyed when closed.
		 * 
		 * @param string|sap.ui.core.Control mContent
		 * @param string sTitle
		 * @param string sState
		 * @param string bResponsive
		 * 
		 * @return sap.m.Dialog
		 */
        showDialog : function (sTitle, mContent, sState, onCloseCallback, bResponsive) {
    		var bStretch = bResponsive ? jQuery.device.is.phone : false;
    		var sType = sap.m.DialogType.Standard;
    		var oContent;
    		if (typeof mContent === 'string' || mContent instanceof String) {
    			oContent = new sap.m.Text({
    				text: mContent
    			});
    			sType = sap.m.DialogType.Message;
    		} else {
    			oContent = mContent;
    		}
    		var oDialog = new sap.m.Dialog({
    			title: sTitle,
    			state: sState,
    			type: sType,
    			stretch: bStretch,
    			content: oContent,
    			endButton: new sap.m.Button({
    				text: '{i18n>ERROR.BUTTON_TEXT}',
    				type: sap.m.ButtonType.Emphasized,
    				press: function () {
    					oDialog.close();
    				}
    			}),
    			afterClose: function() {
    				if (onCloseCallback) {
    					onCloseCallback();
    				}
    				oDialog.destroy();
    			}
    		}).setModel(this.getModel('i18n'), 'i18n');;
    	
    		oDialog.open();
    		return oDialog;
    	},

    	/**
    	 * Creates and opens a dialog with the given HTML as content
    	 * 
    	 * @param String sTitle
    	 * @param String sHtml
    	 * @param String sState
    	 * 
    	 * @return sap.m.Dialog
    	 */
    	showHtmlInDialog : function (sTitle, sHtml, sState) {
    		try {
	    		var oContent = new sap.ui.core.HTML().setContent(sHtml);
    		} catch (e) {
    			return this.showErrorDialog('Unkown error', sTitle, 'string');
    		}
    		return this.showDialog(sTitle, oContent, sState);
    	},
		
		/**
		 * Shows an error dialog for an AJAX error with either HTML or a UI5 JSView in the response body.
		 * 
		 * @param String sBody
		 * @param String sTitle
		 * @param String sContentType string|html|view|json
		 * 
		 * @return sap.m.Dialog
		 */
		showErrorDialog : function(sBody, sTitle, sContentType) {
			var sViewName, oBody, sState = 'Error';
			
			sBody = sBody ? sBody.trim() : '';
			if (! sContentType) {
				switch (true) {
					case sBody.startsWith('{') && sBody.endsWith('}'):
						try {
							oBody = JSON.parse(sBody);
							sContentType = 'json';
						} catch (e) {
							sContentType = 'string';
						}
						break;
					case sBody.startsWith('<') && sBody.endsWith('>'):
						sContentType = 'html';
						break;
					default:
						sContentType = 'string';
				}
			}
			
			if (sContentType === 'html' && (sViewName = this._findViewInString(sBody))) {
				sContentType = 'view';
			}
			
			switch (sContentType) {
				case 'view':
					if (! sViewName) {
						sViewName = this._findViewInString(sBody);
					}
			        return this.showViewDialog(sTitle, sViewName, sBody, 'Error');
				case 'json':
					var sMessage, sDetails, oDetailsControl, oHintControl;
					
					try {
						oBody = oBody ? oBody : JSON.parse(sBody);
						if (oBody.error) {
							var oError = oBody.error;
						} else {
							throw {};
						}
					} catch (e) {
						var oError = {
							message: sBody
						};
					}
					
					// Message
					if (oError.code || oError.title) {
						sTitle = "{i18n>MESSAGE.TYPE." + oError.type + "} {i18n>" + oError.code + "}";
						sMessage = '';
						if (oError.title) {
							sMessage += oError.title;
							sDetails = oError.message;
							if (oError.hint) {
								sDetails = oError.hint + "\n\n{i18n>ERROR.EXCEPTION_MESSAGE} " + sDetails;
							}
						} else {
							sMessage += oError.message;
						}
					} else {
						sMessage = oError.message;
					}
					
					// Title
					sTitle = sTitle ? sTitle : '';
					
					// Dialog content - just showing the message text
					var oDialogContent = new sap.m.VBox({
						items: [
							new sap.m.Text({
								text: sMessage
							})
						]
					}).addStyleClass('sapUiSmallMargin');
					
					// Add details if applicable
					if (sDetails && sDetails !== sMessage) {
						// Make sure, the error message is displayed even if the text cannot be parsed correctly!
						try {
							oDetailsControl = new sap.m.Text({
									text: sDetails,
									visible: false
								}).addStyleClass('sapUiSmallMarginTop');
							oDialogContent.addItem(oDetailsControl);
						} catch (e) {
							console.warn(e);
						}
					}
					
					// Add Log-ID reminder
					if (oError.logid) {
						oDialogContent.addItem(
							new sap.m.MessageStrip({
								text: "Log-ID " + oError.logid + ": {i18n>ERROR.LOG_ID_HINT}",
								type: "Information",
								showIcon: true
							}).addStyleClass('sapUiSmallMarginTop')
						);
					}
					
					// Show the dialog
					switch (oError.type) {
						case 'WARNING': sState = 'Warning'; break;
						case 'SUCCESS': sState = 'Success'; break;
						case 'INFO': case 'HINT': sState = 'Information'; break;
					}
					
					var oDialog = this.showDialog(sTitle, oDialogContent, sState);
					if (oDetailsControl) {
						oDialog.setBeginButton(
							new sap.m.Button({
								text: "{i18n>ERROR.DETAILS}",
								icon: "sap-icon://slim-arrow-down",
								press: function(oEvent) {
									var oBtn = oEvent.getSource();
									if (oDetailsControl.getVisible() === true) {
										oDetailsControl.setVisible(false);
										oBtn.setIcon("sap-icon://slim-arrow-down");
									} else {
										oDetailsControl.setVisible(true);
										oBtn.setIcon("sap-icon://slim-arrow-up");
									}
								}
							})
						);
					}
					return oDialog;
					
				default:
					if (sContentType === 'string') {
						if (sBody.includes('{i18n>')) {
							return this.showDialog(sTitle, new sap.m.Text({text: sBody}).addStyleClass('sapUiSmallMargin'), 'Error');
						}
						sBody = '<p class="sapUiSmallMargin">' + sBody + '</p>';
					}
					return this.showHtmlInDialog(sTitle, sBody, 'Error');
			}
		},
		
		/**
		 * 
		 * @private
		 * @param String sString
		 * @return String|Boolean
		 */
		_findViewInString : function (sString) {
			if (! sString) return false;
			var viewMatch = sString.match(/sap.ui.jsview\("(.*)"/i);
		    if (viewMatch !== null) {
		        return viewMatch[1];
		    }
		    return false;
		},
		
		/**
		 * Shows an error dialog for an AJAX error with either HTML, JSON or a UI5 JSView in the response body.
		 * 
		 * @param jqXHR jqXHR
		 * @param String sMessage
		 * 
		 * @return sap.m.Dialog
		 */
		showAjaxErrorDialog : function (jqXHR, sMessage) {
			var sContentType = jqXHR.getResponseHeader('Content-Type');
			var sBodyType;
			var sBody;
			
			if (sContentType) {
				if (sContentType.match(/json/i)) {
					sBodyType = 'json';
				} else if (sContentType.match(/html/i)) {
					sBodyType = 'html';
				}
			}
			
			if (! sMessage) {
				switch (jqXHR.status) {
					case 0: 
						sMessage = '{i18n>ERROR.NO_CONNECTION}';
						sBody = '{i18n>ERROR.NO_CONNECTION_HINT}';
						break;
					default:
						sMessage = jqXHR.status + " " + jqXHR.statusText;
						sBody = jqXHR.responseText;
				}
			}
			
			return this.showErrorDialog(sBody, sMessage, sBodyType);
		},
		
		/**
		 * Opens a sap.m.Dialog showing the view from the given JS source code.
		 * 
		 * The dialog and the view are destroyed after the dialog is closed!
		 * 
		 * @param string sTitle
		 * @param string sViewName
		 * @param string sViewSource
		 * @param string sState
		 * 
		 * @return void
		 */
		showViewDialog : function (sTitle, sViewName, sViewSource, sState) {
			var oComponent = this;
			var sViewId = oComponent.createId(sViewName);
			var sTagId = 'dynamicview_' + sViewName.replace(/\./g, '_');
			$('body').append('<script type="text/javascript" id="' + sTagId + '">' + sViewSource + '</script>');
			var fnOnClose = function(){
				$('#' + sTagId).remove();
			};
			oComponent.runAsOwner(function(){
                sap.ui.core.mvc.JSView.create({
                    id: sViewId,
                    viewName: sViewName
                })
                .then(function(oView){                    
                    setTimeout(function() {
                        var oContentCtrl = oView.getContent()[0];
                        if (oContentCtrl instanceof sap.m.Dialog) {
                        	oContentCtrl.attachAfterClose(function() {
                                oView.destroy();
                                fnOnClose();
                            });
                        	oContentCtrl.open();
                        } else {
                        	var oDialog = oComponent.showDialog(sTitle, oView, sState);
                        	var oFirstChild;
                        	var fHeight = 0;
                        	var fChildHeight = 0;
                        	
                        	// If the contents has it's own emphasized buttons, make the
                        	// dialogs close-button non-emphasized to avoid confusion
                        	if (oContentCtrl.$().find('.sapMBtnEmphasized').length > 0) {
                        		oDialog.getEndButton().setType(sap.m.ButtonType.Default);
                        	}
                        	
                        	if (oContentCtrl instanceof sap.m.Page) {
                        		oContentCtrl
                        			.setShowNavButton(false)
                        			.setEnableScrolling(false);
                        		fHeight += oContentCtrl.$().height();
                        		oFirstChild = oContentCtrl.getContent()[0];
                        		while (oFirstChild && fChildHeight == 0) {
	                        		fChildHeight = oFirstChild.$().height();
	                        		fHeight += fChildHeight;
	                        		if (oFirstChild.getContent !== undefined) {
	                        			oFirstChild = oFirstChild.getContent()[0];
	                        		} else {
	                        			oFirstChild = null;
	                        		}
                        		}
                            	oView.setHeight('100%');
                            	oDialog
    	                        	.setContentHeight((fHeight+2).toString() + 'px')
    	                    		.attachAfterClose(function() {
    		                            oView.destroy();
    		                            oDialog.destroy();
    		                            fnOnClose();
		                        });
                        	}
                        }
                    }, 0);
                })
                .catch(function(e){
                	var aMatches = e.message.match(/duplicate id '(.*)'/i);
                	var oI18nModel = oComponent.getModel('i18n');
                	var sDuplId;
                	if (aMatches !== null && aMatches.length > 0) {
                		sDuplId = aMatches[1];
                		// If a login form is open, all subsequent server requests will produce
                		// login promts in-turn. Don't show them or show a hint to fill out the
                		// first form.
                		if (sDuplId.startsWith('__LoginPrompt')) {
                			if (sap.ui.getCore().byId(sDuplId).$().parents('.sapMDialog').length === 0) {
                				oComponent.showErrorDialog(oI18nModel.getProperty('ERROR.FILL_OUT_LOGIN_FORM_FIRST'));
                			}
                			return;
                		}
                	}
            		console.error(e);
            		oComponent.showErrorDialog(oI18nModel.getProperty('ERROR.UNKNOWN_UI_ERROR') + " " + e.message, oI18nModel.getProperty('MESSAGE.ERROR_TITLE') + ' 7EDQYXT');
                	return;
                });
            });
		},
		
		_pwa : function(){
			const exfPWAUI5 = {};
			(function(){
				this.updateQueueCount = function(){
					if (exfPWA.actionQueue.get === undefined) {
						return;
					}
					return exfPWA.actionQueue.get('offline')
					.then(function(data){
						var count = data.length;
						if (!exfLauncher){
							return;
						}
						exfLauncher.getShell().getModel().setProperty("/_network/queueCnt", count);
						return count;
					})
				}
				
				this.updateErrorCount = function(){
					if (exfPWA === undefined || exfLauncher === undefined) {
						return;
					}
					if (!exfLauncher.isOnline()) {
						exfLauncher.getShell().getModel().setProperty("/_network/syncErrorCnt", '-');
						return;
					}
					return exfPWA.errors.sync()
					.then(function(data){
						var count = "-";
						if (data) {
							count = data.rows ? data.rows.length : 0;
						} 
						return count;
					})
					.then(function(sCount){
						exfLauncher.getShell().getModel().setProperty("/_network/syncErrorCnt", sCount);
					})
				}
			}).apply(exfPWAUI5);
			return exfPWAUI5;
		}(),
		
		getPWA : function(){
			return this._pwa;
		}		
	});
});
