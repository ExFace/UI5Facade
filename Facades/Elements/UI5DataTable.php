<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Actions\iReadData;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JqueryDataTableTrait;
use exface\Core\Widgets\Button;
use exface\Core\Widgets\ButtonGroup;
use exface\Core\Widgets\Data;
use exface\Core\Widgets\DataTableResponsive;
use exface\Core\Widgets\MenuButton;
use exface\UI5Facade\Facades\Elements\Traits\UI5DataElementTrait;
use exface\Core\Widgets\DataColumn;
use exface\Core\Widgets\DataButton;

/**
 *
 * @method DataTable getWidget()
 *
 * @author Andrej Kabachnik
 *
 */
class UI5DataTable extends UI5AbstractElement
{
    use JqueryDataTableTrait;
    
    use UI5DataElementTrait {
       buildJsDataLoaderOnLoaded as buildJsDataLoaderOnLoadedViaTrait;
       buildJsConstructor as buildJsConstructorViaTrait;
       getCaption as getCaptionViaTrait;
       init as initViaTrait;
    }
    
    protected function init()
    {
        $this->initViaTrait();
        $this->getConfiguratorElement()->setIncludeColumnsTab(true);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsConstructor()
     */
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {
        $this->getPaginatorElement()->registerControllerMethods();
        return $this->buildJsConstructorViaTrait($oControllerJs);
    }
    
    protected function buildJsConstructorForControl($oControllerJs = 'oController') : string
    {
        if ($this->isMTable()) {
            $js = $this->buildJsConstructorForMTable($oControllerJs);
        } else {
            $js = $this->buildJsConstructorForUiTable($oControllerJs);
        }
        
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
        
        return <<<JS
        new sap.m.VBox({
            width: "{$this->getWidth()}",
    		items: [
                new sap.m.Table("{$this->getId()}", {
            		fixedLayout: false,
                    sticky: [sap.m.Sticky.ColumnHeaders, sap.m.Sticky.HeaderToolbar],
                    alternateRowColors: {$striped},
                    noDataText: "{$this->getWidget()->getEmptyText()}",
            		itemPress: {$this->buildJsOnChangeTrigger(true)},
                    mode: {$mode},
                    headerToolbar: [
                        {$this->buildJsToolbar($oControllerJs)}
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
            		}
                })
                {$this->buildJsClickListeners('oController')}
                {$this->buildJsPseudoEventHandlers()}
                ,
                {$this->buildJsConstructorForMTableFooter()}
            ]
        })
        
JS;
    }
    
    protected function buildJsConstructorForMTableFooter(string $oControllerJs = 'oController') : string
    {
        $visible = $this->getWidget()->isPaged() === false || $this->getWidget()->getHideFooter() === true ? 'false' : 'true';
        return <<<JS
                new sap.m.OverflowToolbar({
                    design: "Info",
                    visible: {$visible},
    				content: [
                        {$this->getPaginatorElement()->buildJsConstructor($oControllerJs)},
                        new sap.m.ToolbarSpacer(),
                        {$this->buildJsConfiguratorButtonConstructor($oControllerJs, 'Transparent')}
                    ]
                })
                
JS;
    }
    
    protected function buildJsBindingOptionsForGrouping()
    {
        $widget = $this->getWidget();
        
        if (! $widget->hasRowGroups()) {
            return '';
        }
        
        return <<<JS
        
                sorter: new sap.ui.model.Sorter(
    				'{$widget->getRowGrouper()->getGroupByColumn()->getDataColumnName()}', // sPath
    				false, // bDescending
    				true // vGroup
    			),
    			/*groupHeaderFactory: function(oGroup) {
                    // TODO add support for counters
                    return new sap.m.GroupHeaderListItem({
        				title: oGroup.key,
        				upperCase: false
        			});
                },*/
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
        
        $js = <<<JS
            new sap.ui.table.Table("{$this->getId()}", {
                width: "{$this->getWidth()}",
        		visibleRowCountMode: sap.ui.table.VisibleRowCountMode.Auto,
                selectionMode: {$selection_mode},
        		selectionBehavior: {$selection_behavior},
                enableColumnReordering:true,
                enableColumnFreeze: true,
        		filter: {$controller->buildJsMethodCallFromView('onLoadData', $this)},
        		sort: {$controller->buildJsMethodCallFromView('onLoadData', $this)},
                rowSelectionChange: {$this->buildJsOnChangeTrigger(true)},
        		toolbar: [
        			{$this->buildJsToolbar($oControllerJs, $this->getPaginatorElement()->buildJsConstructor($oControllerJs))}
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
            {$this->buildJsClickListeners('oController')}
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
        // Columns
        $column_defs = '';
        foreach ($this->getWidget()->getColumns() as $column) {
            $column_defs .= ($column_defs ? ", " : '') . $this->getFacade()->getElement($column)->buildJsConstructorForUiColumn();
        }
        
        return $column_defs;
    }
    
    protected function buildJsCellsForMTable()
    {
        $cells = '';
        foreach ($this->getWidget()->getColumns() as $column) {
            $cells .= ($cells ? ", " : '') . $this->getFacade()->getElement($column)->buildJsConstructorForCell();
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
        // because sap.m.table would otherwise have not column headers at all.
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
        
        if (! $promotedFound) {
            $first_col->setVisibility(EXF_WIDGET_VISIBILITY_PROMOTED);
        }
        
        $column_defs = '';
        foreach ($this->getWidget()->getColumns() as $column) {
            $column_defs .= ($column_defs ? ", " : '') . $this->getFacade()->getElement($column)->buildJsConstructorForMColumn();
        }
        
        return $column_defs;
    }
    
    /**
     *
     * @return string
     */
    protected function buildJsDataLoaderFromLocal($oControlEventJsVar = 'oControlEvent', $keepPagePosJsVar = 'keep_page_pos')
    {
        $widget = $this->getWidget();
        $data = $widget->prepareDataSheetToRead($widget->getValuesDataSheet());
        if (! $data->isFresh()) {
            $data->dataRead();
        }
        
        // FIXME make filtering, sorting, pagination, etc. work in lazy mode too!
        
        return <<<JS
        
                try {
        			var data = {$this->getFacade()->encodeData($this->getFacade()->buildResponseData($data, $widget))};
        		} catch (err){
                    console.error('Cannot load data into widget {$this->getId()}!');
                    return;
        		}
                sap.ui.getCore().byId("{$this->getId()}").getModel().setData(data);
                
JS;
    }
    
    protected function buildJsDataLoaderParams(string $oControlEventJsVar = 'oControlEvent', string $oParamsJs = 'params', $keepPagePosJsVar = 'keep_page_pos') : string
    {
        $paginationSwitch = $this->getWidget()->isPaged() ? 'true' : 'false';
        
        $commonParams = <<<JS

        		// Add pagination 
                if ({$paginationSwitch}) {
                    var paginator = {$this->getPaginatorElement()->buildJsGetPaginator('oController')};
                    if (! {$keepPagePosJsVar}) {
                        paginator.resetAll();
                    }
                    {$oParamsJs}.start = paginator.start;
                    {$oParamsJs}.length = paginator.pageSize;
                }

JS;
        
                  
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
     * @param bool $keep_page_pos
     * @param string $oControllerJsVar
     *
     * @return UI5DataTable
     */
    public function buildJsRefresh($keep_page_pos = false, string $oControllerJsVar = null)
    {
        $params = "undefined, " . ($keep_page_pos ? 'true' : 'false');
        if ($oControllerJsVar === null) {
            return $this->getController()->buildJsMethodCallFromController('onLoadData', $this, $params);
        } else {
            return $this->getController()->buildJsMethodCallFromController('onLoadData', $this, $params, $oControllerJsVar);
        }
    }
    
    public function buildJsDataGetter(ActionInterface $action = null)
    {
        if ($action === null) {
            $rows = "sap.ui.getCore().byId('{$this->getId()}').getModel().getData().rows";
        } elseif ($action instanceof iReadData) {
            // If we are reading, than we need the special data from the configurator
            // widget: filters, sorters, etc.
            return $this->getConfiguratorElement()->buildJsDataGetter($action);
        } elseif ($this->isEditable() && $action->implementsInterface('iModifyData')) {
            $rows = "oTable.getModel().getData().rows";
        } else {
            if ($this->isUiTable()) {
                $rows = '[];' . <<<JS
        
        var aSelectedIndices = oTable.getSelectedIndices();
        var aDataRows = oTable.getModel().getData().rows;
        for (var i in aSelectedIndices) {
            rows.push(aDataRows[aSelectedIndices[i]]);
        }

JS;
            } else {
                $rows = '[];' . <<<JS
                
        var aSelectedContexts = oTable.getSelectedContexts();
        for (var i in aSelectedContexts) {
            rows.push(aSelectedContexts[i].getObject());
        }
        
JS;
            }
        }
        return <<<JS
    function() {
        var oTable = sap.ui.getCore().byId('{$this->getId()}');
        var rows = {$rows};
        return {
            oId: '{$this->getWidget()->getMetaObject()->getId()}',
            rows: (rows === undefined ? [] : rows)
        };
    }()
JS;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsValueGetter()
     */
    public function buildJsValueGetter($dataColumnName = null, $rowNr = null)
    {
        $row = $this->buildJsGetSelectedRows('oTable');
        $col = $dataColumnName !== null ? '["' . $dataColumnName . '"]' : '';
        
        return <<<JS
        
function(){
    var oTable = sap.ui.getCore().byId('{$this->getId()}');
    return {$row}{$col};
}()

JS;
    }
        
    protected function buildJsGetSelectedRows(string $oTableJs) : string
    {
        if ($this->isUiTable()) {
            $row = "($oTableJs.getModel().getData().rows[$oTableJs.getSelectedIndex()] || [])";
        } else {
            $row = "($oTableJs.getSelectedItem() ? $oTableJs.getSelectedItem().getBindingContext().getObject() : [])";
        }
        return $row;
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
    protected function buildJsClickListenerIsTargetRowCheck(string $oTargetDomJs = 'oTargetDom') : string
    {
        if ($this->isUiTable()) {
            return "{$oTargetDomJs} !== undefined && $({$oTargetDomJs}).parents('.sapUiTableCCnt').length > 0";
        }
        
        if ($this->isMTable()) {
            return "{$oTargetDomJs} !== undefined && $({$oTargetDomJs}).parents('tr.sapMListTblRow:not(.sapMListTblHeader)').length > 0";
        }
        
        if ($this->isMList()) {
            return "{$oTargetDomJs} !== undefined && $({$oTargetDomJs}).parents('li.sapMSLI').length > 0";
        }
        
        return 'true';
    }
    
    protected function buildJsClickListeners($oControllerJsVar = 'oController')
    {
        $widget = $this->getWidget();
        
        $js = '';
        $rightclick_script = '';
        
        // Double click. Currently only supports one double click action - the first one in the list of buttons
        if ($dblclick_button = $widget->getButtonsBoundToMouseAction(EXF_MOUSE_ACTION_DOUBLE_CLICK)[0]) {
            $js .= <<<JS
            
            .attachBrowserEvent("dblclick", function(oEvent) {
                var oTargetDom = oEvent.target;
                if(! ({$this->buildJsClickListenerIsTargetRowCheck('oTargetDom')})) return;

        		{$this->getFacade()->getElement($dblclick_button)->buildJsClickEventHandlerCall($oControllerJsVar)};
            })
JS;
        }
        
        // Right click. Currently only supports one double click action - the first one in the list of buttons
        if ($rightclick_button = $widget->getButtonsBoundToMouseAction(EXF_MOUSE_ACTION_RIGHT_CLICK)[0]) {
            $rightclick_script = $this->getFacade()->getElement($rightclick_button)->buildJsClickEventHandlerCall($oControllerJsVar);
        } else {
            $rightclick_script = $this->buildJsContextMenuTrigger();
        }
        
        if ($rightclick_script) {
            $js .= <<<JS
            
            .attachBrowserEvent("contextmenu", function(oEvent) {
                var oTargetDom = oEvent.target;
                if(! ({$this->buildJsClickListenerIsTargetRowCheck('oTargetDom')})) return;

                oEvent.preventDefault();
                {$rightclick_script}
        	})
        	
JS;
        }
        
        // Single click. Currently only supports one click action - the first one in the list of buttons
        if ($leftclick_button = $widget->getButtonsBoundToMouseAction(EXF_MOUSE_ACTION_LEFT_CLICK)[0]) {
            if ($this->isUiTable()) {
                $js .= <<<JS
                
            .attachBrowserEvent("click", function(oEvent) {
        		var oTargetDom = oEvent.target;
                if(! ({$this->buildJsClickListenerIsTargetRowCheck('oTargetDom')})) return;

                {$this->getFacade()->getElement($leftclick_button)->buildJsClickEventHandlerCall($oControllerJsVar)};
            })
JS;
            } else {
                $js .= <<<JS
                
            .attachItemPress(function(oEvent) {
                {$this->getFacade()->getElement($leftclick_button)->buildJsClickEventHandlerCall($oControllerJsVar)};
            })
JS;
            }
        }
        
        return $js;
    }
    
    protected function buildJsContextMenuTrigger($eventJsVar = 'oEvent') {
        return <<<JS
        
                var oMenu = {$this->buildJsContextMenu($this->getWidget()->getButtons())};
                var eFocused = $(':focus');
                var eDock = sap.ui.core.Popup.Dock;
                oMenu.open(true, eFocused, eDock.CenterCenter, eDock.CenterBottom,  {$eventJsVar}.target);
                
JS;
    }
    
    /**
     *
     * @param Button[]
     * @return string
     */
    protected function buildJsContextMenu(array $buttons)
    {
        return <<<JS
        
                new sap.ui.unified.Menu({
                    items: [
                        {$this->buildJsContextMenuButtons($buttons)}
                    ]
                })
JS;
    }
    
    /**
     *
     * @param Button[] $buttons
     * @return string
     */
    protected function buildJsContextMenuButtons(array $buttons)
    {
        $context_menu_js = '';
        
        $last_parent = null;
        foreach ($buttons as $button) {
            if ($button->isHidden()) {
                continue;
            }
            if ($button->getParent() == $this->getWidget()->getToolbarMain()->getButtonGroupForSearchActions()) {
                continue;
            }
            if (! is_null($last_parent) && $button->getParent() !== $last_parent) {
                $startSection = true;
            }
            $last_parent = $button->getParent();
            
            $context_menu_js .= ($context_menu_js ? ',' : '') . $this->buildJsContextMenuItem($button, $startSection);
        }
        
        return $context_menu_js;
    }
    
    /**
     *
     * @param Button $button
     * @param boolean $startSection
     * @return string
     */
    protected function buildJsContextMenuItem(Button $button, $startSection = false)
    {
        $menu_item = '';
        
        $startsSectionProperty = $startSection ? 'startsSection: true,' : '';
        
        /* @var $btn_element \exface\UI5Facade\Facades\Elements\UI5Button */
        $btn_element = $this->getFacade()->getElement($button);
        
        if ($button instanceof MenuButton){
            if ($button->getParent() instanceof ButtonGroup && $button === $this->getFacade()->getElement($button->getParent())->getMoreButtonsMenu()){
                $caption = $button->getCaption() ? $button->getCaption() : '...';
            } else {
                $caption = $button->getCaption();
            }
            $menu_item = <<<JS
            
                        new sap.ui.unified.MenuItem({
                            icon: "{$btn_element->buildCssIconClass($button->getIcon())}",
                            text: "{$caption}",
                            {$startsSectionProperty}
                            submenu: {$this->buildJsContextMenu($button->getButtons())}
                        })
JS;
        } else {
            $handler = $btn_element->buildJsClickViewEventHandlerCall();
            $select = $handler !== '' ? 'select: ' . $handler . ',' : '';
            $menu_item = <<<JS
            
                        new sap.ui.unified.MenuItem({
                            icon: "{$btn_element->buildCssIconClass($button->getIcon())}",
                            text: "{$button->getCaption()}",
                            {$select}
                            {$startsSectionProperty}
                        })
JS;
        }
        return $menu_item;
    }
        
    /**
     * Empties the table by replacing it's model by an empty object.
     * 
     * @return string
     */
    protected function buildJsDataResetter() : string
    {
        return "sap.ui.getCore().byId('{$this->getId()}').getModel().setData({})";   
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
        if ($singleResultButton = $this->getWidget()->getButtons(function($btn) {return ($btn instanceof DataButton) && $btn->isBoundToSingleResult() === true;})[0]) {
            $singleResultJs = <<<JS
            if ({$oModelJs}.getData().rows.length === 1) {
                var curRow = {$oModelJs}.getData().rows[0];
                var lastRow = oTable._singleResultActionPerformedFor;
                if (lastRow === undefined || {$this->buildJsRowCompare('curRow', 'lastRow')} === false){
                    {$this->buildJsSelectRowByIndex('oTable', '0')}
                    oTable._singleResultActionPerformedFor = curRow;
                    {$this->getFacade()->getElement($singleResultButton)->buildJsClickEventHandlerCall('oController')};
                } else {
                    oTable._singleResultActionPerformedFor = {};
                }
            }
                        
JS;
        }
                    
        // For some reason, the sorting indicators on the column are changed to the opposite after
        // the model is refreshed. This hack fixes it by forcing sorted columns to keep their
        // indicator.
        if ($this->isUiTable() === true) {
            $sortOrderFix = <<<JS
            
            sap.ui.getCore().byId('{$this->getId()}').getColumns().forEach(function(oColumn){
                if (oColumn.getSorted() === true) {
                    var order = oColumn.getSortOrder()
                    setTimeout(function(){
                        oColumn.setSortOrder(order);
                    }, 0);
                }
            });

JS;
            $setFooterRows = <<<JS

            if (footerRows){
				oTable.setFixedBottomRowCount(parseInt(footerRows));
			}

JS;
            
            // Weird code to make the table fill it's container. If not done, tables within
            // sap.f.Card will not be high enough. 
            $heightFix = 'oTable.setVisibleRowCountMode("Fixed").setVisibleRowCountMode("Auto");';
        }
        
        return $this->buildJsDataLoaderOnLoadedViaTrait($oModelJs) . <<<JS

			var footerRows = {$oModelJs}.getProperty("/footerRows");
            {$setFooterRows}

            {$paginator->buildJsSetTotal($oModelJs . '.getProperty("/recordsFiltered")', 'oController')};
            {$paginator->buildJsRefresh('oController')};  
            {$this->buildJsOnChangeTrigger(false)}
            {$singleResultJs} 
            {$sortOrderFix}   
            {$heightFix}      
            
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
        if ($this->isMList()) {
            return $oTableJs . ".setNoDataText('{$this->translate('WIDGET.DATATABLE.OFFLINE_HINT')}');";
        }
        
        return '';
    }
    
    /**
     *
     * @return UI5DataPaginator
     */
    protected function getPaginatorElement() : UI5DataPaginator
    {
        return $this->getFacade()->getElement($this->getWidget()->getPaginator());
    }
    
    /**
     *
     * @return bool
     */
    protected function hasPaginator() : bool
    {
        return ($this->getWidget() instanceof Data) && $this->getWidget()->isPaged();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see UI5DataElementTrait::getCaption()
     */
    public function getCaption() : string
    {
        if ($caption = $this->getCaptionViaTrait()) {
            $caption .= ($this->hasPaginator() ? ': ' : '');
        }
        return $caption;
    }
    
    /**
     * Returns the JS code to select the row with the zero-based index $iRowIdxJs and scroll it into view.
     * 
     * @param string $oTableJs
     * @param string $iRowIdxJs
     * @return string
     */
    protected function buildJsSelectRowByIndex(string $oTableJs = 'oTable', string $iRowIdxJs = 'iRowIdx') : string
    {
        if ($this->isMList() === true) {
            return <<<JS

                var oItem = {$oTableJs}.getItems()[{$iRowIdxJs}];
                {$oTableJs}.setSelectedItem(oItem);
                oItem.focus();

JS;
        } else {
            return <<<JS

                oTable.setFirstVisibleRow({$iRowIdxJs});
                oTable.setSelectedIndex({$iRowIdxJs});

JS;
        }
    }
    
    /**
     * Returns JS code to select the first row in a table, that has the given value in the specified column.
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
     * @return string
     */
    public function buildJsSelectRowByValue(DataColumn $column, string $valueJs, string $onNotFoundJs = '', string $rowIdxJs = 'rowIdx') : string
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
        {$this->buildJsSelectRowByIndex('oTable', 'iRowIdx')}
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
                            aColumns.forEach(function(oColumn, iColIdx) {
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
                        
                        var bOrderChanged = false;
                        aColsConfig.forEach(function(oColConfig, iConfIdx) {
                            aColumns.forEach(function(oColumn, iColIdx) {
                                if (oColumn.getId() === oColConfig.column_id) {
                                    if (oColumn.getVisible() !== oColConfig.visible) {
                                        oColumn.setVisible(oColConfig.visible);
                                    } else {
                                        if (iColIdx !== iConfIdx) bOrderChanged = true;
                                    }                                    
                                    return;
                                }
                            });
                        });
                        if (bOrderChanged === true) {
                            {$this->buildJsShowMessageError("'Moving columns currently not supported for responsive tables'")}
                        }

JS;
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsOnEventScript()
     */
    public function buildJsOnEventScript(string $eventName, string $scriptJs, string $oEventJs) : string
    {
        switch ($eventName) {
            case self::EVENT_NAME_CHANGE:
                return <<<JS
                
            // Check, if selection actually changed. Return here if not.
            if ((function(){
                var oTable = sap.ui.getCore().byId('{$this->getId()}');
                var newSelection = {$this->buildJsGetSelectedRows('oTable')};
                var oldSelection = oTable.data('exfPreviousSelection') || [];
                oTable.data('exfPreviousSelection', newSelection);
                return {$this->buildJsRowCompare('oldSelection', 'newSelection', false)};
            })()) {
                return;
            }
            {$scriptJs}
            
JS;
            default:
                return parent::buildJsOnEventScript($eventName, $scriptJs, $oEventJs);
        }
    }
}