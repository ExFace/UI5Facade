<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Facades\AbstractAjaxFacade\Elements\EChartsTrait;
use exface\UI5Facade\Facades\Elements\Traits\UI5DataElementTrait;
use exface\Core\Widgets\Data;
use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;
use exface\Core\Factories\ActionFactory;
use exface\Core\Actions\SaveData;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\DataButton;
use exface\UI5Facade\Facades\Interfaces\UI5DataElementInterface;

/**
 * 
 * @method exface\Core\Widgets\Chart getWidget()
 * @method UI5ControllerInterface getController()
 * 
 * @author Andrej Kabachnik
 *
 */
class UI5Chart extends UI5AbstractElement implements UI5DataElementInterface
{
    use EChartsTrait;
    use UI5DataElementTrait {
        buildJsConfiguratorButtonConstructor as buildJsConfiguratorButtonConstructorViaTrait;
        buildJsDataLoaderOnLoaded as buildJsDataLoaderOnLoadedViaTrait;
        UI5DataElementTrait::buildJsRowCompare insteadof EChartsTrait;
        EChartsTrait::buildJsDataResetter insteadof UI5DataElementTrait;
        EChartsTrait::buildJsValueGetter insteadof UI5DataElementTrait;
        EChartsTrait::buildJsDataGetter insteadof UI5DataElementTrait;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsConstructor()
     */
    public function buildJsConstructorForControl($oControllerJs = 'oController') : string
    {
        $this->getFacade()->getElement($this->getWidget()->getConfiguratorWidget())->registerFiltersWithApplyOnChange($this);
        $this->addChartButtons();
        $controller = $this->getController();
        
        $controller->addMethod($this->buildJsDataLoadFunctionName(), $this, '', $this->buildJsDataLoadFunctionBody());
        $controller->addMethod($this->buildJsRedrawFunctionName(), $this, 'oData', $this->buildJsRedrawFunctionBody('oData'));
        $controller->addMethod($this->buildJsSelectFunctionName(), $this, 'oSelection', $this->buildJsSelectFunctionBody('oSelection') . $this->getController()->buildJsEventHandler($this, self::EVENT_NAME_CHANGE, false));
        $controller->addMethod($this->buildJsClicksFunctionName(), $this, 'oParams', $this->buildJsClicksFunctionBody('oParams'));
        $controller->addMethod($this->buildJsSingleClickFunctionName(), $this, 'oParams', $this->buildJsSingleClickFunctionBody('oParams') . $this->getController()->buildJsEventHandler($this, self::EVENT_NAME_CHANGE, false));
        
        $this->registerExternalModules($controller);
        
        $chart = <<<JS

                new sap.ui.core.HTML("{$this->getId()}", {
                    content: "<div id=\"{$this->getId()}\" style=\"height: 100%;\"><div id=\"{$this->getId()}_echarts\" class=\"exf-chart\" style=\"height:100%; min-height: 100px; overflow: hidden;\"></div></div>",
                    afterRendering: function(oEvent) {
                        {$this->buildJsEChartsInit($this->getFacade()->getConfig()->getOption('LIBS.ECHARTS.THEME_NAME'))}
                        {$this->buildJsEventHandlers()}
                        
                        setTimeout(function(){
                            {$this->buildJsEChartsResize()}
                        }, 0);
                        sap.ui.core.ResizeHandler.register(sap.ui.getCore().byId('{$this->getId()}').getParent(), function(){
                            {$this->buildJsEChartsResize()}
                        });
                    }
                })

JS;
                        
        return $this->buildJsPanelWrapper($chart, $oControllerJs);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see exface\Core\Facades\AbstractAjaxFacade\Elements\EChartsTrait::buildJsEChartsInit()
     */
    public function buildJsEChartsInit(string $theme) : string
    {
        return <<<JS
    if (echarts.getInstanceByDom(document.getElementById('{$this->getId()}_echarts')) == undefined) {  
        echarts.init(document.getElementById('{$this->getId()}_echarts'), '{$theme}');
        if (sap.ui.getCore().byId('{$this->getId()}').getModel().getData().rows != undefined) {
            {$this->buildJsRedraw("sap.ui.getCore().byId('{$this->getId()}').getModel().getData().rows")}
        }
    }
    
JS;
    }
    
    protected function buildJsFullscreenContainerGetter() : string
    {
        return "$('#{$this->getId()}').parent().parent()";
    }
    
    /**
     *
     * {@inheritDoc}
     * @see exface\Core\Facades\AbstractAjaxFacade\Elements\EChartsTrait::buildJsEChartsVar()
     */
    protected function buildJsEChartsVar() : string
    {
        
        return "echarts.getInstanceByDom(document.getElementById('{$this->getId()}_echarts'))";
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::registerExternalModules()
     */
    public function registerExternalModules(UI5ControllerInterface $controller) : UI5AbstractElement
    {
        $f = $this->getFacade();
        if ($this->getChartType() === $this->chartTypes['CHART_TYPE_HEATMAP']) {
            $controller->addExternalModule('libs.exface.charts.ECharts', $f->buildUrlToSource('LIBS.ECHARTS.ECHARTSHEATMAP_JS'), null, 'echarts');
        } else {
            $controller->addExternalModule('libs.exface.charts.ECharts', $f->buildUrlToSource('LIBS.ECHARTS.ECHARTS_JS'), null, 'echarts');
        }
        $controller->addExternalModule('libs.exface.charts.Theme', $f->buildUrlToSource('LIBS.ECHARTS.THEME_JS'), null);        
        $controller->addExternalModule('libs.exface.TinyColor', $f->buildUrlToSource('LIBS.TINYCOLOR.JS'), null, 'tinycolor');
        $controller->addExternalModule('libs.exface.TinyGradient', $f->buildUrlToSource('LIBS.TINYGRADIENT.JS'), null, 'tinygradient');
        
        foreach ($this->getWidget()->getData()->getColumns() as $col) {
            $f->getElement($col)->registerExternalModules($controller);
        }
        return $this;
    }
        
    /**
     * 
     * @return array
     */
    protected function getJsIncludes() : array
    {
        $htmlTagsArray = $this->buildHtmlHeadDefaultIncludes();
        $htmlTagsArray[] = '<script type="text/javascript" src="' . $this->getFacade()->buildUrlToSource('LIBS.ECHARTS.THEME_JS') . '"></script>';
        $tags = implode('', $htmlTagsArray);
        $jsTags = [];
        preg_match_all('#<script[^>]*src="([^"]*)"[^>]*></script>#is', $tags, $jsTags);
        return $jsTags[1];
    }
        
    /**
     *
     * {@inheritDoc}
     * @see exface\Core\Facades\AbstractAjaxFacade\Elements\EChartsTrait::buildJsRefresh()
     */
    public function buildJsRefresh(bool $keepPagingPosition = false) : string
    {
        return $this->getController()->buildJsMethodCallFromController($this->buildJsDataLoadFunctionName(), $this, '');
    }
    
    /**
     *
     * {@inheritDoc}
     * @see exface\Core\Facades\AbstractAjaxFacade\Elements\EChartsTrait::buildJsRedraw()
     */
    protected function buildJsRedraw(string $oDataJs) : string
    {
        return $this->getController()->buildJsMethodCallFromController($this->buildJsRedrawFunctionName(), $this, $oDataJs);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see exface\Core\Facades\AbstractAjaxFacade\Elements\EChartsTrait::buildJsSelect()
     */
    protected function buildJsSelect(string $oRowJs = '') : string
    {
        return $this->getController()->buildJsMethodCallFromController($this->buildJsSelectFunctionName(), $this, $oRowJs);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see exface\Core\Facades\AbstractAjaxFacade\Elements\EChartsTrait::buildJsClicks()
     */
    protected function buildJsClicks(string $oParams = '') : string
    {
        return $this->getController()->buildJsMethodCallFromController($this->buildJsClicksFunctionName(), $this, $oParams);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see exface\Core\Facades\AbstractAjaxFacade\Elements\EChartsTrait::buildJsSingleClick()
     */
    protected function buildJsSingleClick(string $oParams = '') : string
    {
        return $this->getController()->buildJsMethodCallFromController($this->buildJsSingleClickFunctionName(), $this, $oParams);
    }
    
    /**
     * function to handle a double click on a chart, when a button is bound to double click
     * 
     * @param string $oControllerJsVar
     * @return string
     */
    protected function buildJsBindToClickHandler($oControllerJsVar = 'oController') : string
    {        
        $widget = $this->getWidget();
        $output = '';
        
        if ($this->isGraphChart() === true) {
            // click actions for graph charts
            // for now you can only call an action when clicking on a node
            if ($dblclick_button = $widget->getButtonsBoundToMouseAction(EXF_MOUSE_ACTION_DOUBLE_CLICK)[0]) {
                $output .= <<<JS
                
            {$this->buildJsEChartsVar()}.on('dblclick', function(params){
                if (params.dataType === 'node') {
                    {$this->buildJsEChartsVar()}._oldSelection = {$this->buildJsGetSelectedRowFunction('params.data')};
                    {$this->getFacade()->getElement($dblclick_button)->buildJsClickEventHandlerCall($oControllerJsVar)}
                }
            });
            
JS;
                    
            }
            /*if ($dblclick_button = $widget->getButtonsBoundToMouseAction(EXF_MOUSE_ACTION_DOUBLE_CLICK)[1]) {
             $output .= <<<JS
             
             {$this->buildJsEChartsVar()}.on('dblclick', {dataType: 'edge'}, function(params){
             {$this->buildJsEChartsVar()}._oldSelection = params.data
             {$this->getFacade()->getElement($dblclick_button)->buildJsClickEventHandlerCall($oControllerJsVar)}
             });
             
             JS;
             
             }*/
            
            if ($rightclick_button = $widget->getButtonsBoundToMouseAction(EXF_MOUSE_ACTION_RIGHT_CLICK)[0]) {
                $output .= <<<JS
                
            {$this->buildJsEChartsVar()}.on('contextmenu', function(params){
                if (params.dataType === 'node') {
                    {$this->buildJsEChartsVar()}._oldSelection = {$this->buildJsGetSelectedRowFunction('params.data')};
                    {$this->getFacade()->getElement($rightclick_button)->buildJsClickEventHandlerCall($oControllerJsVar)}
                    params.event.event.preventDefault();
                }
            });
            
JS;
            }
            
            if ($leftclick_button = $widget->getButtonsBoundToMouseAction(EXF_MOUSE_ACTION_LEFT_CLICK)[0]) {
                $output .= <<<JS
                
            {$this->buildJsEChartsVar()}.on('click', function(params){
                if (params.dataType === 'node') {
                    {$this->buildJsEChartsVar()}._oldSelection = {$this->buildJsGetSelectedRowFunction('params.data')};
                    {$this->getFacade()->getElement($leftclick_button)->buildJsClickEventHandlerCall($oControllerJsVar)}
                }
            });
            
JS;
            }
            
        } else {
            
            // Double click actions for not graph charts
            // Currently only supports one double click action - the first one in the list of buttons
            if ($dblclick_button = $widget->getButtonsBoundToMouseAction(EXF_MOUSE_ACTION_DOUBLE_CLICK)[0]) {
                $output .= <<<JS
                
                {$this->buildJsEChartsVar()}.on('dblclick', function(params){
                    {$this->buildJsEChartsVar()}._oldSelection = {$this->buildJsGetSelectedRowFunction('params.data')};
                    {$this->getFacade()->getElement($dblclick_button)->buildJsClickEventHandlerCall($oControllerJsVar)}
                });
                
JS;
                    
            }
            
            if ($leftclick_button = $widget->getButtonsBoundToMouseAction(EXF_MOUSE_ACTION_LEFT_CLICK)[0]) {
                $output .= <<<JS
                
                {$this->buildJsEChartsVar()}.on('click', function(params){
                    {$this->buildJsEChartsVar()}._oldSelection = {$this->buildJsGetSelectedRowFunction('params.data')};
                    {$this->getFacade()->getElement($leftclick_button)->buildJsClickEventHandlerCall($oControllerJsVar)}
                });
                
JS;
                    
            }
            
            if ($rightclick_button = $widget->getButtonsBoundToMouseAction(EXF_MOUSE_ACTION_RIGHT_CLICK)[0]) {
                $output .= <<<JS
                
                {$this->buildJsEChartsVar()}.on('contextmenu', function(params){
                    {$this->buildJsEChartsVar()}._oldSelection = {$this->buildJsGetSelectedRowFunction('params.data')};
                    {$this->getFacade()->getElement($rightclick_button)->buildJsClickEventHandlerCall($oControllerJsVar)}
                    params.event.event.preventDefault();
                });
                
JS;
                    
            }
        }
        return $output;    
    }
    
    
    
    /**
     * 
     * @return string
     */
    protected function buildJsDataRowsSelector() : string
    {
        return '.data';
    }
    
    /**
     * 
     * {@inheritdoc}
     * @see EChartsTrait::buildJsDataLoadFunctionBody()
     */
    protected function buildJsDataLoadFunctionBody() : string
    {
        // Use the data loader of the UI5DataElementTrait
        return $this->buildJsDataLoader();
    }

    /**
     * 
     * {@inheritdoc}
     * @see UI5DataElementTrait::buildJsDataLoaderOnLoaded()
     */
    protected function buildJsDataLoaderOnLoaded(string $oModelJs = 'oModel') : string
    {
        return $this->buildJsDataLoaderOnLoadedViaTrait($oModelJs) . $this->buildJsRedraw($oModelJs . '.getData().rows');
    }
    
    /**
     * @see UI5DataElementTrait
     */
    protected function hasQuickSearch() : bool
    {
        return false;
    }
    
    /**
     * 
     * @see UI5DataElementTrait
     */
    protected function getDataWidget() : Data
    {
        return $this->getWidget()->getData();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsBusyIconShow()
     */
    public function buildJsBusyIconShow($global = false) : string
    {
        if ($global) {
            return parent::buildJsBusyIconShow($global);
        } else {
            return 'sap.ui.getCore().byId("' . $this->getId() . '").getParent().setBusyIndicatorDelay(0).setBusy(true);';
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsBusyIconHide()
     */
    public function buildJsBusyIconHide($global = false) : string
    {
        if ($global) {
            return parent::buildJsBusyIconShow($global);
        } else {
            return 'sap.ui.getCore().byId("' . $this->getId() . '").getParent().setBusy(false);';
        }
    }
    
    /**
     * {@inheritdoc}
     * @see \exface\UI5Facade\Facades\Elements\Traits\UI5DataElementTrait::buildJsShowMessageOverlay()
     */
    protected function buildJsShowMessageOverlay(string $message) : string
    {
        return $this->buildJsDataResetter() . ';' . $this->buildJsMessageOverlayShow($message);
    }
    
    /**
     *
     * {@inheritdoc}
     * @see UI5DataElementTrait::buildJsOfflineHint()
     */
    protected function buildJsOfflineHint(string $oTableJs = 'oTable') : string
    {
        return $this->buildJsShowMessageOverlay($this->translate('WIDGET.DATATABLE.OFFLINE_HINT'));
    }
    
    /**
     * 
     * @see UI5DataElementTrait::isEditable()
     */
    protected function isEditable()
    {
        return false;
    }
    
    /**
     * 
     * @see UI5DataElementTrait::buildJsGetRowsSelected()
     */
    protected function buildJsGetRowsSelected(string $oControlJs) : string
    {
        return $this->buildJsDataGetter(ActionFactory::createFromString($this->getWorkbench(), SaveData::class)) . '.rows';
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsOnEventScript()
     */
    public function buildJsOnEventScript(string $eventName, string $scriptJs, string $oEventJs) : string
    {
        switch ($eventName) {
            // The UI5DataElementTrait prevents the change event from firing if the currently
            // selected data is the same as the previous selection. This does not work with
            // the EChartsTrait as the previously selected data is updated earlier. Also
            // the EChartsTrait does the change-check by itself.
            case self::EVENT_NAME_CHANGE:
                return <<<JS
                
            // Perform the on-select scripts in any case
            {$this->getController()->buildJsEventHandler($this, 'select', false)}

            {$scriptJs}
            
JS;
            default:
                return parent::buildJsOnEventScript($eventName, $scriptJs, $oEventJs);
        }
    }
    
    /**
     * 
     * @see UI5DataElementTrait::isWrappedInPanel()
     */
    protected function isWrappedInPanel() : bool
    {
        return true;
    }
}
