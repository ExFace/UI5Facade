<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Widgets\ColorIndicator;
use exface\Core\CommonLogic\Constants\Colors;

/**
 * Renders a ColorIndicator widget as sap.ui.core.Icon with a colored circle.
 * 
 * @method ColorIndicator getWidget()
 *        
 * @author Andrej Kabachnik
 *        
 */
class UI5ColorIndicator extends UI5Display
{
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsConstructor()
     */
    public function buildJsConstructorForMainControl($oControllerJs = 'oController')
    {
        $this->registerExternalModules($this->getController());
        $widget = $this->getWidget();
            
        if ($this->isIcon()) {
            return <<<JS
        
        new sap.ui.core.Icon("{$this->getid()}", {
            src: "sap-icon://circle-task-2",
            color: {$this->buildJsColorValue()},
            {$this->buildJsProperties()}
    	})
    	
JS;
        } else {
            $objStatus = new UI5ObjectStatus($widget, $this->getFacade());
            $objStatus->setTitle('');
            $objStatus->setValueBindingPrefix($this->getValueBindingPrefix());
            if ($widget->getFill() === true) {
                $objStatus->setInverted(true);
            }
            return $objStatus->buildJsConstructorForMainControl($oControllerJs);
        }
    }
    
    protected function isIcon() : bool
    {
        $widget = $this->getWidget();
        $colorOnly = true;
        if ($widget instanceof ColorIndicator) {
            // See if the user forced to not use color-only mode
            $colorOnly = $widget->getColorOnly($colorOnly);
        }
        return $colorOnly;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Display::buildJsPropertyAlignment()
     */
    protected function buildJsPropertyAlignment() : string
    {
        return '';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Display::buildJsColorValue()
     */
    protected function buildJsColorValue() : string
    {
        if (! $this->isValueBoundToModel()) {
            $value = $this->buildJsColorValueNoColor(); // TODO
        } else {
            $semColsJs = json_encode($this->getColorSemanticMap());
            $bindingOptions = <<<JS
                formatter: function(value){
                    var sColor = {$this->buildJsScaleResolver('value', $this->getWidget()->getColorScale(), $this->getWidget()->isColorScaleRangeBased())};
                    if (sColor.startsWith('~')) {
                        var oColorScale = {$semColsJs};
                        return oColorScale[sColor];
                    } 
                    return sColor || {$this->buildJsColorValueNoColor()};
                }
                
JS;
            $value = $this->buildJsValueBinding($bindingOptions);
        }
        return $value;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Display::buildJsColorValueNoColor()
     */
    protected function buildJsColorValueNoColor() : string
    {
        if ($this->isIcon()) {
            return '"transparent"';
        } else {
            return parent::buildJsColorValueNoColor();
        }
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
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsValueGetterMethod()
     */
    public function buildJsValueGetterMethod()
    {
        return "getTooltip()";
    }
    
    protected function getColorSemanticMap() : array
    {
        $semCols = [];
        if ($this->isIcon()) {
            foreach (Colors::getSemanticColors() as $semCol) {
                switch ($semCol) {
                    case Colors::SEMANTIC_ERROR: $ui5Color = 'Negative'; break;
                    case Colors::SEMANTIC_WARNING: $ui5Color = 'Critical'; break;
                    case Colors::SEMANTIC_OK: $ui5Color = 'Positive'; break;
                    case Colors::SEMANTIC_INFO: $ui5Color = 'Neutral'; break;
                }
                $semCols[$semCol] = $ui5Color;
            }
        } else {
            $semCols = parent::getColorSemanticMap();
        }
        return $semCols;
    }    
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Display::buildJsValueSetter()
     */
    public function buildJsValueSetter($valueJs)
    {
        if (! $this->isValueBoundToModel() && $this->getWidget()->hasColorScale()) {
            $semColsJs = json_encode($this->getColorSemanticMap());
            if ($this->isIcon()) {
                $setValueJs = "";
            } else {
                $setValueJs = "oControl.setText(mValFormatted)";
            }
            return <<<JS
(function(mVal){
    var oControl = sap.ui.getCore().byId('{$this->getId()}');
    var mValFormatted = {$this->getFacade()->getDataTypeFormatter($this->getWidget()->getValueDataType())->buildJsFormatter('mVal')};
    var sColor = {$this->buildJsScaleResolver('mVal', $this->getWidget()->getColorScale(), $this->getWidget()->isColorScaleRangeBased())};
    var sColorVal;
    {$setValueJs};
    if (sColor.startsWith('~')) {
        var oColorScale = {$semColsJs};
        oControl.setState(oColorScale[sColor]);
    } 
    {$this->buildJsColorCssSetter('oControl', "sColor || {$this->buildJsColorValueNoColor()}")};
})({$valueJs})

JS;
        }
        return parent::buildJsValueSetter($valueJs);
    }
}