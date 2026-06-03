<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\Widgets\KPI;
use exface\Core\CommonLogic\Constants\Colors;
use exface\Core\Interfaces\Widgets\iHaveColorScale;

/**
 * Generates sap.m.NumericContent controls for KPI widgets
 * 
 * @method KPI getWidget()
 *
 * @author Andrej Kabachnik
 *        
 */
class UI5KPI extends UI5Display
{
    private $icon = null;
        
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Value::buildJsConstructorForMainControl()
     */
    public function buildJsConstructorForMainControl($oControllerJs = 'oController')
    {
        $this->registerExternalModules($this->getController());
        if ($this->isLazyLoading() === true) {
            $controller = $this->getController();
            $this->setValueBoundToModel(true);
            
            // TODO take the data from the linked widget if configured
            //if ($this->getWidget()->hasDataWidgetLink() === false) {
                // Make sure the control gets a special model to store the lazy loaded data
                $modelInit = ".setModel(new sap.ui.model.json.JSONModel(), '{$this->getModelNameForLazyData()}')";
                // Add the load-data method to the controller
                $controller->addMethod('onLoadData', $this, 'oEvent', $this->buildJsDataLoderFromServer('oEvent'));
                // Call that method every time the view is prefilled
                $controller->addOnPrefillDataChangedScript($controller->buildJsMethodCallFromController('onLoadData', $this, '', $oControllerJs));
                // Call that method every time the view is shown
                $controller->addOnShowViewScript($controller->buildJsMethodCallFromController('onLoadData', $this, '', $oControllerJs), false);
                // Make sure the lazy-model is empty before the view is prefilled. If not done so,
                // the KPI will show old data while the new prefill is being loaded because the
                // KPI's data is not explicitly connected with the prefill data and will not get
                // reset with it.
                $controller->addOnPrefillBeforeLoadScript("sap.ui.getCore().byId('{$this->getId()}').getModel('{$this->getModelNameForLazyData()}').setData({});");
            //}
        }
        return <<<JS
        
                new sap.m.NumericContent("{$this->getId()}", {
                    truncateValueTo: 7, 
                    nullifyValue: false,
                    withMargin: false,
                    {$this->buildJsPropertyIcon()}
                    {$this->buildJsPropertyValue()}
                    {$this->buildJsPropertyValueColor()}
                    {$this->buildJsPropertyScale()}
                })
                {$modelInit}
                {$this->buildJsPseudoEventHandlers()}
                
JS;
    }
                
    protected function buildJsPropertyScale() : string
    {
        $unit = $this->getWidget()->getUnit();
        if ($unit) {
            return 'scale: "' . $unit . '",';
        }
        return '';
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Value::buildJsPropertyWidth()
     */
    protected function buildJsPropertyWidth()
    {
        return '';
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Display::buildJsPropertyWrapping()
     */
    protected function buildJsPropertyWrapping()
    {
        return '';
    }
    
    /**
     *
     * @return string
     */
    public function getIcon() : ?string
    {
        return $this->icon;
    }
    
    /**
     *
     * @param string $value
     * @return UI5TileNumericContent
     */
    public function setIcon(string $value) : UI5TileNumericContent
    {
        $this->icon = $value;
        return $this;
    }
    
    protected function buildJsPropertyIcon() : string
    {
        if ($icon = $this->getIcon()) {
            return 'icon: "' . $this->getIconSrc($icon) . '",';
        }
        
        return '';
    }
    
    protected function buildJsPropertyValue()
    {
        return <<<JS
            value: {$this->buildJsValue()},
JS;
    }
    
    public function getValueBindingPrefix() : string
    {
        return parent::getValueBindingPrefix() === '/' ? $this->getModelNameForLazyData() . '>/rows/0/' : parent::getValueBindingPrefix();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Display::buildJsValueGetterMethod()
     */
    public function buildJsValueGetterMethod()
    {
        return "getValue()";
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Display::buildJsValueSetterMethod()
     */
    public function buildJsValueSetterMethod($value)
    {
        return "setValue({$value})";
    }
    
    protected function isLazyLoading() : bool
    {
        $widget = $this->getWidget();
        return $widget instanceof KPI && $widget->hasData() === true && $widget->getData()->getLazyLoading() === true;
    }
    
    protected function buildJsDataLoderFromServer(string $oEventJs = 'oEvent') : string
    {
        $widget = $this->getWidget();
        $dataWidget = $this->getDataWidget();
        $configuratorElement = $this->getFacade()->getElement($this->getDataWidget()->getConfiguratorWidget());
        
        return <<<JS
        
                {$this->buildJsBusyIconShow()}
                var oControl = sap.ui.getCore().byId("{$this->getId()}");
                var oParams = {
                    action: "{$dataWidget->getLazyLoadingActionAlias()}",
                    resource: "{$this->getPageId()}",
                    element: "{$widget->getData()->getId()}",
                    object: "{$widget->getMetaObject()->getId()}",
                    data: {$configuratorElement->buildJsDataGetter($dataWidget->getLazyLoadingAction(), true)}
                };
                
                var oModel = oControl.getModel('{$this->getModelNameForLazyData()}');
                var oViewModel = sap.ui.getCore().byId("{$this->getId()}").getModel("view");
                var sPendingPropery = "/_prefill/pending";

                // Skip loading if prefill pending (loader will be triggered again on prefill change)
                if (oViewModel.getProperty(sPendingPropery) === true) {
                    return;
                }

                oModel.setData({});

                {$this->getServerAdapter()->buildJsServerRequest(
                    $dataWidget->getLazyLoadingAction(),
                    'oModel',
                    'oParams',
                    "{$this->buildJsBusyIconHide()};",
                    "{$this->buildJsBusyIconHide()};",
                    "{$this->buildJsBusyIconHide()};"
                )}
                
JS;
    }
    
    protected function getModelNameForLazyData() : string
    {
        return 'lazyData';
    }
    
    /**
     * Returns a JS snippet, that performs the given $onFailJs if required filters are missing.
     *
     * @param string $onFailJs
     * @return string
     */
    protected function buildJsCheckRequiredFilters(string $onFailJs) : string
    {
        $configurator_element = $this->getFacade()->getElement($this->getDataWidget()->getConfiguratorWidget());
        return <<<JS
        
                try {
                    if (! {$configurator_element->buildJsValidator()}) {
                        {$onFailJs};
                    }
                } catch (e) {
                    console.warn('Could not check filter validity - ', e);
                }
                
JS;
    }
    
    protected function getDataWidget() : iShowData
    {
        return $this->getWidget()->getData();
    }
    
    protected function buildJsNoDataHintShow() : string
    {
        // TODO
        return '';
    }
    
    protected function buildJsNoDataHintHide() : string
    {
        // TODO
        return '';
    }
    
    public function buildJsRefresh()
    {
        return $this->getController()->buildJsMethodCallFromController('onLoadData', $this, '');
    }
    
    /**
    * Wraps the element constructor in a layout with a label.
    *
    * @param string $element_constructor
    * @return string
    */
    protected function buildJsLabelWrapper($element_constructor)
    {
        return $this->getWidget()->getHideCaption() === true ? $element_constructor : parent::buildJsLabelWrapper($element_constructor);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Display::getColorSemanticMap()
     */
    protected function getColorSemanticMap() : array
    {
        $semCols = [];
        foreach (Colors::getSemanticColors() as $semCol) {
            switch ($semCol) {
                case Colors::SEMANTIC_ERROR: $ui5Color = 'Error'; break;
                case Colors::SEMANTIC_WARNING: $ui5Color = 'Critical'; break;
                case Colors::SEMANTIC_OK: $ui5Color = 'Good'; break;
                case Colors::SEMANTIC_INFO: $ui5Color = 'Neutral'; break;
            }
            $semCols[$semCol] = $ui5Color;
        }
        return $semCols;
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsPropertyValueColor() : string
    {
        if ($this->getWidget() instanceof iHaveColorScale) {
            $colorResolver = $this->buildJsColorValue();
        }
        
        return $colorResolver ? 'valueColor: ' . $colorResolver . ',' : '';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Display::buildJsColorCssSetter()
     */
    protected function buildJsColorCssSetter(string $oControlJs, string $sColorJs) : string
    {
        return "setTimeout(function(){ $oControlJs.$().find('.sapMNCValue.Neutral').css('color', $sColorJs); },0)";
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Display::buildJsColorValueNoColor()
     */
    protected function buildJsColorValueNoColor() : string
    {
        return 'sap.m.ValueColor.Neutral';
    }
}
?>