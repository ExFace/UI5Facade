<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Widgets\Scheduler;
use exface\UI5Facade\Facades\Elements\Traits\UI5DataElementTrait;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\UI5Facade\Facades\Interfaces\UI5ValueBindingInterface;

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
        
        return <<<JS

new sap.m.PlanningCalendar("{$this->getId()}", {
	startDate: "{/_scheduler/startDate}",
	appointmentsVisualization: "Filled",
	showRowHeaders: {$showRowHeaders},
    showEmptyIntervalHeaders: false,
	showWeekNumbers: true,
	toolbarContent: [
		{$this->buildJsToolbarContent($oControllerJs)}
	],
	rows: {
		path: '/_scheduler/rows',
        template: {$this->buildJsRowsConstructors()}
	}
})
{$this->buildJsClickListeners($oControllerJs)}

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
                    {$widget->getMetaObject()->getUidAttributeAlias()}: oDataRow["{$widget->getMetaObject()->getUidAttributeAlias()}"],
                    {$calItem->getTitleColumn()->getDataColumnName()}: oDataRow["{$calItem->getTitleColumn()->getDataColumnName()}"],
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
    
    public function buildJsDataGetter(ActionInterface $action = null)
    {
        if ($action === null) {
            $getRows = "var rows = sap.ui.getCore().byId('{$this->getId()}').getModel().getData().rows;";
        } elseif ($action instanceof iReadData) {
            // If we are reading, than we need the special data from the configurator
            // widget: filters, sorters, etc.
            return $this->getFacade()->getElement($this->getWidget()->getConfiguratorWidget())->buildJsDataGetter($action);
        } else {
            $getRows = <<<JS

        var aApts = oCal.getSelectedAppointments(),
            sUid,
            rows = [],
            data = sap.ui.getCore().byId('{$this->getId()}').getModel().getData().rows;
console.log(aApts);
        for (var i in aApts) {
            var sUid = sap.ui.getCore().byId(aApts[i]).getKey();
            for (var j in data) {
                if (data[j]['{$this->getWidget()->getMetaObject()->getUidAttributeAlias()}'] == sUid) {
                    rows.push(data[j]);
                }
            }
        }

JS;
        }
        return <<<JS
    function() {
        var oCal = sap.ui.getCore().byId('{$this->getId()}');
        {$getRows}
        return {
            oId: '{$this->getWidget()->getMetaObject()->getId()}',
            rows: (rows === undefined ? [] : rows)
        };
    }()
JS;
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
        		{$this->getFacade()->getElement($dblclick_button)->buildJsClickEventHandlerCall($oControllerJsVar)};
            })
JS;
        }
        
        // Right click. Currently only supports one double click action - the first one in the list of buttons
        if ($rightclick_button = $widget->getButtonsBoundToMouseAction(EXF_MOUSE_ACTION_RIGHT_CLICK)[0]) {
            $rightclick_script = $this->getFacade()->getElement($rightclick_button)->buildJsClickEventHandlerCall($oControllerJsVar);
        } else {
            //$rightclick_script = $this->buildJsContextMenuTrigger();
        }
        
        if ($rightclick_script) {
            $js .= <<<JS
            
            .attachBrowserEvent("contextmenu", function(oEvent) {
                oEvent.preventDefault();
                {$rightclick_script}
        	})
        	
JS;
        }
        
        // Single click. Currently only supports one click action - the first one in the list of buttons
        if ($leftclick_button = $widget->getButtonsBoundToMouseAction(EXF_MOUSE_ACTION_LEFT_CLICK)[0]) {
            $js .= <<<JS
                
            .attachAppointmentSelect(function(oEvent) {
                {$this->getFacade()->getElement($leftclick_button)->buildJsClickEventHandlerCall($oControllerJsVar)};
            })
JS;
        }
        
        return $js;
    }
    
    /**
     * {inheritdoc}
     * @see UI5DataElementTrait::isEditable()
     */
    protected function isEditable()
    {
        return false;
    }
}