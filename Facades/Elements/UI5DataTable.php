<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Actions\iReadData;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JqueryDataTableTrait;
use exface\Core\Interfaces\Widgets\iSupportMultiSelect;
use exface\Core\Widgets\DataTableResponsive;
use exface\UI5Facade\Facades\Elements\Traits\UI5DataElementTrait;
use exface\Core\Widgets\DataColumn;
use exface\Core\Widgets\DataButton;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JsConditionalPropertyTrait;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\DataTypes\SortingDirectionsDataType;
use exface\Core\Exceptions\Widgets\WidgetLogicError;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Interfaces\Actions\iModifyData;
use exface\Core\Interfaces\Actions\iCallOtherActions;
use exface\UI5Facade\Facades\Interfaces\UI5DataElementInterface;
use exface\Core\Widgets\Parts\DataRowGrouper;
use exface\Core\Widgets\DataTable;
use exface\Core\DataTypes\NumberDataType;

/**
 *
 * @method \exface\Core\Widgets\DataTable getWidget()
 *
 * @author Andrej Kabachnik
 *
 */
class UI5DataTable extends UI5AbstractElement implements UI5DataElementInterface
{    
    use JsConditionalPropertyTrait;
    
    use UI5DataElementTrait, JqueryDataTableTrait {
       buildJsDataLoaderOnLoaded as buildJsDataLoaderOnLoadedViaTrait;
       buildJsConstructor as buildJsConstructorViaTrait;
       getCaption as getCaptionViaTrait;
       init as initViaTrait;
       UI5DataElementTrait::buildJsResetter insteadof JqueryDataTableTrait;
       UI5DataElementTrait::buildJsDataResetter as buildJsDataResetterViaTrait;
    }
    
    const EVENT_NAME_FIRST_VISIBLE_ROW_CHANGED = 'firstVisibleRowChanged';
    
    const CONTROLLER_METHOD_RESIZE_COLUMNS = 'resizeColumns';

    /**
     * This JS controller property will hold an object of optional column instances
     * with data_column_name as key and sap.ui.table.Column or sap.m.Column as value.
     * @var string
     */
    const CONTROLLER_VAR_OPTIONAL_COLS = 'optionalCols';
    
    protected function init()
    {
        $this->initViaTrait();
        $this->getConfiguratorElement()->setIncludeColumnsTab(true);
    }
    
    protected function buildJsConstructorForControl($oControllerJs = 'oController') : string
    {
        $widget = $this->getWidget();
        $controller = $this->getController();

        // Initialize optional column from the configurator when the
        // JS controller is initialized.
        // IDEA maybe just initialize the controller var here and run the
        // constructors of the columns only on-demand in buildJsRefreshPersonalization()?
        if ($widget->getConfiguratorWidget()->hasOptionalColumns()) {
            $colsOptional = $widget->getConfiguratorWidget()->getOptionalColumns();
            $colsOptionalInitJs = '';
            if (! empty($colsOptional)) {
                foreach ($colsOptional as $col) {
                    $colsOptionalInitJs .= <<<JS
                
                        oColsOptional['{$col->getDataColumnName()}'] = {$this->getFacade()->getElement($col)->buildJsConstructor()};
JS;
                }
            }
            $controller->addOnInitScript(<<<JS
            
                (function(){
                    var oColsOptional = {};
                    {$colsOptionalInitJs}
                    {$controller->buildJsDependentObjectGetter(self::CONTROLLER_VAR_OPTIONAL_COLS, $this, $oControllerJs)} = oColsOptional;
                })();
JS
            );
        }

        if ($this->isMTable()) {
            $js = $this->buildJsConstructorForMTable($oControllerJs);
        } else {
            $js = $this->buildJsConstructorForUiTable($oControllerJs);
        }
        
        if (($syncAttributeAlias = $widget->getMultiSelectSyncAttributeAlias()) !== null)
        {
            if (($syncDataColumn = $widget->getColumnByAttributeAlias($syncAttributeAlias)) !== null) {
                $this->addOnChangeScript($this->buildJsMultiSelectSync($syncDataColumn, $oControllerJs));
            } else {
                throw new WidgetConfigurationError($widget, "The attribute alias '{$syncAttributeAlias}' for multi select synchronisation was not found in the column attribute aliases for the widget '{$widget->getId()}'!");
            }
        }
        
        // Clear selection every time the prefill data changes. Otherwise in a table within
        // a dialog if the first row was selected when the dialog was opened for object 1,
        // the first row will also be selected if the dialog will be opened for object 2, etc.
        // TODO it would be even better to check if previously selected UIDs are still there
        // and select their rows again like we do in EuiData::buildJsonOnLoadSuccessSelectionFix()
        if ($this->isUiTable()) {
            $clearSelectionJs = "sap.ui.getCore().byId('{$this->getId()}').clearSelection();";
        } else {
            $clearSelectionJs = "sap.ui.getCore().byId('{$this->getId()}').removeSelections(true);";
        }
        $controller->addOnPrefillDataChangedScript($clearSelectionJs);
        
        return $js;
    }

    public function isMList() : bool
    {
        return $this->isMTable();
    }
    
    public function isMTable()
    {
        return $this->getWidget() instanceof DataTableResponsive;
    }
    
    public function isUiTable()
    {
        return ! ($this->getWidget() instanceof DataTableResponsive);
    }
    
    /**
     * Returns the javascript constructor for a sap.m.Table
     *
     * @return string
     */
    protected function buildJsConstructorForMTable(string $oControllerJs = 'oController')
    {
        $mode = $this->getWidget()->getMultiSelect() ? 'sap.m.ListMode.MultiSelect' : 'sap.m.ListMode.SingleSelectMaster';
        $striped = $this->getWidget()->getStriped() ? 'true' : 'false';
        
        if ($this->getDynamicPageShowToolbar() === false) {
            $toolbar = $this->buildJsToolbar($oControllerJs);
        } else {
            $toolbar = '';
        }
        
        $controller = $this->getController();
        return <<<JS
        new sap.m.VBox({
            {$this->buildJsPropertyVisibile()}
            width: "{$this->getWidth()}",
    		items: [
                new sap.m.Table("{$this->getId()}", {
            		fixedLayout: false,
                    contextualWidth: "Auto",
                    sticky: [sap.m.Sticky.ColumnHeaders, sap.m.Sticky.HeaderToolbar],
                    alternateRowColors: {$striped},
                    noDataText: "{$this->getWidget()->getEmptyText()}",
            		itemPress: {$controller->buildJsEventHandler($this, self::EVENT_NAME_CHANGE, true)},
                    selectionChange: function (oEvent) { {$this->buildJsPropertySelectionChange('oEvent')} },
                    updateFinished: function(oEvent) { {$this->buildJsColumnStylers()} },
                    mode: {$mode},
                    headerToolbar: [
                        {$toolbar}
            		],
            		columns: [
                        {$this->buildJsColumnsForMTable()}
            		],
            		items: {
            			path: '/rows',
                        {$this->buildJsBindingOptionsForGrouping()}
                        template: new sap.m.ColumnListItem({
                            type: "Active",
                            cells: [
                                {$this->buildJsCellsForMTable()}
                            ]
                        }),
            		},
                    contextMenu: [
                        // A context menu is required for the contextmenu browser event to fire!
                        new sap.ui.unified.Menu()
                    ]
                })
                {$this->buildJsClickHandlers('oController')}
                {$this->buildJsPseudoEventHandlers()}
                ,
                {$this->buildJsConstructorForMTableFooter()}
            ]
        })
        
JS;
    }
    
    /**
     * 
     * @param string $oControllerJs
     * @return string
     */
    protected function buildJsConstructorForMTableFooter(string $oControllerJs = 'oController') : string
    {
        $visible = $this->getWidget()->isPaged() === false || $this->getWidget()->getHideFooter() === true ? 'false' : 'true';
        return <<<JS
                new sap.m.OverflowToolbar({
                    visible: {$visible},
    				content: [
                        {$this->getPaginatorElement()->buildJsConstructor($oControllerJs)},
                        new sap.m.ToolbarSpacer(),
                        {$this->buildJsConfiguratorButtonConstructor($oControllerJs, 'Transparent')}
                    ]
                })
                
JS;
    }



    /**
     * On every selection change event save the selection and perform on-change scripts
     * 
     * This method also ensures, that previously selected rows are selected again when
     * following the pagination back and fourth or using filters. That is, 
     * - if you select a row on one page, go to the next one and return back, the initially 
     * selected row will still be selected
     * - if you select a row on one page, go to another one and press a button, the (invisible)
     * selected row on the first page will be among the action data
     * 
     * @return string
     */
    protected function buildJsPropertySelectionChange(string $oEventJs)
    {
        $controller = $this->getController();
        $widget = $this->getWidget();
        $uidColJs = $widget->hasUidColumn() ? $this->escapeString($widget->getUidColumn()->getDataColumnName()) : 'null';
        if ($widget->getMultiSelect() === false) {
            return <<<JS

            {$controller->buildJsEventHandler($this, self::EVENT_NAME_CHANGE, false)};
JS;
            
        }
        
        return <<<JS
        
            const oTable = $oEventJs.getSource();
            const oModelSelected = oTable.getModel('{$this->getModelNameForSelections()}');
            const bMultiSelect = oTable.getMode !== undefined ? oTable.getMode() === sap.m.ListMode.MultiSelect : {$this->escapeBool($widget->getMultiSelect())};
            const bMultiSelectSave = {$this->escapeBool(($widget instanceof DataTable) && $widget->isMultiSelectSavedOnNavigation())}
            const sUidCol = {$uidColJs};
            var aRowsVisible = [];
            var aRowsMerged = [];
            var aRowsSelectedVisible = {$this->buildJsGetRowsSelected('oTable')};

            if (bMultiSelect === true && bMultiSelectSave === true) {
                aRowsVisible = {$this->buildJsGetRowsAll('oTable')};
                // Keep all previously selected rows, that are NOT in the current page
                // because they definitely could not be deselected
                oModelSelected.getProperty('/rows').forEach(oRowOld => {
                    if (exfTools.data.indexOfRow(aRowsVisible, oRowOld, sUidCol) === -1) { 
                        aRowsMerged.push(oRowOld);
                    }
                });
                // Add all currently visible selected rows
                aRowsMerged.push(...aRowsSelectedVisible);

                oModelSelected.setProperty('/rows', aRowsMerged);
            } else {
                oModelSelected.setProperty('/rows', aRowsSelectedVisible);
            }
            
            {$controller->buildJsEventHandler($this, self::EVENT_NAME_CHANGE, false)};
JS;
    }


    
    /**
     * 
     * @return string
     */
    protected function buildJsBindingOptionsForGrouping()
    {
        $widget = $this->getWidget();
        
        if (! $widget->hasRowGroups()) {
            return '';
        }
        
        $grouper = $widget->getRowGrouper();
        
        $sorterDir = 'true';
        foreach ($this->getWidget()->getSorters() as $sorterUxon) {
            if ($sorterUxon->getProperty('attribute_alias') === $grouper->getGroupByColumn()->getAttributeAlias()) {
                if ($sorterUxon->getProperty('direction') === SortingDirectionsDataType::DESC) {
                    $sorterDir = 'true';
                } else {
                    $sorterDir = 'false';
                }
                break;
            }
        }
        
        $caption = $grouper->getHideCaption() ? '' : $this->escapeJsTextValue($grouper->getCaption());
        $caption .= $caption ? ': ' : '';
        
        // Row grouping is defined inside a sorter, so we must add a client-side sorter to have the
        // groups. Since the actual sorting is normally done elsewhere (in the server or by the data,
        // loader) we use a sorter with a custom compare function here, that does not really do anything.
        // This is important, as the built-in sorter yielded very strage result for some data types like
        // dates.
        return <<<JS
        
                sorter: new sap.ui.model.Sorter(
    				'{$grouper->getGroupByColumn()->getDataColumnName()}', // sPath
    				{$sorterDir}, // bDescending
    				true, // vGroup
                    function(a, b) { // fnComparator
                        return 0;
                    }
    			),
    			groupHeaderFactory: function(oGroup) {
                    // TODO add support for counters
                    return new sap.m.GroupHeaderListItem({
        				title: "{$caption}" + (oGroup.key !== null ? oGroup.key : "{$this->escapeJsTextValue($grouper->getEmptyText())}"),
                        type: "Active",
                        press: function(oEvent) {
                            var oHeaderItem = oEvent.getSource();
                            var oList = oHeaderItem.getParent();
                            var iHeaderIdx = oList.indexOfItem(oHeaderItem);
                            var aItems = oList.getItems();
                            var oItem;

                            for (var i=0; i<aItems.length; i++) {
                                if (i <= iHeaderIdx) continue;
                                oItem = aItems[i];
                                if (oItem instanceof sap.m.GroupHeaderListItem) break;
                                if (oItem.getVisible()) {
                                    oItem.setVisible(false);
                                    oHeaderItem.setType('Navigation');
                                } else {
                                    oItem.setVisible(true);
                                    oHeaderItem.setType('Active');
                                }
                            }
                        }
        			});
                },
JS;
    }
    
    /**
     * Returns the javascript constructor for a sap.ui.table.Table
     *
     * @return string
     */
    protected function buildJsConstructorForUiTable(string $oControllerJs = 'oController')
    {
        $widget = $this->getWidget();
        $controller = $this->getController();
        
        $selection_mode = $widget->getMultiSelect() ? 'sap.ui.table.SelectionMode.MultiToggle' : 'sap.ui.table.SelectionMode.Single';
        $selection_behavior = $widget->getMultiSelect() ? 'sap.ui.table.SelectionBehavior.Row' : 'sap.ui.table.SelectionBehavior.RowOnly';
        
        if ($this->getDynamicPageShowToolbar() === false) {
            $toolbar = $this->buildJsToolbar($oControllerJs, $this->getPaginatorElement()->buildJsConstructor($oControllerJs));
        } else {
            $toolbar = '';
        }
        $freezeColumnsCount = $widget->getFreezeColumns();
        if ($freezeColumnsCount > 0) {
            $columns = $widget->getColumns();
            for ($i = 0; $i < $freezeColumnsCount; $i++) {
                if ($columns[$i]->isHidden() == true) {
                    $freezeColumnsCount++;
                }
            }
            // increase the count if the DirtyFlag column is added as the first column in the table
            if ($this->hasDirtyColumn()) {
                $freezeColumnsCount++;
            }
        }
        $enableGrouping = $widget->hasRowGroups() ? 'enableGrouping: true,' : '';
        
        if ($widget->getDragToOtherWidgets() === true) {
            $initDnDJs = <<<JS

                dragDropConfig: [
                    new sap.ui.core.dnd.DragInfo({
                        sourceAggregation: "rows",
                        dragStart: function(oEvent) {
                            var oDraggedRow = oEvent.getParameter("target");
                            var oModel = oDraggedRow.getModel();
                            var oRow = oModel.getProperty(oDraggedRow.getBindingContext().getPath());
                            var oDataSheet = {
                                oId: '{$this->getMetaObject()->getId()}',
                                rows: (oRow ? [oRow] : [])
                            };
                            oEvent.getParameter('browserEvent').dataTransfer.setData("dataSheet", JSON.stringify(oDataSheet));
                        }
                    }),
                ],
JS;
        } else {
            $initDnDJs = '';
        }
        
        $js = <<<JS
            new sap.ui.table.Table("{$this->getId()}", {
                width: "{$this->getWidth()}",
                visibleRowCountMode: sap.ui.table.VisibleRowCountMode.Auto,
                {$this->buildJsPropertyMinAutoRowCount()}
                selectionMode: {$selection_mode},
        		selectionBehavior: {$selection_behavior},
                enableColumnReordering:true,
                fixedColumnCount: {$freezeColumnsCount},
                enableColumnFreeze: true,
                {$enableGrouping}
        		filter: {$controller->buildJsMethodCallFromView('onLoadData', $this)},
        		sort: {$controller->buildJsMethodCallFromView('onLoadData', $this)},
                rowSelectionChange: function (oEvent) { {$this->buildJsPropertySelectionChange('oEvent')} },
                firstVisibleRowChanged: {$controller->buildJsEventHandler($this, self::EVENT_NAME_FIRST_VISIBLE_ROW_CHANGED, true)},
        		{$this->buildJsPropertyVisibile()}
                {$initDnDJs}
                toolbar: [
        			{$toolbar}
        		],
        		columns: [
        			{$this->buildJsColumnsForUiTable()}
        		],
                noData: [
                    new sap.m.FlexBox({
                        height: "100%",
                        width: "100%",
                        justifyContent: "Center",
                        alignItems: "Center",
                        items: [
                            new sap.m.Text("{$this->getIdOfNoDataOverlay()}", {text: "{$widget->getEmptyText()}"})
                        ]
                    })
                ],
                rows: "{/rows}"
        	})
            {$this->buildJsClickHandlers('oController')}
            {$this->buildJsPseudoEventHandlers()}
JS;
            
            return $js;
    }
    
    /**
     * 
     * @return string
     */
    protected function getIdOfNoDataOverlay() : string
    {
        return $this->getId() . '_noData';
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsPropertyMinAutoRowCount() : string
    {
        $widget = $this->getWidget();
        $heightInRows = $widget instanceof DataTable ? $widget->getHeightInRows() : null;
        $heightInRowsDefault = $this->getFacade()->getConfig()->getOption('WIDGET.DATATABLE.ROWS_SHOWN_BY_DEFAULT');
        $height = $widget->getHeight();
        $singleRowHeightPx = '33';

        switch (true) {
            case $heightInRows !== null:
                $minAutoRowCount = $heightInRows;
                break;
            case $height->isRelative():
            case $height->isFacadeSpecific() && StringDataType::endsWith($height->getValue(), 'px', false):
                // TODO determine the height elements via JS
                // iRowHeight = oTable.getRowHeight() // but oTable is not there yet. Maybe on-resize?
                $heightPx = StringDataType::substringBefore($height->getValue(), 'px', $height->getValue(), true);
                $heightPx = NumberDataType::cast($heightPx);
                $minAutoRowCount = <<<JS
                function(){
                    var iRowHeight = {$singleRowHeightPx};
                    var jqTest = $('<div class="sapMTB sapMTBHeader-CTX"></div>').appendTo('body');
                    var iToolbarHeight = jqTest.height();
                    var iTableHeight = {$heightPx};
                    jqTest.remove();
                    return Math.floor((iTableHeight - iRowHeight - iToolbarHeight) / iRowHeight);
                }()

JS;
                break;
            // Calculate the max. number of rows, that will fit the given percentage of the
            // height of the container. Note, the immediate container might be simply the
            // FormElement, that will always have the same height as the table, so look for
            // the Form further up the hierarchy
            // TODO add other container types in case the table is not part of the form
            case $height->isPercentual():
            case $height->isMax():
                if ($height->isPercentual()) {
                    $heightPercent = StringDataType::substringBefore($height->getValue(), '%', $height->getValue());
                    $heightPercent = NumberDataType::cast($heightPercent);
                } else {
                    $heightPercent = 100;
                }
                // NOTE: this JS code makes use of the oController variable. Since this is called inside the
                // table constructor, oController should always be defined as 
                $minAutoRowCount = <<<JS
                function(){
                    var iRowHeight = iHeaderHeight = {$singleRowHeightPx};
                    var iRowsDefault = {$heightInRowsDefault};
                    var jqTest = $('<div class="sapMTB sapMTBHeader-CTX"></div>').appendTo('body');
                    var iToolbarHeight = jqTest.height();
                    var fnCalcHeight = function(oContainer){
                        var jqContainer = oContainer.$();
                        var iContainerHeight = jqContainer ? jqContainer.innerHeight() : null;
                        var iTargetHeight;
                        if (iContainerHeight) {
                            iTargetHeight = iContainerHeight / 100 * {$heightPercent};
                            return Math.floor((iTargetHeight - iHeaderHeight - iToolbarHeight) / iRowHeight);
                        }
                        return null;
                    };
                    jqTest.remove();

                    setTimeout(function(){
                        var oTable = sap.ui.getCore().byId('{$this->getId()}');
                        var oContainer = oController.findParentOfType(oTable, sap.ui.layout.form.Form);
                        var bResizing;
                        if (! oContainer) {
                            return;
                        }

                        sap.ui.core.ResizeHandler.register(oContainer, function(){
                            var iRows;
                            if (bResizing === true) {
                                bResizing = false;
                                return;
                            }
                            bResizing = true;
                            setTimeout(function(){
                                iRows = fnCalcHeight(oContainer);
                                if (iRows !== null) {
                                    oTable.setMinAutoRowCount(iRows);
                                }
                            }, 10);
                        });
                    }, 0);
                    
                    return iRowsDefault;
                }()

JS;
                break;
            //case $height->isUndefined():
            //case $height->isAuto():
            default:
                $minAutoRowCount = $heightInRowsDefault;
                break;            
        }
        
        return "minAutoRowCount: {$minAutoRowCount},";
    }
    
    /**
     * Returns a comma separated list of column constructors for sap.ui.table.Table
     *
     * @return string
     */
    protected function buildJsColumnsForUiTable()
    {
        $widget = $this->getWidget();
        $column_defs = '';
        
        // Add dirty-column for offline actions
        if ($this->hasDirtyColumn()) {
            $column_defs .= <<<JS
            
        new sap.ui.table.Column('{$this->getDirtyFlagAlias()}',{
            hAlign: "Center",
            autoResizable: true,
            width: "48px",
            minWidth: 48,
            visible: true,
            template: new sap.m.Button({
                icon: "sap-icon://time-entry-request",
                visible: "{= \$\{{$this->getDirtyFlagAlias()}\}  === true}",
                tooltip: "{i18n>WEBAPP.SHELL.NETWORK.OFFLINE_CHANGES_PENDING}",
                type: sap.m.ButtonType.Transparent,
                press: function(oEvent) {
                    var oBtn = oEvent.getSource();
                    exfLauncher.showOfflineQueuePopoverForItem(
                        "{$widget->getMetaObject()->getAliasWithNamespace()}",
                        "{$widget->getUidColumn()->getDataColumnName()}",
                        oBtn.getModel().getProperty(oBtn.getBindingContext().getPath() + '/{$widget->getUidColumn()->getDataColumnName()}'),
                        oBtn
                    );
                }
            })
        }),
JS;
        }
        
        foreach ($widget->getColumns() as $column) {
            $column_defs .= $this->getFacade()->getElement($column)->buildJsConstructorForUiColumn() . ',';
        }
        
        return $column_defs;
    }
    
    protected function buildJsCellsForMTable()
    {
        $widget = $this->getWidget();
        $cells = '';
        
        
        // Add dirty-column for offline actions
        // NOTE: in the case of sap.m.Table it is important to place the dirty column
        // first because it checks for the UID column and eventually adds it. This MUST
        // happen before columns are rendered as there is no explicit link between columns
        // and cells and having more columns than cells (because of adding the UID column
        // at some point) causes very strange behavior!
        if ($this->hasDirtyColumn()) {
            $cells .= <<<JS
        new sap.m.Button({
            icon: "sap-icon://time-entry-request",
            visible: "{= \$\{{$this->getDirtyFlagAlias()}\}  === true}",
            tooltip: "{i18n>WEBAPP.SHELL.NETWORK.OFFLINE_CHANGES_PENDING}",
            type: sap.m.ButtonType.Transparent,
            press: function(oEvent) {
                var oBtn = oEvent.getSource();
                exfLauncher.showOfflineQueuePopoverForItem(
                    "{$widget->getMetaObject()->getAliasWithNamespace()}",
                    "{$widget->getUidColumn()->getDataColumnName()}",
                    oBtn.getModel().getProperty(oBtn.getBindingContext().getPath() + '/{$widget->getUidColumn()->getDataColumnName()}'),
                    oBtn
                );
            }
        }),
JS;
        }
        
        foreach ($widget->getColumns() as $column) {
            $cells .= $this->getFacade()->getElement($column)->buildJsConstructorForCell() . ",";
        }
        
        return $cells;
    }
    
    /**
     * Returns a comma-separated list of column constructors for sap.m.Table
     *
     * @return string
     */
    protected function buildJsColumnsForMTable()
    {
        $widget = $this->getWidget();
        
        // See if there are promoted columns. If not, make the first visible column promoted,
        // because sap.m.table would otherwise have no column headers at all.
        $promotedFound = false;
        $first_col = null;
        foreach ($widget->getColumns() as $col) {
            if (is_null($first_col) && ! $col->isHidden()) {
                $first_col = $col;
            }
            if ($col->getVisibility() === EXF_WIDGET_VISIBILITY_PROMOTED && ! $col->isHidden()) {
                $promotedFound = true;
                break;
            }
        }
        
        if (! $promotedFound && $first_col !== null) {
            $first_col->setVisibility(EXF_WIDGET_VISIBILITY_PROMOTED);
        }
        
        $column_defs = '';
        
        // Add dirty-column for offline actions
        if ($this->hasDirtyColumn()) {
            $column_defs .= <<<JS
            
                    new sap.m.Column('{$this->getDirtyFlagAlias()}',{
                        hAlign: "Center",
                        importance: "High",
                        visible: false,
                        popinDisplay: sap.m.PopinDisplay.Inline,
						demandPopin: true,
                    }),
JS;
        }
        
        foreach ($this->getWidget()->getColumns() as $column) {
            $column_defs .= $this->getFacade()->getElement($column)->buildJsConstructorForMColumn() . ",";
        }
        
        return $column_defs;
    }
    
    /**
     * {@inheritdoc}
     * @see UI5DataElementTrait::buildJsDataLoaderParams()
     */
    protected function buildJsDataLoaderParams(string $oControlEventJsVar = 'oControlEvent', string $oParamsJs = 'params', $keepPagePosJsVar = 'bKeepPagingPos') : string
    {
        $commonParams = $this->buildJsDataLoaderParamsPaging($oParamsJs, $keepPagePosJsVar);
                  
        if ($this->isUiTable() === true) {            
            $tableParams = <<<JS

            {$oParamsJs}.data.columns = [];
            // Process currently visible columns:
            // - Add filters and sorters from column menus
            // - Add column name to ensure even optional data is read if required
            oTable.getColumns().forEach(oColumn => {
                var mVal = oColumn.getFilterValue();
                var fnParser = oColumn.data('_exfFilterParser');
                var oColParam;
                if (oColumn.data('_exfDataColumnName')) {
                    if (oColumn.data('_exfAttributeAlias')) {
                        oColParam = {
                            attribute_alias: oColumn.data('_exfAttributeAlias')
                        };
                        if (oColumn.data('_exfDataColumnName') !== oColParam.attribute_alias) {
                            oColParam.name = oColumn.data('_exfDataColumnName');
                        }
                    } else if (oColumn.data('_exfCalculation')) {
                        oColParam = {
                            name: oColumn.data('_exfDataColumnName'),
                            expression: oColumn.data('_exfCalculation')
                        };
                    }
                    if (oColParam !== undefined) {
                        {$oParamsJs}.data.columns.push(oColParam);
                    }
                }
    			if (oColumn.getFiltered() === true && mVal !== undefined && mVal !== null && mVal !== ''){
                    mVal = fnParser !== undefined ? fnParser(mVal) : mVal;
    				{$oParamsJs}['{$this->getFacade()->getUrlFilterPrefix()}' + oColumn.getFilterProperty()] = mVal;
    			}
    		});
            
            // If filtering just now, make sure the filter from the event is set too (eventually overwriting the previous one)
    		if ({$oControlEventJsVar} && {$oControlEventJsVar}.getId() == 'filter'){
                (function(oEvent) {
                    var oColumn = oEvent.getParameters().column;
                    var sFltrProp = oColumn.getFilterProperty();
                    var sFltrVal = oEvent.getParameters().value;
                    var fnParser = oColumn.data('_exfFilterParser'); 
                    var mFltrParsed = fnParser !== undefined ? fnParser(sFltrVal) : sFltrVal;

                    {$oParamsJs}['{$this->getFacade()->getUrlFilterPrefix()}' + sFltrProp] = mFltrParsed;
                    
                    if (mFltrParsed !== null && mFltrParsed !== undefined && mFltrParsed !== '') {
                        oColumn.setFiltered(true).setFilterValue(sFltrVal);
                    } else {
                        oColumn.setFiltered(false).setFilterValue('');
                    }         
    
                    // Also make sure the built-in UI5-filtering is not applied.
                    oEvent.cancelBubble();
                    oEvent.preventDefault();
                })($oControlEventJsVar);
            }
    		
    		// If sorting just now, overwrite the sort string and make sure the sorter in the configurator is set too
    		if ({$oControlEventJsVar} && {$oControlEventJsVar}.getId() == 'sort'){
                {$oParamsJs}.sort = {$oControlEventJsVar}.getParameters().column.getSortProperty();
                {$oParamsJs}.order = {$oControlEventJsVar}.getParameters().sortOrder === 'Descending' ? 'desc' : 'asc';
                
                sap.ui.getCore().byId('{$this->getP13nElement()->getIdOfSortPanel()}')
                .destroySortItems()
                .addSortItem(
                    new sap.m.P13nSortItem({
                        columnKey: {$oControlEventJsVar}.getParameters().column.getSortProperty(),
                        operation: {$oControlEventJsVar}.getParameters().sortOrder
                    })
                );

                // Also make sure, the built-in UI5-sorting is not applied.
                $oControlEventJsVar.cancelBubble();
                $oControlEventJsVar.preventDefault();
    		}

            // Set sorting indicators for columns
            var aSortProperties = ({$oParamsJs}.sort ? {$oParamsJs}.sort.split(',') : []);
            var aSortOrders = ({$oParamsJs}.sort ? {$oParamsJs}.order.split(',') : []);
            var iIdx = -1;
            sap.ui.getCore().byId('{$this->getId()}').getColumns().forEach(function(oColumn){
                iIdx = aSortProperties.indexOf(oColumn.getSortProperty());
                if (iIdx > -1) {
                    oColumn.setSorted(true);
                    oColumn.setSortOrder(aSortOrders[iIdx] === 'desc' ? sap.ui.table.SortOrder.Descending : sap.ui.table.SortOrder.Ascending);
                } else {
                    oColumn.setSorted(false);
                }
            });
            
            // Make sure, the column filter indicator is ON if the column is filtered over via advanced search 
            (function(){
                var oSearchPanel = sap.ui.getCore().byId('{$this->getConfiguratorElement()->getIdOfSearchPanel()}');
                var aSearchFItems = oSearchPanel.getFilterItems();
                var aColumns = oTable.getColumns();
                aColumns.forEach(function(oColumn) {
                    var sFilterVal = oColumn.getFilterValue();
                    var bFiltered = sFilterVal !== '' && sFilterVal !== null && sFilterVal !== undefined;
                    if (bFiltered) {
                        return;
                    }
                    aSearchFItems.forEach(function(oItem){
                        if (oItem.getColumnKey() === oColumn.data('_exfAttributeAlias')) {
                            bFiltered = true;
                        }
                    });
                    oColumn.setFiltered(bFiltered);
                });
            })();
		
JS;
        } elseif ($this->isMTable()) {
            $tableParams = <<<JS

            // request config for opt. columns
            {$oParamsJs}.data.columns = [];

            // Set sorting indicators for columns
            var aSortProperties = ({$oParamsJs}.sort ? {$oParamsJs}.sort.split(',') : []);
            var aSortOrders = ({$oParamsJs}.sort ? {$oParamsJs}.order.split(',') : []);
            var iIdx = -1;
            sap.ui.getCore().byId('{$this->getId()}').getColumns().forEach(function(oColumn){
                iIdx = aSortProperties.indexOf(oColumn.data('_exfAttributeAlias'));
                var oColParam;

                if (iIdx > -1) {
                    oColumn.setSortIndicator(aSortOrders[iIdx] === 'desc' ? 'Descending' : 'Ascending');
                } else {
                    oColumn.setSortIndicator(sap.ui.core.SortOrder.None);
                }

                // add optional columns as request data 
                if (oColumn.data('_exfDataColumnName')) {
                    if (oColumn.data('_exfAttributeAlias')) {
                        oColParam = {
                            attribute_alias: oColumn.data('_exfAttributeAlias')
                        };
                        if (oColumn.data('_exfDataColumnName') !== oColParam.attribute_alias) {
                            oColParam.name = oColumn.data('_exfDataColumnName');
                        }
                    } else if (oColumn.data('_exfCalculation')) {
                        oColParam = {
                            name: oColumn.data('_exfDataColumnName'),
                            expression: oColumn.data('_exfCalculation')
                        };
                    }
                    if (oColParam !== undefined) {
                        {$oParamsJs}.data.columns.push(oColParam);
                    }
                }
            });

JS;
        }
		
        return $commonParams . $tableParams;
    }
    
    /**
     * Returns inline JS code to refresh the table.
     *
     * If the code snippet is to be used somewhere, where the controller is directly accessible, you can pass the
     * name of the controller variable to $oControllerJsVar to increase performance.
     *
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsRefresh()
     *
     * @param bool $keepPagingPos
     * @param string $oControllerJsVar
     *
     * @return UI5DataTable
     */
    public function buildJsRefresh(bool $keepPagingPos = false, string $oControllerJsVar = null)
    {
        $params = "undefined, " . ($keepPagingPos ? 'true' : 'false');
        if ($oControllerJsVar === null) {
            return $this->getController()->buildJsMethodCallFromController('onLoadData', $this, $params);
        } else {
            return $this->getController()->buildJsMethodCallFromController('onLoadData', $this, $params, $oControllerJsVar);
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsDataGetter()
     */
    public function buildJsDataGetter(ActionInterface $action = null)
    {
        $widget = $this->getWidget();
        $dataObj = $this->getMetaObjectForDataGetter($action);
        
        $data = <<<JS
{
            oId: '{$this->getWidget()->getMetaObject()->getId()}',
            rows: aRows
        }
JS;
        if ($action !== null && $action->isDefinedInWidget() && $action->getWidgetDefinedIn() instanceof DataButton) {
            $customMode = $action->getWidgetDefinedIn()->getInputRows();
        } else {
            $customMode = null;
        }
        
        switch (true) {
            // If no action is specified, return the entire row model
            case $customMode === DataButton::INPUT_ROWS_ALL:
            case $action === null:
                $aRowsJs = "{$this->buildJsGetRowsAll('oTable')} || []";
                break;
            
            // If the button requires none of the rows explicitly
            case $customMode === DataButton::INPUT_ROWS_NONE:
                return '{}';
                
            // If we are reading, than we need the special data from the configurator
            // widget: filters, sorters, etc.
            case $action instanceof iReadData:
                return $this->getConfiguratorElement()->buildJsDataGetter($action);
                
            // Editable tables with modifying actions return all rows either directly or as subsheet
            case $customMode === DataButton::INPUT_ROWS_ALL_AS_SUBSHEET:
            case $this->isEditable() && ($action instanceof iModifyData):
            case $this->isEditable() && ($action instanceof iCallOtherActions) && $action->containsActionClass(iModifyData::class):
                $aRowsJs = "{$this->buildJsGetRowsAll('oTable')} || []";
                switch (true) {
                    case $dataObj->is($widget->getMetaObject()) && $customMode !== DataButton::INPUT_ROWS_ALL_AS_SUBSHEET:
                    case $action->getInputMapper($widget->getMetaObject()) !== null && $customMode !== DataButton::INPUT_ROWS_ALL_AS_SUBSHEET:
                        break;
                    default:
                        // If the data is intended for another object, make it a nested data sheet
                        // If the action is based on the same object as the widget's parent, use the widget's
                        // logic to find the relation to the parent. Otherwise try to find a relation to the
                        // action's object and throw an error if this fails.
                        if ($widget->hasParent() && $dataObj->is($widget->getParent()->getMetaObject()) && $relPath = $widget->getObjectRelationPathFromParent()) {
                            $relAlias = $relPath->toString();
                        } elseif ($relPath = $dataObj->findRelationPath($widget->getMetaObject())) {
                            $relAlias = $relPath->toString();
                        }
                        
                        if ($relAlias === null || $relAlias === '') {
                            throw new WidgetLogicError($widget, 'Cannot use editable table with object "' . $widget->getMetaObject()->getName() . '" (alias ' . $widget->getMetaObject()->getAliasWithNamespace() . ') as input widget for action "' . $action->getName() . '" with object "' . $dataObj->getName() . '" (alias ' . $dataObj->getAliasWithNamespace() . '): no forward relation could be found from action object to widget object!', '7B7KU9Q');
                        }
                        $data = <<<JS
({$this->buildJsIsDataPending()} ? {} : {
            oId: '{$dataObj->getId()}',
            rows: [
                {
                    '{$relAlias}': {
                        oId: '{$widget->getMetaObject()->getId()}',
                        rows: aRows
                    }
                }
            ]
        })
            
JS;
                }
                break;
                
            // In all other cases the data are the selected rows
            default:
                if (($widget instanceof iSupportMultiSelect) && $widget->getMultiSelect() === true) {
                    $aRowsJs = "oTable.getModel('{$this->getModelNameForSelections()}').getProperty('/rows')";
                } else {
                    $aRowsJs = $this->buildJsGetRowsSelected('oTable');
                }
                
        }
        
        // Determine the columns we need in the actions data
        $colNamesList = implode(',', $widget->getActionDataColumnNames());
        
        return <<<JS
    function() {
        var oTable = sap.ui.getCore().byId('{$this->getId()}');
        var oDirtyColumn = sap.ui.getCore().byId('{$this->getDirtyFlagAlias()}');
        var aRows = {$aRowsJs};
        
        // Remove any keys, that are not in the columns of the widget
        aRows = aRows.map(({ $colNamesList }) => ({ $colNamesList }));

        return $data;
    }()
JS;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\Traits\UI5DataElementTrait::buildJsDataLoaderOnLoadedRestoreSelection()
     */
    protected function buildJsDataLoaderOnLoadedRestoreSelection(string $oTableJs) : string
    {
        $widget = $this->getWidget();
        if ($widget->getMultiSelect() === true ) {
            $uidColJs = $widget->hasUidColumn() ? $this->escapeString($widget->getUidColumn()->getDataColumnName()) : 'null';
                    
            // Restore previous selection
            return <<<JS
                setTimeout(function(oTable) {
                    const oModelSelected = oTable.getModel('{$this->getModelNameForSelections()}');
                    const aPrevSelectedRows = oModelSelected.getProperty('/rows');
                    const aNowSelectedRows = {$this->buildJsGetRowsSelected($oTableJs, true)};
                    const aRows = {$this->buildJsGetRowsAll($oTableJs)};
                    const sUidCol = $uidColJs;
                    const fnSelect = function(iRowIdx) {
                        {$this->buildJsSelectRowByIndex($oTableJs, 'iRowIdx', false, 'false')}
                    };
                    const fnDeselect = function(iRowIdx) {
                        {$this->buildJsSelectRowByIndex($oTableJs, 'iRowIdx', true, 'false')}
                    }
                    if (aPrevSelectedRows === undefined) {
                        return;
                    }
                    aNowSelectedRows.forEach(function (oRow) {
                        var bSelected = exfTools.data.indexOfRow(aPrevSelectedRows, oRow, sUidCol) > -1;
                        var iRowIdx = exfTools.data.indexOfRow(aRows, oRow, sUidCol);
                        if (iRowIdx === -1) {
                            return;
                        }
                        if (bSelected) {
                            fnSelect(iRowIdx);
                        } else {
                            fnDeselect(iRowIdx);
                        }
                    });
                    aPrevSelectedRows.forEach(function (oRow) {
                        var iRowIdx = exfTools.data.indexOfRow(aRows, oRow, sUidCol);
                        if (iRowIdx === -1) {
                            return;
                        } else {
                            fnSelect(iRowIdx);
                        }
                    });
                }, 0, {$oTableJs});

JS;
        } else {
            return '';
        }
    }
    
    /**
     * 
     * @see UI5DataElementTrait::buildJsGetRowsSelected()
     */
    protected function buildJsGetRowsSelected(string $oTableJs) : string
    {
        if ($this->isUiTable()) {
            if($this->getWidget()->getMultiSelect() === false) {
                $rows = "($oTableJs && $oTableJs.getSelectedIndex() !== -1 && $oTableJs.getContextByIndex($oTableJs.getSelectedIndex()) !== undefined ? [$oTableJs.getContextByIndex($oTableJs.getSelectedIndex()).getObject()] : [])";
            } else {
                $rows = <<<JS
                    (function(oTable){
                        var selectedIdx = oTable.getSelectedIndices(); 
                        var aRows = []; 
                        selectedIdx.forEach(function(i) {
                            var oCtxt = oTable.getContextByIndex(i);
                            if (oCtxt && oCtxt.getObject()) {
                                aRows.push(oCtxt.getObject());
                            }
                        }); 
                        return aRows;
                    })($oTableJs)
JS;
            }
        } else {
            if($this->getWidget()->getMultiSelect() === false) {
                $rows = "($oTableJs && $oTableJs.getSelectedItem() ? [$oTableJs.getSelectedItem().getBindingContext().getObject()] : [])";
            } else {
                $rows = <<<JS
                    $oTableJs.getSelectedContexts().reduce(
                        function(aRows, oCtxt) {
                            aRows.push(oCtxt.getObject()); 
                            return aRows;
                        },
                        []
                    )
JS;
            }
        }
        return $rows;
    }
        
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsValueGetter()
     */
    public function buildJsValueSetter($value, $dataColumnName = null, $rowNr = null)
    {
        if ($rowNr === null) {
            if ($this->isUiTable()) {
                $rowNr = "oTable.getSelectedIndex()";
            } else {
                $rowNr = "oTable.indexOfItem(oTable.getSelectedItem())";
            }
        }
        
        if ($dataColumnName === null) {
            $dataColumnName = $this->getWidget()->getUidColumn()->getDataColumnName();
        }
        
        return <<<JS
        
function(){
    var oTable = sap.ui.getCore().byId('{$this->getId()}');
    var oModel = oTable.getModel();
    var iRowIdx = {$rowNr};
    
    if (iRowIdx !== undefined && iRowIdx >= 0) {
        var aData = oModel.getData().rows;
        aData[iRowIdx]["{$dataColumnName}"] = $value;
        oModel.setProperty("/rows", aData);
        // TODO why does the code below not work????
        // oModel.setProperty("/rows(" + iRowIdx + ")/{$dataColumnName}", {$value});
    }
}()

JS;
    }
        
    /**
     * Returns an inline JS-condition, that evaluates to TRUE if the given oTargetDom JS expression
     * is a DOM element inside a list item or table row.
     * 
     * This is important for handling browser events like dblclick. They can only be attached to
     * the entire control via attachBrowserEvent, while we actually only need to react to events
     * on the items, not on headers, footers, etc.
     * 
     * @param string $oTargetDomJs
     * @return string
     */
    protected function buildJsClickIsTargetRowCheck(string $oTargetDomJs = 'oTargetDom') : string
    {
        if ($this->isUiTable()) {
            return "{$oTargetDomJs} !== undefined && $({$oTargetDomJs}).parents('.sapUiTableCCnt').length > 0";
        }
        
        if ($this->isMTable()) {
            return "{$oTargetDomJs} !== undefined && ($({$oTargetDomJs}).parents('tr.sapMListTblRow:not(.sapMListTblHeader)').length > 0 || $({$oTargetDomJs}).parents('tr.sapMListTblSubRow').length > 0)";
        }
        
        if ($this->isMList()) {
            return "{$oTargetDomJs} !== undefined && $({$oTargetDomJs}).parents('li.sapMSLI').length > 0";
        }
        
        return 'true';
    }
    
    /**
     * 
     * @param string $oDomElementClickedJs
     * @return string
     */
    protected function buildJsClickGetRowIndex(string $oDomElementClickedJs) : string
    {
        if ($this->isUiTable()) {
            return "sap.ui.getCore().byId('{$this->getId()}').getFirstVisibleRow() + $({$oDomElementClickedJs}).parents('tr').index()";
        } 
        
        if ($this->isMTable()) {
            // NOTE: sap.m.Table with row groups will count group headers as "items" - same as the actual rows.
            // So we can't call `indexOfItem()` here. Instead, to get the index of the real row, we need to filter 
            // away the group headers explicitly
            return <<<JS
(function(){
    var jqTr = $({$oDomElementClickedJs}).parents('tr');
    var oItem;
    var iIdx = -1;
    if (jqTr.hasClass('sapMListTblSubRow')) {
        jqTr = jqTr.prev();
    }
    oItem = sap.ui.getCore().byId(jqTr[0].id);
    if (oItem) {
        iIdx = sap.ui.getCore().byId('{$this->getId()}')
            .getItems()
            .filter(function(oItem){
                return oItem.getBindingContext() !== undefined
            })
            .indexOf(oItem);
    }
    return iIdx;
})()
JS;
        }
           
        if ($this->isMList()) {
            return "$({$oDomElementClickedJs}).parents('li.sapMSLI').length";
        }
        
        return "-1";
    }
    
    /**
     * 
     * @see UI5DataElementTrait::buildJsClickGetColumnAttributeAlias()
     */
    protected function buildJsClickGetColumnAttributeAlias(string $oDomElementClickedJs) : string
    {
        if ($this->isUiTable()) {
            return "(function(domEl){var oCell = sap.ui.getCore().byId($(domEl).closest('[data-sap-ui-colid]').data('sap-ui-colid')); return oCell ? oCell.data('_exfAttributeAlias') : null;})($oDomElementClickedJs)";
        }
        if ($this->isMTable()) {
            return "(function(domEl){var oCell = sap.ui.getCore().byId($(domEl).closest('[data-sap-ui-column]').data('sap-ui-column')); return oCell ? oCell.data('_exfAttributeAlias') : null;})($oDomElementClickedJs)";
        }
        return "null";
    }
    
    /**
     * 
     * {@inheritdoc}
     * @see UI5DataElementTrait::buildJsClickHandlerLeftClick()
     */
    protected function buildJsClickHandlerLeftClick($oControllerJsVar = 'oController') : string
    {
        // IDEA Theoretically the sap.m.ListBase has it's own support for a context menu, but that triggers
        // the browser context menu too. Could not find a way to avoid it, so we use a custom context
        // menu here. This requires an empty menu in the contextMenu property of the list control - 
        // see. buildJsConstructorForMTable()
        
        // Single click. Currently only supports one click action - the first one in the list of buttons
        if ($leftclick_button = $this->getWidget()->getButtonsBoundToMouseAction(EXF_MOUSE_ACTION_LEFT_CLICK)[0]) {
            if ($this->isUiTable()) {
                return <<<JS
                
            .attachBrowserEvent("click", function(oEvent) {
        		var oTargetDom = oEvent.target;
                if(! ({$this->buildJsClickIsTargetRowCheck('oTargetDom')})) return;

                {$this->getFacade()->getElement($leftclick_button)->buildJsClickEventHandlerCall($oControllerJsVar)};
            })
JS;
            } else {
                return <<<JS
                
            .attachItemPress(function(oEvent) {
                var oListItem = oEvent.getParameters().listItem;
                if (oListItem === undefined || oListItem.getMetadata().getName() === 'sap.m.GroupHeaderListItem') {
                    return;
                }

                {$this->getFacade()->getElement($leftclick_button)->buildJsClickEventHandlerCall($oControllerJsVar)};
            })
JS;
            }
        }
        
        return '';
    }
    
    /**
     * 
     * {@inheritdoc}
     * @see UI5DataElementTrait::buildJsDataLoaderOnLoaded()
     */
    protected function buildJsDataLoaderOnLoaded(string $oModelJs = 'oModel') : string
    {
        $paginator = $this->getPaginatorElement();
        $uidColNameJs = $this->getDataWidget()->hasUidColumn() ? "'{$this->getDataWidget()->getUidColumn()->getDataColumnName()}'" : 'undefined';
        
        // Add single-result action to onLoadSuccess. Make sure it is only fired once as
        // long as the same UID is selected. This means, if the row itself changes (e.g.
        // being saved from the single-select-action) it is still regarded as the same row
        // as long as it has the same UID. Thus the action will not get called repeatedly.
        if (($singleResultButton = $this->getWidget()->getButtons(function($btn) {return ($btn instanceof DataButton) && $btn->isBoundToSingleResult() === true;})[0]) || $this->getWidget()->getSelectSingleResult()) {
            $buttonClickJs = '';
            if ($singleResultButton) {
                $buttonClickJs = <<<JS

                    if (lastRow === undefined || exfTools.data.compareRows(curRow, lastRow, sUidCol) === false) {
                        oTable._singleResultActionPerformedFor = curRow;
                        {$this->getFacade()->getElement($singleResultButton)->buildJsClickEventHandlerCall('oController')};
                    }
JS;
            }
            $singleResultJs = <<<JS

            (function(oTable, oModel){
                if (oModel.getData().rows.length === 1) {
                    var sUidCol = $uidColNameJs;
                    var curRow = {$oModelJs}.getData().rows[0];
                    var lastRow = oTable._singleResultActionPerformedFor;
                    {$this->buildJsSelectRowByIndex('oTable', '0')}
                    {$buttonClickJs}                
                } else {
                    oTable._singleResultActionPerformedFor = {};
                }
            })(oTable, {$oModelJs});            
JS;
        }
                    
        // For some reason, the sorting indicators on the column are changed to the opposite after
        // the model is refreshed. This hack fixes it by forcing sorted columns to keep their
        // indicator.
        $uiTablePostprocessing = '';
        $uiTableSetFooterRows = '';
        if ($this->isUiTable() === true) {
            $this->getController()->addMethod(self::CONTROLLER_METHOD_RESIZE_COLUMNS, $this, 'oTable, oModel', $this->buildJsUiTableColumnResize('oTable', 'oModel'));
            
            $uiTablePostprocessing .= <<<JS

            oTable.getColumns().forEach(function(oColumn){
                if (oColumn.getSorted() === true) {
                    var order = oColumn.getSortOrder()
                    setTimeout(function(){
                        oColumn.setSortOrder(order);
                    }, 0);
                }
            });
JS;
            $uiTableSetFooterRows = <<<JS

            if (footerRows){
				oTable.setFixedBottomRowCount(parseInt(footerRows));
			}
JS;
            
            // Weird code to make the table fill it's container. If not done, tables within
            // sap.f.Card will not be high enough. 
            $uiTablePostprocessing .= 'oTable.setVisibleRowCountMode("Fixed").setVisibleRowCountMode("Auto");';
            
            // Make sure, row grouping works
            $uiTablePostprocessing .= $this->buildJsUiTableInitRowGrouping('oTable', 'oModel');
            
            // Optimize column width AFTER all columns are rendered
            // TODO #ui5-update mode column width optimization to rowsUpdated event of sap.ui.table.Table (available since 1.86)
            $uiTablePostprocessing .= <<<JS

            setTimeout(function(){
                {$this->getController()->buildJsMethodCallFromController(self::CONTROLLER_METHOD_RESIZE_COLUMNS, $this, 'oTable, ' . $oModelJs)}
            }, 100);
JS;
            
            // TODO #ui5-update move stylers to rowsUpdated event of sap.ui.table.Table (available since 1.86)
            if ($stylersJs = $this->buildJsColumnStylers()) {
                $this->getController()->addOnEventScript($this, self::EVENT_NAME_FIRST_VISIBLE_ROW_CHANGED, "setTimeout(function(){ $stylersJs }, 0);");
                $uiTablePostprocessing .= <<<JS

            setTimeout(function(){
                $stylersJs
            }, 500);
JS;
            }
        }
        
        $updatePaginator = '';
        if ($this->hasPaginator()) {
            $updatePaginator = <<<JS
            
            {$paginator->buildJsSetTotal($oModelJs . '.getProperty("/recordsFiltered")', 'oController')};
            {$paginator->buildJsRefresh('oController')};
JS;
        }
        return $this->buildJsDataLoaderOnLoadedViaTrait($oModelJs) . <<<JS

			var footerRows = {$oModelJs}.getProperty("/footerRows");
            {$uiTableSetFooterRows}
            {$updatePaginator}
            {$this->getController()->buildJsEventHandler($this, self::EVENT_NAME_CHANGE, false)};
            {$singleResultJs};
            {$uiTablePostprocessing};
            {$this->buildJsCellConditionalDisablers()};           
JS;
    }
    
    /**
     * To get the experimental row grouping of the ui.table working, we need to
     * 
     * 1. set `enableGrouping` of the table (see `buildJsConstructorForUiTable()`)
     * 2. set the `grouped` flag on the column (see `UI5DataColumn::buildJsConstructorForUiColumn()`)
     * 3. pass the column or its id to the table via `setGroupBy` which is done here
     * 
     * Strangely `.setGroupBy()` fails if the column has no model data, so we
     * must do it here after the model was loaded.
     * 
     * Also note, that group names are cached in the context of each row binding context,
     * so we must call resetExperimentalGrouping() every time data is replaced.
     * See `sap.ui.table.utils._GroupingUtils` in UI5 for more details.
     * 
     * In contrast to the sap.m.Table, there is no official way to influence the group names,
     * so we use a hack here to explicitly set them in the group info of the contexts, which
     * is used by UI5 internally.
     * 
     * @param string $oTableJs
     * @param string $oModelJs
     * @return string
     */
    protected function buildJsUiTableInitRowGrouping(string $oTableJs, string $oModelJs) : string
    {
        if (! $this->getWidget()->hasRowGroups()) {
            return '';
        }
        $grouper = $this->getWidget()->getRowGrouper();
        $groupFormatterJs = $this->getFacade()->getDataTypeFormatter($grouper->getGroupByColumn()->getDataType())->buildJsFormatter('mVal');
        $groupCaption = $grouper->getHideCaption() ? '' : $this->escapeJsTextValue($grouper->getCaption());
        $groupCaption .= $groupCaption ? ': ' : '';
        switch ($grouper->getExpandGroups()) {
            case DataRowGrouper::EXPAND_NO_GROUPS: $expandGroupJs = 'false'; break;
            case DataRowGrouper::EXPAND_FIRST_GROUP: $expandGroupJs = '1'; break;
            default: $expandGroupJs = 'true';
            
        }
        
        // NOTE: sap.ui.table.utils._GroupingUtils.resetExperimentalGrouping($oTableJs) did not work: it produced
        // empty group titles whenever their content was to change
        return  <<<JS
            
            (function(oTable, oModel) {
                if (! oModel.getData().rows || oModel.getData().rows.length === 0) {
                    return;
                }
                oTable.setEnableGrouping(true);
                oTable.setGroupBy('{$this->getFacade()->getElement($grouper->getGroupByColumn())->getId()}');
                
                var oBinding = oTable.getBinding('rows');
                var iRowCnt = oTable._getTotalRowCount();
                var iHeaderIdx = -1;
                var mExpand = $expandGroupJs;
                var aCtxts = oBinding.getContexts(0, iRowCnt);
                var iExpanded = 0;
                for (var i = 0; i < iRowCnt; i++) {
                    if (aCtxts[i].__groupInfo) {
                        aCtxts[i].__groupInfo.name = (function(mVal) {
                            if (mVal === null || mVal === undefined) {
                                return '{$groupCaption}{$this->escapeJsTextValue($grouper->getEmptyText())}';
                            }
                            return '{$groupCaption}' + {$groupFormatterJs}
                        })(aCtxts[i].__groupInfo.name);
                    }
                    if (oBinding.isGroupHeader(i)) {
                        iHeaderIdx++;
                        if (mExpand === false || (Number.isInteger(mExpand) && iHeaderIdx >= (mExpand - 1))) {
                            oBinding.collapse(i);
                        }
                    }
                }
                // Resize columns every time a group gets expanded
                oBinding.attachChange(function(oEvent) {
                    var iExpandedBefore = iExpanded;
                    // Change-events on expand/collapse do not have a reason. Ignore others
                    if (oEvent.getParameters().reason !== undefined) {
                        return;
                    }
                    iExpanded = 0;
                    for (var i=0; i<iRowCnt; i++) {
                        if(oBinding.isExpanded(i)) iExpanded += 1;
                    }
                    // If a group just got expanded, there are more expanded nodes now.
                    // Resize the columns to match the newly visible data
                    if (iExpanded > iExpandedBefore) {
                        setTimeout(function(){
                            {$this->getController()->buildJsMethodCallFromController(self::CONTROLLER_METHOD_RESIZE_COLUMNS, $this, 'oTable, ' . $oModelJs)}
                        }, 100);
                    }
                });
            })($oTableJs, $oModelJs);
JS;
    }
    
    /**
     * Optimize column width. This is not easy with sap.ui.table.Table :(
     * 
     * 1. oTable.autoResizeColumn() sets the focus to the column, so it is scrolled into
     * view. After a number of workaround attempts, a hack of UI5 solves the problem now. 
     * See Docs/UI5_modifications.md for details.
     * 2. The optimizer only works AFTER all column were populated, so we need a setTimeout().
     * TODO would be better to have an event, but none seemed suitable... Perhaps rowsUpdated? #ui5-update
     * 3. Since the optimizer works asynchronously, it will break if while it is running the
     * underlying data changes. In an attempt to avoid this, we do not optimize empty data.
     * 4. It seems, that the empty space on the right side of the table (if it is not occupied
     * with columns completely) is a column too. Optimizing that column will stretch some of
     * the others again as it does not have any data. So we check if each column has a DOM
     * element (that special column does not) and only optimize it then.
     * 4. The optimizer does not take the column header into account, so on narrow columns
     * the header gets truncated. We need to double-check this after all columns are resized
     * 5. Also need to make sure, the maximum width of the column is not exceeded
     * 6. TODO might need to check for minimum width too!
     * 
     * @param string $oTableJs
     * @param string $oModelJs
     * @return string
     */
    protected function buildJsUiTableColumnResize(string $oTableJs, string $oModelJs) : string
    {
        if (($this->getWidget() instanceof DataTable) && $this->getWidget()->getAutoColumnWidth() === false) {
            return '';
        }
        return <<<JS

                var bResized = false;
                var oInitWidths = {};
                if (! $oModelJs.getData().rows || $oModelJs.getData().rows.length === 0) {
                    return;
                }
                
                $oTableJs.getColumns().reverse().forEach(function(oCol, i) {
                    var oWidth = oCol.data('_exfWidth');
                    if (! oWidth || $('#'+oCol.getId()).length === 0) return;
                    oInitWidths[$oTableJs.indexOfColumn(oCol)] = $('#'+oCol.getId()).width();
                    if (oCol.getVisible() === true && oWidth.auto === true) {
                        bResized = true;
                        $oTableJs.autoResizeColumn($oTableJs.indexOfColumn(oCol));
                    }
                    if (oWidth.fixed) {
                        oCol.setWidth(oWidth.fixed);
                    }
                });

                if (bResized) {
                    setTimeout(function(){
                        $oTableJs.getColumns().forEach(function(oCol){
                            var oWidth = oCol.data('_exfWidth');
                            var jqCol = $('#'+oCol.getId());
                            var jqLabel = jqCol.find('label');
                            var iWidth = null;
                            var iColIdx = $oTableJs.indexOfColumn(oCol);

                            if (! oWidth) return;
                            if (! oCol.getWidth() && oWidth.auto === true && oInitWidths[iColIdx] !== undefined) {
                                oCol.setWidth(oInitWidths[iColIdx] + 'px');
                            }
                            if (oCol.getVisible() === true && oWidth.auto === true) {
                                if (! jqLabel[0]) return;
                                if (jqLabel[0].scrollWidth > jqLabel.width()) {
                                    oCol.setWidth((jqLabel[0].scrollWidth + (jqCol.outerWidth()-jqLabel.width()) + 1).toString() + 'px');
                                }
                                if (oWidth.max) {
                                    iWidth = $('<div style="width: ' + oWidth.max + '"></div>').width();
                                    if (jqCol.outerWidth() > iWidth) {
                                        oCol.setWidth(oWidth.max);
                                    }
                                }
                            }
                        });
                    }, 0);
                }

                setTimeout(function(){
                    {$this->buildJsFixRowHeight($oTableJs)}
                }, 0);
JS;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see UI5DataElementTrait::buildJsDataLoaderPrepare()
     */
    protected function buildJsDataLoaderPrepare() : string
    {
        return $this->buildJsShowMessageOverlay($this->getWidget()->getEmptyText());
    }
    
    /**
     *
     * {@inheritdoc}
     * @see UI5DataElementTrait::buildJsOfflineHint()
     */
    protected function buildJsOfflineHint(string $oTableJs = 'oTable') : string
    {
        $hint = $this->escapeJsTextValue($this->translate('WIDGET.DATATABLE.OFFLINE_HINT'));
        if ($this->isMList() || $this->isMTable()) {
            return $oTableJs . '.setNoDataText("' . $hint . '");';
        } else {
            return "sap.ui.getCore().byId('{$this->getIdOfNoDataOverlay()}').setText(\"{$hint}\")";
        }
        return '';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see UI5DataElementTrait::getCaption()
     */
    public function getCaption() : string
    {
        if ($caption = $this->getCaptionViaTrait()) {
            $caption .= ($this->isUiTable() && $this->hasPaginator() ? ': ' : '');
        }
        return $caption;
    }
    
    /**
     * Returns the JS code to select the row with the zero-based index $iRowIdxJs and scroll it into view.
     * 
     * @param string $oTableJs
     * @param string $iRowIdxJs
     * @param bool $deSelect
     * @return string
     */
    public function buildJsSelectRowByIndex(string $oTableJs = 'oTable', string $iRowIdxJs = 'iRowIdx', bool $deSelect = false, string $bScrollToJs = 'true') : string
    {
        if ($this->isMList() === true) {
            $setSelectJs = ($deSelect === true) ? 'false' : 'true';
            //filter items to only get items with binding context
            //necessary as row groupers add item without binding to table
            return <<<JS

                var oItem = {$oTableJs}.getItems().filter(function(oItem){
                    return oItem.getBindingContext() !== undefined
                })[{$iRowIdxJs}];
                {$oTableJs}.setSelectedItem(oItem, {$setSelectJs});
                {$oTableJs}.fireSelectionChange({
                    listItem: oItem, 
                    selected: $setSelectJs
                });
                if ($bScrollToJs && oItem !== undefined) {
                    oItem.focus();
                }

JS;

                
        } else {
            $deSelectJs = $deSelect ? 'true' : 'false';
            // Cannot use the row index directly here because row group headers
            // are also part of the row numbering. In any case, it is much more
            // reliable to check each binding and compare its path to the row
            // number inside the rows array of the model.
            return <<<JS
                (function(oTable, iRowIdx, bDeselect, bScrollTo) {
                    var aSelections = oTable.getSelectedIndices();
                    var iTableIdx = iRowIdx;
                    var oBinding = oTable.getBinding("rows");
                    var fnFindTableIdx = function(iRowIdx) {
                        for (var i = 0; i < oBinding.getLength(); i++) {
                            var context = oBinding.getContexts(i, 1)[0]; // Get context for each row
                            if (context && context.getPath() === `/rows/` + iRowIdx) {
                                return i; // Match found
                            }
                        }
                    };
                    iTableIdx = fnFindTableIdx(iRowIdx);
                    oTable.clearSelection();
                    if (bDeselect === false) {
                        oTable.setSelectedIndex(iTableIdx);
                    }
                    if (bScrollTo) {
                        oTable.setFirstVisibleRow(iTableIdx);
                    }
                    aSelections.forEach(function(i){
                        if (i !== iTableIdx) {
                            oTable.addSelectionInterval(i, i);
                        }
                    });
                })($oTableJs, $iRowIdxJs, $deSelectJs, $bScrollToJs)

JS;
        }
    }
    
    /**
     * 
     * @see UI5DataElementTrait::buildJsShowMessageOverlay()
     */
    protected function buildJsShowMessageOverlay(string $message) : string
    {
        $hint = $this->escapeJsTextValue($message);
        if ($this->isMList() || $this->isMTable()) {
            $setNoData = "sap.ui.getCore().byId('{$this->getId()}').setNoDataText('{$hint}')";
        } elseif ($this->isUiTable()) {
            $setNoData = "sap.ui.getCore().byId('{$this->getIdOfNoDataOverlay()}').setText('{$hint}')";
        }
        return $this->buildJsDataResetter() . ';' . $setNoData;
    }
    
    public function buildJsRefreshPersonalization() : string
    {
        $widget = $this->getWidget();
        $uidColName = $widget->hasUidColumn() ? $widget->getUidColumn()->getDataColumnName() : "''";
        if ($this->isUiTable() === true) {
            return <<<JS

                        var oController = {$this->getController()->buildJsControllerGetter($this)};
                        var aColsConfig = {$this->getConfiguratorElement()->buildJsP13nColumnConfig()};
                        var oTable = sap.ui.getCore().byId('{$this->getId()}');
                        var aColumns = oTable.getColumns();
                        var oColsOptional = {$this->getController()->buildJsDependentObjectGetter(self::CONTROLLER_VAR_OPTIONAL_COLS, $this, 'oController')};
                        var aColumnsNew = [];
                        var bOrderChanged = false;
                        var iConfOffset = 0;
                        var oDirtyColumn = aColumns.filter(oColumn => oColumn.getId() === "{$this->getDirtyFlagAlias()}")[0];
                        var oUidCol = aColumns.filter(oColumn => oColumn.data('data_column_name') === {$this->escapeString($uidColName)})[0];

                        if (oDirtyColumn !== undefined) {
                            iConfOffset += 1;
                            aColumnsNew.push(oDirtyColumn);  
                        }
                        
                        aColsConfig.forEach(function(oColConfig, iConfIdx) {
                            var bFoundCol = false;
                            var oColumn;
                            // See if the column is part of the table right now
                            for (var iColIdx = 0; iColIdx < aColumns.length; iColIdx++) {
                                oColumn = aColumns[iColIdx];
                                if (oColumn.getId() === oColConfig.column_id) {
                                    if (iColIdx !== iConfIdx + iConfOffset) bOrderChanged = true;
                                    oColumn.setVisible(oColConfig.visible);
                                    aColumnsNew.push(oColumn);
                                    bFoundCol = true;
                                    return;
                                }
                            }
                            // If it is not AND it is an optional column, add it to the table
                            if (oColConfig.visible === true) {
                                oColumn = oColsOptional[oColConfig.column_name];
                                if (oColumn !== undefined) {
                                    oColumn.setVisible(true);
                                    aColumnsNew.push(oColumn); 
                                    bOrderChanged = true;
                                }   
                            }  
                        });

                        // TODO what if the column was part of the table, but is not in the config?
                        // e.g. the UID column, that is always added automatically. It seems to be
                        // added at the end, so we handle it here. But that does not feel good!
                        // UPDATE: Doesnt seem to work??
                        if (oUidCol !== undefined) {
                            aColumnsNew.push(oUidCol); 
                        }

                        if (bOrderChanged === true) {
                            oTable.removeAllColumns();
                            aColumnsNew.forEach(oColumn => {
                                oTable.addColumn(oColumn);
                            });
                        }

JS;
        } else {
            return <<<JS
                        // Responsive table
                        var aColsConfig = {$this->getConfiguratorElement()->buildJsP13nColumnConfig()};
                        var oTable = sap.ui.getCore().byId('{$this->getId()}');
                        var aColumns = oTable.getColumns();
                        var aColumnsNew = [];
                        var oController = {$this->getController()->buildJsControllerGetter($this)};
                        var oColsOptional = {$this->getController()->buildJsDependentObjectGetter(self::CONTROLLER_VAR_OPTIONAL_COLS, $this, 'oController')};

                        var bOrderChanged = false;

                        // add dirty column first
                        var oDirtyColumn = aColumns.find(col => col.getId() === "{$this->getDirtyFlagAlias()}");
                        if (oDirtyColumn) {
                            aColumnsNew.push(oDirtyColumn);
                        }

                        aColsConfig.forEach(function(oColConfig, iConfIdx) {
                            // table columns
                            aColumns.forEach(function(oColumn, iColIdx) {
                                if (oColumn.getId() === oColConfig.column_id) {
                                    if (oColumn.getVisible() !== oColConfig.visible) {
                                        oColumn.setVisible(oColConfig.visible);
                                    }
                                    aColumnsNew.push(oColumn);                                    
                                    return;
                                }  
                            });
                            // optional columns
                            if (oColConfig.visible === true) {
                                var oColumn = oColsOptional[oColConfig.column_name];
                                if (oColumn !== undefined) {
                                    oColumn.setVisible(true);
                                    aColumnsNew.push(oColumn); 
                                    return;
                                }   
                            }
                        });

                        // compare cols if re-order is needed
                        var aOldOrderIds = aColumns.filter(col => col.getVisible()).map(col => col.getId());
                        var aNewOrderIds = aColumnsNew.map(col => col.getId());

                        if (JSON.stringify(aOldOrderIds) !== JSON.stringify(aNewOrderIds)) {
                            bOrderChanged = true;
                        }

                        //if order/content changed, apply new columns and rebuild template
                        if (bOrderChanged) {
                            var oTemplate = oTable.getBindingInfo("items").template;
                            var aOldCells = oTemplate.getCells();
                            var aNewCells = [];

                            // map data by column id for re-ordering
                            var mColumnIdToCell = {};
                            aColumns.forEach((col, idx) => {
                                var colId = col.getId();
                                if (aOldCells[idx]) {
                                    mColumnIdToCell[colId] = aOldCells[idx];
                                }
                            });

                            // remove all columns
                            oTable.removeAllColumns();

                            // re-add columns in new order and update their cells
                            aColumnsNew.forEach((col, idx) => {
                                oTable.addColumn(col);

                                var colId = col.getId();
                                if (mColumnIdToCell[colId]) {
                                    // if is existing column, reuse cell from old template
                                    aNewCells.push(mColumnIdToCell[colId]);
                                } 
                                else {
                                    // if is new/optional column, bind data to col name from config
                                    var oColConfig = aColsConfig.find(c => c.column_id === colId);
                                    var sProperty = oColConfig.column_name;
                                    
                                    aNewCells.push(new sap.m.Text({
                                        text: '{' + sProperty + '}'
                                    }));
                                }
                            });

                            //upadte template
                            oTemplate.removeAllAggregation("cells");
                            aNewCells.forEach(cell => oTemplate.addCell(cell));
                        }

JS;
        }
    }

    /**
     * 
     * @return string
     */
    protected function buildJsCellConditionalDisablers() : string
    {
        foreach ($this->getWidget()->getColumns() as $col) {
            if ($conditionalProperty = $col->getCellWidget()->getDisabledIf()) {
                // TODO how to implement on-true/false widget functions here?
                foreach ($conditionalProperty->getConditions() as $condition) {
                    $leftExpressionIsRef = $condition->getValueLeftExpression()->isReference();
                    $rightExpressionIsRef = $condition->getValueRightExpression()->isReference();
                    if ($leftExpressionIsRef === true || $rightExpressionIsRef === true) {
                        $cellWidget = $col->getCellWidget();
                        $cellControlJs = 'oCellCtrl';
                        $cellElement =  $this->getFacade()->getElement($cellWidget);
                        $disablerJS = $cellElement->buildJsSetDisabled(true);
                        $disablerJS = str_replace("sap.ui.getCore().byId('{$cellElement->getId()}')", $cellControlJs, $disablerJS);
                        $enablerJS = $cellElement->buildJsSetDisabled(false);
                        $enablerJS = str_replace("sap.ui.getCore().byId('{$cellElement->getId()}')", $cellControlJs, $enablerJS);
                        $conditionalPropertyJs = $this->buildJsConditionalProperty($conditionalProperty, $disablerJS, $enablerJS);
                        
                        $selfRefOnTheLeft = ($leftExpressionIsRef && $this->getFacade()->getElement($condition->getValueLeftExpression()->getWidgetLink($cellWidget)->getTargetWidget()) === $this);
                        $selfRefOnTheRight = ($rightExpressionIsRef && $this->getFacade()->getElement($condition->getValueRightExpression()->getWidgetLink($cellWidget)->getTargetWidget()) === $this);
                        
                        if ($this->isUiTable() === true) {
                            return $this->buildJsCellConditionalDisablerForUiTable($col, $cellControlJs, $conditionalPropertyJs, ($selfRefOnTheLeft || $selfRefOnTheRight));
                        } elseif ($this->isMTable() === true) {
                            return $this->buildJsCellConditionalDisablerForMTable($col, $cellControlJs, $conditionalPropertyJs, ($selfRefOnTheLeft || $selfRefOnTheRight));
                        }
                    }
                }
            }
        }
        
        return '';
    }
    
    /**
     * Performs the $conditionalLogicJs for every current row and makes sure the cell control is available via $cellControlJs.
     * 
     * While iterating, every row is selected for a fraction of a second to make sure, that if
     * the conditional logic includes a call to the value-getter of the table itself, that getter
     * will return the value of processed row. This makes it possible to disable cell widget
     * depending on the value of other cells of the same row. E.g.:
     * 
     * ```
        {
          "widget_type": "DataTable",
          "object_alias": "exface.Core.ATTRIBUTE",
          "id": "tabelle",
          "filters": [
            {
              "attribute_alias": "OBJECT"
            }
          ],
          "columns": [
            {
              "attribute_alias": "NAME",
              "editable": false
            },
            {
              "attribute_alias": "RELATED_OBJ__LABEL",
              "editable": false
            },
            {
              "attribute_alias": "DELETE_WITH_RELATED_OBJECT",
              "cell_widget": {
                "widget_type": "InputCheckBox",
                "disabled_if": {
                  "operator": "AND",
                  "conditions": [
                    {
                      "value_left": "=tabelle!RELATED_OBJ__LABEL",
                      "comparator": "==",
                      "value_right": ""
                    }
                  ]
                }
              }
            }
          ]
        }
     * ```
     * 
     * TODO will this cause on-change-events to fire for every row selection???
     * 
     * @param DataColumn $col
     * @param string $cellControlJs
     * @param string $conditionalLogicJs
     * @param bool $logicDependsOnTable
     * @return string
     */
    protected function buildJsCellConditionalDisablerForMTable(DataColumn $col, string $cellControlJs, string $conditionalLogicJs, bool $logicDependsOnTable = false) : string
    {
        $colName = $col->getDataColumnName();
        
        // If the logic depends on the table itself, select the current row before executing it
        // and unselect it afterwards. Restore the selection after going through all rows
        if ($logicDependsOnTable === true) {
            $saveSelectionJs = 'var oldSelection = tbl.getSelectedItems(); tbl.removeSelections();';
            $conditionalLogicJs = <<<JS

            tbl.setSelectedItem(r);
            {$conditionalLogicJs}
            tbl.setSelectedItem(r, false);
JS;
            $restoreSelectionJs = <<<JS

    if (Array.isArray(oldSelection) && oldSelection.length > 0) {
        for (var i = 0; i < oldSelection.length; i++) {
            tbl.setSelectedItem(oldSelection[i]);
        }
    }
JS;
        } else {
            $saveSelectionJs = '';
            $restoreSelectionJs = '';
        }
        
        return <<<JS
        
setTimeout(function(){
    var tbl = sap.ui.getCore().byId('{$this->getId()}');
    {$saveSelectionJs}
    var iColIdx = 1;
    if (tbl.getMode() == sap.m.ListMode.MultiSelect) {
        iColIdx++;
    }
    tbl.getColumns().some(function(oColumn){
        if (oColumn.data('_exfDataColumnName') === '$colName') {
            return true; // stop iterating! .some() stops if a callback returns TRUE.
        }
        if (oColumn.getVisible() === true) {
            iColIdx++;
        }
    });
    
    tbl.getItems().forEach(function(r) {
        var cb = r.$().children('td').eq(iColIdx).children().first();
        var {$cellControlJs} = sap.ui.getCore().byId(cb.attr('id'));
        if ({$cellControlJs} != undefined) {
            {$conditionalLogicJs}
        }
    });
    {$restoreSelectionJs}
},0);

JS;
    }
    
    /**
     * @see buildJsCellConditionalDisablerForMTable()
     * @param DataColumn $col
     * @param string $cellControlJs
     * @param string $conditionalLogicJs
     * @param bool $logicDependsOnTable
     * @return string
     */
    protected function buildJsCellConditionalDisablerForUiTable(DataColumn $col, string $cellControlJs, string $conditionalLogicJs, bool $logicDependsOnTable = false) : string
    {
        $colName = $col->getDataColumnName();
        
        // If the logic depends on the table itself, select the current row before executing it
        // and unselect it afterwards. Restore the selection after going through all rows
        if ($logicDependsOnTable === true) {
            $saveSelectionJs = 'var oldSelection = tbl.getSelectedIndices().slice(); tbl.clearSelection();';
            $conditionalLogicJs = <<<JS
            
            tbl.addSelectionInterval(r.getIndex(), r.getIndex());
            {$conditionalLogicJs}
JS;
            $clearSelectionJs = 'tbl.clearSelection();';
            $restoreSelectionJs = <<<JS
            
    if (Array.isArray(oldSelection) && oldSelection.length > 0) {
        for (var i = 0; i < oldSelection.length; i++) {
            tbl.addSelectionInterval(oldSelection[i], oldSelection[i]);
        }
    }
JS;
        } else {
            $saveSelectionJs = '';
            $restoreSelectionJs = '';
            $clearSelectionJs = '';
        }
        
        $conditionalPropertiesJs = <<<JS
        
(function() {
    var tbl = sap.ui.getCore().byId('{$this->getId()}');
    {$saveSelectionJs}
    var iColIdx = 0;
    tbl.getColumns().some(function(oColumn){
        if (oColumn.data('_exfDataColumnName') === '$colName') {
            return true;
        }
        if (oColumn.getVisible() === true) {
            iColIdx++;
        }
    });
    tbl.getRows().forEach(function(r) {
        var cb = r.$().find('.sapUiTableCellInner').eq(iColIdx).children().first();
        var {$cellControlJs} = sap.ui.getCore().byId(cb.attr('id'));
        if ({$cellControlJs} != undefined) {
            {$conditionalLogicJs}
        }
        {$clearSelectionJs}
    });
    {$restoreSelectionJs}
})();

JS;
            
        $this->getController()->addOnEventScript($this, self::EVENT_NAME_FIRST_VISIBLE_ROW_CHANGED, $conditionalPropertiesJs);
        return $conditionalPropertiesJs;
    }
    
    /**
     * Builds the javascript to select all rows with the same value in the DataColumn as the selected row
     * 
     * @param DataColumn $column
     * @return string
     */
    protected function buildJsMultiSelectSync(DataColumn $column) : string
    {
        $widget = $this->getWidget();
        $syncDataColumnName = $column->getDataColumnName();
        if ($this->isMList() === true) {
            
            return <<<JS
            
                var oTable = sap.ui.getCore().byId('{$this->getId()}');
                if (oTable.getModel()._syncChanges === undefined) {
                    oTable.getModel()._syncChanges = false;
                }
                var selected = false;
                var selectedItems = [];
                if (oEvent !== undefined) {
                    selected = oEvent.getParameters().selected;
                    selectedItems = oEvent.getParameter("listItems");
                }
                
                if (oTable.getModel()._syncChanges === false && oEvent !== undefined && selectedItems.length !== 0) {
                    oTable.getModel()._syncChanges = true;
                    var itemValues = selectedItems[0].getBindingContext().getObject();
                    var value = itemValues['{$syncDataColumnName}'];
                    if (value !== undefined) {
                        var aData = oTable.getModel().getData().rows;
                        for (var i in aData) {
                            if (value === aData[i]['{$syncDataColumnName}']) {
                                var index = parseInt(i);
                                var oItem = oTable.getItems()[index];
                                oTable.setSelectedItem(oItem, selected);
                            }
                        }                        
                        var exfSelection = {$this->buildJsGetRowsSelected('oTable')};
                        oTable.data('exfPreviousSelection', exfSelection);
                    } else {
                        var error = "Data Column '{$syncDataColumnName}' not found in data columns for widget '{$widget->getId()}'!";
                        {$this->buildJsShowMessageError('error', '"ERROR"')}
                    }
                
                    oTable.getModel()._syncChanges = false;
                }
                
JS;
        } else {
            
            return <<<JS
            
                var oTable = sap.ui.getCore().byId('{$this->getId()}');
                if (oTable.getModel()._syncChanges === undefined) {
                    oTable.getModel()._syncChanges = false;
                }
                var rowIdx = -1;
                if (oEvent !== undefined) {
                    rowIdx = oEvent.getParameters().rowIndex;
                }
                var selectedRowsIdx = [];
                selectedRowsIdx = oTable.getSelectedIndices();
                var selected = false; 
                if (selectedRowsIdx.includes(rowIdx)) {
                    selected = true;    
                }
                
                if (oTable.getModel()._syncChanges === false && oEvent !== undefined) {
                    oTable.getModel()._syncChanges = true;
                    var rowValues = oEvent.getParameters().rowContext.getObject();
                    var value = rowValues['{$syncDataColumnName}'];
                    if (value !== undefined) {
                            var aData = oTable.getModel().getData().rows;
                            for (var i in aData) {
                                if (value === aData[i]['{$syncDataColumnName}']) {
                                    var index = parseInt(i);
                                    if (selected === true) {
                                        oTable.addSelectionInterval(index, index);
                                    } else {
                                        oTable.removeSelectionInterval(index, index);
                                    }
                                }
                            }
                    } else {
                        var error = "Data Column '{$syncDataColumnName}' not found in data columns for widget '{$widget->getId()}'!";
                        {$this->buildJsShowMessageError('error', '"ERROR"')}
                    }

                    oTable.getModel()._syncChanges = false;
                }
                
JS;
        }
    }
    
    public function needsContainerHeight() : bool
    {
        return $this->isWrappedInDynamicPage() || $this->isUiTable();
    }
    
    protected function buildJsColumnStylers() : string
    {
        $js = '';
        foreach ($this->getWidget()->getColumns() as $col) {
            $js .= StringDataType::replacePlaceholders(($col->getCellStylerScript() ?? ''), ['table_id' => $this->getId()]);
        }
        return $js;
    }
    
    /**
     * 
     * @see UI5DataElementTrait::buildJsDataResetter()
     */
    protected function buildJsDataResetter() : string
    {
        $resetTableJs = '';
        if ($this->isUiTable()) {
            $resetTableJs = ! $this->isUiTable() ? '' : <<<JS

            if (sap.ui.getCore().byId('{$this->getId()}').getEnableGrouping() === true) {
                sap.ui.getCore().byId('{$this->getId()}').setEnableGrouping(false);
            }   
JS;
        } elseif ($this->isMTable()) {
            /* TODO clear selectios or not??? Not sure, why we removed this, but it caused trouble
            $resetTableJs = <<<JS

            //sap.ui.getCore().byId('{$this->getId()}').removeSelections();
JS;*/
        }
        return $resetTableJs . $this->buildJsDataResetterViaTrait();
    }
    
    protected function buildJsFixRowHeight(string $oTableJs) : string
    {
        if ($this->hasFixedRowHeight() === true) {
            return '';
        }
        
        return <<<JS

                    var jqTable = $('#{$this->getId()}');
                    var iRowCntOrig = jqTable.data('_exfMinRows');
                    var iHeaderHeight = jqTable.find('.sapUiTableHeaderRow').height() - 1;
                    var iRowHeightMax = iHeaderHeight;
                    var fnCalcRowHeight = function() {
                        var iNewVisibleRowCount;
                        var iRowCntCur = $oTableJs.getMinAutoRowCount();
                        // On first run, just remember the curent min row count
                        // On subsequent runs, check if min row count was decreased. If so, restore
                        // it, wait for rerender and repeat the optimization
                        if (iRowCntOrig === undefined) {
                            iRowCntOrig = iRowCntCur;
                            jqTable.data('_exfMinRows', iRowCntOrig);
                        } else if (iRowCntCur < iRowCntOrig) {
                            $oTableJs.setMinAutoRowCount(iRowCntOrig);
                            setTimeout(fnCalcRowHeight, 0);
                            return;
                        }
                        // Find the maximum height of immediate children of table cells
                        iRowHeightMax = Math.max.apply(null, jqTable.find('.sapUiTableRow > td > *').map(
                                function () {
                                    return $(this).height();
                                }
                            ).get()
                        );
                        // If the maximum height is greater, than the default height, increase row
                        // row height and decrease the minimum number of rows shown
                        if (iRowHeightMax > iHeaderHeight) {
                            iNewVisibleRowCount = Math.round(iRowCntOrig / (iRowHeightMax / iHeaderHeight));
                            $oTableJs
                                .setColumnHeaderHeight(iHeaderHeight)
                                .setMinAutoRowCount(iNewVisibleRowCount)
                                .setRowHeight(iRowHeightMax);
                        }
                    };
                    $oTableJs.setRowHeight(0);
                    fnCalcRowHeight();
JS;
    }
    
    protected function hasFixedRowHeight() : bool
    {
        foreach ($this->getWidget()->getColumns() as $col) {
            switch (true) {
                case $col->isHidden() === true:
                    continue 2;
                case $col->getCellWidget()->getHeight()->isUndefined() === false:
                    continue 2;
                case $col->getNowrap() === false:
                    return false;
            }
        }
        return true;
    }
}