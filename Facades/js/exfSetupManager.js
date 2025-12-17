;(function (global, factory) {
  typeof exports === 'object' && typeof module !== 'undefined' ? module.exports = factory() :
      typeof define === 'function' && define.amd ? define(factory()) :
          global.exfSetupManager = factory()
}(this, (function () { 'use strict';

  var exfSetupManager = {

    /**
     * Dexie IndexedDB configuration for widget setups database
     */
    _dexieDbConfig: {
        name: 'exf-ui5-widgets',
        version: 1,
        stores: {
            setups: '[page_id+widget_id], setup_uid, date_last_applied'
        }
    },

    /**
     * Checks whether Dexie (IndexedDb wrapper) is available in the current context
     * 
     * @returns boolean 
     */
    _bIsDexieAvailable: function() {
        if (typeof Dexie === 'undefined'){
            console.warn('Dexie.js not available, cannot manage setups in IndexedDB.');
            return false;
        }
        return true;
    },


    /**
     * Function to reset the quick select button change indicator (*)
     * 
     * @param {string} sDataTableId 
     */
    _resetQuickSelectChangeIndicator: function(sDataTableId) {
        // reset change indicator in quickselect button caption
        // this is bound to a property (configChanged) in the button model for consistency
        let oButton = sap.ui.getCore().byId(sDataTableId + '_setupQuickselectBtn');
        if (oButton){
            let oButtonModel = new sap.ui.model.json.JSONModel({
                buttonCaption: null,
                configChanged: false 
            });
            oButton.setModel(oButtonModel);
        }
    },


    /**
     * Initializes or resets the model for the quick select menu button 
     * @param {string} sButtonId Id of quickselect button
     */
    _initializeQuickSelectButtonModel: function(sButtonId) {
        let oButton = sap.ui.getCore().byId(sButtonId);
        if (oButton){
            let oButtonModel = new sap.ui.model.json.JSONModel({
                buttonCaption: null,
                configChanged: false 
            });
            if (oButton) {
                oButton.setModel(oButtonModel);
            }
        }
    },

    // UI5 DataTable specific functions
    datatable: {

        /**
         * Function to reset the data property that tracks changes attached to a data table
         * 
         * @param {string} sDataTableId 
         */
        _resetDataTableChangeProperty: function(sDataTableId) {
            let oDataTable = sap.ui.getCore().byId(sDataTableId); 
            if (oDataTable){
                oDataTable.data('_exfConfigChanged', false);
            }
        },

        /**
         * onChange function that is called when the dataTable configuration changes.
         * Sets the change tracking data property on the data table to true and updates the quick select button indicator.
         * See @trackDataTableConfigChanges
         * 
         * @param {string} sDataTableId id of the current UI5DataTable
         * @param {string} sQuickSelectButtonId id of quickselect button
         */
        _onDataTableConfigChange: function(sDataTableId, sQuickSelectButtonId) {
            let oDataTable = sap.ui.getCore().byId(sDataTableId);
            let oButton = sap.ui.getCore().byId(sQuickSelectButtonId);

            oDataTable.data('_exfConfigChanged', true);
                    
            if (oButton){
                oButton.getModel().setProperty("/configChanged", true);
            }
        },

        /**
         * Resets the change tracking (is the current configuration different from the applied setup or initial state?)
         * for a given data table and its associated quick select button.
         * 
         * @param {string} sDataTableId id of the current UI5DataTable
         */
        resetDataTableChangeTracking: function(sDataTableId) {
            exfSetupManager.datatable._resetDataTableChangeProperty(sDataTableId);
            exfSetupManager._resetQuickSelectChangeIndicator(sDataTableId);
        },

        /**
         * Tracks changes made to the DataTable configuration, including columns, sorters, filters, and manual resizes.
         * This is done by attaching event listeners to the relevant UI5 components and models and updating a change flag and indicator.
         * (see @_onDataTableConfigChange for onchange event function)
         * 
         * Can be reset usinng @resetDataTableChangeTracking
         * 
         * TODO: This is now implemented quick and easy, by attaching a bunch of event listeners;
         * But it could be refactored to track changes more meaningfully. (changing/reverting etc.) This could be done 
         * by comparing the currenty state (json stringify maybe?) of configuration to the applied setup or initial state.
         * 
         * @param {string} sDataTableId ID of current UI5DataTable
         * @param {string} sP13nId ID of the personalization dialog
         * @param {string} sP13nModelName Name of the personalization model
         * @param {string} sP13nSearchPanelId Id of the personalization filter panel
         */
        trackDataTableConfigChanges: function(sDataTableId, sP13nId, sP13nModelName, sP13nSearchPanelId) {
            // in order to only attach the event listeners once per table instance we check for a flag 
            // _exf_fnTrackSetupChangesAttached
            let oDataTable = sap.ui.getCore().byId(sDataTableId); 
            if (oDataTable && !oDataTable.data("_exf_fnTrackSetupChangesAttached")){
                
                // initilize change tracking property on data table
                oDataTable.data('_exfConfigChanged', false);

                // initialize quick select button model
                // chnages are tracked here as well, to display a change indicator (*) next to the button caption 
                let sQuickSelectButtonId = sDataTableId + '_setupQuickselectBtn';
                exfSetupManager._initializeQuickSelectButtonModel(sQuickSelectButtonId);

                // Get the P13n model and the filter panel
                // NOTE: we need the filter panel separately, as its not part of the p13n json model, 
                // but is being attached to the server requests as params at runtime
                let oDialog = sap.ui.getCore().byId(sP13nId); 
                if (oDialog == undefined){
                    return;
                }
                let oP13nModel = oDialog.getModel(sP13nModelName);
                let oFilterPanel = sap.ui.getCore().byId(sP13nSearchPanelId);

                // attach event listeners for filter, columns, sorters, manual resizes
                // (see @_onDataTableConfigChange for the onchange function)
                if (oP13nModel){
                    oP13nModel.bindProperty("/columns").attachChange(() => {
                        exfSetupManager.datatable._onDataTableConfigChange(sDataTableId, sQuickSelectButtonId);
                    });
                    oP13nModel.bindProperty("/sorters").attachChange(() => {
                        exfSetupManager.datatable._onDataTableConfigChange(sDataTableId, sQuickSelectButtonId);
                    });
                }
                if (oFilterPanel) {
                    oFilterPanel.attachEvent("addFilterItem", (oEvent) => {
                        exfSetupManager.datatable._onDataTableConfigChange(sDataTableId, sQuickSelectButtonId); 
                    });
                    oFilterPanel.attachEvent("updateFilterItem", (oEvent) => {
                        exfSetupManager.datatable._onDataTableConfigChange(sDataTableId, sQuickSelectButtonId);
                    });
                    oFilterPanel.attachEvent("removeFilterItem", (oEvent) => {
                        exfSetupManager.datatable._onDataTableConfigChange(sDataTableId, sQuickSelectButtonId);
                    });
                }
                oDataTable.attachEvent("columnResize", (oEvent) => {
                    if (oDataTable.data("_exfIsAutoResizing")) {
                        return; // ignore automatic resizes
                    }
                    exfSetupManager.datatable._onDataTableConfigChange(sDataTableId, sQuickSelectButtonId); 
                });

                // update flag that listners are attached to table instance
                oDataTable.data("_exf_fnTrackSetupChangesAttached", true);
            }
        },

        /**
         * Collects and returns the current configuration of a ui5 data table (columns, advanced search, sorters) in JSON format.
         *  - Columns: column_name, show, custom_width (if manually resized)
         *  - Advanced Search: attribute_alias, comparator, value, exclude
         *  - Sorters: attribute_alias, direction
         * 
         * Example: 
         * 
         * {
         *  "columns": 
         *      [    
         *          { "column_name": "COL1", "show": true, "custom_width": "150px" },
         *      ],
         *  "advanced_search": 
         *      [ 
         *          { "attribute_alias": "ATTR1", "comparator": "Contains", "value": "Test", "exclude": false },
         *      ],
         * "sorters": 
         *      [
         *          { "attribute_alias": "ATTR2", "direction": "Ascending" },
         *      ]
         * }
         * 
         * @param {string} sDataTableId id of the current UI5DataTable
         * @param {string} sP13nId id of the personalization dialog
         * @param {string} sP13nModelName name of the personalization model
         * @param {string} sP13nSearchPanelId name of the personalization filter panel (advanced search)
         * @returns JSON object in widget_setup format representing the current configuration of the data table (columns, advanced_search, sorters)
         */
        getDataTableConfiguration : function(sDataTableId, sP13nId, sP13nModelName, sP13nSearchPanelId) {

            // json object to save current configuration state in
            let oSetupJson = {
                columns: [],
                advanced_search: [],
                sorters: []
            };

            // get the current states
            // NOTE: we need the filter panel separately, as its not part of the p13n json model (oP13nModel), 
            // but is being attached to the server requests as params at runtime
            let oDialog = sap.ui.getCore().byId(sP13nId); 
            let oP13nModel = oDialog.getModel(sP13nModelName); 
            let aColumns = oP13nModel.getProperty('/columns');
            let aSorters = oP13nModel.getProperty('/sorters');
            let aFilters = sap.ui.getCore().byId(sP13nSearchPanelId).getFilterItems();

            // save current column config
            if (aColumns !== undefined && aColumns.length > 0) {
                aColumns.forEach(function(oColumn) {
                    if (oColumn.column_name != null){
                        // save column_name and visibility
                        oSetupJson.columns.push({
                            column_name: oColumn.column_name,
                            show: oColumn.visible
                        });
                    }
                });
            }

            // loop through table columns (not the p13n model)
            // and add any custom (manually resized) widths to the setup config
            let oTable = sap.ui.getCore().byId(sDataTableId); 
            if (oTable != null){
                oTable.getColumns().forEach(function(oCol){

                    // if a column has a manually resized width, add it to the config
                    let sCustomWidth = oCol.data("_exfCustomColWidth");
                    if (sCustomWidth) {

                        // find the column in the setup config
                        let oColumnEntry = oSetupJson.columns.find(function(column) {
                                return column.column_name === oCol.data("_exfDataColumnName");
                        });
                        
                        // save custom width in setup
                        if (oColumnEntry){
                            oColumnEntry.custom_width = sCustomWidth;
                        }
                    }
                });
            }

            // save sorters
            if (aSorters !== undefined && aSorters.length > 0) {
                aSorters.forEach(function(oColumn) {
                    oSetupJson.sorters.push({
                        attribute_alias: oColumn.attribute_alias,
                        direction: oColumn.direction
                    });
                });
            }

            // save filters/advanced search
            if (aFilters !== undefined && aFilters.length > 0) {
                aFilters.forEach(function(oFilter){
                    oSetupJson.advanced_search.push({
                        attribute_alias: oFilter.mProperties.columnKey,
                        comparator: oFilter.mProperties.operation,
                        value: oFilter.mProperties.value1,
                        exclude: oFilter.mProperties.exclude
                    });
                });
            }

            return oSetupJson;
        },

        /**
         * Applies a given configuration/widget_setup JSON to a ui5 data table.
         * 
         * @param {string} sDataTableId Id of the ui5 DataTable
         * @param {string} sP13nModelName Name of the configuration model
         * @param {string} sP13nColumnsPanelId Id of the columns panel in the p13n dialogue
         * @param {string} sP13nSortPanelId Id of the sorters panel in the p13n dialogue
         * @param {string} sP13nSearchPanelId Id of the advanced search panel in the p13n dialogue
         * @param {object} oSetupJson JSON object in widget_setup format containing the configuration to apply
         * @returns 
         */
        applyDataTableConfiguration : function(sDataTableId, sP13nModelName, sP13nColumnsPanelId, sP13nSortPanelId, sP13nSearchPanelId, oSetupJson) {
            
            // setup configuration
            let oSetupUxon = oSetupJson;
            if (!oSetupUxon) {
                return;
            }

            // Apply setup
            if (oSetupUxon.columns !== undefined){
                // COLUMN SETUP
                let oDialog = sap.ui.getCore().byId(sP13nColumnsPanelId);
                let oModel = oDialog.getModel(sP13nModelName);
                let oInitModel = oDialog.getModel(sP13nModelName+'_initial');
                let aInitCols = JSON.parse(JSON.stringify(oInitModel.getData()['columns'])); // deep copy to avoid reference issues
                let aColumnSetup = oSetupUxon.columns;
                let oDataTable = sap.ui.getCore().byId(sDataTableId); 

                // reset current custom width properties of the table columns
                // only do this for ui.table
                if (oDataTable && oDataTable instanceof sap.ui.table.Table) {
                    oDataTable.getColumns().forEach(oCol => {
                        oCol.data("_exfCustomColWidth", null);
                    });
                }
            
                // build the new column model:
                let aNewColModel = [];
                
                // loop through the widget setup columns
                aColumnSetup.forEach(oItem => {

                    // skip entries without attribute alias or column_name 
                    // (eg. faulty or older setups)
                    if (oItem.attribute_alias == null && oItem.column_name == null){
                        return;
                    }

                    // find the corresponding column (by column_name) in the p13n model
                    // -> also ensure backwards compatiblity with attribute alias
                    let oColumnEntry = null;
                    if (oItem.attribute_alias != null){
                        // old: attribute alias columns
                        oColumnEntry = aInitCols.find(function(column) {
                            return column.attribute_alias === oItem.attribute_alias;
                        });
                    }
                    else if (oItem.column_name != null){
                        // new: column_name columns
                        oColumnEntry = aInitCols.find(function(column) {
                            return column.column_name === oItem.column_name;
                        });
                    }

                    // if column exists, set visibility of column according to setup
                    // and add to new config model (check if id is already in config, to avoid duplicates here)
                    if (oColumnEntry && aNewColModel.some(col => col && col.column_id === oColumnEntry.column_id) === false) {
                        oColumnEntry.visible = oItem.show;
                        aNewColModel.push(oColumnEntry);
                    }

                    // if column has a custom width assigned (in widget setup), set column width to that value 
                    // and also set the data property on the column (so they dont get optimized/resized in buildJsUiTableColumnResize)
                    // this is only done with ui.table
                    if (oItem.custom_width && oItem.custom_width != '' && oDataTable && oDataTable instanceof sap.ui.table.Table) {
                        
                        // find the actual column in the table (not p13n model)
                        let oMatchingCol = null;
                        if (oItem.column_name != null){
                            oMatchingCol = oDataTable.getColumns().find(function(oCol) {
                                return oCol.data("_exfDataColumnName") === oItem.column_name;
                            });
                        }
                        else{
                            // attribute alias cols (older setups)
                            oMatchingCol = oDataTable.getColumns().find(function(oCol) {
                                return oCol.data("_exfAttributeAlias") === oItem.attribute_alias;
                            });
                        }
                        
                        // if column exists, set custom width (and also custom width data property)
                        if (oMatchingCol) {
                            oMatchingCol.data("_exfCustomColWidth", oItem.custom_width);
                            oMatchingCol.setWidth(oItem.custom_width);
                        }
                    }
                });

                // add any missing columns back in as hidden columns at the end; 
                // this avoids data loss when columns are missing in widget setup, 
                // or if columns were added to the table later on (and the setup is older)
                aInitCols.forEach(oColConf => {
                    let oColumnEntry = null;
                    oColumnEntry = aNewColModel.find(function(column) {
                        return column.column_id === oColConf.column_id;
                    });
                    if (oColumnEntry) {
                        // column already in new model, skip
                        return;
                    }

                    oColConf.visible = false;
                    aNewColModel.push(oColConf);
                });
                oModel.setProperty('/columns', aNewColModel);

                // toggle checkboxes in columns tab according to setup
                // otherwise the UI doesnt seem to get updated, since we dont manually interact with the checkboxes
                let oTable = oDialog.getAggregation('content')[1].getAggregation('content')[0];
                let oTableModel = oTable.getModel();
                let aColsConfig = oModel.getProperty('/columns');
                let oVisibleFilter = new sap.ui.model.Filter("toggleable", sap.ui.model.FilterOperator.EQ, true);
                oDialog.getBinding("items").filter(oVisibleFilter);
                let aItems = oTableModel.getProperty('/items');
                
                aColsConfig.forEach(function(oColConfig){
                    aItems.forEach(function(oItem, iItemIdx){
                        if (oItem.columnKey === oColConfig.column_id) {
                            oItem.persistentSelected = oColConfig.visible; 
                            return;
                        }
                    })
                }); 

            }
            if (oSetupUxon.sorters !== undefined) {
                // SORTER SETUP
                let oDialog = sap.ui.getCore().byId(sP13nSortPanelId);
                let oModel = oDialog.getModel(sP13nModelName);
                let aSorterSetup = oSetupUxon.sorters;

                let aSortItems = [];
                aSorterSetup.forEach(oItem => {
                    aSortItems.push({
                        attribute_alias: oItem.attribute_alias,
                        direction: oItem.direction
                    });
                });

                oModel.setProperty("/sorters", aSortItems);
            }
            if (oSetupUxon.advanced_search !== undefined) {
                // ADVANCED SEARCH SETUP

                // remove and re-add filters from config
                let aFilterSetup = oSetupUxon.advanced_search;
                let oDialog = sap.ui.getCore().byId(sP13nSearchPanelId);
                oDialog.removeAllFilterItems();

                aFilterSetup.forEach(oItem => {
                    var oFilterItem = new sap.m.P13nFilterItem({
                        "columnKey": oItem.attribute_alias,
                        "exclude": oItem.exclude,
                        "operation": oItem.comparator,
                        "value1": oItem.value
                    });
                    oDialog.addFilterItem(oFilterItem);
                });
            }
        },

    },

    

    /**
     * Helper function that returns a passed value as a resolved promise (immediately) or retrieves a specified key from the widget_setup IndexedDB entry
     * This is needed because the indexedDB calls are asynchronous, so we need to work with promises either way.
     * 
     * Example: in apply_setup, we either need to use the passed setupUxon fron the input data (if we press the applySetup button) or we need 
     * to retrieve it from indexedDb (when auto-applying steups onLoad). In both cases we need to work with a promise, because the apply_setup logic uses .then() etc.
     * 
     * @param {string} sPageId the page identifier, e.g. 'page1' (part of the IndexedDb pk)
     * @param {string} sWidgetId the widget identifier, e.g. 'myTable' (part of the IndexedDb pk)
     * @param {string|null} sPassedData if a value is passed, it will be returned immediately as a resolved promise
     * @param {string|null} sKey the key of the value to retrieve from indexedDb, e.g. 'setup_uxon' or 'setup_uid'
     * @returns {Promise<string|null>} a promise that resolves to the requested setup data or null if not found
     */
    getSetupData: function(sPageId, sWidgetId, sPassedData = null, sKey = null) {
        // If data is passed in function, return it immediately as a resolved promise
        if (sPassedData !== null) {
            return Promise.resolve(sPassedData);
        }

        // get entry from indexed db and return the requested key
        return exfSetupManager.getCurrentSetupFromDexie(sPageId, sWidgetId)
        .then(entry => {
            if (entry && entry[sKey]) {
                if (sKey === 'setup_uxon') {
                    return JSON.parse(entry[sKey]); //parse setup uxon
                }
                return entry[sKey]; //return other values as is
            }
            return null;
        })
        .catch(err => {
            console.error('Error reading from IndexedDB:', err);
            return null;
        });
    },

    /**
     * Function that returns the current setup entry for a given page and widget from the IndexedDB, if it exists.
     * can be checked/used with .then(entry => ...) to access the values 
     * 
     * @param {string} sPageId Id of the current page
     * @param {string} sWidgetId id of the current widget
     * @returns Promise resolving to the current setup entry from IndexedDB, if it exists
     */
    getCurrentSetupFromDexie: function(sPageId, sWidgetId) {

        // if dexie is not available for some reason, return null
        if (! exfSetupManager._bIsDexieAvailable()){
            return Promise.resolve(null);
        }

        // open indexedDb connection 
        const oSetupsDb = new Dexie(exfSetupManager._dexieDbConfig.name);
        oSetupsDb.version(exfSetupManager._dexieDbConfig.version).stores(exfSetupManager._dexieDbConfig.stores);

        // check if setup exists for page+widget pk and return the entry
        return oSetupsDb.setups.get([sPageId, sWidgetId])
        .catch(err => {
            console.error('Error reading from IndexedDB:', err);
        })
        .finally(() => {
            oSetupsDb.close();
        });
    },

    /**
     * Deletes the current setup entry for a given page and widget from the IndexedDB, if it exists. 
     * Does not do anything if no entry exists.
     * 
     * @param {string} sPageId id of the current page
     * @param {string} sWidgetId id of the current widget
     * @returns Promise that resolves when the deletion is complete
     */
    deleteCurrentSetupFromDexie: function(sPageId, sWidgetId) {

        // if dexie is not available for some reason, return null
        if (! exfSetupManager._bIsDexieAvailable()){
            return Promise.resolve(null);
        }

        // open indexedDb connection 
        const oSetupsDb = new Dexie(exfSetupManager._dexieDbConfig.name);
        oSetupsDb.version(exfSetupManager._dexieDbConfig.version).stores(exfSetupManager._dexieDbConfig.stores);

        // delete entry if it exists
        return oSetupsDb.setups.delete([sPageId, sWidgetId])
        .catch(err => {
            console.error('Error deleting entry from IndexedDB:', err);
        })
        .finally(() => {
            oSetupsDb.close();
        });
    },

    /**
     * Saves or updates a passed setup as the last applied setup for a given page and widget in the IndexedDB. 
     * 
     * @param {string} sPageId current page id (part of dexie pk)
     * @param {string} sWidgetId current widget id (part of dexie pk)
     * @param {string} sSetupUid current widget setup uid (used to identify the applied setup/adding checkmark in setups table)
     * @param {string} sSetupUxon current widget setup uxon
     * @param {string} sSetupName current widget setup name
     */
    saveLastAppliedSetupToDexie: function(sPageId, sWidgetId, sSetupUid, sSetupUxon, sSetupName) { 
        
        // setup entry to store in dexie.
        // combination of page and widget id as primary key for db entry
        let oSetupObj = {
            page_id: sPageId,
            widget_id: sWidgetId,
            setup_uid: sSetupUid,
            date_last_applied: new Date().toISOString(),
            setup_uxon: sSetupUxon,
            setup_name: sSetupName
        };

        // exit if dexie not available
        if (! exfSetupManager._bIsDexieAvailable()){
            return;
        }

        // open indexedDb connection
        const oSetupsDb = new Dexie(exfSetupManager._dexieDbConfig.name);
        oSetupsDb.version(exfSetupManager._dexieDbConfig.version).stores(exfSetupManager._dexieDbConfig.stores);

        // Save setup in db, then close connection 
        oSetupsDb.setups.put(
            oSetupObj
        ).catch(err => {
            console.error('Error accessing IndexedDb:', err);
        }).finally(() => {
            oSetupsDb.close();
        });
    },

    /**
     * Attaches an event listener that marks the currently applied setup as active (with checkmark icon) in the setups table at runtime.
     * If the setup was applied manually, the table model is refreshed once to trigger the event listener.
     * 
     * @param {string} sSetupsTableId Id of the table that lists the setups
     * @param {string} sPageId current page id (used to retrieve data from indexedDb)
     * @param {string} sWidgetId current widget id (used to retrieve data from indexedDb)
     * @param {boolean} bIsManuallyApplied whether the setup was applied manually or onLoad/form local storage (default: false)
     * @returns 
     */
    markCurrentSetupAsActive: function(sSetupsTableId, sPageId, sWidgetId, bIsManuallyApplied = false) {

        // get the ui5 datatable that lists the setups
        let oSetupTable = sap.ui.getCore().byId(sSetupsTableId);
        if (oSetupTable == undefined){
            return;
        }
        
        // attach event listener to that table, to mark the currently applied setup as active (with checkmark icon)
        if (!oSetupTable.data("_exf_fnSetAsDefaultAttached")){
            // the setups table seems to refresh on every re-open so its not enough to set in once,
            // so it needs to be some sort of event listener that re-sets it on re-open/update
            let fnSetAsDefault = function(oEvent) {
                // retrieve the currently applied setup uid from indexedDb/session storage
                exfSetupManager.getSetupData(sPageId, sWidgetId, null, 'setup_uid')
                .then(sSetupUid => {
                    let oModel = oSetupTable.getModel();
                    let oData = oModel.getProperty('/');
                    if (oData && Array.isArray(oData.rows) && oData.rows.length > 0) {
                        oData.rows.forEach(row => {
                            row.SETUP_APPLIED = "";
                            if (row.UID === sSetupUid) {
                                row.SETUP_APPLIED = "sap-icon://accept";
                            }
                        });

                        oModel.setProperty('/rows', oData.rows);
                    }
                });
            };

            oSetupTable.detachUpdateFinished(fnSetAsDefault);
            oSetupTable.attachUpdateFinished(fnSetAsDefault);

            // make sure listener is only attached once
            oSetupTable.data("_exf_fnSetAsDefaultAttached", true);
        }

        // if setup is manually applied, refresh ui to trigger event listener
        let oModel = oSetupTable.getModel();
        if (bIsManuallyApplied === true){
            oModel.refresh(true);
        }
    },

    /**
     * Updates the caption of the setup quickselect button to the currently applied setup name.
     * (The caption name is saved in the model of the button, to ensure consistency. If the ) 
     * 
     * @param {string} sPageId Id of the current page
     * @param {string} sWidgetId Id of the current widget
     * @param {string} sDataTableId Id of the current data table (to find the quickselect button)
     */
    updateQuickSelectButtonCaption: function(sPageId, sWidgetId, sDataTableId) {

        // get the currently applied setup name from indexedDb
        // and update the quickselect button caption accordingly
        exfSetupManager.getSetupData(sPageId, sWidgetId, null, 'setup_name')
        .then(sSetupName => {
            if (sSetupName !== null){
                let oButton = sap.ui.getCore().byId(sDataTableId + '_setupQuickselectBtn');
                if (oButton){
                    // update the caption of the quickselect btn if it exists
                    oButton.getModel().setProperty("/buttonCaption", sSetupName); 
                }
            }
        });
    }

  }
  return exfSetupManager;
}))); 