<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\Widgets\KPI;
use exface\Core\CommonLogic\Constants\Colors;

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
        if ($this->isLazyLoading() === true) {
            $controller = $this->getController();
            
            // TODO take the data from the linked widget if configured
            //if ($this->getWidget()->hasDataWidgetLink() === false) {
                $controller->addMethod('onLoadData', $this, 'oEvent', $this->buildJsDataLoderFromServer('oEvent'));
                $controller->addOnShowViewScript($oControllerJs . '.' . $controller->buildJsMethodName('onLoadData', $this) . '();');
                $modelInit = ".setModel(new sap.ui.model.json.JSONModel(), '{$this->getModelNameForLazyData()}')";
            //}
        }
        return <<<JS
        
                new sap.m.NumericContent("{$this->getId()}", {
                    nullifyValue: false,
                    {$this->buildJsPropertyIcon()}
                    {$this->buildJsPropertyValue()}
                })
                {$modelInit}
                {$this->buildJsPseudoEventHandlers()}
                
JS;
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
                    element: "{$widget->getId()}",
                    object: "{$widget->getMetaObject()->getId()}",
                    data: {$configuratorElement->buildJsDataGetter($dataWidget->getLazyLoadingAction(), true)}
                };
                
                var oModel = oControl.getModel('{$this->getModelNameForLazyData()}');
                oModel.loadData("{$this->getAjaxUrl()}", oParams);
                {$this->buildJsBusyIconHide()}
                
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
     * @see \exface\UI5Facade\Facades\Elements\UI5Display::getColorScaleSemanticColorMap()
     */
    protected function getColorScaleSemanticColorMap() : array
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
    
    protected function buildJsColorSetter(string $colorValueJs, bool $isSemanticColor) : string
    {
        if ($isSemanticColor) {
            return "sap.ui.getCore().byId('{$this->getId()}').setValueColor({$colorValueJs});";
        } else {
            // TODO
            return <<<JS
            
        console.warn('Cannot set color "' + {$colorValueJs} + '" - only UI5 semantic colors currently supported!');
        
JS;
        }
    }
}
?>