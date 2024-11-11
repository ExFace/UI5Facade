<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\UI5Facade\Facades\Elements\Traits\UI5DataElementTrait;
use exface\Core\Interfaces\WidgetInterface;
use exface\UI5Facade\Facades\Interfaces\UI5ValueBindingInterface;
use exface\Core\Widgets\Parts\DataTimeline;
use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JsValueScaleTrait;
use exface\Core\Widgets\Parts\DataCalendarItem;
use exface\Core\Exceptions\Facades\FacadeUnsupportedWidgetPropertyWarning;
use exface\UI5Facade\Facades\Interfaces\UI5DataElementInterface;
use exface\UI5Facade\Facades\Elements\Traits\UI5ColorClassesTrait;

/**
 * 
 * @method \exface\Core\Widgets\Scheduler getWidget()
 * 
 * @author Andrej Kabachnik
 *
 */
class UI5Scheduler extends UI5AbstractElement implements UI5DataElementInterface
{
    use UI5DataElementTrait {
        buildJsDataLoaderOnLoaded as buildJsDataLoaderOnLoadedViaTrait;
        buildJsValueGetter as buildJsValueGetterViaTrait;
        buildJsDataResetter as buildJsDataResetterViaTrait;
    }
    
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
        
        $cssClasses = '';
        $customViewsJs = <<<JS
        
        new sap.m.PlanningCalendarView({
            description: 'Years',
            key: 'Years',
            intervalsL: 24,
            intervalsM: 16,
            intervalsS: 12,
            intervalType: 'Month',
            showSubIntervals: false
        }),
        new sap.m.PlanningCalendarView({
            description: 'All',
            key: 'All',
            intervalsL: 24,
            intervalsM: 16,
            intervalsS: 12,
            intervalType: 'Month',
            showSubIntervals: false
        }),
JS;
        
        // Calculate the interval type
        $showRowHeaders = $this->getWidget()->hasResources() ? 'true' : 'false';
        switch ($this->getWidget()->getTimelineConfig()->getGranularity(DataTimeline::GRANULARITY_HOURS)) {
            case DataTimeline::GRANULARITY_HOURS: $viewKey = 'sap.ui.unified.CalendarIntervalType.Hour'; break;
            case DataTimeline::GRANULARITY_DAYS: $viewKey = 'sap.ui.unified.CalendarIntervalType.Day'; break;
            case DataTimeline::GRANULARITY_DAYS_PER_WEEK: $viewKey = 'sap.ui.unified.CalendarIntervalType.Week'; break;
            case DataTimeline::GRANULARITY_DAYS_PER_MONTH: $viewKey = 'sap.ui.unified.CalendarIntervalType.OneMonth'; break;
            case DataTimeline::GRANULARITY_MONTHS: $viewKey = 'sap.ui.unified.CalendarIntervalType.Month'; break;
            case DataTimeline::GRANULARITY_WEEKS: throw new FacadeUnsupportedWidgetPropertyWarning('Timeline granularity `weeks` currently not supported in UI5!'); break;
            case DataTimeline::GRANULARITY_YEARS: $viewKey = "'Years'"; break;
            case DataTimeline::GRANULARITY_ALL: $viewKey = "'All'"; break;
            default: $viewKey = 'sap.ui.unified.CalendarIntervalType.Hour'; break;
        }
        
        // Add event handler for changing the view/time or any other redrawing operation
        $this->registerEventTimeshift();
        
        $this->getController()->addOnInitScript(<<<JS

$(document).on('click', '#{$this->getId()} .sapUiCalItemText', function(){
    setTimeout(function(){
        {$this->getController()->buildJsEventHandler($this, self::EVENT_NAME_TIMELINE_SHIFT, false)} 
    }, 0);
});

JS, false);
        
        if ($this->getWidget()->isPaged()) {
            $refreshOnNavigation = <<<JS

    startDateChange: {$controller->buildJsMethodCallFromView('onLoadData', $this)},
    viewChange: function(oEvent){
            var oPCal = oEvent.getSource();
            {$controller->buildJsMethodCallFromController('onLoadData', $this, '', $oControllerJs)}
            {$this->buildJsNavRefresh('oPCal')}
    },
JS;
        } else {
            $refreshOnNavigation = <<<JS
            
    startDateChange: {$controller->buildJsEventHandler($this, self::EVENT_NAME_TIMELINE_SHIFT, true)},
    viewChange: function(oEvent){
        var oPCal = oEvent.getSource();
        if (oPCal.getViewKey() === 'All') {
            {$controller->buildJsMethodCallFromController('onLoadData', $this, '', $oControllerJs)}
            sap.ui.getCore().byId('{$this->getIdOfBigNavButtonPrev()}').setVisible(false);
            sap.ui.getCore().byId('{$this->getIdOfBigNavButtonNext()}').setVisible(false);
        } else {
            {$controller->buildJsEventHandler($this, self::EVENT_NAME_TIMELINE_SHIFT, false)}
        }
    },
JS;
        }
        
        if (! $this->getWidget()->getItemsConfig()->hasSubtitle()) {
            $aptHeight = 'appointmentHeight: sap.ui.unified.CalendarAppointmentHeight.HalfSize,';
            // Reduce the height of the planning calendar rows too with a CSS hack
            // @see https://answers.sap.com/questions/13327688/row-height-of-the-planning-calendar.html
            $cssClasses .= 'halfHeight';
        } else {
            $aptHeight = '';
        }
        
        $startDateProp = $this->getWidget()->getStartDate() ? "startDate: exfTools.date.parse('{$this->getWidget()->getStartDate()}')," : '';
        
        // Add large nav-buttons on the sides of the scheduler to avoid having to move the mouse
        // up to the header to navigate left/right.
        $toolbar = $this->buildJsToolbarContent($oControllerJs);
        $toolbar .= <<<JS
        
                    new sap.m.Button("{$this->getIdOfBigNavButtonPrev()}", {
                        icon: "sap-icon://slim-arrow-left",
                        press: function(oEvent) {
                            sap.ui.getCore().byId('{$this->getId()}-Header-NavToolbar-PrevBtn').firePress();
                        }
                    }).addStyleClass('exf-scheduler-nav exf-scheduler-navleft'),
                    new sap.m.Button("{$this->getIdOfBigNavButtonNext()}", {
                        icon: "sap-icon://slim-arrow-right",
                        press: function(oEvent) {
                            sap.ui.getCore().byId('{$this->getId()}-Header-NavToolbar-NextBtn').firePress();
                        }
                    }).addStyleClass('exf-scheduler-nav exf-scheduler-navright'),
JS;
        
        return <<<JS

new sap.m.PlanningCalendar("{$this->getId()}", {
    {$this->buildJsPropertyVisibile()}
    {$startDateProp}
    {$aptHeight}
	appointmentsVisualization: sap.ui.unified.CalendarAppointmentVisualization.Filled,
    groupAppointmentsMode: sap.ui.unified.GroupAppointmentsMode.Expanded,
    builtInViews: [
        sap.ui.unified.CalendarIntervalType.Hour,
        sap.ui.unified.CalendarIntervalType.Day,
        sap.ui.unified.CalendarIntervalType.Week,
        sap.ui.unified.CalendarIntervalType.OneMonth,
        sap.ui.unified.CalendarIntervalType.Month
    ],
    views: [
        $customViewsJs
    ],
    viewKey: $viewKey,
	showRowHeaders: {$showRowHeaders},
    showEmptyIntervalHeaders: false,
    showIntervalHeaders: true,
	showWeekNumbers: true,
    {$refreshOnNavigation}
    appointmentSelect: {$controller->buildJsEventHandler($this, self::EVENT_NAME_CHANGE, true)},
    rowSelectionChange: {$controller->buildJsEventHandler($this, self::EVENT_NAME_ROW_SELECTION_CHANGE, true)},
	toolbarContent: [
		{$toolbar}
	],
	rows: {
		path: '/_scheduler/rows',
        template: {$this->buildJsRowsConstructors()}
	}
})
.data('_exfStartDate', {$this->escapeString($this->getWidget()->getStartDate())})
.addStyleClass('$cssClasses')
.addEventDelegate({
    onAfterRendering: function() {
        setTimeout(function(){ 
            {$this->getController()->buildJsEventHandler($this, self::EVENT_NAME_TIMELINE_SHIFT, false)} 
        }, 0)
    }
})
{$this->buildJsClickHandlers($oControllerJs)}

JS;
    }
    
    protected function getIdOfBigNavButtonPrev() : string
    {
        return "{$this->getId()}_navleft";
    }
    
    protected function getIdOfBigNavButtonNext() : string
    {
        return "{$this->getId()}_navright";
    }
		
    protected function buildJsRowsConstructors() : string
    {
        $widget = $this->getWidget();
        
        $calItem = $widget->getItemsConfig();
        if ($calItem->hasSubtitle()) {
            $subtitleOptions = "text: {$this->buildJsValueBindingForWidget($calItem->getSubtitleColumn()->getCellWidget())},";
        } else {
            $subtitleOptions = '';
        }
        
        $rowProps = '';
        if ($widget->hasResources() === true) {
            $resource = $widget->getResourcesConfig();
            $rowProps .= 'title: ' . $this->buildJsValueBindingForWidget($resource->getTitleColumn()->getCellWidget()) . ',';
            if ($resource->hasSubtitle()) {
                $rowProps .= 'text: ' . $this->buildJsValueBindingForWidget($resource->getSubtitleColumn()->getCellWidget()) . ',';
            }
        }
        
        return <<<JS

        new sap.m.PlanningCalendarRow({
			{$rowProps}
			appointments: {
                path: 'items', 
                templateShareable: true,
                template: new sap.ui.unified.CalendarAppointment({
					startDate: "{_start}",
					endDate: "{_end}",
					// icon: "{pic}",
					title: {$this->buildJsValueBindingForWidget($calItem->getTitleColumn()->getCellWidget())},
					tooltip: {$this->buildJsValueBindingForWidget($calItem->getTitleColumn()->getCellWidget())},
					{$subtitleOptions}
					key: "{{$this->getMetaObject()->getUidAttributeAlias()}}",
                    {$this->buildJsAppointmentPropertyColor($calItem)}
				})
            },/*
			intervalHeaders: {
                path: 'headers', 
                templateShareable: true,
                template: new sap.ui.unified.CalendarAppointment({
					startDate: "{start}",
					endDate: "{end}",
					icon: "{pic}",
					title: "{title}"
				})
            },*/
		})
JS;
    }
    
    /**
     * 
     * @return void
     */
    protected function registerEventTimeshift()
    {
        $widget = $this->getWidget();
        if ($widget->getItemsConfig()->hasIndicator()) {
            $indicatorCfg = $widget->getItemsConfig()->getIndicatorConfig();
            if ($indicatorCfg->hasColorScale()) {
                $indicatorColorScale = $indicatorCfg->getColorScale();
                $this->registerColorClasses($indicatorColorScale, '.exf-custom-color.exf-color-[#color#]', 'border-left-color: [#color#] !important; border-left-width: 1rem;', false);
                $semanticColors = $this->getFacade()->getSemanticColors();
                $semanticColorsJs = json_encode(empty($semanticColors) ? new \stdClass() : $semanticColors);
                $setIndicatorClassesJs = <<<JS
                
                    oPCal.getRows().forEach(function(oCalRow){
                        oCalRow.getAppointments().forEach(function(oAppt){
                            var oData = oAppt.getBindingContext().getObject();
                            var mVal = oData['{$indicatorCfg->getColorColumn()->getDataColumnName()}'];
                            var sColor = {$this->buildJsScaleResolver('mVal', $indicatorColorScale, $indicatorCfg->isColorScaleRangeBased())}
                            var sCssColor = '';
                            var oSemanticColors = $semanticColorsJs;
                            if (sColor.startsWith('~')) {
                                sCssColor = oSemanticColors[sColor] || '';
                            } else if (sColor) {
                                sCssColor = sColor;
                            }
                            
                            (oAppt.$().attr('class') || '').split(/\s+/).forEach(function(sClass) {
                                if (sClass.startsWith('exf-color-')) {
                                    oAppt.$().removeClass(sClass);
                                }
                            });
                            if (sColor === null) {
                                oAppt.$().removeClass('exf-custom-color');
                            } else {
                                oAppt.$().addClass('exf-custom-color exf-color-' + sCssColor.replace("#", ""));
                            }
                        });
                    });
JS;
            }
        }
        
        $this->getController()->addOnEventScript($this, self::EVENT_NAME_TIMELINE_SHIFT, <<<JS
            
                (function(){
                    var oPCal = sap.ui.getCore().byId('{$this->getId()}');
                    var oView = oPCal._getView(oPCal.getViewKey());
                    var iMonth = oPCal.getStartDate().getMonth() + 1;
                    if (oView.getIntervalType() === 'Month' && oPCal._getIntervals(oView) >= 3) {
                        oPCal._oMonthsRow.$().find('.sapUiCalItems > div').each(function(i, div){
                            // here the month january is replaced with the current year in the format e.g. '24' for 2024
                            if ((i+iMonth-1) % 12 === 0) {
                                $(div).find('.sapUiCalItemText')
                                .addClass('exf-scheduler-head-year')
                                .text(parseInt(oPCal.getStartDate().getFullYear().toString().substr(-2)) + Math.floor(i/12) + 1);
                            }
                        })
                    }
                    setTimeout(function(){
                        {$setIndicatorClassesJs}
                        {$this->buildJsNavRefresh('oPCal')}
                    }, 0);
                })()
JS
        );
    }
    
    protected function buildJsAppointmentPropertyColor(DataCalendarItem $calItem) : string
    {
        switch (true) {
            case $colorCol = $calItem->getColorColumn();
                $colorResolverJs = ! $calItem->hasColorScale() ? '(value || "").toString()' : $this->buildJsScaleResolver('value', $calItem->getColorScale(), $calItem->isColorScaleRangeBased());
                $semanticColors = $this->getFacade()->getSemanticColors();
                $semanticColorsJs = json_encode(empty($semanticColors) ? new \stdClass() : $semanticColors);
                return <<<JS
                    color: {
                        path: "{$colorCol->getDataColumnName()}",
                        formatter: function(value){
                            var sColor = {$colorResolverJs};
                            var sValueColor;
                            var oCtrl = this;
                            var sCssColor = '';
                            var oSemanticColors = $semanticColorsJs;
                            if (sColor.startsWith('~')) {
                                sCssColor = oSemanticColors[sColor] || '';
                            } else if (sColor) {
                                sCssColor = sColor;
                            }
                            return sCssColor;
                        }
                    },
JS;
            case null !== $colorVal = $calItem->getColor():
                return 'color: ' . $this->escapeString($colorVal) . ',';
        }
        return '';
    }
        
    /**
     * 
     * @return string
     */
    protected function buildJsDataResetter() : string
    {
        return $this->buildJsDataResetterViaTrait() . "; sap.ui.getCore().byId('{$this->getId()}').data('_exfStartDate', {$this->escapeString($this->getWidget()->getStartDate())})";
    }
    
    /**
     * 
     * @param string $oModelJs
     * @return string
     */
    protected function buildJsDataLoaderOnLoaded(string $oModelJs = 'oModel') : string
    {
        $widget = $this->getWidget();
        $calItem = $widget->getItemsConfig();
        
        $endTime = $calItem->hasEndTime() ? "oDataRow['{$calItem->getEndTimeColumn()->getDataColumnName()}']" : "''";
        
        if ($workdayStart = $widget->getTimelineConfig()->getWorkdayStartTime()){
            $workdayStartSplit = explode(':', $workdayStart);
            $workdayStartSplit = array_map('intval', $workdayStartSplit);
            $workdayStartJs = 'dMin.setHours(' . implode(', ', $workdayStartSplit) . ');';
        }
        
        if ($widget->hasResources()) {
            $rConf = $widget->getResourcesConfig();
            $rowKeyGetter = "oDataRow['{$rConf->getTitleColumn()->getDataColumnName()}']";
            if ($rConf->hasSubtitle()) {
                $rSubtitle = "{$rConf->getSubtitleColumn()->getDataColumnName()}: oDataRow['{$rConf->getSubtitleColumn()->getDataColumnName()}'],";
            }
            $rowProps = <<<JS

                        {$rConf->getTitleColumn()->getDataColumnName()}: oDataRow['{$rConf->getTitleColumn()->getDataColumnName()}'],
                        {$rSubtitle}

JS;
        } else {
            $rowKeyGetter = "''";
        }
        
        return $this->buildJsDataLoaderOnLoadedViaTrait($oModelJs) . <<<JS
        
            var aData = {$oModelJs}.getProperty('/rows');
            var oRows = [];
            var dMin, dMax, dStart, dEnd, sEnd, oDataRow, sRowKey, iDurationMonth;
            var oPCal = sap.ui.getCore().byId('{$this->getId()}');
            var sColNameStart = "{$calItem->getStartTimeColumn()->getDataColumnName()}";
            for (var i in aData) {
                oDataRow = aData[i];
                
                if (oDataRow[sColNameStart] === undefined || oDataRow[sColNameStart] === null || oDataRow[sColNameStart] === '') {
                    continue;
                }

                sRowKey = {$rowKeyGetter};
                if (oRows[sRowKey] === undefined) {
                    oRows[sRowKey] = {
                        {$rowProps}
                        items: [],
                        headers: []
                    };
                }

                dStart = new Date(oDataRow[sColNameStart]);
                if (dMin === undefined || dMin > dStart) {
                    dMin = new Date(dStart.getTime());
                    {$workdayStartJs}
                }
                sEnd = $endTime;
                if (sEnd) {
                    dEnd = new Date(sEnd);
                } else {
                    dEnd = new Date(dStart.getTime());
                    dEnd.setHours(dEnd.getHours() + {$calItem->getDefaultDurationHours(1)});
                }
                if (dMax === undefined || dMax < dEnd) {
                	dMax = dEnd;
                }
                oRows[sRowKey].items.push(
                    $.extend({
                            _start: dStart,
                            _end: dEnd,
                            _dataRowIdx: i
                        }, 
                        oDataRow
                    )
                );
            }

            if (dMin !== undefined && ! oPCal.data('_exfStartDate') && {$oModelJs}.getProperty('/_scheduler') === undefined) {
                oPCal.data('_exfStartDate', dMin).setStartDate(dMin);
            }
            {$oModelJs}.setProperty('/_scheduler', {
                rows: Object.values(oRows),
            });
            
            if (dMin !== undefined && dMax > dMin) {
	            iDurationMonth = (function monthDiff(d1, d2) {
				    var months;
				    months = (d2.getFullYear() - d1.getFullYear()) * 12;
				    months -= d1.getMonth();
				    months += d2.getMonth();
				    return months <= 0 ? 0 : months;
				})(dMin, dMax);
				
	            oPCal.getViews().filter(function(oView) {return oView.getKey() === 'All'})[0].setIntervalsL(iDurationMonth + 1);
                // TODO set start date also when switching from another view to the all-view
	            if (oPCal.getViewKey() === 'All') {
	            	oPCal.setStartDate(dMin);
	            }
            }            
            // fire time shift to update nav buttons, indicators, etc.
            setTimeout(function(){
                {$this->getController()->buildJsEventHandler($this, self::EVENT_NAME_TIMELINE_SHIFT, false)}
            }, 0);
            // fire selection change as selected rows are reseted
            setTimeout(function(){               
                oPCal.fireRowSelectionChange();
            }, 0);
			
JS;
    }
    
    protected function buildJsNavRefresh(string $oPCalJs) : string
    {
        return <<<JS
                (function(oPCal) {
                    if (oPCal.getViewKey() === 'All') {
                        sap.ui.getCore().byId('{$this->getIdOfBigNavButtonPrev()}').setVisible(false);
                        sap.ui.getCore().byId('{$this->getIdOfBigNavButtonNext()}').setVisible(false);
                    } else {
                        sap.ui.getCore().byId('{$this->getIdOfBigNavButtonPrev()}').setVisible(true);
                        sap.ui.getCore().byId('{$this->getIdOfBigNavButtonNext()}').setVisible(true);
                        setTimeout(function(){
                            var iTop = oPCal.$().find('.sapMListInfoTBarContainer').innerHeight() + oPCal.$().find('.sapMPCHead').innerHeight();
                            oPCal.$().find('.exf-scheduler-nav').css('top', iTop);
                            oPCal.$().find('.exf-scheduler-nav').height(oPCal.$().find('.sapMTableTBody').height());
                        }, 0)
                    }
                })($oPCalJs)
JS;
    }
    
    /**
     *
     * @param string $oControlEventJsVar
     * @param string $oParamsJs
     * @param string $keepPagePosJsVar
     * @return string
     */
    protected function buildJsDataLoaderParams(string $oControlEventJsVar = 'oControlEvent', string $oParamsJs = 'params', $keepPagePosJsVar = 'bKeepPagingPos') : string
    {
        // Don't call the parent here as we don't want "regular" pagination. 
        $js = '';
        
        // If we are paging, page via start/end dates of the currently visible timeline
        if ($this->getWidget()->isPaged()) {
            $dateFormat = DateTimeDataType::DATETIME_ICU_FORMAT_INTERNAL;
            return <<<JS
        
            var oPCal = sap.ui.getCore().byId('{$this->getId()}');
            var oSchedulerModel = oPCal.getModel().getProperty('/_scheduler');
            var oStartDate = oPCal.getStartDate();
            var oEndDate = oPCal.getEndDate !== undefined ? oPCal.getEndDate() : oPCal._getFirstAndLastRangeDate().oEndDate.oDate;
            if (oSchedulerModel !== undefined && oPCal.getViewKey() !== 'All') {
                if ($oParamsJs.data.filters === undefined) {
                    $oParamsJs.data.filters = {operator: "AND", conditions: []};
                }
                $oParamsJs.data.filters.conditions.push({
                    expression: '{$this->getWidget()->getItemsConfig()->getStartTimeColumn()->getDataColumnName()}',
                    comparator: '>=',
                    value: exfTools.date.format(oStartDate, '$dateFormat')
                });
                $oParamsJs.data.filters.conditions.push({
                    expression: '{$this->getWidget()->getItemsConfig()->getStartTimeColumn()->getDataColumnName()}',
                    comparator: '<=',
                    value: exfTools.date.format(oEndDate, '$dateFormat')
                });
            }
            
JS;
        }
        return $js;
    }
    
    protected function buildJsValueBindingForWidget(WidgetInterface $tplWidget, string $modelName = null) : string
    {
        $tpl = $this->getFacade()->getElement($tplWidget);
        // Disable using widget id as control id because this is a template for multiple controls
        $tpl->setUseWidgetId(false);
        
        $modelPrefix = $modelName ? $modelName . '>' : '';
        if ($tpl instanceof UI5Display) {
            $tpl->setValueBindingPrefix($modelPrefix);
        } elseif ($tpl instanceof UI5ValueBindingInterface) {
            $tpl->setValueBindingPrefix($modelPrefix);
        }
        
        return $tpl->buildJsValueBinding();
    }
    
    /**
     * 
     * @see UI5DataElementTrait::buildJsGetRowsSelected()
     */
    protected function buildJsGetRowsSelected(string $oCalJs) : string
    {
        return <<<JS
        function(){
            var aApts = $oCalJs.getSelectedAppointments(),
                sUid,
                rows = [],
                data = sap.ui.getCore().byId('{$this->getId()}').getModel().getData().rows;
    
            for (var i in aApts) {
                var sUid = sap.ui.getCore().byId(aApts[i]).getKey();
                for (var j in data) {
                    if (data[j]['{$this->getWidget()->getMetaObject()->getUidAttributeAlias()}'] == sUid) {
                        rows.push(data[j]);
                    }
                }
            }
            return rows;
        }()

JS;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see UI5DataElementTrait::buildJsClickHandlerLeftClick()
     */
    protected function buildJsClickHandlerLeftClick($oControllerJsVar = 'oController') : string
    {
        // Single click. Currently only supports one click action - the first one in the list of buttons
        if ($leftclick_button = $this->getWidget()->getButtonsBoundToMouseAction(EXF_MOUSE_ACTION_LEFT_CLICK)[0]) {
            return <<<JS
            
            .attachAppointmentSelect(function(oEvent) {
                {$this->getFacade()->getElement($leftclick_button)->buildJsClickEventHandlerCall($oControllerJsVar)};
            })
JS;
        }
        
        return '';
    }
    
    /**
     * {@inheritdoc}
     * @see UI5DataElementTrait::isEditable()
     */
    protected function isEditable()
    {
        return false;
    }
    
    /**
     * {@inheritdoc}
     * @see UI5DataElementTrait::hasPaginator()
     */
    protected function hasPaginator() : bool
    {
        return false;
    }
    
    public function buildJsValueGetter($dataColumnName = null, $rowNr = null)
    {
        if ($dataColumnName !== null) {
            $dateFormat = DateTimeDataType::DATETIME_ICU_FORMAT_INTERNAL;
            if (mb_strtolower($dataColumnName) === '~start_date') {
                return "exfTools.date.format(sap.ui.getCore().byId('{$this->getId()}').getStartDate(), '$dateFormat')";
            }
            if (mb_strtolower($dataColumnName) === '~end_date') {
                return "exfTools.date.format(function(oPCal){return oPCal.getEndDate !== undefined ? oPCal.getEndDate() : oPCal._getFirstAndLastRangeDate().oEndDate.oDate}(sap.ui.getCore().byId('{$this->getId()}')), '$dateFormat')";
            }            
            if (mb_strtolower($dataColumnName) === '~resources_title') {
                $col = $this->getWidget()->getResourcesConfig()->getTitleColumn();
                $delim = $col && $col->isBoundToAttribute() ? $col->getAttribute()->getValueListDelimiter() : EXF_LIST_SEPARATOR;
                return <<<JS
                
(function(){
    var oPCal = sap.ui.getCore().byId('{$this->getId()}');
    var aSelectedRows = oPCal.getSelectedRows();
    var aTitles = [];
    for (var i = 0; i < aSelectedRows.length; i++) {
        if (aSelectedRows[i].getTitle() !== '' && aSelectedRows[i].getTitle() !== undefined) {
            aTitles.push(aSelectedRows[i].getTitle());
        }    
    }
    return aTitles.join('{$delim}');
}() || '')

JS;
            }
            
        }
        return $this->buildJsValueGetterViaTrait($dataColumnName, $rowNr);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::addOnChangeScript()
     */
    public function addOnChangeScript($js)
    {
        if (strpos($js, $this->buildJsValueGetter('~start_date')) !== false || strpos($js, $this->buildJsValueGetter('~end_date')) !== false) {
            $this->getController()->addOnEventScript($this, self::EVENT_NAME_TIMELINE_SHIFT, $js);
            return $this;
        }
        if (strpos($js, $this->buildJsValueGetter('~resources_title')) !== false) {
            $this->getController()->addOnEventScript($this, SELF::EVENT_NAME_ROW_SELECTION_CHANGE, $js);
            return $this;
        }
        return parent::addOnChangeScript($js);
    }
    
    /**
     * @see UI5DataElementTrait::buildJsSelectRowByIndex()
     * @param string $oTableJs
     * @param string $iRowIdxJs
     * @param bool $deSelect
     * @param string $bScrollToJs
     * @return string
     */
    public function buildJsSelectRowByIndex(string $oTableJs = 'oTable', string $iRowIdxJs = 'iRowIdx', bool $deSelect = false, string $bScrollToJs = 'true') : string
    {
        if ($deSelect) {
            $js = "if (oItem.getBindingContext().getProperty('_dataRowIdx') == iRowIdx) { oItem.setSelected(false); }";
        } else {
            $js = "oItem.setSelected((oItem.getBindingContext().getProperty('_dataRowIdx') == iRowIdx ? true : false));";
        }
        
        return <<<JS
        (function(oTable, iRowIdx, bScrollTo){
            oTable.getRows().forEach(function(oCalRow){
                oCalRow.getAppointments().forEach(function(oItem){
                    $js
                });
            });
        })($oTableJs, $iRowIdxJs, $bScrollToJs)

JS;
    }
    
    /**
     * @see UI5DataElementTrait::buildJsClickGetRowIndex()
     * @param string $oDomElementClickedJs
     * @return string
     */
    protected function buildJsClickGetRowIndex(string $oDomElementClickedJs) : string
    {
        return <<<JS
        (function(domClicked){
            var sCalDomId = $(domClicked).parents('div.sapUiCalendarApp').data('sap-ui');
            var oCalItem = sap.ui.getCore().byId(sCalDomId);
            if (oCalItem === undefined) return -1;
            return oCalItem.getBindingContext().getProperty('_dataRowIdx');
        })($oDomElementClickedJs)
JS;
        return "sap.ui.getCore().byId($({$oDomElementClickedJs}).parents('div.sapUiCalendarApp').data('data-sap-ui')).getKey()";
    }
}