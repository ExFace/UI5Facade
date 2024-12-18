
/**
 * OpenUI5 Error Tracking Module
 * 
 * This module handles visualization of error logs that were captured by the main openui5.facade.
 * It implements a separation of concerns where:
 * 1. Error capturing remains in facade.js for early initialization
 * 2. Error visualization is moved here to reduce initial load time
 * 3. Module is loaded dynamically only when debug mode is active
 * 
 * Performance Benefits:
 * - Reduces initial bundle size for regular users
 * - Loads visualization code only when needed through debug context
 * - Maintains error capturing from page load in facade.js
 */
(function (global) {
    var exfTracer = {
        /**
         * Displays a dialog showing the list of captured errors
         * Gets error data that was collected by facade.js
         * 
         * @param {Array} aErrors - Array of error objects to display
         * @returns {void}
         */
        showErrorLog: function (aErrors) {
            console.log('showErrorLog called with errors:', aErrors); // For debugging
            var self = this;

            // Input validation and error array initialization
            if (!aErrors || !Array.isArray(aErrors)) {
                console.log('No errors provided, using global capturedErrors'); // For debugging
                aErrors = window.capturedErrors || [];
            }

            // Early return if no errors to display
            if (!aErrors || aErrors.length === 0) {
                console.log('No errors to display'); // For debugging
                sap.m.MessageToast.show("No errors to display");
                return;
            }

            // Log the errors we’ll be displaying
            console.log('Processing errors:', aErrors); // For debugging

            /**
                * Shows detailed information for a single error in a dialog
                * 
                * @param {Object} oError - Error object containing message, stack trace etc.
            */
            function showErrorDetail(error) {
                var oDetailDialog = new sap.m.Dialog({
                    title: "Error Details",
                    contentWidth: "60%",
                    contentHeight: "60%",
                    state: "Error",
                    content: [
                        new sap.m.VBox({
                            items: [
                                new sap.m.MessageStrip({
                                    text: "Error Time: " + new Date(error.timestamp).toLocaleString(),
                                    type: "Error",
                                    showIcon: true
                                }).addStyleClass("sapUiSmallMarginBottom"),
                                new sap.m.Panel({
                                    headerText: "Error Message",
                                    expandable: true,
                                    expanded: true,
                                    content: [
                                        new sap.m.Text({
                                            text: error.message
                                        })
                                    ]
                                }),
                                new sap.m.Panel({
                                    headerText: "Stack Trace",
                                    expandable: true,
                                    expanded: true,
                                    content: [
                                        new sap.m.TextArea({
                                            value: error.stack,
                                            rows: 10,
                                            width: "100%",
                                            editable: false,
                                            growing: true
                                        })
                                    ]
                                }),
                                new sap.m.Panel({
                                    headerText: "Additional Information",
                                    expandable: true,
                                    expanded: true,
                                    content: [
                                        new sap.m.List({
                                            items: [
                                                new sap.m.DisplayListItem({
                                                    label: "URL",
                                                    value: error.url
                                                }),
                                                new sap.m.DisplayListItem({
                                                    label: "Network Type",
                                                    value: error.networkStatus
                                                }),
                                                new sap.m.DisplayListItem({
                                                    label: "Connection Status",
                                                    value: error.connectionStatus
                                                })
                                            ]
                                        })
                                    ]
                                })
                            ]
                        }).addStyleClass("sapUiSmallMargin")
                    ],
                    beginButton: new sap.m.Button({
                        text: "Copy to Clipboard",
                        press: function () {
                            var errorText = `Error Details:
                                Time: ${new Date(error.timestamp).toLocaleString()}
                                Message: ${error.message}
                                Stack: ${error.stack}
                                URL: ${error.url}
                                Network: ${error.networkStatus}
                                Connection: ${error.connectionStatus}`;
                            navigator.clipboard.writeText(errorText).then(function () {
                                sap.m.MessageToast.show("Error details copied to clipboard");
                            });
                        }
                    }),
                    endButton: new sap.m.Button({
                        text: "Close",
                        press: function () {
                            oDetailDialog.close();
                        }
                    }),
                    afterClose: function () {
                        oDetailDialog.destroy();
                    }
                });
                oDetailDialog.open();
            }

            var oTable = new sap.m.Table({
                autoPopinMode: true,
                fixedLayout: false,
                columns: [
                    new sap.m.Column({
                        header: new sap.m.Label({ text: "Time" }),
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
                    }),
                    new sap.m.Column({
                        header: new sap.m.Label({ text: "Actions" }),
                        width: "100px"
                    })
                ],
                items: {
                    path: "/aErrors",
                    template: new sap.m.ColumnListItem({
                        type: "Active",
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
                            self._createExpandableCell("message"),
                            self._createExpandableCell("url"),
                            self._createExpandableCell("stack"),
                            new sap.m.Text({ text: "{networkStatus}" }),
                            new sap.m.Text({ text: "{connectionStatus}" }),
                            new sap.m.Button({
                                icon: "sap-icon://detail-view",
                                type: "Transparent",
                                tooltip: "Show Details",
                                press: function (oEvent) {
                                    var oContext = oEvent.getSource().getBindingContext();
                                    var error = oContext.getObject();
                                    showErrorDetail(error);
                                }
                            })
                        ]
                    })
                }
            });

            // Process errors and enrich with additional information
            var aProcessedErrors = aErrors.map(function (error) {
                try {
                    const oNetState = window.exfPWA.network.getState();
                    return {
                        timestamp: new Date(error.timestamp || new Date()),
                        message: error.message || 'No message',
                        url: error.url || window.location.href,
                        stack: error.stack || 'Stack trace unavailable',
                        networkStatus: navigator.connection ? navigator.connection.effectiveType : 'Unknown',
                        connectionStatus: oNetState ? oNetState.toString() : 'Unknown'
                    };
                } catch (e) {
                    console.warn('Error occurred while processing error:', e);
                    return null;
                }
            }).filter(Boolean);

            var oModel = new sap.ui.model.json.JSONModel({
                aErrors: aProcessedErrors
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
                    text: "Clear All",
                    press: function () {
                        window.capturedErrors = [];
                        oTable.getModel().setProperty("/aErrors", []);
                        window.exfLauncher.showMessageToast("Error log cleared");
                        dialog.close();
                    }
                }),
                afterClose: function () {
                    dialog.destroy();
                }
            });

            dialog.open();
        },

        _createExpandableCell: function (bindingPath) {
            return new sap.m.VBox({
                items: [
                    new sap.m.Text({
                        text: {
                            path: bindingPath,
                            formatter: function (text) {
                                return text && text.length > 100 ? text.substring(0, 100) + "..." : text;
                            }
                        }
                    }).addStyleClass("sapUiTinyMarginBottom"),
                    new sap.m.Link({
                        text: {
                            path: bindingPath,
                            formatter: function (text) {
                                return text && text.length > 100 ? "More" : "";
                            }
                        },
                        press: function (oEvent) {
                            var oLink = oEvent.getSource();
                            var oVBox = oLink.getParent();
                            var oText = oVBox.getItems()[0];
                            var sFullText = oEvent.getSource().getBindingContext().getObject()[bindingPath];

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
            });
        }
    };

    // Add to global scope
    global.exfTracer = exfTracer;

})(window);
