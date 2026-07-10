<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Widgets\ButtonGroup;
use exface\Core\Widgets\Parts\DataTimelineThicklines;
use exface\Core\Widgets\Parts\DataTimelineView;
use exface\Core\Widgets\Parts\DataTimelineHeader;
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
    
    const CONTROLLER_METHOD_SYNC_TO_GANTT = 'syncTreeToGantt';
    
    const CONTROLLER_METHOD_CHECK_TABLE_IS_READY = 'checkTableIsReady';
    
    // Default Gantt ViewModes: hours, days, weeks, months, years
    // The defaults are written in simplified array structure that is used by the "view-mode-builder.js"
    // that translates it to the FrappeGantt specific structure.
    // Keep in mind to add "TRANSLATE:" prefix to the name of the view
    // and make sure it gets translated bevor usage.
    private array $viewModeDefaults = [
        DataTimeline::GRANULARITY_HOURS => [
            'name' => 'TRANSLATE:WIDGET.GANTT_CHARD.VIEW_MODE_HOUR',
            'step' => '1h',
            'date_format' => 'YYYY-MM-dd HH:',
            'column_width' => null,
            'padding' => '7d',
            'snap_at' => null,
            'upper_text_frequency' => 24,
            'header' => [
                'upper' => [
                    'interval' => 'Date',
                    'date_format' => 'dd',
                    'date_format_at_border' => null,
                ],
                'lower' => [
                    'interval' => 'Date',
                    'date_format' => 'HH',
                    'date_format_at_border' => null,
                ]
            ]
        ],
        DataTimeline::GRANULARITY_DAYS => [
            'name' => 'TRANSLATE:WIDGET.GANTT_CHARD.VIEW_MODE_DAY', 
            'step' => '1d',
            'date_format' => 'yyyy-MM-dd',
            'column_width' => null,
            'padding' => '7d',
            'snap_at' => null,
            'upper_text_frequency' => null,
            'header' => [
                'upper' => [
                    'interval' => 'Month',
                    'date_format' => '',
                    'date_format_at_border' => 'MMMM'
                ], 
                'lower' => [
                    'interval' => 'Date',
                    'date_format' => 'dd',
                    'date_format_at_border' => null,
                ]
            ],
            'thick_line' => [
                'from' => null,
                'to' => null,
                'interval' => 'week',
                'value' => 1
            ],
            'thick_line_color' => null,
        ],
        DataTimeline::GRANULARITY_WEEKS => [
            'name' => 'TRANSLATE:WIDGET.GANTT_CHARD.VIEW_MODE_WEEK',
            'step' => '7d',
            'date_format' => 'yyyy-MM-dd',
            'column_width' => 140,
            'padding' => '1m',
            'snap_at' => null,
            'upper_text_frequency' => 4,
            'header' => [
                'upper' => [
                    'interval' => 'Month',
                    'date_format' => '',
                    'date_format_at_border' => 'MMMM'
                ],
                'lower' => [
                    'interval' => null,
                    'date_format' => '~weekRange',
                    'date_format_at_border' => null,
                ]
            ],
            'thick_line' => [
                'interval' => 'month_range_in_days',
                'from' => 1,
                'to' => 7,
                'value' => null
            ],
            'thick_line_color' => null,
        ],
        DataTimeline::GRANULARITY_MONTHS => [
            'name' => 'TRANSLATE:WIDGET.GANTT_CHARD.VIEW_MODE_MONTH',
            'step' => '1m',
            'date_format' => 'yyyy-MM',
            'column_width' => 120,
            'padding' => '2m',
            'snap_at' => '7d',
            'upper_text_frequency' => null,
            'header' => [
                'upper' => [
                    'interval' => 'Year',
                    'date_format' => '',
                    'date_format_at_border' => 'YYYY',
                ],
                'lower' => [
                    'interval' => null,
                    'date_format' => 'MMMM',
                    'date_format_at_border' => null,
                ]
            ],
            'thick_line' => [
                'interval' => 'month_range_in_days',
                'from' => 1,
                'to' => 7,
                'value' => null
            ],
            'thick_line_color' => null,
        ],
        DataTimeline::GRANULARITY_YEARS => [
            'name' => 'TRANSLATE:WIDGET.GANTT_CHARD.VIEW_MODE_YEAR',
            'step' => '1y',
            'date_format' => 'YYYY',
            'column_width' => 120,
            'padding' => '2y',
            'snap_at' => '30d',
            'upper_text_frequency' => null,
            'header' => [
                'upper' => [
                    'interval' => 'Decade',
                    'date_format' => '',
                    'date_format_at_border' => '~decade',
                ],
                'lower' => [
                    'interval' => 'Year',
                    'date_format' => 'YYYY',
                    'date_format_at_border' => null,
                ]
            ],
        ],
    ];
    
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
        $controller->addMethod(self::CONTROLLER_METHOD_SYNC_TO_GANTT, $this, 'oTable', $this->buildJsSyncTreeToGantt('oTable'));
        $controller->addMethod(self::CONTROLLER_METHOD_CHECK_TABLE_IS_READY, $this,'oTable', $this->buildJsCheckTableIsReady('oTable'));
        $keepScrollPosition = json_encode($widget->getKeepScrollPosition());
        
        if ($calItem->hasColorScale()) {
            $this->registerColorClasses($calItem->getColorScale());
        }
        
        // adds the view mode buttons to the toolbar
        $aViewModes = $widget->getTimelineConfig()->getViews();
        $this->addGanttViewModeButtons($this->getWidget()->getToolbarMain()->getButtonGroup(0),2, $aViewModes);
        $this->addGanttScrollButtons($this->getWidget()->getToolbarMain()->getButtonGroup(0),1);
        
        // reloads the gantt task data at navigation return
        $controller->addOnShowViewScript(
            <<<JS
               setTimeout(function(){
                 const oTableReload = sap.ui.getCore().getElementById('{$this->getId()}');
                 var oCtrl = sap.ui.getCore().byId('{$this->getId()}');
                 
                 {$controller->buildJsMethodCallFromController(self::CONTROLLER_METHOD_SYNC_TO_GANTT, $this, 'oTableReload')};
                 
                 let toolbarOffsetHeight = sap.ui.getCore().byId('{$this->getId()}').$().parents('.sapMPanel').children('.exf-datatoolbar')[0]?.offsetHeight
                 if (toolbarOffsetHeight !== undefined) {
                   sap.ui.getCore().byId('{$this->getId()}').$().parents('.sapMPanelContent').css("height", "calc(100% - " + toolbarOffsetHeight + "px)");
                 }
                 
                 // In some cases (tab switch or back navigation) the gantt shows the left coner of the diagram. 
                 // This is a fix for this behaviour.
                 if ($keepScrollPosition) {
                    setTimeout(function(){
                      oCtrl.gantt.set_scroll_position("today");
                    },150);
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
                            var oTable = sap.ui.getCore().getElementById('{$this->getId()}');
                             
                            if (oCtrl.gantt === undefined) {
                                oCtrl.gantt = {$this->buildJsGanttInit()}
                                
                                var oRowsBinding = new sap.ui.model.Binding(sap.ui.getCore().byId('{$this->getId()}').getModel(), '/rows', sap.ui.getCore().byId('{$this->getId()}').getModel().getContext('/rows'));
                                oRowsBinding.attachChange(function(oEvent){
                                    var oBinding = oEvent.getSource();
                                    {$controller->buildJsMethodCallFromController(self::CONTROLLER_METHOD_SYNC_TO_GANTT, $this, 'oTable')};
                                });
                                

                                sap.ui.core.ResizeHandler.register(sap.ui.getCore().byId('{$this->getId()}').getParent(), function(){
                                    {$controller->buildJsMethodCallFromController(self::CONTROLLER_METHOD_SYNC_TO_GANTT, $this, 'oTable')};  
                                });
                            } else if ($keepScrollPosition) {
                              setTimeout(function(){
                                oCtrl.gantt.set_scroll_position("today");
                              },100);
                            }
                            {$controller->buildJsMethodCallFromController(self::CONTROLLER_METHOD_SYNC_TO_GANTT, $this, 'oTable')};
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
     * This method builds the JS property for column header height of the left table.
     * 
     * @return string
     */
    protected function buildJsPropertyColumnHeaderHeight() : string
    {
        return 'columnHeaderHeight: 75,';
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
                    {$this->getController()->buildJsMethodCallFromController(self::CONTROLLER_METHOD_SYNC_TO_GANTT, $this, 'oTable')};
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
                    {$this->getController()->buildJsMethodCallFromController(self::CONTROLLER_METHOD_SYNC_TO_GANTT, $this, 'oTable')};
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
        $defaultDurationHours = $calItem->getDefaultDurationHours(48);
        $viewModesConfig = $this->getViewModesConfig();
        $editableJs = ($calItem->getStartTimeColumn()->isEditable() && $calItem->getEndTimeColumn()->isEditable()) ? 'true' : 'false';
        $initialViewName = $widget->getTimelineConfig()->getInitialViewName();
        
        $viewModesConfigJson = json_encode($viewModesConfig, JSON_UNESCAPED_SLASHES);
                
        if ($startCol->getDataType() instanceof DateDataType) {
            $dateFormat = $startFormatter->getFormat();
        } else {
            $dateFormat = $this->getWorkbench()->getCoreApp()->getTranslator()->translate('LOCALIZATION.DATE.DATE_FORMAT');
        }
        
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
    // Builds frappe-gantt readable view modes from the simplified config
    const buildedViewModes = viewModeBuilder.buildViewModesFromSimpleConfig({$viewModesConfigJson});
  
    return new Gantt("#{$this->getId()}_gantt", [
      {
        id: 1,
        name: 'Loading...',
        start: (() => {
          const d = new Date();
          return "" + d.getFullYear() + "-" + d.getMonth() + "-" + d.getDate() + "";
        })(),
        duration: '3d',
      }
    ], {
        view_mode_select: false, // TODO SR: Remove this property, as we now have custom buttons for view mode selection
        today_button: false,
        upper_header_height: 40,
        lower_header_height: 25,
        auto_move_label: true,
        view_modes: buildedViewModes,
        view_mode: '{$initialViewName}',
        infinite_padding: true,
        // <<< New properties-----------------------------------------------------------------------
        // TODO SR: Build uxon properties if ready:
        popup_on: 'click', //hover, click
        holidays: null, // { 'var(--g-weekend-highlight-color)': 'weekend' }
        stripe_rows: true,
        date_formatter: exfTools.date.format, // Uses or exfTools formatter
        date_format_default: 'yyyy-MM-dd HH:mm:ss.SSS',
        row_height: 33, //33 //TODO SR: Default value. Change it only after the row hight of the left UI5Table is implemented and is set to the same value!
        row_lanes: 2, //2 //TODO SR: Default value. Increase it only after the increase of the row_height to keep the text of the bars readable.
        popup_aggregate_expand_tasks: false, //TODO SR: Not ready for prod. Keep at false. // Shows a compact Gantt next to the aggregation popup task list.
        include_today_in_padding: false, //TODO SR: If the padding is added to the right side, the "today" is currently also at the right side and not an the left.
        popup_aggregate_gantt_width: 360, // Width in px for the Gantt shown inside aggregation popups.
        popup_aggregate_style: 'list', // 'list' | 'table'
        popup_aggregate_include_upper_row_tasks: false, // Includes tasks that are in the top lane of the row in the aggregate popup. Set to false to only include tasks inside the aggregation block.
        popup: {$this->buildJsRenderPopup()},
        start_of_week: 'monday', // 'monday' | 'sunday' TODO SR: 'sunday' currentlly dont work properly.
        //
        readonly: !($editableJs),
        //column_width: 30,
        //step: 24,
        bar_height: 19,
        //bar_corner_radius: 3,
        //arrow_curve: 5,
        padding: 14,
        //view_mode: 'Tage', //TODO SR: Currently still overwritten by ‘view_modes’ and only works if no custom ‘view_modes’ have been passed.
        label_overflow: '$titleOverflow',
        keep_scroll_position: '$keepScrollPosition',
        default_duration: Math.ceil('$defaultDurationHours' / 24), //TODO SR: default_duration is currently only available in days. mybe add support for hours in the future.
        language: 'en', // or 'es', 'it', 'ru', 'ptBr', 'fr', 'tr', 'zh', 'de', 'hu'
        //custom_popup_html: null,
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
        $colorResolversJs = $this->buildJsColorResolver($calItem, 'oRow');
        $controller = $this->getController();
        $colorPreference = $this->getFacade()->getConfig()->getOption('WIDGET.OBJECT_STATUS.TEXT_COLOR_PREFERENCE');
        
        if ($calItem->getNestedDataColumn() || $calItem->getColorColumn()) {
            $nestedDataColName = $this->escapeString($calItem->getNestedDataColumn()->getDataColumnName());
        } else {
            $nestedDataColName = 'null';
        }
        return <<<JS
            const syncTreeToGantt = function(oTable) {
                var oGantt = sap.ui.getCore().byId('{$this->getId()}').gantt;
                if (oGantt === undefined) return;
                
                var aTasks = [];
                var sNestedColName = {$nestedDataColName}
                let lineIndex = 0;
                const rowKeys = [];
                
                oTable.getRows().forEach(function(oTreeRow) {
                    var oCtxt = oTreeRow.getBindingContext();
                    var oRow, sColor;
                    
                    function fnRowToTask(oRow) {
                        sColor = {$colorResolversJs};
                        sColor = sColor ?? '#b8c2cc'; // Default color.
                        var oTask = {
                            id: oRow['{$widget->getUidColumn()->getDataColumnName()}'],
                            name: oRow['{$calItem->getTitleColumn()->getDataColumnName()}'],
                            start: oRow["{$calItem->getStartTimeColumn()->getDataColumnName()}"],
                            end: oRow["{$calItem->getEndTimeColumn()->getDataColumnName()}"],
                            progress: 0,
                            dependencies: '',
                            lineIndex: lineIndex,
                            draggable: $draggableJs, //TODO SR: depricated. Use readonly property of gantt instead.
                            color: sColor,
                            colorHover: exfColorTools.shadeCssColor(sColor, -0.08),    // slightly darker
                            progressColor: exfColorTools.shadeCssColor(sColor, -0.28), // significantly darker
                            textColor: exfColorTools.pickTextColorForBackgroundColor(sColor, {$colorPreference}),
                        };
        
                        if(oRow?._children?.length > 0 && oTask.start && oTask.end) {
                            oTask.custom_class += ' bar-folder';
                        }
                        
                        // Exludes tasks with no start and end date.
                        if (oTask.start || oTask.end) {
                          aTasks.push(oTask);
                        }
                    }
                    
                    if (!oCtxt) return;
                    
                    oRow = oTable.getModel().getProperty(oCtxt.sPath);
                    rowKeys.push(lineIndex);
                    
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
                
                oGantt.options.row_keys = rowKeys;
                oGantt.tasks = aTasks;
                oGantt.refresh(aTasks);
            };

            let isTableReady = {$controller->buildJsMethodCallFromController(self::CONTROLLER_METHOD_CHECK_TABLE_IS_READY, $this, $oTableJs)};
            if (isTableReady) {
              setTimeout(function(){
                syncTreeToGantt($oTableJs);
              },0);
            } else {
              setTimeout(function(){
                syncTreeToGantt($oTableJs);
              },200);
            }
            
JS;
    }

    /**
     * This function checks if the oTable with relevant data is ready.
     * 
     * @param string $oTableJs
     * @return string
     */
    public function buildJsCheckTableIsReady(string $oTableJs) : string
    {
        return <<<JS
            return (function checkTableIsReady(oTable) {
              const oTableRows = oTable.getRows();
              return oTableRows.some(row => !!row.getBindingContext());
            })($oTableJs);
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
        return $this->getWidget()->isPaged();
    }
    
    public function registerExternalModules(UI5ControllerInterface $controller) : UI5AbstractElement
    {
        $f = $this->getFacade();
        $controller->addExternalModule('libs.moment.moment', $f->buildUrlToSource("LIBS.MOMENT.JS"), null, 'moment');
        
        $controller->addExternalModule('libs.exface.gantt.Gantt', $f->buildUrlToSource("LIBS.FRAPPE_GANTT.JS"), null, 'Gantt');
        $controller->addExternalCss($f->buildUrlToSource("LIBS.FRAPPE_GANTT.CSS"));
        // task overlapping feature css:
        $controller->addExternalCss($f->buildUrlToSource("LIBS.FRAPPE_GANTT.EXF.CSS"));
        // additional tools for color manipulation and view mode generation
        $controller->addExternalModule('libs.exface.exfColorTools', $f->buildUrlToSource("LIBS.EXFCOLORTOOLS.JS"), null, 'exfColorTools');
        $controller->addExternalModule('libs.exface.viewModeBuilder.viewModeBuilder',  $f->buildUrlToSource("LIBS.FRAPPE_GANTT.VIEW_BUILDER.JS"), null, 'viewModeBuilder');
        
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
                            var value = {$oRowJs}['{$colorCol->getDataColumnName()}']
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
        return 'null';
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
    public function addGanttViewModeButtons(ButtonGroup $btnGrp, int $index = 0, array $viewModes) : void 
    {
        if (empty($viewModes)) {
            return;
        }

        $buttons = [];

        foreach ($viewModes as $viewMode) {
            $viewName = $viewMode->getName();
            $viewIcon = $viewMode->getIcon() ?? '';
            
            $buttons[] = [
                'caption' => $viewName,
                'action'  => [
                    'alias'  => 'exface.Core.CustomFacadeScript',
                    'icon' => $viewIcon,
                    'script' => <<<JS
                        sap.ui.getCore().byId('[#element_id:~input#]').gantt.change_view_mode('$viewName');
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

    /**
     * Adds the scroll navigation buttons to the button group at the toolbar:
     * "<<":    navigates to the start of chard
     * "Today": navigates to today
     * ">>":    navigates to the end of the chard
     * 
     * @param ButtonGroup $btnGrp
     * @param int $index
     * @return void
     */
    protected function addGanttScrollButtons(ButtonGroup $btnGrp, int $index = 0) : void 
    {
        $sToday = $this->getWorkbench()->getCoreApp()->getTranslator()->translate('LOCALIZATION.DATE.TODAY');
        
        $buttons = [
            [
                'caption' => '',     
                'icon' => 'angle-double-left',
                'script' => <<<JS
                        sap.ui.getCore().byId('[#element_id:~input#]').gantt.set_scroll_position("start");
JS
            ],
            [
                'caption' => $sToday,
                'icon' => '',
                'script' => <<<JS
                        sap.ui.getCore().byId('[#element_id:~input#]').gantt.scroll_current();
JS
            ],
            [
                'caption' => '',     
                'icon' => 'angle-double-right',
                'script' => <<<JS
                        sap.ui.getCore().byId('[#element_id:~input#]').gantt.set_scroll_position("end");
JS
            ],
        ];
        
        foreach ($buttons as $i => $button) {
            $btnGrp->addButton($btnGrp->createButton(new UxonObject([
                'widget_type' => 'Button',
                'caption' => $button['caption'],
                'icon' => $button['icon'],
                'action'  => [
                    'alias'  => 'exface.Core.CustomFacadeScript',
                    'icon' => '',
                    'script' => $button['script'],
                ],
            ])), $index + $i);
            
        }
    }
    
    protected function convertDataTimelineGranularityToGanttStep($granularity) : string 
    {
        return match ($granularity) {
            DataTimeline::GRANULARITY_HOURS => '1h',
            DataTimeline::GRANULARITY_QUARTER_DAYS => '6h',
            DataTimeline::GRANULARITY_HALF_DAYS => '12h',
            DataTimeline::GRANULARITY_DAYS, 
            DataTimeline::GRANULARITY_DAYS_PER_WEEK, 
            DataTimeline::GRANULARITY_DAYS_PER_MONTH => '1d',
            DataTimeline::GRANULARITY_WEEKS => '7d',
            DataTimeline::GRANULARITY_MONTHS => '1m',
            DataTimeline::GRANULARITY_YEARS => '1y',
            default => throw new InvalidArgumentException('The Gantt chart only supports the following granularities for the view modes: "hours", "quarter_days", "half_days", "days", "weeks", "months" and "years".'),
        };
    }
    
    protected function convertDataTimelineSnapToGanttSnap($snapString)
    {
        return match ($snapString) {
            DataTimelineView::SNAP_AT_DAILY => '1d',
            DataTimelineView::SNAP_AT_WEEKLY => '7d',
            DataTimelineView::SNAP_AT_MONTHLY => '30d', // "30d" is the original value of the gantt library
            null => null,
            default => throw new InvalidArgumentException('The Gantt chart only supports the following snap strings: "daily", "weekly" and "monthly".'),
        };
    }
    
    protected function convertDataTimeLineIntervalToGanttInterval($value) : string
    {
        return match ($value) {
            DataTimeline::INTERVAL_DAY => 'Date',
            DataTimeline::INTERVAL_MONTH => 'Month',
            DataTimeline::INTERVAL_YEAR => 'Year',
            DataTimeline::INTERVAL_DECADE => 'Decade',
            default => throw new InvalidArgumentException('The Gantt chard only supports the following intervals for the header lines: "day", "month", "year" and "decade".'),
        };
    }
    
    /**
     * It maps uxon DataTimelineView views to a simplified array structure, 
     * that can be converted with view-mode-builder.js to the required gantt view mode structure.
     * 
     * Example output:
     * ```
     * Week: {
     *      padding: '1m',
     *      step: '7d',
     *      date_format: 'YYYY-MM-dd',
     *      column_width: 140,
     *      upper_text_frequency: 4,
     *      
     *      header: {
     *          upper: {
     *              interval: 'Month',
     *              date_format: '',
     *              date_format_at_border: 'MMMM',
     *          },
     *
     *      lower: {
     *          interval: null,
     *          date_format: '~weekRange',
     *          },
     *      },
     *
     *      thick_line: {
     *          interval: 'month_range_in_days',
     *          from: 1,
     *          to: 7
     *      },
     * },
     * ```
     * 
     * @return array
     */
    protected function getViewModesConfig(): array
    {
        $widget = $this->getWidget();
        $viewModes = $widget->getTimelineConfig()->getViews();
        $simple_view_modes = [];
        
        foreach ($viewModes as $viewMode) {
            $simple_view_mode = [];
            $name = $viewMode->getName();
            $headerLines = $viewMode->getHeaderLines() ?? [];
            $thickLines = $viewMode->getThickLines() ?? [];

            if (null !== $val = $viewMode->getName()) {
                $simple_view_mode['name'] = $val;
            }

            if (null !== $val = $this->convertDataTimelineGranularityToGanttStep($viewMode->getGranularity())) {
                $simple_view_mode['step'] = $val;
            }

            if (null !== $val = $viewMode->getDateFormat()) {
                $simple_view_mode['date_format'] = $val;
            }

            if (null !== $dim = $viewMode->getColumnWidth()) {
                if (!is_numeric($dim->getValue())) {
                    throw new FacadeRuntimeError('Only numbers are supported in column_width for Gantt timeline views');
                }
                $simple_view_mode['column_width'] = (int) $dim->getValue();
            }

            if (null !== $val = $viewMode->getPadding()) {
                $simple_view_mode['padding'] = $val;
            }

            if (null !== $val = $this->convertDataTimelineSnapToGanttSnap($viewMode->getSnapAt())) {
                $simple_view_mode['snap_at'] = $val;
            }

            if (null !== $val = $viewMode->getUpperTextFrequency()) {
                if (!is_numeric($val)) {
                    throw new FacadeRuntimeError('Only numbers are supported in column_width for Gantt timeline views');
                }
                $simple_view_mode['upper_text_frequency'] = (int) $val;
            }

            // Gantt only supports 2 header lines, so we just take the first 2.
            /** @var DataTimelineHeader|null $upper */
            $upper = $headerLines[0] ?? null;
            /** @var DataTimelineHeader|null $lower */
            $lower = $headerLines[1] ?? null;
            /** @var DataTimelineThicklines|null $thickLine */
            $thickLine = $thickLines[0] ?? null;

            if ($upper !== null) {
                if (null !== $val = $upper->getDateFormat()) {
                    $simple_view_mode['header']['upper']['date_format'] = $val;
                }

                if (null !== $val = $upper->getDateFormatAtBorder()) {
                    $simple_view_mode['header']['upper']['date_format_at_border'] = $val;
                }

                if (null !== $val = $upper->getInterval()) {
                    $simple_view_mode['header']['upper']['interval'] = $this->convertDataTimeLineIntervalToGanttInterval($val);
                }
            }

            if ($lower !== null) {
                if (null !== $val = $lower->getDateFormat()) {
                    $simple_view_mode['header']['lower']['date_format'] = $val;
                }

                if (null !== $val = $lower->getDateFormatAtBorder()) {
                    $simple_view_mode['header']['lower']['date_format_at_border'] = $val;
                }

                if (null !== $val = $lower->getInterval()) {
                    $simple_view_mode['header']['lower']['interval'] = $this->convertDataTimeLineIntervalToGanttInterval($val);
                }
            }
            
            if ($thickLine !== null) {
                if (null !== $val = $thickLine->getColor()) {
                    $simple_view_mode['thick_line_color'] = $val;
                }

                if (null !== $val = $thickLine->getFrom()) {
                    $simple_view_mode['thick_line']['from'] = $val;
                }

                if (null !== $val = $thickLine->getTo()) {
                    $simple_view_mode['thick_line']['to'] = $val;
                }

                if (null !== $val = $thickLine->getValue()) {
                    $simple_view_mode['thick_line']['value'] = $val;
                }

                if (null !== $val = $thickLine->getInterval()) {
                    $simple_view_mode['thick_line']['interval'] = $val;
                }
            }
            
            $baseViewMode = $this->getViewModeDefault($viewMode->getGranularity());
            $simple_view_mode = array_replace_recursive($baseViewMode, $simple_view_mode);
            
            if (str_starts_with($simple_view_mode['name'],'TRANSLATE:'))
            {
                $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
                $untranslatedName = substr(
                    $simple_view_mode['name'],
                    strlen('TRANSLATE:')
                );
                $simple_view_mode['name'] = $translator->translate($untranslatedName);
            }
            
            $simple_view_modes[$name] = $simple_view_mode;
        }
        return $simple_view_modes;
    }

    /**
     * Returns an array with default values for each granularity
     * 
     * @param string $granularity
     * @return array
     */
    protected function getViewModeDefault(string $granularity) : array
    {
        $default = $this->viewModeDefaults[$granularity] ?? null;
        if ($default === null) {
            // If no specific defaults for the granularity are defined,
            // we take the defaults of the 'days' granularity,
            // as it is the most common one and also the default granularity for the gantt chart.
            return $this->viewModeDefaults['days'];
        }
        return $default;
    }

    /**
     * Builds JS for the normal popup renderer.
     * This has no effect on the aggregated popups.
     * 
     * @return string
     */
    protected function buildJsRenderPopup() : string
    {
        
        $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
        
        return <<< JS
          (ctx) => {
                ctx.set_title(ctx.task.name);
                if (ctx.task.description) ctx.set_subtitle(ctx.task.description);
                else ctx.set_subtitle('');
        
                const start_date = exfTools.date.format(
                    ctx.task._start,
                    'dd.MM.yy',
                    ctx.chart.options.language,
                );
                const end_date = exfTools.date.format(
                    exfTools.date.add(ctx.task.orig_end, -1, 'second'),
                    'dd.MM.yy',
                    ctx.chart.options.language,
                );
                
                const hasRealStart = !!(ctx.task.start);
                const hasRealEnd = (!!(ctx.task.end) || ctx.task.duration !== undefined);
        
                if (hasRealStart || hasRealEnd) {
                  if (hasRealStart && hasRealEnd) {
                    // Note: You can include the "progress" value as followed:  <br/>Progress: \${Math.floor(ctx.task.progress * 100) / 100}%
                    ctx.set_details(
                        `\${start_date} - \${end_date} 
                        (\${ctx.task.actual_duration} {$translator->translate('WIDGET.GANTT_CHARD.POPUP_CAPTION_DAYS')}\${ctx.task.ignored_duration ? ' + ' + ctx.task.ignored_duration + ' {$translator->translate('WIDGET.GANTT_CHARD.POPUP_CAPTION_EXCLUDED')}' : ''})`,
                    );
                  } else if (hasRealStart && !hasRealEnd) {
                    ctx.set_details(
                        `\${start_date} - ...`,
                    );
                  } else if (hasRealEnd && !hasRealStart) {
                    ctx.set_details(
                        `... - \${end_date}`,
                    );
                  }
                }
          }
JS;
    }
}