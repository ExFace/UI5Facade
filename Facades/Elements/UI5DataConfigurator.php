<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Facades\AbstractAjaxFacade\Elements\JqueryDataConfiguratorTrait;
use exface\Core\Widgets\DataConfigurator;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\DataTypes\SortingDirectionsDataType;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Widgets\iHaveColumns;

/**
 * 
 * @method DataConfigurator getWidget()
 * 
 * @author Andrej Kabachnik
 *
 */
class UI5DataConfigurator extends UI5Tabs
{
    use JqueryDataConfiguratorTrait {
        buildJsDataGetter as buildJsDataGetterViaTrait;
        buildJsResetter as buildJsResetterViaTrait;
    }
    
    const EVENT_BUTTON_OK = 'ok';
    const EVENT_BUTTON_CANCEL = 'cancel';
    const EVENT_BUTTON_RESET = 'reset';
        
    
    private $include_filter_tab = true;
    
    private $include_columns_tab = false;
    
    private $modelNameForConfig = null;
       
    /**
     * Can't use JqueryDataConfiguratorTrait::init() here because it would call registerFiltersWithApplyOnChange() too
     * early: before the controller was initialized! Instead, the method will be called in buildJsConstructor()
     * 
     * @see JqueryDataConfiguratorTrait::init()
     */
    protected function init()
    {
        parent::init();
    }
    
    /**
     * 
     * @param boolean $true_or_false
     * @return \exface\UI5Facade\Facades\Elements\UI5DataConfigurator
     */
    public function setIncludeFilterTab($true_or_false)
    {
        $this->include_filter_tab = BooleanDataType::cast($true_or_false);
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function getIncludeFilterTab() : bool
    {
        return $this->include_filter_tab;
    }
    
    protected function hasTabFilters() : bool
    {
        return $this->getIncludeFilterTab();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Tabs::buildJsConstructor()
     */
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {
        $controller = $this->getController();
        
        $dataElement = $this->getDataElement();
        if ($dataElement instanceof UI5DataTable) {
            $refreshP13n = $dataElement->buildJsRefreshPersonalization();
        }
        
        $this->registerFiltersWithApplyOnChange();
        
        $okScript = <<<JS
                
                    oEvent.getSource().close();
                    {$refreshP13n}
                    {$dataElement->buildJsRefresh()};

JS;
        $controller->addOnEventScript($this, self::EVENT_BUTTON_OK, $okScript);
        $controller->addOnEventScript($this, self::EVENT_BUTTON_CANCEL, 'oEvent.getSource().close();');
        $controller->addOnEventScript($this, self::EVENT_BUTTON_RESET, $this->buildJsResetter() . '; oEvent.getSource().setShowResetEnabled(true).close()');
        
        return <<<JS

        new sap.m.P13nDialog("{$this->getId()}", {
            ok: {$controller->buildJsEventHandler($this, self::EVENT_BUTTON_OK, true)},
            cancel: {$controller->buildJsEventHandler($this, self::EVENT_BUTTON_CANCEL, true)},
            showReset: true,
            showResetEnabled: true,
            reset: {$controller->buildJsEventHandler($this, self::EVENT_BUTTON_RESET, true)},
            panels: [
                {$this->buildJsTabFilters()}
                {$this->buildJsTabSorters()}
                {$this->buildJsTabSearch()}
                {$this->buildJsTabColumns()}
            ]
        })
        .setModel(function(){
            var oModel = new sap.ui.model.json.JSONModel();
            var columns = {$this->buildJsonColumnData()};
            var sortables = {$this->buildJsonSorterData()};
            var data = {
                "columns": columns,
                "sortables": sortables,
                "sorters": [{$this->buildJsInitialSortItems()}]
            }
            oModel.setData(data);
            return oModel;        
        }(), "{$this->getModelNameForConfig()}").setModel(function(){
            var oModel = new sap.ui.model.json.JSONModel();
            var columns = {$this->buildJsonColumnData()};
            var sortables = {$this->buildJsonSorterData()};
            var data = {
                "columns": columns,
                "sortables": sortables,
                "sorters": [{$this->buildJsInitialSortItems()}]
            }
            oModel.setData(data);
            return oModel;          
        }(), "{$this->getModelNameForConfig()}_initial")

JS;
    }
               
    /**
     * 
     * @return string
     */
    public function buildJsInitialSortItems() : string
    {
        $js = '';
        $operations = [SortingDirectionsDataType::ASC => 'Ascending', SortingDirectionsDataType::DESC => 'Descending'];
        foreach ($this->getWidget()->getDataWidget()->getSorters() as $sorter) {
            $js .= <<<JS

                    {attribute_alias: "{$sorter->getProperty('attribute_alias')}", direction: "{$operations[strtoupper($sorter->getProperty('direction'))]}"},
JS;
        }
        return $js;
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsTabFilters() : string
    {
        if (! $this->getIncludeFilterTab()) {
            return '';
        }
        
        return <<<JS

                new exface.openui5.P13nLayoutPanel({
                    title: "{$this->translate('WIDGET.DATATABLE.SETTINGS_DIALOG.FILTERS')}",
                    content: [
                        new sap.ui.layout.Grid({
                            defaultSpan: "L6 S12",
                            content: [
                                {$this->buildJsFilters()}
        					]
                        })
                    ]
                }),
JS;
        
    }
           
    /**
     * 
     * @return string
     */
    protected function buildJsTabSorters() : string
    {
        return <<<JS

                new sap.m.P13nSortPanel("{$this->getIdOfSortPanel()}", {
                    title: "{$this->translate('WIDGET.DATATABLE.SETTINGS_DIALOG.SORTING')}",
                    visible: true,
                    type: "sort",
                    layoutMode: "Desktop",
                    items: {
                        path: '{$this->getModelNameForConfig()}>/sortables',
                        template: new sap.m.P13nItem({
                            columnKey: "{{$this->getModelNameForConfig()}>attribute_alias}",
                            text: "{{$this->getModelNameForConfig()}>caption}"
                        })
                    },
                    sortItems: {
                        path: '{$this->getModelNameForConfig()}>/sorters',
                        template: new sap.m.P13nSortItem({
                            columnKey: "{{$this->getModelNameForConfig()}>attribute_alias}",
                            operation: "{{$this->getModelNameForConfig()}>direction}"
                        })
                    },
                    addSortItem: function(oEvent) {
            			var oParameters = oEvent.getParameters();
                        var oModel = this.getModel("{$this->getModelNameForConfig()}");
            			var aSortItems = oModel.getProperty("/sorters");
            			oParameters.index > -1 ? aSortItems.splice(oParameters.index, 0, {
            				attribute_alias: oParameters.sortItemData.getColumnKey(),
            				direction: oParameters.sortItemData.getOperation()
            			}) : aSortItems.push({
            				attribute_alias: oParameters.sortItemData.getColumnKey(),
            				direction: oParameters.sortItemData.getOperation()
            			});
            			oModel.setProperty("/sorters", aSortItems);
            		},
                    removeSortItem: function(oEvent) {
            			var oParameters = oEvent.getParameters();
            			var oModel = this.getModel("{$this->getModelNameForConfig()}");
            			if (oParameters.index > -1) {
            				var aSortItems = this.getModel("{$this->getModelNameForConfig()}").getProperty("/sorters");
            				aSortItems.splice(oParameters.index, 1);
            				oModel.setProperty("/sorters", aSortItems);
            			}
            		}
                }),
JS;
    }
        
    /**
     * 
     * @return string
     */
    protected function buildJsTabColumns() : string
    {
        if ($this->hasTabColumns() === false) {
            return '';
        }
        
        return <<<JS

                new sap.m.P13nColumnsPanel('{$this->getId()}_ColumnsPanel', {
                    title: "{$this->translate('WIDGET.DATATABLE.SETTINGS_DIALOG.COLUMNS')}",
                    visible: true,
                    changeColumnsItems: function(oEvent){
                        var aItems = oEvent.getParameters().items;
                        var oModel = oEvent.getSource().getModel('{$this->getModelNameForConfig()}');
                        var aNewColModel = [];
                        aItems.forEach(oItem => {
                            oModel.getData()['columns'].forEach(oColConf => {
                                if (oColConf.column_id === oItem.columnKey) {
                                    oColConf.visible = oItem.visible;
                                    aNewColModel.push(oColConf);
                                    return;
                                }
                            });
                        });
                        oModel.setProperty('/columns', aNewColModel);
                    },
                    type: "columns",
                    items: {
                        path: '{$this->getModelNameForConfig()}>/columns',
                        template: new sap.m.P13nItem({
                            columnKey: "{{$this->getModelNameForConfig()}>column_id}",
                            text: "{{$this->getModelNameForConfig()}>caption}",
                            visible: "{{$this->getModelNameForConfig()}>visible}"
                        })
                    },
                    beforeNavigationTo: function(oEvent) {
                        {$this->buildJsTabColumnsUpdate('oEvent.getSource()')}                        
                    }
                }),
JS;
    }
    
    protected function buildJsTabColumnsUpdate(string $oPanelJs, bool $resetSelection = false) : string
    {
        /* This script sorts the columns in the panel's list to be sorted exactly the way, they
         * are positioned in the table - regardless of their visibility. By default, unchecked
         * columns are placed at the end of the the list. This forces the user to move them
         * after enabling. This fix makes sure, the position of the column is kept when enabling/disabling
         * and allows table designers to position optional columns meaningfully.
         */
        if ($resetSelection === true) {
            $resetSelection = "oItem.persistentSelected = oItem.persistentSelected = oColConfig.visible = oColConfig.visible; ";
        } else {
            $resetSelection = '';
        }
        return <<<JS
                        try {
                            var oPanel = $oPanelJs;
                            var oTable = oPanel.getAggregation('content')[1].getAggregation('content')[0];
                            var oTableModel = oTable.getModel();
                            var oConfigModel = oPanel.getModel('{$this->getModelNameForConfig()}');
                            if (oTableModel === undefined || oConfigModel === undefined) return;
                            setTimeout(function(){
                                var aColsConfig = oConfigModel.getProperty('/columns');
                                var aItems = oTableModel.getProperty('/items');
                                var aListItems = oTable.getItems();
                                var aItemsNew = [];
                                aColsConfig.forEach(function(oColConfig){
                                    aItems.forEach(function(oItem, iItemIdx){
                                        if (oItem.columnKey === oColConfig.column_id) {
                                            $resetSelection;
                                            if (oColConfig.toggleable === false && aListItems[iItemIdx] !== undefined) {
                                                aListItems[iItemIdx].setVisible(false);
                                            }
                                            aItemsNew.push(oItem);
                                            return;
                                        }
                                    })
                                });
                                
                                oTableModel.setProperty('/items', aItemsNew);
                            });
                        } catch (e) {
                            console.warn('Cannot properly sort columns for personalization - using default sorting: ', e);
                        }
                        
JS;
    }
        
    protected function buildJsTabSearch()
    {
        return <<<JS
                function() {
                    var oPanel = new sap.m.P13nFilterPanel("{$this->getId()}_AdvancedSearchPanel", {
                        title: "{$this->translate('WIDGET.DATATABLE.SETTINGS_DIALOG.ADVANCED_SEARCH')}",
                        visible: true,
                        layoutMode: "Desktop",
                        addFilterItem: function(oEvent){
                            var oParameters = oEvent.getParameters();
                            var oFilterItem = new sap.m.P13nFilterItem(oParameters.filterItemData.mProperties);
                            oEvent.getSource().insertFilterItem(oFilterItem, oParameters.index);
                        },
                        updateFilterItem: function(oEvent){
                            var oParameters = oEvent.getParameters();
                            var oPanel = oEvent.getSource();
                            var idx = oParameters.index;
                            var oFilterItem = new sap.m.P13nFilterItem(oParameters.filterItemData.mProperties);
                            oPanel.removeFilterItem(idx);
                            oPanel.insertFilterItem(oFilterItem, idx);
                        },
                        removeFilterItem: function(oEvent){
                            var oParameters = oEvent.getParameters();
                            oEvent.getSource().removeFilterItem(oParameters.index);
                        },
                        items: {
                            path: '{$this->getModelNameForConfig()}>/columns',
                            template: new sap.m.P13nItem({
                                columnKey: "{{$this->getModelNameForConfig()}>attribute_alias}",
                                text: "{{$this->getModelNameForConfig()}>caption}"
                            })
                        },
                        filterItems: [
    
                        ]
                    });

                    oPanel.setIncludeOperations(["Contains", "EQ", "LT", "LE", "GT", "GE"]);
                    return oPanel;
                }(),
JS;
    }
              
    /**
     * 
     * @return string
     */
    protected function buildJsonColumnData() : string
    {
        $data = [];
        if ($this->hasTabColumns() === true) {
            foreach ($this->getWidget()->getDataWidget()->getColumns() as $col) {
                $data[] = [
                    "attribute_alias" => $col->getAttributeAlias(),
                    "column_id" => $this->getFacade()->getElement($col)->getId(),
                    "column_name" => $col->getDataColumnName(),
                    "caption" => $col->getCaption(),
                    "visible" => $col->isHidden() || $col->getVisibility() === EXF_WIDGET_VISIBILITY_OPTIONAL ? false : true,
                    "toggleable" => $col->isHidden() ? false : true
                ];
            }
        }
        return json_encode($data);
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsonSorterData() : string
    {
        $data = [];
        $sorters = [];
        $table = $this->getWidget()->getDataWidget();
        foreach ($table->getSorters() as $sorter) {
            $sorters[] = $sorter->getProperty('attribute_alias');
            $data[] = [
                "attribute_alias" => $sorter->getProperty('attribute_alias'),
                "caption" => $this->getMetaObject()->getAttribute($sorter->getProperty('attribute_alias'))->getName()
            ];
        }
        foreach ($table->getColumns() as $col) {
            if (! $col->isBoundToAttribute()) {
                continue;
            }
            if (in_array($col->getAttributeAlias(), $sorters)) {
                continue;
            }
            $data[] = [
                "attribute_alias" => $col->getAttributeAlias(),
                "caption" => $col->getCaption()
            ];
        }
        return json_encode($data);
    }
    
    /**
     * Returns an comma separated list of control constructors for filters
     * 
     * @return string
     */
    public function buildJsFilters() : string
    {
        $filters = '';
        $filters_hidden = '';
        foreach ($this->getWidget()->getFilters() as $filter) {
            $filter_element = $this->getFacade()->getElement($filter);
            if (! $filter_element->isVisible()) {
                $filters_hidden .= $this->buildJsFilter($filter_element);
            } else {
                $filters .= $this->buildJsFilter($filter_element);
            }
        }
        return $filters . $filters_hidden;
    }
    
    /**
     * Returns a constructor for the give filter element followed by a comma.
     * 
     * The constructor for a filter element within a data configurator is different from a
     * filter's general constructor!
     * 
     * @param UI5Filter $element
     * @return string
     */
    protected function buildJsFilter(UI5Filter $element) : string
    {
        $element->addPseudoEventHandler('onsapenter', $this->getFacade()->getElement($this->getWidget()->getWidgetConfigured())->buildJsRefresh());
        return <<<JS
        
                        new sap.ui.layout.VerticalLayout({
                            width: "100%",
                            {$element->buildJsPropertyVisibile()}
                            content: [
                        	    {$element->buildJsConstructor()}
                            ]
                        }),
                        
JS;
    }
          
    /**
     * 
     * @return string
     */
    public function getIdOfSortPanel() : string
    {
        return $this->getId() . '_SortPanel';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see JqueryDataConfiguratorTrait::buildJsDataGetter()
     */
    public function buildJsDataGetter(ActionInterface $action = null, bool $unrendered = false)
    {
        if ($unrendered === true) {
            $prefillFiltersJs = '';
            foreach ($this->getWidget()->getFilters() as $filter) {
                $input = $filter->getInputWidget();
                $inputEl = $this->getFacade()->getElement($input);
                if ($inputEl->isValueBoundToModel()) {
                    $prefillFiltersJs .= <<<JS
    
    aPrefillConditions.push({
        expression: "{$filter->getAttributeAlias()}", 
        comparator: "{$filter->getComparator()}", 
        value: {$this->getController()->buildJsControllerGetter($this)}.getView().getModel().getProperty('{$inputEl->buildJsValueBindingPath()}'), 
        object_alias: "{$filter->getMetaObject()->getAliasWithNamespace()}"
    });console.log(aPrefillConditions);

JS;
                }
            }
            return <<<JS

function(){
    var oData = {$this->buildJsDataGetterViaTrait($action, $unrendered)};
    var aPrefillConditions = [];
    $prefillFiltersJs

    if (aPrefillConditions.length > 0) {
        if (oData.filters === undefined) {
            oData.filters = {};
        }
            
        if (oData.filters.nested_groups === undefined) {
            oData.filters.nested_groups = [];
        }

        oData.filters.nested_groups.push({
            operator: "AND", 
            conditions: aPrefillConditions
        })
    }
    
    return oData;
}()


JS;
        }
        
        return <<<JS

function(){
    var oData = {$this->buildJsDataGetterViaTrait($action)};
    var aFilters = sap.ui.getCore().byId('{$this->getId()}_AdvancedSearchPanel').getFilterItems();
    var i = 0;
    if (aFilters.length > 0) {
        var includeGroup = {operator: "AND", conditions: []};
        var excludeGroup = {operator: "NAND", conditions: []};
        var oComponent = {$this->getController()->buildJsComponentGetter()};
        var oFilter, oCondition;
        for (i in aFilters) {
            oFilter = aFilters[i];
            oCondition = {
                expression: oFilter.getColumnKey(), 
                comparator: oComponent.convertConditionOperationToConditionGroupOperator(oFilter.getOperation()), 
                value: oFilter.getValue1(), 
                object_alias: "{$this->getWidget()->getMetaObject()->getAliasWithNamespace()}"
            };
            if (oFilter.getExclude() === false) {
                includeGroup.conditions.push(oCondition);
            } else {
                excludeGroup.conditions.push(oCondition);
            }
        }
        
        if (oData.filters === undefined) {
            oData.filters = {};
        }
        
        if (oData.filters.nested_groups === undefined) {
            oData.filters.nested_groups = [];
        }
        oData.filters.nested_groups.push(includeGroup);
        //oData.filters.nested_groups.push(excludeGroup);
    }
    return oData;
}()

JS;
    }
        
    protected function getModelNameForConfig() : string
    {
        return $this->modelNameForConfig;
    }
    
    public function setModelNameForConfig(string $name) : UI5DataConfigurator
    {
        $this->modelNameForConfig = $name;
        return $this;
    }
    
    public function setIncludeColumnsTab(bool $trueOrFalse) : UI5DataConfigurator
    {
        $this->include_columns_tab = $trueOrFalse;
        return $this;
    }
    
    protected function hasTabColumns() : bool
    {
        return $this->include_columns_tab;
    }
    
    /**
     * 
     * @return UI5AbstractElement
     */
    protected function getDataElement() : UI5AbstractElement
    {
        return $this->getFacade()->getElement($this->getWidget()->getDataWidget());
    }
    
    public function buildJsP13nColumnConfig() : string
    {
        return "sap.ui.getCore().byId('{$this->getId()}').getModel('{$this->getModelNameForConfig()}').getData()['columns']";
    }
    
    /**
     *
     * {@inheritdoc}
     * @see JqueryContainerTrait::buildJsResetter()
     */
    public function buildJsResetter() : string
    {
        return $this->buildJsResetModel() . $this->buildJsResetterViaTrait();
    }
    
    protected function buildJsResetModel() : string
    {
        $initialModelName = $this->getModelNameForConfig() . '_initial';
        
        if ($this->hasTabColumns() === true) {
            $dataElement = $this->getDataElement();
            if ($dataElement instanceof UI5DataTable) {
                $refreshP13n = $dataElement->buildJsRefreshPersonalization();
            }
            
            $resetColumns = <<<JS
// reset columns
                oCurrentModel.setProperty('/columns', oInitModel.getProperty('/columns'));
                {$this->buildJsTabColumnsUpdate("sap.ui.getCore().byId('{$this->getId()}_ColumnsPanel')", true)}
                {$refreshP13n}
JS;
        } else {
            $resetColumns = '';
        }
        
        return <<<JS

            (function(){
                var oDialog = sap.ui.getCore().byId('{$this->getId()}');
                var oInitModel = oDialog.getModel('$initialModelName');
                var oCurrentModel = oDialog.getModel('{$this->getModelNameForConfig()}');
                
                // reset advanced search filters
                sap.ui.getCore().byId('{$this->getId()}_AdvancedSearchPanel').removeAllFilterItems();
                
                // reset sorters
                oCurrentModel.setProperty('/sorters', oInitModel.getProperty('/sorters'));
                
                {$resetColumns}
            }());

JS;
    }
}