<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\UI5Facade\Facades\Elements\Traits\UI5DataElementTrait;
use exface\Core\Widgets\Parts\DataTimeline;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JsValueScaleTrait;
use exface\Core\Widgets\Parts\DataCalendarItem;
use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;
use exface\UI5Facade\Facades\Elements\Traits\UI5ColorClassesTrait;

/**
 * 
 * @method \exface\Core\Widgets\Gantt getWidget()
 * 
 * @author Andrej Kabachnik
 *
 */
class UI5Gantt extends UI5DataTable
{
    use JsValueScaleTrait;
    use UI5ColorClassesTrait;

    const EVENT_NAME_TIMELINE_SHIFT = 'timeline_shift';
    
    const EVENT_NAME_ROW_SELECTION_CHANGE = 'row_selection_change';
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsConstructor()
     */
    public function buildJsConstructorForControl($oControllerJs = 'oController') : string
    {
        $controller = $this->getController();
        $this->initConfiguratorControl($controller);
        $widget = $this->getWidget();
        $calItem = $widget->getTasksConfig();
        
        if ($calItem->hasColorScale()) {
            $this->registerColorClasses($calItem->getColorScale());
        }
        
        $selection_mode = $widget->getMultiSelect() ? 'sap.ui.table.SelectionMode.MultiToggle' : 'sap.ui.table.SelectionMode.Single';
        $selection_behavior = $widget->getMultiSelect() ? 'sap.ui.table.SelectionBehavior.Row' : 'sap.ui.table.SelectionBehavior.RowOnly';
        
        if ($widget->getTreeExpandedLevels() !== null) {
            $numberOfExpandedLevelsJs = "numberOfExpandedLevels: {$widget->getTreeExpandedLevels()},";
        } else {
            $numberOfExpandedLevelsJs = "";
        }
        
        $gantt = <<<JS
        new sap.ui.layout.Splitter({
            contentAreas: [
                new sap.ui.table.TreeTable('{$this->getId()}', {
                    columnHeaderHeight: 52,
                    rows: {
                        path:'/rows', 
                        parameters: {
                            arrayNames: [
                                '_children'
                            ],
                            {$numberOfExpandedLevelsJs}
                        }
                    },
                    selectionMode: {$selection_mode},
    		        selectionBehavior: {$selection_behavior},
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
                                new sap.m.Text("{$this->getId()}_noData", {text: "{$this->getWidget()->getEmptyText()}"})
                            ]
                        })
                    ],
                    toggleOpenState: function(oEvent) {
                        var oTable = oEvent.getSource();
                        setTimeout(function(){
                            {$this->buildJsSyncTreeToGantt('oTable')};
                        },10);
                    },
                    firstVisibleRowChanged: function(oEvent) {
                        var oTable = oEvent.getSource();
                        var domGanttContainer = $('#{$this->getId()}_gantt .gantt-container')[0];
                        var iScrollLeft = domGanttContainer.scrollLeft;
                        setTimeout(function(){
                            {$this->buildJsSyncTreeToGantt('oTable')};
                            domGanttContainer.scrollTo(iScrollLeft, 0);
                        },10);
                    }
                })
                {$this->buildJsClickHandlers('oController')}
                {$this->buildJsPseudoEventHandlers()}
                ,
                new sap.ui.core.HTML("{$this->getId()}_wrapper", {
                    content: "<div id=\"{$this->getId()}_gantt\" class=\"exf-gantt\" style=\"height:100%; min-height: 100px; overflow: hidden;\"></div>",
                    afterRendering: function(oEvent) {
                        setTimeout(function() {
                            var oCtrl = sap.ui.getCore().byId('{$this->getId()}');
                            if (oCtrl.gantt === undefined) {
                                oCtrl.gantt = {$this->buildJsGanttInit()}
                                var oRowsBinding = new sap.ui.model.Binding(sap.ui.getCore().byId('{$this->getId()}').getModel(), '/rows', sap.ui.getCore().byId('{$this->getId()}').getModel().getContext('/rows'));
                                oRowsBinding.attachChange(function(oEvent){
                                    var oBinding = oEvent.getSource();
                                    var oTable = sap.ui.getCore().getElementById('{$this->getId()}');
                                    setTimeout(function(){
                                        {$this->buildJsSyncTreeToGantt('oTable')};
                                    },100);
                                });
                            }
                            
                            setTimeout(function(){
                                // TODO
                            }, 0);
                            sap.ui.core.ResizeHandler.register(sap.ui.getCore().byId('{$this->getId()}').getParent(), function(){
                                // TODO
                            });
                        },0);
                    }
                })
            ]
        })

JS;
        return $this->buildJsPanelWrapper($gantt, $oControllerJs) . ".addStyleClass('sapUiNoContentPadding')";
    }
    
    protected function buildJsGanttGetInstance() : string
    {
        return $this->getController()->buildJsDependentObjectGetter('gantt', $this);
    }
		
    protected function buildJsGanttInit() : string
    {
        $widget = $this->getWidget();
        $dateFormat = $this->escapeString($this->getWorkbench()->getCoreApp()->getTranslator()->translate('LOCALIZATION.DATE.DATE_FORMAT'));
        
        $calItem = $widget->getTasksConfig();
        $startCol = $calItem->getStartTimeColumn();
        $startFormatter = $this->getFacade()->getDataTypeFormatter($startCol->getDataType());
        $endCol = $calItem->getEndTimeColumn();
        $endFormatter = $this->getFacade()->getDataTypeFormatter($endCol->getDataType());
        
        switch ($widget->getTimelineConfig()->getGranularity(DataTimeline::GRANULARITY_HOURS)) {
            case DataTimeline::GRANULARITY_HOURS: $viewMode = 'Quater Day'; break;
            case DataTimeline::GRANULARITY_DAYS: $viewMode = 'Day'; break;
            case DataTimeline::GRANULARITY_DAYS_PER_WEEK: $viewMode = 'Day'; break;
            case DataTimeline::GRANULARITY_DAYS_PER_MONTH: $viewMode = 'Day'; break;
            case DataTimeline::GRANULARITY_MONTHS: $viewMode = 'Month'; break;
            case DataTimeline::GRANULARITY_WEEKS: $viewMode = 'Week'; break;
            case DataTimeline::GRANULARITY_YEARS: $viewMode = 'Year'; break;
            default: $viewMode = 'sap.ui.unified.CalendarIntervalType.Hour'; break;
        }
        
        return <<<JS
(function() {   
    return new Gantt("#{$this->getId()}_gantt", [
      {
        id: 1,
        name: 'Loading...',
        start: null,
        end: null
      }
    ], {
        header_height: 46,
        column_width: 30,
        step: 24,
        view_modes: ['Quarter Day', 'Half Day', 'Day', 'Week', 'Month'],
        bar_height: 19,
        bar_corner_radius: 3,
        arrow_curve: 5,
        padding: 14,
        view_mode: '$viewMode',
        date_format: $dateFormat,
        language: 'en', // or 'es', 'it', 'ru', 'ptBr', 'fr', 'tr', 'zh', 'de', 'hu'
        custom_popup_html: null,
    	on_date_change: function(oTask, dStart, dEnd) {
    		var oTable = sap.ui.getCore().byId('{$this->getId()}');
            var oModel = oTable.getModel();
            var oGantt = sap.ui.getCore().byId('{$this->getId()}').gantt;
            var iRow = oGantt.tasks.indexOf(oTask);
            var oCtxt = oTable.getRows()[iRow].getBindingContext();
            var oRow = oTable.getModel().getProperty(oCtxt.sPath);
            var sColNameStart = '{$startCol->getDataColumnName()}';
            var sColNameEnd = '{$endCol->getDataColumnName()}';

            // move children with parent when parent is dragged along the timeline
            var oldStart = new Date(oRow[sColNameStart]);
            var oldEnd = new Date(oRow[sColNameEnd]);
            var newStart = new Date({$startFormatter->buildJsFormatDateObjectToInternal('dStart')});
            var newEnd = new Date({$startFormatter->buildJsFormatDateObjectToInternal('dEnd')});

            // Check if the parent has been moved without the duration changing
            var iDurationOld = oldEnd - oldStart;
            var iDurationNew = newEnd - newStart;
            
            if (iDurationOld ===  iDurationNew) {
                var moveDiffInDays = (newStart - oldStart) / 1000 / 60 / 60 / 24;
                
                function processChildrenRecursively(oRow, moveDiffInDays, sColNameStart, sColNameEnd) {
                    oRow._children.forEach(function(oChildRow, iIdx) {
                        // move dates of oChildRow as far as the parent row was moved
                        var startDateChild = new Date(oChildRow['date_start_plan']);
                        var endDateChild = new Date(oChildRow['date_end_plan']);
                        startDateChild.setDate(startDateChild.getDate() + moveDiffInDays);
                        endDateChild.setDate(endDateChild.getDate() + moveDiffInDays);
                        oRow._children[iIdx][sColNameStart] = {$startFormatter->buildJsFormatDateObjectToInternal('startDateChild')};
                        oRow._children[iIdx][sColNameEnd] = {$startFormatter->buildJsFormatDateObjectToInternal('endDateChild')};

                        // if the child row has children too, call the function recursively
                        if (oChildRow._children && oChildRow._children.length > 0) {
                            processChildrenRecursively(oChildRow, moveDiffInDays, sColNameStart, sColNameEnd);
                        }
                    });
                }
                processChildrenRecursively(oRow, moveDiffInDays, sColNameStart, sColNameEnd);
            }
            oModel.setProperty(oCtxt.sPath + '/' + sColNameStart, {$startFormatter->buildJsFormatDateObjectToInternal('dStart')});
            oModel.setProperty(oCtxt.sPath + '/' + sColNameEnd, {$endFormatter->buildJsFormatDateObjectToInternal('dEnd')});
    	}
    });
})();

JS;
    }
    
    /**
     * 
     * @param string $oModelJs
     * @return string
     */
    protected function buildJsDataLoaderOnLoaded(string $oModelJs = 'oModel') : string
    {    
        if (! $this->getWidget()->getTreeParentRelationAlias()) {
            $treeModeJs = "sap.ui.getCore().byId('{$this->getId()}').setUseFlatMode(true);";
        } else {
            $treeModeJs = '';
        }
        return parent::buildJsDataLoaderOnLoaded($oModelJs) . <<<JS

                var oDataTree = {$this->buildJsTransformToTree($oModelJs . '.getData()')};
                {$oModelJs}.setData(oDataTree);
                {$treeModeJs}

JS;
    }
    
    /**
     * 
     * @param string $oControlJs
     * @return string
     */
    protected function buildJsGetRowsAll(string $oControlJs) : string
    {
        // NOTE: oTable.getModel().getData() returns only the top level rows, but .getJSON() yields
        // all. This is why the JSON parsing became neccessary
        return "({$this->buildJsTransformFromTree("JSON.parse({$oControlJs}.getModel().getJSON()")}).rows || [])";
    }
    
    /**
     * 
     * @param string $oDataJs
     * @return string
     */
    protected function buildJsTransformFromTree(string $oDataJs) : string
    {
        return <<<JS
        
                (function(oDataTree) {
                    var oDataFlat = $.extend({}, oDataTree);
                    var fnFlatten = function(aRows) {
                        var aFlat = [];
                        aRows.forEach(function(oRow) {
                            aFlat.push(oRow);
                            if (Array.isArray(oRow._children) && oRow._children.length > 0) {
                                aFlat.push(...fnFlatten(oRow._children));
                            }
                            delete oRow._children;
                        });
                        return aFlat;
                    };
                    oDataFlat.rows = fnFlatten(oDataTree.rows || []);                  
                    return oDataFlat;
                })($oDataJs)
                
JS;
    }
    
    /**
     * 
     * @param string $oDataJs
     * @return string
     */
    protected function buildJsTransformToTree(string $oDataJs) : string
    {
        $cleanupJs = '';
        $widget = $this->getWidget();
        
        // remove rows without children in oDataTree.rows if $folderFlagAlias is set to 1
        if (null !== $folderFlagAlias = $widget->getTreeFolderFlagAttributeAlias()) {
            $cleanupJs = <<<JS

                    for (let i = oDataTree.rows.length - 1; i >= 0; i--) {
                        (function(oItem, iIndex, aArr) {
                            if (oItem['{$folderFlagAlias}'] === 1 && oItem['_children'].length === 0) {
                                aArr.splice(iIndex, 1);
                            }
                         })(oDataTree.rows[i], i, oDataTree.rows);
                    }  
JS;
        }
        
        return <<<JS

                (function(oDataFlat) {
                    var oDataTree = $.extend({}, oDataFlat);
                    var sParentCol = '{$widget->getTreeParentRelationAlias()}';

                    function list_to_tree(list) {
                      var map = {}, node, roots = [], i;
                      
                      for (i = 0; i < list.length; i += 1) {
                        map[list[i].id] = i; // initialize the map
                        list[i]._children = []; // initialize the children
                      }
                      
                      for (i = 0; i < list.length; i += 1) {
                        node = list[i];
                        // Check, if parent node exists and place the node in its _children array
                        // Nodes with non-existent parents will be the roots
                        if (node[sParentCol] !== '' && node[sParentCol] !== null && map[node[sParentCol]] !== undefined) {
                            list[map[node[sParentCol]]]._children.push(node);
                        } else {
                            roots.push(node);
                        }
                      }
                      return roots;
                    }
                    if (sParentCol !== '') {
                        oDataTree.rows = list_to_tree(oDataFlat.rows);
                    } else {
                        for (var i = 0; i < oDataTree.rows.length; i++) {
                            oDataTree.rows[i]._children = [];
                        }
                    }

                    $cleanupJs
                    
                    return oDataTree;
                })($oDataJs)

JS;
    }
    
    protected function buildJsSyncTreeToGantt(string $oTableJs) : string
    {
        $widget = $this->getWidget();
        $calItem = $widget->getTasksConfig();
        $draggableJs = ($calItem->getStartTimeColumn()->isEditable() && $calItem->getEndTimeColumn()->isEditable()) ? 'true' : 'false';
        if ($calItem->hasColorScale()) {
            $colorResolversJs = $this->buildJsColorResolver($calItem, 'oRow');
        } else {
            $colorResolversJs = 'null';
        }
        return <<<JS
            (function(oTable) {
                var oGantt = sap.ui.getCore().byId('{$this->getId()}').gantt;
                var aTasks = [];
                oTable.getRows().forEach(function(oTreeRow) {
                    var oCtxt = oTreeRow.getBindingContext();
                    var oRow, sColor;
                    if (! oCtxt) return;
                    oRow = oTable.getModel().getProperty(oCtxt.sPath);
                    sColor = {$colorResolversJs};
                    var oTask = {
                        id: oRow['{$widget->getUidColumn()->getDataColumnName()}'],
                        name: oRow['{$calItem->getTitleColumn()->getDataColumnName()}'],
                        start: oRow["{$calItem->getStartTimeColumn()->getDataColumnName()}"],
                        end: oRow["{$calItem->getEndTimeColumn()->getDataColumnName()}"],
                        progress: 0,
                        dependencies: '',
                        draggable: $draggableJs
                    };

                    if(sColor !== null) {
                        oTask.custom_class += 'exf-custom-color exf-color-' + sColor.replace("#", "");
                    }
    
                    if(oRow._children.length > 0 && oTask.start && oTask.end) {
                        oTask.custom_class += ' bar-folder';
                    }
    
                    aTasks.push(oTask);
                });

                oGantt.tasks = aTasks;
                if (aTasks.length > 0) {
                    oGantt.refresh(aTasks);
                } else  {
                    oGantt.clear();
                }
            })($oTableJs)
            
JS;
    }
    
    /**
     * {@inheritdoc}
     * @see UI5DataElementTrait::isEditable()
     */
    public function isEditable()
    {
        return $this->getWidget()->isEditable();
    }
    
    /**
     * {@inheritdoc}
     * @see UI5DataElementTrait::hasPaginator()
     */
    protected function hasPaginator() : bool
    {
        return false;
    }
    
    public function registerExternalModules(UI5ControllerInterface $controller) : UI5AbstractElement
    {
        // $f = $this->getFacade();
        $controller->addExternalModule('libs.exface.gantt.Gantt', 'vendor/exface/UI5Facade/Facades/js/frappe-gantt/dist/frappe-gantt.js', null, 'Gantt');
        $controller->addExternalCss('vendor/exface/UI5Facade/Facades/js/frappe-gantt/dist/frappe-gantt.min.css');
        return $this;
    }
    
    /**
     * 
     * @param DataCalendarItem $calItem
     * @param string $oRowJs
     * @return string
     */
    protected function buildJsColorResolver(DataCalendarItem $calItem, string $oRowJs) : string
    {
        switch (true) {
            case $colorCol = $calItem->getColorColumn();
                $semanticColors = $this->getFacade()->getSemanticColors();
                $semanticColorsJs = json_encode(empty($semanticColors) ? new \stdClass() : $semanticColors);
                if ($calItem->hasColorScale()) {
                    return <<<JS
                        (function(oRow){
                            var value = oRowJs['{$colorCol->getDataColumnName()}']
                            var sColor = {$this->buildJsScaleResolver('value', $calItem->getColorScale(), $calItem->isColorScaleRangeBased())};
                            var sCssColor = '';
                            var oSemanticColors = $semanticColorsJs;
                            if (sColor.startsWith('~')) {
                                sCssColor = oSemanticColors[sColor] || '';
                            } else if (sColor) {
                                sCssColor = sColor;
                            }
                            return sCssColor;
                        })(oRow)
JS;
                } else {
                    return "oRow['{$colorCol->getDataColumnName()}']";
                }
            case null !== $colorVal = $calItem->getColor():
                return $this->escapeString($colorVal);
        }
        return '';
    }
    
    /**
     *
     * @return string
     */
    protected function buildJsDataResetter() : string
    {
        return parent::buildJsDataResetter() . "; sap.ui.getCore().byId('{$this->getId()}').data('_exfStartDate', {$this->escapeString($this->getWidget()->getStartDate())});";
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5DataTable::isUiTable()
     */
    protected function isUiTable() : bool
    {
        return true;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5DataTable::isMTable()
     */
    protected function isMTable() : bool
    {
        return false;
    }
    
    /**
     * TODO For some reason, transforming model data to tree did not work when there was a dirty-column
     * 
     * @return bool
     */
    protected function hasDirtyColumn() : bool
    {
        return false;
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsFullscreenContainerGetter() : string
    {
        return $this->isWrappedInDynamicPage() ? "$('#{$this->getId()}').parents('.sapMPanel').first().parent()" : "$('#{$this->getId()}').parents('.sapMPanel').first()";
    }
}