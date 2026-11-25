<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Interfaces\Widgets\iHaveHintScale;
use exface\UI5Facade\Facades\Elements\Traits\UI5ColorClassesTrait;

/**
 * Renders a sap.m.ProgressIndicator for a ProgressBar widget
 *
 * Uses the widget's effective color scale, including its default scale when
 * neither color_scale nor a fixed color is configured.
 * 
 * @method \exface\Core\Widgets\ProgressBar getWidget()
 *        
 * @author Andrej Kabachnik
 *        
 */
class UI5ProgressBar extends UI5Display
{
    use UI5ColorClassesTrait;
    
    private $textBindingPath = null;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsConstructor()
     */
    public function buildJsConstructorForMainControl($oControllerJs = 'oController')
    {
        // Register stuff here, that is needed for in-table rendering where buildJsConstructor()
        // is not called
        $this->registerExternalModules($this->getController());
        $widget = $this->getWidget();
        $scale = $widget->getColorScale();
        if ($scale !== []) {
            $this->registerColorClasses($scale, '.exf-custom-color.exf-color-[#color#] .sapMPIBar', 'background-color: [#color#]');
        }
        
        
        // NOTE: displayOnly:true makes the progressbar look nice inside responsive table 
        // cells! Otherwise it has top and bottom margins and is displayed uneven with the
        // caption.
        
        $constructorJs = <<<JS
        
        new sap.m.ProgressIndicator("{$this->getid()}", {
            showValue: true,
            displayOnly: true,
    		percentValue: {$this->buildJsValuePercent()},
            displayValue: {$this->buildJsDisplayValue()},
            {$this->buildJsProperties()}
            {$this->buildJsPropertyState()}
            {$this->buildJsPropertyDisplayAnimation()}
    	})
        {$this->buildJsPseudoEventHandlers()}
    	
JS;

        // Model-bound values are already handled by the state binding's formatter.
        if ($scale === [] || $this->isValueBoundToModel()) {
            return $constructorJs;
        }

        // Static values have no binding to invoke the formatter. Wrap the constructor
        // to run it once with the control as `this` for CSS classes, apply its semantic
        // state, and still return the control to the caller.
        $formatterJs = $this->buildJsProgressColorFormatter($scale);
        $valueJs = json_encode($widget->getValue(), JSON_THROW_ON_ERROR);
        return <<<JS
        (function(oControl){
            oControl.setState(($formatterJs).call(oControl, $valueJs));
            return oControl;
        })($constructorJs)
JS;
    }
            
    public function buildJsDisplayValue() : string
    {
        $widget = $this->getWidget();
        
        if ($widget->isTextBoundToAttribute() === false) {
            return $this->buildJsValue();
        }
        
        if (! $this->isValueBoundToModel()) {
            $value = $widget->getTextForValue($widget->getValue());
        } else {
            $textAttribute = $widget->getTextAttribute();
            $value = <<<JS
            {
                path: "{$this->getTextBindingPath()}",
                {$this->getFacade()->getDataTypeFormatterForUI5Bindings($textAttribute->getDataType())->buildJsBindingProperties()}
            }
JS;
        }
        return $value;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Interfaces\UI5ValueBindingInterface::getValueBindingPath()
     */
    public function getTextBindingPath() : string
    {
        if ($this->textBindingPath === null) {
            $widget = $this->getWidget();
            $model = $this->getView()->getModel();
            if ($model->hasBinding($widget, 'text')) {
                return $model->getBindingPath($widget, 'text');
            }
            return $this->getValueBindingPrefix() . $this->getWidget()->getTextDataColumnName();
        }
        return $this->textBindingPath;
    }
            
    public function buildJsValuePercent() : string
    {
       if (! $this->isValueBoundToModel()) {
            $value = $this->getWidget()->getValueDataType()->parse($this->getWidget()->getValue());
        } else {
            $bindingOptions = <<<JS
                formatter: function(value){
                    if (value === null || value === undefined) {
                        this.setVisible(false);
                        return null;
                    }
                    this.setVisible(true);
                    return parseFloat(value);
                }

JS;
            $value = $this->buildJsValueBinding($bindingOptions);
        }
        return $value ?? 0;
    }
            
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Value::buildJsValueBindingPropertyName()
     */
    public function buildJsValueBindingPropertyName() : string
    {
        return 'percentValue';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Display::buildJsValueSetterMethod()
     */
    public function buildJsValueSetterMethod($valueJs)
    {
        return "setPercentValue({$valueJs})";
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
     * @see \exface\UI5Facade\Facades\Elements\UI5Display::buildJsPropertyWrapping()
     */
    protected function buildJsPropertyWrapping()
    {
        return '';
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
     * @return string
     */
    protected function buildJsPropertyState() : string
    {
        $scale = $this->getWidget()->getColorScale();
        if ($scale === [] || ! $this->isValueBoundToModel()) {
            return 'state: "None",';
        }

        return 'state: ' . $this->buildJsValueBinding('formatter: ' . $this->buildJsProgressColorFormatter($scale)) . ',';
    }

    private function buildJsProgressColorFormatter(array $scale) : string
    {
        $widget = $this->getWidget();
        // The default scale always has numeric thresholds, even for string-valued progress.
        return $this->buildJsColorScaleFormatter($scale, ! $widget->hasColorScale() || $widget->isColorScaleRangeBased());
    }
    
    protected function buildJsPropertyDisplayAnimation() : string
    {
        return $this->getWidget()->isInTable() ? 'displayAnimation: false,' : '';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Display::buildJsColorValueNoColor()
     */
    protected function buildJsColorValueNoColor() : string
    {
        return 'sap.ui.core.ValueState.None';
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
        $jsEmpty = $this->getValueBindingFormatter()->getJsFormatter()->getJsEmptyText('mVal');
        switch (true) {
            case ($widget instanceof iHaveHintScale) && ! $widget->getHintScale()->isEmpty():
                $scale = $widget->getHintScale();
                $value = $this->buildJsValueBinding(<<<JS
                
                    formatter: function(mVal){
                        var sHint = {$this->buildJsScaleResolver('mVal', $scale->getScaleValues(), $scale->isRangeBased())};
                        if (sHint === null || sHint === undefined) {
                            sHint = (mVal || {$jsEmpty}).toString();
                        }
                        return sHint;
                    },
JS);
                return 'tooltip: ' . $value .',';
            case $widget->isInTable() === true && $this->isValueBoundToModel():
                $value = $this->buildJsValueBinding("formatter: function(mVal){return (mVal === null || mVal === undefined) ? {$jsEmpty} : mVal.toString();},");
                return 'tooltip: ' . $value .',';
        }
        
        return parent::buildJsPropertyTooltip();
    }
}