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
use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;

/**
 * 
 * @method \exface\Core\Widgets\Scheduler getWidget()
 * 
 * @author Andrej Kabachnik
 *
 */
class UI5Gantt extends UI5AbstractElement
{
    use UI5DataElementTrait {
        buildJsDataLoaderOnLoaded as buildJsDataLoaderOnLoadedViaTrait;
        buildJsValueGetter as buildJsValueGetterViaTrait;
        buildJsDataResetter as buildJsDataResetterViaTrait;
    }
    
    use JsValueScaleTrait;

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
        
        $showRowHeaders = $this->getWidget()->hasResources() ? 'true' : 'false';
        switch ($this->getWidget()->getTimelineConfig()->getGranularity(DataTimeline::GRANULARITY_HOURS)) {
            case DataTimeline::GRANULARITY_HOURS: $viewKey = 'sap.ui.unified.CalendarIntervalType.Hour'; break;
            case DataTimeline::GRANULARITY_DAYS: $viewKey = 'sap.ui.unified.CalendarIntervalType.Day'; break;
            case DataTimeline::GRANULARITY_DAYS_PER_WEEK: $viewKey = 'sap.ui.unified.CalendarIntervalType.Week'; break;
            case DataTimeline::GRANULARITY_DAYS_PER_MONTH: $viewKey = 'sap.ui.unified.CalendarIntervalType.OneMonth'; break;
            case DataTimeline::GRANULARITY_MONTHS: $viewKey = 'sap.ui.unified.CalendarIntervalType.Month'; break;
            case DataTimeline::GRANULARITY_WEEKS: throw new FacadeUnsupportedWidgetPropertyWarning('Timeline granularity `weeks` currently not supported in UI5!'); break;
            default: $viewKey = 'sap.ui.unified.CalendarIntervalType.Hour'; break;
        }
        
        $templatesJS = $this->escapeString($this->buildHtmlGanttTemplates());
        return <<<JS

                new sap.ui.core.HTML("{$this->getId()}", {
                    content: "<div id=\"{$this->getId()}\" style=\"height: 100%;\"><div id=\"{$this->getId()}_gantt\" class=\"exf-gantt\" style=\"height:100%; min-height: 100px; overflow: hidden;\"></div>" + {$templatesJS} + "</div>",
                    afterRendering: function(oEvent) {
                        {$this->buildJsGanttInit()}
                        
                        setTimeout(function(){
                            console.log('resize');
                        }, 0);
                        sap.ui.core.ResizeHandler.register(sap.ui.getCore().byId('{$this->getId()}').getParent(), function(){
                            console.log('resize');
                        });
                    }
                })
                {$this->buildJsClickHandlers($oControllerJs)}

JS;
    }
    
    protected function buildJsGanttInit() : string
    {
        return <<<JS

var ge;
$(function() {
  var canWrite=true; //this is the default for test purposes

  // here starts gantt initialization
  ge = new GanttMaster();
  ge.set100OnClose=true;

  ge.shrinkParent=true;

  ge.init($("#workSpace"));
  loadI18n(); //overwrite with localized ones

  //in order to force compute the best-fitting zoom level
  delete ge.gantt.zoom;

  var project=loadFromLocalStorage();

  if (!project.canWrite)
    $(".ganttButtonBar button.requireWrite").attr("disabled","true");

  ge.loadProject(project);
  ge.checkpoint(); //empty the undo stack

  initializeHistoryManagement(ge.tasks[0].id);
});

JS;
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

            if (dMin !== undefined && ! sap.ui.getCore().byId('{$this->getId()}').data('_exfStartDate') && {$oModelJs}.getProperty('/_scheduler') === undefined) {
                sap.ui.getCore().byId('{$this->getId()}').data('_exfStartDate', dMin).setStartDate(dMin);
            }
            {$oModelJs}.setProperty('/_scheduler', {
                rows: Object.values(oRows),
            });

            setTimeout(function(){
                {$this->getController()->buildJsEventHandler($this, UI5Gantt::EVENT_NAME_TIMELINE_SHIFT, false)}
            }, 0);
            // fire selection change as selected rows are reseted
            setTimeout(function(){                
                sap.ui.getCore().byId('{$this->getId()}').fireRowSelectionChange();
            }, 0);
			
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
            if (oSchedulerModel !== undefined) {
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
            $this->getController()->addOnEventScript($this, UI5Gantt::EVENT_NAME_TIMELINE_SHIFT, $js);
            return $this;
        }
        if (strpos($js, $this->buildJsValueGetter('~resources_title')) !== false) {
            $this->getController()->addOnEventScript($this, UI5Gantt::EVENT_NAME_ROW_SELECTION_CHANGE, $js);
            return $this;
        }
        return parent::addOnChangeScript($js);
    }
    
    protected function buildHtmlGanttTemplates() : string
    {
        return <<<HTML

<div id="gantEditorTemplates" style="display:none;">
<div class="__template__" type="GANTBUTTONS">
  <!--
  <div class="ganttButtonBar noprint">
    <div class="buttons">
      <a href="https://gantt.twproject.com/"><img src="res/twGanttLogo.png" alt="Twproject" align="absmiddle" style="max-width: 136px; padding-right: 15px"></a>

      <button onclick="$('#workSpace').trigger('undo.gantt');return false;" class="button textual icon requireCanWrite" title="undo"><span class="teamworkIcon">&#39;</span></button>
      <button onclick="$('#workSpace').trigger('redo.gantt');return false;" class="button textual icon requireCanWrite" title="redo"><span class="teamworkIcon">&middot;</span></button>
      <span class="ganttButtonSeparator requireCanWrite requireCanAdd"></span>
      <button onclick="$('#workSpace').trigger('addAboveCurrentTask.gantt');return false;" class="button textual icon requireCanWrite requireCanAdd" title="insert above"><span class="teamworkIcon">l</span></button>
      <button onclick="$('#workSpace').trigger('addBelowCurrentTask.gantt');return false;" class="button textual icon requireCanWrite requireCanAdd" title="insert below"><span class="teamworkIcon">X</span></button>
      <span class="ganttButtonSeparator requireCanWrite requireCanInOutdent"></span>
      <button onclick="$('#workSpace').trigger('outdentCurrentTask.gantt');return false;" class="button textual icon requireCanWrite requireCanInOutdent" title="un-indent task"><span class="teamworkIcon">.</span></button>
      <button onclick="$('#workSpace').trigger('indentCurrentTask.gantt');return false;" class="button textual icon requireCanWrite requireCanInOutdent" title="indent task"><span class="teamworkIcon">:</span></button>
      <span class="ganttButtonSeparator requireCanWrite requireCanMoveUpDown"></span>
      <button onclick="$('#workSpace').trigger('moveUpCurrentTask.gantt');return false;" class="button textual icon requireCanWrite requireCanMoveUpDown" title="move up"><span class="teamworkIcon">k</span></button>
      <button onclick="$('#workSpace').trigger('moveDownCurrentTask.gantt');return false;" class="button textual icon requireCanWrite requireCanMoveUpDown" title="move down"><span class="teamworkIcon">j</span></button>
      <span class="ganttButtonSeparator requireCanWrite requireCanDelete"></span>
      <button onclick="$('#workSpace').trigger('deleteFocused.gantt');return false;" class="button textual icon delete requireCanWrite" title="Elimina"><span class="teamworkIcon">&cent;</span></button>
      <span class="ganttButtonSeparator"></span>
      <button onclick="$('#workSpace').trigger('expandAll.gantt');return false;" class="button textual icon " title="EXPAND_ALL"><span class="teamworkIcon">6</span></button>
      <button onclick="$('#workSpace').trigger('collapseAll.gantt'); return false;" class="button textual icon " title="COLLAPSE_ALL"><span class="teamworkIcon">5</span></button>

    <span class="ganttButtonSeparator"></span>
      <button onclick="$('#workSpace').trigger('zoomMinus.gantt'); return false;" class="button textual icon " title="zoom out"><span class="teamworkIcon">)</span></button>
      <button onclick="$('#workSpace').trigger('zoomPlus.gantt');return false;" class="button textual icon " title="zoom in"><span class="teamworkIcon">(</span></button>
    <span class="ganttButtonSeparator"></span>
      <button onclick="$('#workSpace').trigger('print.gantt');return false;" class="button textual icon " title="Print"><span class="teamworkIcon">p</span></button>
    <span class="ganttButtonSeparator"></span>
      <button onclick="ge.gantt.showCriticalPath=!ge.gantt.showCriticalPath; ge.redraw();return false;" class="button textual icon requireCanSeeCriticalPath" title="CRITICAL_PATH"><span class="teamworkIcon">&pound;</span></button>
    <span class="ganttButtonSeparator requireCanSeeCriticalPath"></span>
      <button onclick="ge.splitter.resize(.1);return false;" class="button textual icon" ><span class="teamworkIcon">F</span></button>
      <button onclick="ge.splitter.resize(50);return false;" class="button textual icon" ><span class="teamworkIcon">O</span></button>
      <button onclick="ge.splitter.resize(100);return false;" class="button textual icon"><span class="teamworkIcon">R</span></button>
      <span class="ganttButtonSeparator"></span>
      <button onclick="$('#workSpace').trigger('fullScreen.gantt');return false;" class="button textual icon" title="FULLSCREEN" id="fullscrbtn"><span class="teamworkIcon">@</span></button>
      <button onclick="ge.element.toggleClass('colorByStatus' );return false;" class="button textual icon"><span class="teamworkIcon">&sect;</span></button>

    <button onclick="editResources();" class="button textual requireWrite" title="edit resources"><span class="teamworkIcon">M</span></button>
      &nbsp; &nbsp; &nbsp; &nbsp;
    </div>

    <div>
      <button onclick="saveGanttOnServer();" class="button first big requireWrite" title="Save">Save</button>
      <input type="file" name="load-file" id="load-file">
      <label for="load-file">Load</label>
      <button onclick='newProject();' class='button requireWrite newproject'><em>clear project</em></button>
      <button class="button login" title="login/enroll" onclick="loginEnroll($(this));" style="display:none;">login/enroll</button>
      <button class="button opt collab" title="Start with Twproject" onclick="collaborate($(this));" style="display:none;"><em>collaborate</em></button>
    </div>
  </div>
  -->
</div>

<div class="__template__" type="TASKSEDITHEAD"><!--
  <table class="gdfTable" cellspacing="0" cellpadding="0">
    <thead>
    <tr style="height:40px">
      <th class="gdfColHeader" style="width:35px; border-right: none"></th>
      <th class="gdfColHeader" style="width:25px;"></th>
      <th class="gdfColHeader gdfResizable" style="width:300px;">name</th>
      <th class="gdfColHeader"  align="center" style="width:17px;" title="Start date is a milestone."><span class="teamworkIcon" style="font-size: 8px;">^</span></th>
      <th class="gdfColHeader gdfResizable" style="width:80px;">start</th>
      <th class="gdfColHeader"  align="center" style="width:17px;" title="End date is a milestone."><span class="teamworkIcon" style="font-size: 8px;">^</span></th>
      <th class="gdfColHeader gdfResizable" style="width:80px;">End</th>
      <th class="gdfColHeader gdfResizable" style="width:50px;">dur.</th>
      <th class="gdfColHeader gdfResizable" style="width:20px;">%</th>
      <th class="gdfColHeader gdfResizable requireCanSeeDep" style="width:50px;">depe.</th>
      <th class="gdfColHeader gdfResizable" style="width:1000px; text-align: left; padding-left: 10px;">assignees</th>
    </tr>
    </thead>
  </table>
  --></div>

<div class="__template__" type="TASKROW"><!--
  <tr id="tid_(#=obj.id#)" taskId="(#=obj.id#)" class="taskEditRow (#=obj.isParent()?'isParent':''#) (#=obj.collapsed?'collapsed':''#)" level="(#=level#)">
    <th class="gdfCell edit" align="right" style="cursor:pointer;"><span class="taskRowIndex">(#=obj.getRow()+1#)</span> <span class="teamworkIcon" style="font-size:12px;" >e</span></th>
    <td class="gdfCell noClip" align="center"><div class="taskStatus cvcColorSquare" status="(#=obj.status#)"></div></td>
    <td class="gdfCell indentCell" style="padding-left:(#=obj.level*10+18#)px;">
      <div class="exp-controller" align="center"></div>
      <input type="text" name="name" value="(#=obj.name#)" placeholder="name">
    </td>
    <td class="gdfCell" align="center"><input type="checkbox" name="startIsMilestone"></td>
    <td class="gdfCell"><input type="text" name="start"  value="" class="date"></td>
    <td class="gdfCell" align="center"><input type="checkbox" name="endIsMilestone"></td>
    <td class="gdfCell"><input type="text" name="end" value="" class="date"></td>
    <td class="gdfCell"><input type="text" name="duration" autocomplete="off" value="(#=obj.duration#)"></td>
    <td class="gdfCell"><input type="text" name="progress" class="validated" entrytype="PERCENTILE" autocomplete="off" value="(#=obj.progress?obj.progress:''#)" (#=obj.progressByWorklog?"readOnly":""#)></td>
    <td class="gdfCell requireCanSeeDep"><input type="text" name="depends" autocomplete="off" value="(#=obj.depends#)" (#=obj.hasExternalDep?"readonly":""#)></td>
    <td class="gdfCell taskAssigs">(#=obj.getAssigsString()#)</td>
  </tr>
  --></div>

<div class="__template__" type="TASKEMPTYROW"><!--
  <tr class="taskEditRow emptyRow" >
    <th class="gdfCell" align="right"></th>
    <td class="gdfCell noClip" align="center"></td>
    <td class="gdfCell"></td>
    <td class="gdfCell"></td>
    <td class="gdfCell"></td>
    <td class="gdfCell"></td>
    <td class="gdfCell"></td>
    <td class="gdfCell"></td>
    <td class="gdfCell"></td>
    <td class="gdfCell requireCanSeeDep"></td>
    <td class="gdfCell"></td>
  </tr>
  --></div>

<div class="__template__" type="TASKBAR"><!--
  <div class="taskBox taskBoxDiv" taskId="(#=obj.id#)" >
    <div class="layout (#=obj.hasExternalDep?'extDep':''#)">
      <div class="taskStatus" status="(#=obj.status#)"></div>
      <div class="taskProgress" style="width:(#=obj.progress>100?100:obj.progress#)%; background-color:(#=obj.progress>100?'red':'rgb(153,255,51);'#);"></div>
      <div class="milestone (#=obj.startIsMilestone?'active':''#)" ></div>

      <div class="taskLabel"></div>
      <div class="milestone end (#=obj.endIsMilestone?'active':''#)" ></div>
    </div>
  </div>
  --></div>


<div class="__template__" type="CHANGE_STATUS"><!--
    <div class="taskStatusBox">
    <div class="taskStatus cvcColorSquare" status="STATUS_ACTIVE" title="Active"></div>
    <div class="taskStatus cvcColorSquare" status="STATUS_DONE" title="Completed"></div>
    <div class="taskStatus cvcColorSquare" status="STATUS_FAILED" title="Failed"></div>
    <div class="taskStatus cvcColorSquare" status="STATUS_SUSPENDED" title="Suspended"></div>
    <div class="taskStatus cvcColorSquare" status="STATUS_WAITING" title="Waiting" style="display: none;"></div>
    <div class="taskStatus cvcColorSquare" status="STATUS_UNDEFINED" title="Undefined"></div>
    </div>
  --></div>


</div>

HTML;
    }
    
    public function registerExternalModules(UI5ControllerInterface $controller) : UI5AbstractElement
    {
        $f = $this->getFacade();

        $controller->addExternalCss('vendor/exface/UI5Facade/Facades/js/jquery-gantt/platform.css');
        $controller->addExternalCss('vendor/exface/UI5Facade/Facades/js/jquery-gantt/libs/jquery/dateField/jquery.dateField.css');
        $controller->addExternalCss('vendor/exface/UI5Facade/Facades/js/jquery-gantt/gantt.css');
        
        $controller->addExternalModule('libs.exface.gantt.jqueryui',    'vendor/exface/UI5Facade/Facades/js/jquery-gantt/libs/jquery/jquery-ui.min.js', null);
        $controller->addExternalModule('libs.exface.gantt.livequery',   'vendor/exface/UI5Facade/Facades/js/jquery-gantt/libs/jquery/jquery.livequery.1.1.1.min.js', null);
        $controller->addExternalModule('libs.exface.gantt.timers',      'vendor/exface/UI5Facade/Facades/js/jquery-gantt/libs/jquery/jquery.timers.js', null);
        $controller->addExternalModule('libs.exface.gantt.utilities',   'vendor/exface/UI5Facade/Facades/js/jquery-gantt/libs/utilities.js', null);
        $controller->addExternalModule('libs.exface.gantt.forms',       'vendor/exface/UI5Facade/Facades/js/jquery-gantt/libs/forms.js', null);
        $controller->addExternalModule('libs.exface.gantt.date',        'vendor/exface/UI5Facade/Facades/js/jquery-gantt/libs/date.js', null);
        $controller->addExternalModule('libs.exface.gantt.dialot',      'vendor/exface/UI5Facade/Facades/js/jquery-gantt/libs/dialogs.js', null);
        $controller->addExternalModule('libs.exface.gantt.layout',      'vendor/exface/UI5Facade/Facades/js/jquery-gantt/libs/layout.js', null);
        $controller->addExternalModule('libs.exface.gantt.i18',         'vendor/exface/UI5Facade/Facades/js/jquery-gantt/libs/i18nJs.js', null);
        $controller->addExternalModule('libs.exface.gantt.dateField',   'vendor/exface/UI5Facade/Facades/js/jquery-gantt/libs/jquery/dateField/jquery.dateField.js', null);
        $controller->addExternalModule('libs.exface.gantt.JST',         'vendor/exface/UI5Facade/Facades/js/jquery-gantt/libs/jquery/JST/jquery.JST.js', null);
        $controller->addExternalModule('libs.exface.gantt.slider',      'vendor/exface/UI5Facade/Facades/js/jquery-gantt/libs/jquery/valueSlider/jquery.mb.slider.js', null);
        $controller->addExternalModule('libs.exface.gantt.svg',         'vendor/exface/UI5Facade/Facades/js/jquery-gantt/libs/jquery/svg/jquery.svg.min.js', null);
        $controller->addExternalModule('libs.exface.gantt.svgdom',      'vendor/exface/UI5Facade/Facades/js/jquery-gantt/libs/jquery/svg/jquery.svgdom.1.8.js', null);
        $controller->addExternalModule('libs.exface.gantt.ganttUtilities', 'vendor/exface/UI5Facade/Facades/js/jquery-gantt/ganttUtilities.js', null);
        $controller->addExternalModule('libs.exface.gantt.ganttTask',   'vendor/exface/UI5Facade/Facades/js/jquery-gantt/ganttTask.js', null);
        $controller->addExternalModule('libs.exface.gantt.ganttDrawerSVG', 'vendor/exface/UI5Facade/Facades/js/jquery-gantt/ganttDrawerSVG.js', null);
        $controller->addExternalModule('libs.exface.gantt.ganttZoom',   'vendor/exface/UI5Facade/Facades/js/jquery-gantt/ganttZoom.js', null);        
        $controller->addExternalModule('libs.exface.gantt.ganttGridEditor', 'vendor/exface/UI5Facade/Facades/js/jquery-gantt/ganttGridEditor.js', null);
        $controller->addExternalModule('libs.exface.gantt.ganttMaster', 'vendor/exface/UI5Facade/Facades/js/jquery-gantt/ganttMaster.js', null);
        $controller->addExternalModule('libs.exface.gantt.gantt',       'vendor/exface/UI5Facade/Facades/js/jquery-gantt/gantt.js', null);
        
        foreach ($this->getWidget()->getColumns() as $col) {
            $f->getElement($col)->registerExternalModules($controller);
        }
        return $this;
    }
}