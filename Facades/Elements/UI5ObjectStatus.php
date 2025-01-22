<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\Widgets\iHaveColorScale;
use exface\Core\Interfaces\Widgets\iHaveHintScale;
use exface\Core\Interfaces\Widgets\iHaveValue;
use exface\Core\Widgets\ColorIndicator;
use exface\UI5Facade\Facades\Elements\Traits\UI5ColorClassesTrait;
use exface\UI5FAcade\Facades\UI5PropertyBinding;

/**
 * Generates sap.m.ObjectStatus for any value widget.
 * 
 * In contrast to a regular element, ObjectStatus does not have a widget prototype. Any
 * value widget can be rendered as ObjectStatus by instantiating it manually:
 * 
 * ```
 * $element = new UI5ObjectStatus($widget, $this->getFacade());
 * 
 * ```
 *
 * @author Andrej Kabachnik
 *        
 */
class UI5ObjectStatus extends UI5Display
{    
    use UI5ColorClassesTrait;
    
    private $title = null;
    
    private $inverted = false;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Value::buildJsConstructorForMainControl()
     */
    public function buildJsConstructorForMainControl($oControllerJs = 'oController')
    {
        $this->registerExternalModules($this->getController());
        if ($this->getWidget()->hasColorScale()) {
            if ($this->isInverted()) {
                $colorCss = 'background-color: [#color#]';
            } else {
                $colorCss = 'color: [#color#]';
            }
            $this->registerColorClasses($this->getWidget()->getColorScale(), '.exf-custom-color.exf-color-[#color#] .sapMObjStatusText', $colorCss);
        }
        return <<<JS
        
        new sap.m.ObjectStatus("{$this->getId()}", {
            title: "{$this->escapeJsTextValue($this->getTitle())}",
            {$this->buildJsProperties()}
            {$this->buildJsPropertyValue()}
            {$this->buildJsPropertyState()}
            {$this->buildJsPropertyInverted()}
        })
        {$this->buildJsPseudoEventHandlers()}
        
JS;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Value::isLabelRendered()
     */
    protected function isLabelRendered() : bool
    {
        return false;
    }
    
    /***
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Value::getRenderCaptionAsLabel()
     */
    protected function getRenderCaptionAsLabel(bool $default = true) : bool
    {
        return false;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Value::buildJsLabelWrapper()
     */
    protected function buildJsLabelWrapper($element_constructor) {
        return $element_constructor;
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
    
    protected function buildJsPropertyState() : string
    {
        if ($this->getWidget() instanceof iHaveColorScale) {
            $stateJs = $this->buildJsColorValue();
        }
        
        return $stateJs ? 'state: ' . $stateJs . ',' : '';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Display::buildJsColorValueNoColor()
     */
    protected function buildJsColorValueNoColor() : string
    {
        return 'null';
    }
    
    /**
     *
     * @return string
     */
    protected function getTitle() : string
    {
        return $this->title ?? $this->getCaption();
    }
    
    /**
     * 
     * @param string $value
     * @return UI5ObjectStatus
     */
    public function setTitle(string $value) : UI5ObjectStatus
    {
        $this->title = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Display::buildJsColorCssSetter()
     */
    protected function buildJsColorCssSetter(string $oControlJs, string $sColorJs) : string
    {
        return $this->buildJsColorClassSetter($oControlJs, $sColorJs, 'exf-custom-color', 'exf-color-');
    }
    
    /**
     *
     * @return bool
     */
    protected function isInverted() : bool
    {
        return $this->inverted;
    }
    
    /**
     * 
     * @param bool $value
     * @return UI5ObjectStatus
     */
    public function setInverted(bool $value) : UI5ObjectStatus
    {
        $this->inverted = $value;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsPropertyInverted() : string
    {
        return 'inverted: ' . ($this->isInverted() ? 'true' : 'false') . ',';
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Display::buildJsPropertyTooltip()
     */
    protected function buildJsPropertyTooltip()
    {
        if (! $this->isValueBoundToModel()) {
            return parent::buildJsPropertyTooltip();
        }

        $widget = $this->getWidget();
        switch (true) {
            case ($widget instanceof iHaveHintScale) && ! $widget->getHintScale()->isEmpty():
                $scale = $widget->getHintScale();
                $value = $this->buildJsValueBinding(<<<JS
                
                    formatter: function(mVal){
                        var sHint = {$this->buildJsScaleResolver('mVal', $scale->getScaleValues(), $scale->isRangeBased())};
                        if (sHint === null || sHint === undefined) {
                            sHint = (mVal || '').toString();
                        }
                        return sHint;
                    },
JS);
                return 'tooltip: ' . $value .',';
            case $widget->isInTable() === true && $this->isValueBoundToModel():
                $value = $this->buildJsValueBinding('formatter: function(value){return (value === null || value === undefined) ? value : value.toString();},');
                return 'tooltip: ' . $value .',';
        }
        
        return parent::buildJsPropertyTooltip();
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
            return <<<JS
(function(mVal){
    var oControl = sap.ui.getCore().byId('{$this->getId()}');
    var mValFormatted = {$this->getFacade()->getDataTypeFormatter($this->getWidget()->getValueDataType())->buildJsFormatter('mVal')};
    var sColor = {$this->buildJsScaleResolver('mVal', $this->getWidget()->getColorScale(), $this->getWidget()->isColorScaleRangeBased())};
    var sColorVal;
    oControl.setText(mValFormatted);
    if (sColor.startsWith('~')) {
        var oColorScale = {$semColsJs};
        oControl.setState(oColorScale[sColor]);
    }
    {$this->buildJsColorCssSetter('oControl', "sColor")};
})({$valueJs})

JS;
        }
        return parent::buildJsValueSetter($valueJs);
    }

    protected function buildJsColorValue() : string
    {
        $widget = $this->getWidget();
        if (! ($widget instanceof ColorIndicator)) {
            return parent::buildJsColorValue();
        }
        
        $widgetColorBinding = $widget->getColorBinding();
        $ui5ColorBinding = new UI5PropertyBinding($this, 'state', $widgetColorBinding);
        if (! $ui5ColorBinding->isBoundToModel()) {
            $value = ''; // TODO
        } else {
            $semColsJs = json_encode($this->getColorSemanticMap());
            $formatterJs = <<<JS
                formatter: function(value){
                    var sColor = {$this->buildJsScaleResolver('value', $widget->getColorScale(), $widget->isColorScaleRangeBased())};
                    var sValueColor;
                    var oCtrl = this;
                    console.log('{$this->getWidget()->getAttributeAlias()}', sColor)
                    if (sColor.startsWith('~')) {
                        var oColorScale = {$semColsJs};
                        {$this->buildJsColorCssSetter('oCtrl', 'null')}
                        return oColorScale[sColor];
                    } else if (sColor) {
                        {$this->buildJsColorCssSetter('oCtrl', 'sColor')}
                    }
                    return {$this->buildJsColorValueNoColor()};
                }
                
JS;
            $value = $ui5ColorBinding->buildJsModelBinding($formatterJs);
        }
        return $value;
    }

    /**
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Display::buildJsValueBindingOptions()
     */
    public function buildJsValueBindingOptions()
    {
        $widget = $this->getWidget();
        // Make sure, booleans are formatted as yes/no and not as icons, which is default
        // for UI Display widgets.
        if ($widget instanceof iHaveValue && ($type = $widget->getValueDataType()) instanceof BooleanDataType) {
            return <<<JS

                formatter: function(value) {
                    if (value === "1" || value === "true" || value === 1 || value === true) {
                        return {$this->escapeString($type->format(true))};
                    } else {
                        return {$this->escapeString($type->format(false))};
                    }
                },
JS;
        }
        return $this->getValueBindingFormatter()->buildJsBindingProperties();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Display::buildJsValueBindingPropertyName()
     */
    public function buildJsValueBindingPropertyName() : string
    {
        // Make sure the value binding ist always `text` and not `src` for booleans because
        // the ObjectStatus does not show booleans as icons
        return 'text';
    }
}