<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Widgets\Scheduler;
use exface\UI5Facade\Facades\Elements\Traits\UI5DataElementTrait;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\UI5Facade\Facades\Interfaces\UI5ValueBindingInterface;
use exface\Core\Widgets\Parts\DataTimeline;

/**
 * 
 * @method Scheduler getWidget()
 * 
 * @author Andrej Kabachnik
 *
 */
class UI5Scheduler extends UI5AbstractElement
{
    use UI5DataElementTrait {
        buildJsDataLoaderOnLoaded as buildJsDataLoaderOnLoadedViaTrait;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsConstructor()
     */
    public function buildJsConstructorForControl($oControllerJs = 'oController') : string
    {
        $controller = $this->getController();
        $this->initConfiguratorControl($controller);
        
        $showRowHeaders = $this->getWidget()->hasResources() ? 'true' : 'false';
        switch ($this->getWidget()->getTimelineConfig()->getGranularity(DataTimeline::GRANULARITY_HOUR)) {
            case DataTimeline::GRANULARITY_DAY: $viewKey = 'sap.ui.unified.CalendarIntervalType.Day'; break;
            case DataTimeline::GRANULARITY_HOUR: $viewKey = 'sap.ui.unified.CalendarIntervalType.Hour'; break;
            case DataTimeline::GRANULARITY_WEEK: $viewKey = 'sap.ui.unified.CalendarIntervalType.Week'; break;
            case DataTimeline::GRANULARITY_MONTH: $viewKey = 'sap.ui.unified.CalendarIntervalType.Month'; break;
            default: $viewKey = 'sap.ui.unified.CalendarIntervalType.Hour'; break;
        }
        
        return <<<JS

new sap.m.PlanningCalendar("{$this->getId()}", {
	startDate: "{/_scheduler/startDate}",
	appointmentsVisualization: "Filled",
    viewKey: $viewKey,
	showRowHeaders: {$showRowHeaders},
    showEmptyIntervalHeaders: false,
	showWeekNumbers: true,
    appointmentSelect: {$controller->buildJsEventHandler($this, self::EVENT_NAME_CHANGE, true)},
	toolbarContent: [
		{$this->buildJsToolbarContent($oControllerJs)}
	],
	rows: {
		path: '/_scheduler/rows',
        template: {$this->buildJsRowsConstructors()}
	}
})
{$this->buildJsClickHandlers($oControllerJs)}

JS;
    }
		
    protected function buildJsRowsConstructors() : string
    {
        $widget = $this->getWidget();
        
        $calItem = $widget->getItemsConfig();
        $subtitleBinding = $calItem->hasSubtitle() ? $this->buildJsValueBindingForWidget($calItem->getSubtitleColumn()->getCellWidget()) : '""';
        
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
					icon: "{pic}",
					title: {$this->buildJsValueBindingForWidget($calItem->getTitleColumn()->getCellWidget())},
					text: {$subtitleBinding},
					key: "{{$this->getMetaObject()->getUidAttributeAlias()}}",
					type: "{type}",
					tentative: "{tentative}",
				})
            },
			intervalHeaders: {
                path: '_scheduler/headers', 
                templateShareable: true,
                template: new sap.ui.unified.CalendarAppointment({
					startDate: "{start}",
					endDate: "{end}",
					icon: "{pic}",
					title: {$this->buildJsValueBindingForWidget($calItem->getTitleColumn()->getCellWidget())},
					text: {$subtitleBinding},
					type: "{type}",
				})
            },
		})

JS;
    }
        
    protected function buildJsDataResetter() : string
    {
        // TODO
        return '';
    }
    
    protected function buildJsDataLoaderOnLoaded(string $oModelJs = 'oModel') : string
    {
        $widget = $this->getWidget();
        $calItem = $widget->getItemsConfig();
        
        $endTime = $calItem->hasEndTime() ? "oDataRow['{$calItem->getEndTimeColumn()->getDataColumnName()}']" : "''";
        $subtitle = $calItem->hasSubtitle() ? "{$calItem->getSubtitleColumn()->getDataColumnName()}: oDataRow['{$calItem->getSubtitleColumn()->getDataColumnName()}']," : '';
        
        if ($widget->hasUidColumn()) {
            $uid = "{$widget->getUidColumn()->getDataColumnName()}: oDataRow['{$widget->getUidColumn()->getDataColumnName()}'],";
        }
        
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
            var dMin, dStart, dEnd, sEnd, oDataRow, sRowKey;
            for (var i in aData) {
                oDataRow = aData[i];

                sRowKey = {$rowKeyGetter};
                if (oRows[sRowKey] === undefined) {
                    oRows[sRowKey] = {
                        {$rowProps}
                        items: [],
                        headers: []
                    };
                }

                dStart = new Date(oDataRow["{$calItem->getStartTimeColumn()->getDataColumnName()}"]);
                if (dMin === undefined) {
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
                oRows[sRowKey].items.push({
                    _start: dStart,
                    _end: dEnd,
                    {$calItem->getTitleColumn()->getDataColumnName()}: oDataRow["{$calItem->getTitleColumn()->getDataColumnName()}"],
                    {$uid}
                    {$subtitle}
                });
            }
            {$oModelJs}.setProperty('/_scheduler', {
                startDate: dMin,
                rows: Object.values(oRows),
            });
			
JS;
    }
    
    /**
     *
     * @return bool
     */
    protected function hasQuickSearch() : bool
    {
        return true;
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
     * @see UI5DataElementTrait::buildJsGetSelectedRows()
     */
    protected function buildJsGetSelectedRows(string $oCalJs) : string
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
}