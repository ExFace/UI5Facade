<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Widgets\ProgressBar;
use exface\Core\Interfaces\Widgets\iHaveColorScale;

/**
 *
 * @method ProgressBar getWidget()
 *        
 * @author Andrej Kabachnik
 *        
 */
class UI5ProgressBar extends UI5Display
{
    private $textBindingPath = null;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsConstructor()
     */
    public function buildJsConstructorForMainControl($oControllerJs = 'oController')
    {
        $this->registerExternalModules($this->getController());
        // NOTE: displayOnly:true makes the progressbar look nice inside responsive table 
        // cells! Otherwise it has top and bottom margins and is displayed uneven with the
        // caption.
        return <<<JS
        
        new sap.m.ProgressIndicator("{$this->getid()}", {
            showValue: true,
            state: "None",
            displayOnly: true,
    		percentValue: {$this->buildJsValuePercent()},
            displayValue: {$this->buildJsDisplayValue()},
            {$this->buildJsProperties()}
            {$this->buildJsPropertyState()}
    	})
    	
JS;
    }
            
    public function buildJsDisplayValue() : string
    {
        $widget = $this->getWidget();
        
        if ($widget->isTextBoundToAttribute() === false) {
            return $this->buildJsValue();
        }
        
        if (! $this->isValueBoundToModel()) {
            $value = $widget->getText($widget->getValue());
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
    protected function buildJsPropertyAlignment()
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
        // Set the color explicitly and make sure it is set again every time the progressbar
        // is resized - this happens very often in tables: e.g. after navigating back from
        // a large-dialog-view.
        return <<<JS
        
        setTimeout(function(){ 
            var sColorVal = $sColorJs;
            $oControlJs.$().find('.sapMPIBar').css('background-color', sColorVal); 
            if ($oControlJs.data('_exfColored') !== true) {
                sap.ui.core.ResizeHandler.register($oControlJs, function(){
                    $oControlJs.$().find('.sapMPIBar').css('background-color', sColorVal);
                });
                $oControlJs.data('_exfColored', true);
            } 
        }, 10);
JS;
    }
    
    /**
     * 
     * @return string
     */
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
        return 'sap.ui.core.ValueState.None';
    }
}