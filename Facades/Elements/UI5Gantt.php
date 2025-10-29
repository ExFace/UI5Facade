<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\ButtonGroup;
use exface\UI5Facade\Facades\Elements\Traits\UI5DataElementTrait;
use exface\Core\Widgets\Parts\DataTimeline;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JsValueScaleTrait;
use exface\Core\Widgets\Parts\DataCalendarItem;
use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;
use exface\UI5Facade\Facades\Elements\Traits\UI5ColorClassesTrait;
use exface\Core\DataTypes\DateDataType;
use exface\Core\Widgets\Parts\ConditionalPropertyConditionGroup;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Interfaces\Widgets\iShowSingleAttribute;
use exface\Core\Interfaces\Widgets\iHaveColumns;
use exface\Core\Exceptions\Facades\FacadeRuntimeError;

/**
 * 
 * @method \exface\Core\Widgets\Gantt getWidget()
 * 
 * @author Andrej Kabachnik
 *
 */
class UI5Gantt extends UI5DataTree
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
        $widget = $this->getWidget();
        $calItem = $widget->getTasksConfig();
        $controller = $this->getController();
        
        if ($calItem->hasColorScale()) {
            $this->registerColorClasses($calItem->getColorScale());
        }
        
        // adds the view mode buttons to the toolbar
        $aSelectedViewModes = array_map([$this, 'convertDataTimelineGranularityToGanttViewMode'], $widget->getTimelineConfig()->getGranularitySelectable());
        $this->addGanttViewModeButtons($this->getWidget()->getToolbarMain()->getButtonGroup(0),2, $aSelectedViewModes);
        
        // reloads the gantt task data at navigation return
        $controller->addOnShowViewScript(
            <<<JS
               setTimeout(function(){
                 const oTableReload = sap.ui.getCore().getElementById('{$this->getId()}');
                 
                 {$this->buildJsSyncTreeToGantt('oTableReload')};
                 
                 let toolbarOffsetHeight = sap.ui.getCore().byId('{$this->getId()}').$().parents('.sapMPanel').children('.exf-datatoolbar')[0]?.offsetHeight
                 if (toolbarOffsetHeight !== undefined) {
                   sap.ui.getCore().byId('{$this->getId()}').$().parents('.sapMPanelContent').css("height", "calc(100% - " + toolbarOffsetHeight + "px)");
                 }
               },0);
JS
            ,false);
        
        $gantt = <<<JS
        new sap.ui.layout.Splitter({
            {$this->buildJsProperties()}
            contentAreas: [
                {$this->buildJsConstructorForTreeTable($oControllerJs)}
                ,
                new sap.ui.core.HTML("{$this->getId()}_wrapper", {
                    content: "<div id=\"{$this->getId()}_gantt\" class=\"exf-gantt\" style=\"height:100%; min-height: 100px; overflow: hidden;\"></div>",
                    afterRendering: function(oEvent) {
                        setTimeout(function() {
                            var oCtrl = sap.ui.getCore().byId('{$this->getId()}');
                            if (oCtrl.gantt === undefined) {
                                oCtrl.gantt = {$this->buildJsGanttInit()}
                               
                                // It renders the unrendered task bars on separate tabs
                                var oSwitchTabTable = sap.ui.getCore().getElementById('{$this->getId()}');
                                setTimeout(function(){
                                    {$this->buildJsSyncTreeToGantt('oSwitchTabTable')}
                                ;},0);
                                
                                var oRowsBinding = new sap.ui.model.Binding(sap.ui.getCore().byId('{$this->getId()}').getModel(), '/rows', sap.ui.getCore().byId('{$this->getId()}').getModel().getContext('/rows'));
                                oRowsBinding.attachChange(function(oEvent){
                                    var oBinding = oEvent.getSource();
                                    var oTable = sap.ui.getCore().getElementById('{$this->getId()}');
                                    setTimeout(function(){
                                        {$this->buildJsSyncTreeToGantt('oTable')};
                                    },100);
                                });
                            } else {
                              // This refreshes the gantt chart, centring it on the first task bar again.
                              const oGanttRefresh = sap.ui.getCore().byId('{$this->getId()}').gantt;
                              if (oGanttRefresh.tasks.length > 0) {
                                oGanttRefresh.refresh(oGanttRefresh.tasks);
                              }
                            }
                        },0);
                    }
                })
            ]
        })

JS;
        return $this->buildJsPanelWrapper($gantt, $oControllerJs) . ".addStyleClass('sapUiNoContentPadding')";
    }
    
    /**
     *
     * @see UI5DataElementTrait::isWrappedInPanel()
     */
    protected function isWrappedInPanel() : bool
    {
        return true;
    }
    
    /**
     *
     * @return string
     */
    protected function buildJsPropertyColumnHeaderHeight() : string
    {
        return 'columnHeaderHeight: 52,';
    }
    
    /**
     *
     * @param string $oEventJs
     * @return string
     */
    protected function buildJsOnOpenScript(string $oEventJs) : string
    {
        return <<<JS

                var oTable = oEvent.getSource();
                setTimeout(function(){
                    {$this->buildJsSyncTreeToGantt('oTable')};
                },10);
JS;
    }
    
    /**
     *
     * @param string $oEventJs
     * @return string
     */
    protected function buildJsOnCloseScript(string $oEventJs) : string
    {
        return <<<JS
        
                var oTable = oEvent.getSource();
                var domGanttContainer = $('#{$this->getId()}_gantt .gantt-container')[0];
                var iScrollLeft = domGanttContainer.scrollLeft;
                setTimeout(function(){
                    {$this->buildJsSyncTreeToGantt('oTable')};
                    domGanttContainer.scrollTo(iScrollLeft, 0);
                },10);
JS;
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsGanttGetInstance() : string
    {
        return $this->getController()->buildJsDependentObjectGetter('gantt', $this);
    }
		
    /**
     * 
     * @return string
     */
    protected function buildJsGanttInit() : string
    {
        $widget = $this->getWidget();
        
        $calItem = $widget->getTasksConfig();
        $startCol = $calItem->getStartTimeColumn();
        $startFormatter = $this->getFacade()->getDataTypeFormatter($startCol->getDataType());
        $endCol = $calItem->getEndTimeColumn();
        $endFormatter = $this->getFacade()->getDataTypeFormatter($endCol->getDataType());
        $titleOverflow = $calItem->getTitleOverflow() ?? 'outside';
        $keepScrollPosition = $widget->getKeepScrollPosition();
        $autoRelayoutOnChange = $widget->getAutoRelayoutOnChange();
                
        if ($startCol->getDataType() instanceof DateDataType) {
            $dateFormat = $startFormatter->getFormat();
        } else {
            $dateFormat = $this->getWorkbench()->getCoreApp()->getTranslator()->translate('LOCALIZATION.DATE.DATE_FORMAT');
        }
        
/*        switch ($widget->getTimelineConfig()->getGranularity(DataTimeline::GRANULARITY_HOURS)) {
            case DataTimeline::GRANULARITY_HOURS: $viewMode = 'Quater Day'; break;
            case DataTimeline::GRANULARITY_DAYS: $viewMode = 'Day'; break;
            case DataTimeline::GRANULARITY_DAYS_PER_WEEK: $viewMode = 'Day'; break;
            case DataTimeline::GRANULARITY_DAYS_PER_MONTH: $viewMode = 'Day'; break;
            case DataTimeline::GRANULARITY_MONTHS: $viewMode = 'Month'; break;
            case DataTimeline::GRANULARITY_WEEKS: $viewMode = 'Week'; break;
            case DataTimeline::GRANULARITY_YEARS: $viewMode = 'Year'; break;
            default: $viewMode = 'sap.ui.unified.CalendarIntervalType.Hour'; break;
        }*/
        
        $viewMode = $this->convertDataTimelineGranularityToGanttViewMode(
            $widget->getTimelineConfig()->getGranularity(DataTimeline::GRANULARITY_HOURS)
        );
        
        // see if this particular child(oChildRow)is to be moved along with its parent if the parent is moved
        // check if there is a condition set to adjust which children are to be moved along with its parent
        /* @var $condProp \exface\Core\Widgets\Parts\ConditionalProperty */
        if (null !== $condProp = $widget->getChildrenMoveWithParentIf()) {
            $rowMoveFilterJs = <<<JS
                        // check if the set condition is matching the property of the child. If not, continue with the next item
                        if (false === Boolean({$this->buildJsConditionalPropertyRowCheck($condProp->getConditionGroup(), 'oChildRow')})) {
                            return;
                        }
JS;
            // if the set condition is matching the property of the child, continue with moving the child
        } else {
            $rowMoveFilterJs = '';
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
        header_height: 39, //TODO SR: Fix Header lower padding and the number back to 46 here.
        column_width: 30,
        step: 24,
        view_modes: ['Quarter Day', 'Half Day', 'Day', 'Week', 'Month'],
        bar_height: 19,
        bar_corner_radius: 3,
        arrow_curve: 5,
        padding: 14,
        view_mode: '$viewMode',
        date_format: {$this->escapeString($dateFormat)},
        label_overflow: '$titleOverflow',
        keep_scroll_position: '$keepScrollPosition',
        auto_relayout_on_change: '$autoRelayoutOnChange',
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
            var bMoveChildrenWithParent = Boolean({$this->getWidget()->getChildrenMoveWithParent()});

            var oldStart = moment(oGantt.dateUtils.parse(oRow[sColNameStart])); // string '10.04.2024' -> date object 10.04.2024 02:00:00 GMT+2 
            var oldEnd = moment(oGantt.dateUtils.parse(oRow[sColNameEnd])); // string '11.04.2024' -> date object 11.04.2024 02:00:00 GMT+2 
            var newStart = moment(dStart);
            var newEnd = moment(dEnd);
            
            // Update the table in any case
            oModel.setProperty(oCtxt.sPath + '/' + sColNameStart, {$startFormatter->buildJsFormatDateObjectToInternal('newStart.toDate()')});
            oModel.setProperty(oCtxt.sPath + '/' + sColNameEnd, {$endFormatter->buildJsFormatDateObjectToInternal('newEnd.toDate()')});

            // Move children with parent when parent is dragged along the timeline
            // Only move children if UXON children_move_with_parent is set to true. True is the default value.
            if (bMoveChildrenWithParent === true) {
            
                // Check if the parent has been moved without the duration changing
                var iDurationNewMoment = newEnd.diff(newStart, 'hours');
                var iDurationOldMoment = oldEnd.diff(oldStart, 'hours');

                // Compare hour difference of old & new task dates, if they are same the children tasks will also be moved
                if (iDurationNewMoment === iDurationOldMoment) {
                    var moveDiffInHours = newStart.diff(oldStart, 'hours')
                    function processChildrenRecursively(oRow, moveDiffInHours, sColNameStart, sColNameEnd) {
                        oRow._children.forEach(function(oChildRow, iIdx) {
                            // check if there is a condition that enables/disables the moving of a child along with its parent
                            {$rowMoveFilterJs}
                            // move dates of oChildRow as far as the parent row was moved
                            var startDateChild = moment(new Date(oChildRow['date_start_plan'])).add(moveDiffInHours, 'hours');
                            var endDateChild = moment(new Date(oChildRow['date_end_plan'])).add(moveDiffInHours, 'hours');
                            oRow._children[iIdx][sColNameStart] = {$startFormatter->buildJsFormatDateObjectToInternal('startDateChild')};
                            oRow._children[iIdx][sColNameEnd] = {$endFormatter->buildJsFormatDateObjectToInternal('endDateChild')};

                            // if the child row has children too, call the function recursively
                            if (oChildRow._children && oChildRow._children.length > 0) {
                                processChildrenRecursively(oChildRow, moveDiffInHours, sColNameStart, sColNameEnd);
                            }
                        });
                    }
                    processChildrenRecursively(oRow, moveDiffInHours, sColNameStart, sColNameEnd);
                }
            }
            if (oGantt.options.auto_relayout_on_change) {
                oGantt.refresh(oGantt.tasks); // calls compute_rows_and_lanes() again.
            }
    	}
    });
})();

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
        
        if ($calItem->getNestedDataColumn()) {
            $nestedDataColName = $this->escapeString($calItem->getNestedDataColumn()->getDataColumnName());
        } else {
            $nestedDataColName = 'null';
        }
        return <<<JS
            (function(oTable) {
                var oGantt = sap.ui.getCore().byId('{$this->getId()}').gantt;
                if (oGantt === undefined) return;
                
                let colorUtils = new ColorUtils();
                var aTasks = [];
                var sNestedColName = {$nestedDataColName}
                let lineIndex = 0;
                
                oTable.getRows().forEach(function(oTreeRow) {
                    var oCtxt = oTreeRow.getBindingContext();
                    var oRow, sColor;
                    
                    function fnRowToTask(oRow) {
                        sColor = {$colorResolversJs};
                        var oTask = {
                            id: oRow['{$widget->getUidColumn()->getDataColumnName()}'],
                            name: oRow['{$calItem->getTitleColumn()->getDataColumnName()}'],
                            start: oRow["{$calItem->getStartTimeColumn()->getDataColumnName()}"],
                            end: oRow["{$calItem->getEndTimeColumn()->getDataColumnName()}"],
                            progress: 0,
                            dependencies: '',
                            lineIndex: lineIndex,
                            draggable: $draggableJs,
                            //...colorUtils.deriveColors(sColor) //TODO SR: put the right color here.
                        };
    
                        if(sColor !== null) { //TODO SR: Delete this and use the "...colorUtils.deriveColors(sColor)" instead.
                            oTask.custom_class += 'exf-custom-color exf-color-' + sColor.replace("#", "");
                        }
        
                        if(oRow?._children?.length > 0 && oTask.start && oTask.end) {
                            oTask.custom_class += ' bar-folder';
                        }
                        
                        aTasks.push(oTask);
                    }
                    
                    if (!oCtxt) return;
                    
                    oRow = oTable.getModel().getProperty(oCtxt.sPath);
                    if (sNestedColName !== null) {
                        var oNestedData = oRow[sNestedColName];
                        oNestedData.rows.forEach(function(oNestedRow) {
                            fnRowToTask(oNestedRow)
                        })
                    } else {
                        fnRowToTask(oRow);
                    }
                    
                    lineIndex++
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
        $f = $this->getFacade();
        $controller->addExternalModule('libs.moment.moment', $f->buildUrlToSource("LIBS.MOMENT.JS"), null, 'moment');
        $controller->addExternalModule('libs.exface.gantt.Gantt', 'vendor/exface/UI5Facade/Facades/js/frappe-gantt/dist/frappe-gantt.js', null, 'Gantt');
        $controller->addExternalModule('libs.exface.colorUtils.ColorUtils', 'vendor/exface/UI5Facade/Facades/js/frappe-gantt/tools/color-utils.js', null, 'ColorUtils');
        
        $controller->addExternalCss('vendor/exface/UI5Facade/Facades/js/frappe-gantt/dist/frappe-gantt.min.css');
        //$controller->addExternalCss('vendor/exface/UI5Facade/Facades/js/frappe-gantt/dist/frappe-gantt.css');
        // task overlapping feature css:
        $controller->addExternalCss('vendor/exface/UI5Facade/Facades/js/frappe-gantt/dist/exf-frappe-gantt.css');
        
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
     * @return string
     */
    protected function buildJsFullscreenContainerGetter() : string
    {
        return $this->isWrappedInDynamicPage() ? "$('#{$this->getId()}').parents('.sapMPanel').first().parent()" : "$('#{$this->getId()}').parents('.sapMPanel').first()";
    }
    
    /**
     * Returns a JS snippet, that yields TRUE if the provided JS data row matches the condition group and FALSE otherwise
     * 
     * This is a modified version of `JsConditionalPropertyTrait::buildJsConditionalPropertyIf()`
     * for checking the conditions for each row of an object instead of just the conditions for the object itself.
     * 
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\JsConditionalPropertyTrait::buildJsConditionalPropertyIf()
     * 
     * @param ConditionalPropertyConditionGroup $conditionGroup
     * @param string $oRowJs
     * @throws FacadeRuntimeError
     * @return string
     */
    protected function buildJsConditionalPropertyRowCheck(ConditionalPropertyConditionGroup $conditionGroup, string $oRowJs) : string
    {
        $widget = $this->getWidget();
        $jsConditions = [];
        
        // First evaluate the conditions
        // Make sure that the widget link can be entered in the right or in the left expression
        foreach ($conditionGroup->getConditions() as $condition) {
            $leftJs = null;
            $leftExpr = $condition->getValueLeftExpression();
            if ($leftExpr->isReference() === true) {
                $leftLink = $leftExpr->getWidgetLink($widget);
                if ($leftLink->getTargetWidget() === $widget) {
                    $leftJs = "{$oRowJs}['{$leftLink->getTargetColumnId()}']";
                }
            }
            if ($leftJs === null) {
                $leftJs = $this->buildJsConditionalPropertyValue($condition->getValueLeftExpression(), $conditionGroup->getConditionalProperty());
            }
            
            $rightJs = null;
            $rightExpr = $condition->getValueRightExpression();
            if ($rightExpr->isReference() === true) {
                $rightLink = $rightExpr->getWidgetLink($widget);
                if ($rightLink->getTargetWidget() === $widget) {
                    $rightJs = "{$oRowJs}['{$rightLink->getTargetColumnId()}']";
                }
            }
            if ($rightJs === null) {
                $rightJs = $this->buildJsConditionalPropertyValue($condition->getValueRightExpression(), $conditionGroup->getConditionalProperty());
            }
            
            $delim = EXF_LIST_SEPARATOR;
            // Try to get the possibly customized delimiter from the right side of the
            // condition if it is an IN-condition
            if ($condition->getComparator() === ComparatorDataType::IN || $condition->getComparator() === ComparatorDataType::NOT_IN) {
                $rightExpr = $condition->getValueRightExpression();
                if ($rightExpr->isReference() === true) {
                    $targetWidget = $rightExpr->getWidgetLink()->getTargetWidget();
                    if (($targetWidget instanceof iShowSingleAttribute) && $targetWidget->isBoundToAttribute()) {
                        $delim = $targetWidget->getAttribute()->getValueListDelimiter();
                    } elseif ($targetWidget instanceof iHaveColumns && $colName = $rightExpr->getWidgetLink()->getTargetColumnId()) {
                        $targetCol = $targetWidget->getColumnByDataColumnName($colName);
                        if ($targetCol->isBoundToAttribute() === true) {
                            $delim = $targetCol->getAttribute()->getValueListDelimiter();
                        }
                    }
                }
            }
            $jsConditions[] = "exfTools.data.compareValues($leftJs, $rightJs, '{$condition->getComparator()}', '$delim')";
        }
        
        // Then just append condition groups evaluated by a recursive call to this method
        foreach ($conditionGroup->getConditionGroups() as $nestedGrp) {
            $jsConditions[] = '(' . $this->buildJsConditionalPropertyRowCheck($nestedGrp, $oRowJs) . ')';
        }
        
        // Now glue everything together using the logical operator
        switch ($conditionGroup->getOperator()) {
            case EXF_LOGICAL_AND: $op = ' && '; break;
            case EXF_LOGICAL_OR: $op = ' || '; break;
            default:
                throw new FacadeRuntimeError('Unsupported logical operator for conditional property "' . $conditionGroup->getPropertyName() . '" in widget "' . $this->getWidget()->getWidgetType() . ' with id "' . $this->getWidget()->getId() . '"');
        }
        
        // cond1 && cond2 && (grp1cond1 || grp1cond2) && ...
        return implode($op, $jsConditions);
    }

    /**
     * Adds gantt view mode selection buttons to the toolbar
     *
     * @param ButtonGroup $btnGrp
     * @param int $index
     * @param array $viewModes
     * @return void
     */
    public function addGanttViewModeButtons(ButtonGroup $btnGrp, int $index = 0, array $viewModes = []) : void
    {
        if (empty($viewModes)) {
            $viewModes = ['Day', 'Week', 'Month'];
        }

        $buttons = [];

        foreach ($viewModes as $viewMode) {
            $buttons[] = [
                'caption' => $viewMode,
                'action'  => [
                    'alias'  => 'exface.Core.CustomFacadeScript',
                    'hide_icon' => true,
                    'script' => <<<JS
                        sap.ui.getCore().byId('[#element_id:~input#]').gantt.change_view_mode('$viewMode');
JS
                ],
            ];
        }

        $btnGrp->addButton($btnGrp->createButton(new UxonObject([
            'widget_type' => 'MenuButton',
            'icon' => 'calendar',
            'hide_caption' => true,
            'buttons' => $buttons
        ])), $index);
    }
    
    protected function convertDataTimelineGranularityToGanttViewMode($granularity) : string 
    {
        switch ($granularity) {
            case DataTimeline::GRANULARITY_HOURS: $viewMode = 'Quater Day'; break;
            case DataTimeline::GRANULARITY_DAYS: $viewMode = 'Day'; break;
            case DataTimeline::GRANULARITY_DAYS_PER_WEEK: $viewMode = 'Day'; break;
            case DataTimeline::GRANULARITY_DAYS_PER_MONTH: $viewMode = 'Day'; break;
            case DataTimeline::GRANULARITY_MONTHS: $viewMode = 'Month'; break;
            case DataTimeline::GRANULARITY_WEEKS: $viewMode = 'Week'; break;
            case DataTimeline::GRANULARITY_YEARS: $viewMode = 'Year'; break;
            default: $viewMode = 'sap.ui.unified.CalendarIntervalType.Hour'; break;
        }
        
        return $viewMode;
    }
}