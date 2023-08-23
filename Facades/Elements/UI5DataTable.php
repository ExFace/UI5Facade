<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Actions\iReadData;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JqueryDataTableTrait;
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

/**
 *
 * @method DataTable getWidget()
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
    }
    
    const EVENT_NAME_FIRST_VISIBLE_ROW_CHANGED = 'firstVisibleRowChanged';
    
    protected function init()
    {
        $this->initViaTrait();
        $this->getConfiguratorElement()->setIncludeColumnsTab(true);
    }
    
    protected function buildJsConstructorForControl($oControllerJs = 'oController') : string
    {
        $widget = $this->getWidget();
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
            $clearSelectionJs = "sap.ui.getCore().byId('{$this->getId()}').clearSelection()";
        } else {
            $clearSelectionJs = "sap.ui.getCore().byId('{$this->getId()}').removeSelections(true)";
        }
        $this->getController()->addOnPrefillDataChangedScript($clearSelectionJs);
        
        return $js;
    }

    protected function isMList() : bool
    {
        return $this->isMTable();
    }
    
    protected function isMTable()
    {
        return $this->getWidget() instanceof DataTableResponsive;
    }
    
    protected function isUiTable()
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
            width: "{$this->getWidth()}",
    		items: [
                new sap.m.Table("{$this->getId()}", {
            		fixedLayout: false,
                    contextualWidth: "Auto",
                    sticky: [sap.m.Sticky.ColumnHeaders, sap.m.Sticky.HeaderToolbar],
                    alternateRowColors: {$striped},
                    noDataText: "{$this->getWidget()->getEmptyText()}",
            		itemPress: {$controller->buildJsEventHandler($this, self::EVENT_NAME_CHANGE, true)},
                    selectionChange: {$controller->buildJsEventHandler($this, self::EVENT_NAME_CHANGE, true)},
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
        
        $js = <<<JS
            new sap.ui.table.Table("{$this->getId()}", {
                width: "{$this->getWidth()}",
        		visibleRowCountMode: sap.ui.table.VisibleRowCountMode.Auto,
                minAutoRowCount: 5,
                selectionMode: {$selection_mode},
        		selectionBehavior: {$selection_behavior},
                enableColumnReordering:true,
                fixedColumnCount: {$freezeColumnsCount},
                enableColumnFreeze: true,
                {$enableGrouping}
        		filter: {$controller->buildJsMethodCallFromView('onLoadData', $this)},
        		sort: {$controller->buildJsMethodCallFromView('onLoadData', $this)},
                rowSelectionChange: {$controller->buildJsEventHandler($this, self::EVENT_NAME_CHANGE, true)},
                firstVisibleRowChanged: {$controller->buildJsEventHandler($this, self::EVENT_NAME_FIRST_VISIBLE_ROW_CHANGED, true)},
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
                            new sap.m.Text("{$this->getId()}_noData", {text: "{$widget->getEmptyText()}"})
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
        
            // Add filters and sorters from column menus
            oTable.getColumns().forEach(oColumn => {
    			if (oColumn.getFiltered() === true){
    				{$oParamsJs}['{$this->getFacade()->getUrlFilterPrefix()}' + oColumn.getFilterProperty()] = oColumn.getFilterValue();
    			}
    		});
            
            // If filtering just now, make sure the filter from the event is set too (eventually overwriting the previous one)
    		if ({$oControlEventJsVar} && {$oControlEventJsVar}.getId() == 'filter'){
                var oColumn = {$oControlEventJsVar}.getParameters().column;
                var sFltrProp = oColumn.getFilterProperty();
                var sFltrVal = {$oControlEventJsVar}.getParameters().value;
                
                {$oParamsJs}['{$this->getFacade()->getUrlFilterPrefix()}' + sFltrProp] = sFltrVal;
                
                if (sFltrVal !== null && sFltrVal !== undefined && sFltrVal !== '') {
                    oColumn.setFiltered(true).setFilterValue(sFltrVal);
                } else {
                    oColumn.setFiltered(false).setFilterValue('');
                }         

                // Also make sure the built-in UI5-filtering is not applied.
                $oControlEventJsVar.cancelBubble();
                $oControlEventJsVar.preventDefault();
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
		
JS;
        } elseif ($this->isMTable()) {
            $tableParams = <<<JS

            // Set sorting indicators for columns
            var aSortProperties = ({$oParamsJs}.sort ? {$oParamsJs}.sort.split(',') : []);
            var aSortOrders = ({$oParamsJs}.sort ? {$oParamsJs}.order.split(',') : []);
            var iIdx = -1;
            sap.ui.getCore().byId('{$this->getId()}').getColumns().forEach(function(oColumn){
                iIdx = aSortProperties.indexOf(oColumn.data('_exfAttributeAlias'));
                if (iIdx > -1) {
                    oColumn.setSortIndicator(aSortOrders[iIdx] === 'desc' ? 'Descending' : 'Ascending');
                } else {
                    oColumn.setSortIndicator(sap.ui.core.SortOrder.None);
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
{
            oId: '{$dataObj->getId()}',
            rows: [
                {
                    '{$relAlias}': {
                        oId: '{$widget->getMetaObject()->getId()}',
                        rows: aRows
                    }
                }
            ]
        }
            
JS;
                }
                break;
                
            // In all other cases the data are the selected rows
            default:
                // NOTE: selected indices are not neccessarily the row indices in the model!
                // The table sometimes sorts the rows differently (e.g. when grouping in used).
                // This is why getContextByIndex() must be used instead of direct access to
                // the rows array.
                
                // NOTE: if there are total rows at the bottom, they can be selected too and will
                // even match data rows as the totals are appended to the data by the loader. This
                // is why we need to chek if the selected index is greater than the number of
                // real data rows (excluding the totals).
                // TODO: this might not work correctly with row grouping. Need some more testing!
                if ($this->isUiTable()) {
                    $aRowsJs = '[];' . <<<JS
                    
        var aSelectedIndices = oTable.getSelectedIndices();
        var oModel = oTable.getModel();
        var oCxt;
        var iFixedRowsCnt = oTable.getFixedBottomRowCount();
        for (var i in aSelectedIndices) {
            if (iFixedRowsCnt > 0 && aSelectedIndices[i] >= (oModel.getData().rows.length - iFixedRowsCnt)) {
                continue;
            }
            oCxt = oTable.getContextByIndex(aSelectedIndices[i]);
            if (oCxt) {
                aRows.push(oModel.getProperty(oCxt.sPath));
            }
        }
        
JS;
                } else {
                    $aRowsJs = '[];' . <<<JS
                    
        var aSelectedContexts = oTable.getSelectedContexts();
        for (var i in aSelectedContexts) {
            aRows.push(aSelectedContexts[i].getObject());
        }
        
JS;
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
     * @see UI5DataElementTrait::buildJsGetRowsSelected()
     */
    protected function buildJsGetRowsSelected(string $oTableJs) : string
    {
        if ($this->isUiTable()) {
            if($this->getWidget()->getMultiSelect() === false) {
                $rows = "($oTableJs && $oTableJs.getSelectedIndex() !== -1 && $oTableJs.getModel().getData().rows !== undefined ? [$oTableJs.getModel().getData().rows[$oTableJs.getSelectedIndex()]] : [])";
            } else {
                $rows = "function(){var selectedIdx = $oTableJs.getSelectedIndices(); var aRows = []; selectedIdx.forEach(index => aRows.push($oTableJs.getModel().getData().rows[index])); return aRows;}()";
            }
        } else {
            if($this->getWidget()->getMultiSelect() === false) {
                $rows = "($oTableJs && $oTableJs.getSelectedItem() ? [$oTableJs.getSelectedItem().getBindingContext().getObject()] : [])";
            } else {
                $rows = "$oTableJs.getSelectedContexts().reduce(function(aRows, oCtxt) {aRows.push(oCtxt.getObject()); return aRows;},[])";
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
        var aData = oModel.getData().data;
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
        
        // Add single-result action to onLoadSuccess
        if (($singleResultButton = $this->getWidget()->getButtons(function($btn) {return ($btn instanceof DataButton) && $btn->isBoundToSingleResult() === true;})[0]) || $this->getWidget()->getSelectSingleResult()) {
            $buttonClickJs = '';
            if ($singleResultButton) {
                $buttonClickJs = <<<JS

                if (lastRow === undefined || {$this->buildJsRowCompare('curRow', 'lastRow')} === false) {
                    oTable._singleResultActionPerformedFor = curRow;
                    {$this->getFacade()->getElement($singleResultButton)->buildJsClickEventHandlerCall('oController')};
                }
JS;
            }
            $singleResultJs = <<<JS
            if ({$oModelJs}.getData().rows.length === 1) {
                var curRow = {$oModelJs}.getData().rows[0];
                var lastRow = oTable._singleResultActionPerformedFor;
                {$this->buildJsSelectRowByIndex('oTable', '0')}
                {$buttonClickJs}                
            } else {
                oTable._singleResultActionPerformedFor = {};
            }
                        
JS;
        }
                    
        // For some reason, the sorting indicators on the column are changed to the opposite after
        // the model is refreshed. This hack fixes it by forcing sorted columns to keep their
        // indicator.
        $uiTablePostprocessing = '';
        $uiTableSetFooterRows = '';
        if ($this->isUiTable() === true) {
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
            
            // To get the experimental row grouping of the ui.table working, we need to
            // 1. set `enableGrouping` of the table (see `buildJsConstructorForUiTable()`)
            // 2. set the `grouped` flag on the column (see `UI5DataColumn::buildJsConstructorForUiColumn()`)
            // 3. pass the column or its id to the table via `setGroupBy` which is done here
            // Strangely Table.setGroupBy() fails if the column has no model data, so we
            // must do it here after the model was loaded.
            if ($this->getWidget()->hasRowGroups()) {
                $uiTablePostprocessing .= "oTable.setGroupBy('{$this->getFacade()->getElement($this->getWidget()->getRowGrouper()->getGroupByColumn())->getId()}');";
            }
            
            // Optimize column width. This is not easy with sap.ui.table.Table :(
            // 1) oTable.autoResizeColumn() sets the focus to the column, so it is scrolled into
            // view. After a number of workaround attempts, a hack of UI5 solves the problem now. 
            // See Docs/UI5_modifications.md for details.
            // 2) The optimizer only works AFTER all column were populated, so we need a setTimeout().
            // TODO would be better to have an event, but none seemed suitable... Perhaps rowsUpdated? #ui5-update
            // 3) Since the optimizer works asynchronously, it will break if while it is running the
            // underlying data changes. In an attempt to avoid this, we do not optimize empty data.
            // 4) It seems, that the empty space on the right side of the table (if it is not occupied
            // with columns completely) is a column too. Optimizing that column will stretch some of
            // the others again as it does not have any data. So we check if each column has a DOM
            // element (that special column does not) and only optimize it then.
            // 4) The optimizer does not take the column header into account, so on narrow columns
            // the header gets truncated. We need to double-check this after all columns are resized
            // 5) Also need to make sure, the maximum width of the column is not exceeded
            // 6) TODO might need to check for minimum width too!
            $uiTablePostprocessing .= <<<JS

            setTimeout(function(){
                var bResized = false;
                var oInitWidths = {};
                /*
                var domFocused = document.activeElement;
                var domTableScroll = oTable.$().closest(".sapUiTableCtrlScr"); // Find the closest container element that wraps the table
                var iTableLeft = domTableScroll.scrollLeft();
                var domPageScroll = oTable.$().closest('.sapUxAPObjectPageScroll');
                var iPageTop = domPageScroll.scrollTop();
                */
                if (! $oModelJs.getData().rows || $oModelJs.getData().rows.length === 0) {
                    return;
                }
                
                oTable.getColumns().reverse().forEach(function(oCol, i) {
                    var oWidth = oCol.data('_exfWidth');
                    if (! oWidth || $('#'+oCol.getId()).length === 0) return;
                    oInitWidths[oTable.indexOfColumn(oCol)] = $('#'+oCol.getId()).width();
                    if (oCol.getVisible() === true && oWidth.auto === true) {
                        bResized = true;
                        oTable.autoResizeColumn(oTable.indexOfColumn(oCol));
                        // document.activeElement.blur();
                        // domPageScroll.scrollTop(iPageTop);
                    }
                    if (oWidth.fixed) {
                        oCol.setWidth(oWidth.fixed);
                    }
                });

                if (bResized) {
                    setTimeout(function(){
                        /*
                        var domHScroll = (oTable._getScrollExtension() || {}).getHorizontalScrollbar();

                        if (domFocused) {
                            domFocused.focus();
                        }
                        */

                        oTable.getColumns().forEach(function(oCol){
                            var oWidth = oCol.data('_exfWidth');
                            var jqCol = $('#'+oCol.getId());
                            var jqLabel = jqCol.find('label');
                            var iWidth = null;
                            var iColIdx = oTable.indexOfColumn(oCol);

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
            /*if (typeof oTable === 'undefined') {
                var oTable = sap.ui.getCore().byId('{$this->getId()}');
            }*/

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
            return "sap.ui.getCore().byId('{$this->getId()}_noData').setText(\"{$hint}\")";
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
                oItem.focus();

JS;

                
        } else {
            return <<<JS

                if ($bScrollToJs) {
                    $oTableJs.setFirstVisibleRow({$iRowIdxJs});
                }
                $oTableJs.setSelectedIndex({$iRowIdxJs});

JS;
        }
    }
    
    /**
     * Returns JS code to select the first row in a table, that has the given value in the specified column.
     * If the parameter '$deSelect' is true, it will deselect the row instead.
     *
     * The generated code will search the current values of the $column for an exact match
     * for the value of $valueJs JS variable, mark the first matching row as selected and
     * scroll to it to ensure it is visible to the user.
     *
     * The row index (starting with 0) is saved to the JS variable specified in $rowIdxJs.
     *
     * If the $valueJs is not found, $onNotFoundJs will be executed and $rowIdxJs will be
     * set to -1.
     *
     * @param DataColumn $column
     * @param string $valueJs
     * @param string $onNotFoundJs
     * @param string $rowIdxJs
     * @param bool $deSelect
     * @return string
     */
    public function buildJsSelectRowByValue(DataColumn $column, string $valueJs, string $onNotFoundJs = '', string $rowIdxJs = 'rowIdx', bool $deSelect = false) : string
    {
        return <<<JS
        
var {$rowIdxJs} = function() {
    var oTable = sap.ui.getCore().byId("{$this->getId()}");
    var aData = oTable.getModel().getData().rows;
    var iRowIdx = -1;
    for (var i in aData) {
        if (aData[i]['{$column->getDataColumnName()}'] == $valueJs) {
            iRowIdx = i;
        }
    }

    if (iRowIdx == -1){
		{$onNotFoundJs};
	} else {
        {$this->buildJsSelectRowByIndex('oTable', 'iRowIdx', $deSelect)}
	}

    return iRowIdx;
}();

JS;
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
            $setNoData = "sap.ui.getCore().byId('{$this->getId()}_noData').setText('{$hint}')";
        }
        return $this->buildJsDataResetter() . ';' . $setNoData;
    }
    
    public function buildJsRefreshPersonalization() : string
    {
        if ($this->isUiTable() === true) {
            return <<<JS

                        var aColsConfig = {$this->getConfiguratorElement()->buildJsP13nColumnConfig()};
                        var oTable = sap.ui.getCore().byId('{$this->getId()}');
                        var aColumns = oTable.getColumns();
                        
                        var aColumnsNew = [];
                        var bOrderChanged = false;
                        aColsConfig.forEach(function(oColConfig, iConfIdx) {
                            var iConfOffset = 0;
                            aColumns.forEach(function(oColumn, iColIdx) {
                                if (oColumn.getId() === "{$this->getDirtyFlagAlias()}") {
                                    iConfOffset += 1;
                                    aColumnsNew.push(oColumn);  
                                    return;
                                }
                                if (oColumn.getId() === oColConfig.column_id) {
                                    if (iColIdx !== iConfIdx) bOrderChanged = true;
                                    oColumn.setVisible(oColConfig.visible);
                                    aColumnsNew.push(oColumn);
                                    return;
                                }
                            });
                        });
                        if (bOrderChanged === true) {
                            oTable.removeAllColumns();
                            aColumnsNew.forEach(oColumn => {
                                oTable.addColumn(oColumn);
                            });
                        }

JS;
        } else {
            return <<<JS

                        var aColsConfig = {$this->getConfiguratorElement()->buildJsP13nColumnConfig()};
                        var oTable = sap.ui.getCore().byId('{$this->getId()}');
                        var aColumns = oTable.getColumns();
                        var aColumnsNew = [];
                       
                        var bOrderChanged = false;
                        var aOrderChanges = new Array;
                        aColsConfig.forEach(function(oColConfig, iConfIdx) {
                            var iConfOffset = 0;
                            aColumns.forEach(function(oColumn, iColIdx) {
                                if (oColumn.getId() === "{$this->getDirtyFlagAlias()}") {
                                    iConfOffset += 1;
                                    aColumnsNew.push(oColumn);  
                                    return;
                                }
                                if (oColumn.getId() === oColConfig.column_id) {
                                    iConfIdx += iConfOffset;
                                    if (oColumn.getVisible() !== oColConfig.visible) {
                                        oColumn.setVisible(oColConfig.visible);
                                    }
                                    if (iColIdx !== iConfIdx) {
                                        bOrderChanged = true;
                                        aOrderChanges.push({idxFrom: iColIdx, idxTo: iConfIdx}); 
                                    }
                                    aColumnsNew.push(oColumn);                                    
                                    return;
                                }
                            });
                        });

                        if (bOrderChanged === true) {

                            var aCellBuffer = new Array;
                            var aRemovableCells = new Array;
                            var aCells = oTable.getBindingInfo("items").template.getCells();

                            oTable.removeAllColumns();
                            aColumnsNew.forEach(oColumn => {
                                oTable.addColumn(oColumn);
                            });

                            aOrderChanges.forEach(function(oOrderChange, oOrderChangeIdx){

                                var oCellFromBuffer = null;
                                aCellBuffer.forEach(function(oCellBuffer, oCellBufferIdx){
                                    if (oCellBuffer.previousIdx == oOrderChange.idxFrom){
                                        oCellFromBuffer = oCellBuffer.cell;
                                        return;
                                    }
                                });
                                
                                if (aRemovableCells.includes(oOrderChange.idxTo) == false){
                                    aCellBuffer.push({previousIdx: oOrderChange.idxTo, cell: aCells[oOrderChange.idxTo]});
                                }
                                
                                if (oCellFromBuffer != null){
                                    aCells[oOrderChange.idxTo] = oCellFromBuffer;
                                } else {
                                    aCells[oOrderChange.idxTo] = aCells[oOrderChange.idxFrom];
                                    aRemovableCells.push(oOrderChange.idxFrom);
                                }
                            }); 

                            oTable.getBindingInfo("items").template.mAggregations.cells = aCells;

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
}
