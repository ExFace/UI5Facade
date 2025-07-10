<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Widgets\InputCheckBox;

/**
 * Generates OpenUI5 inputs
 *
 * @author Andrej Kabachnik
 *        
 */
class UI5InputCheckBox extends UI5Input
{    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Text::buildJsConstructorForMainControl()
     */
    public function buildJsConstructorForMainControl($oControllerJs = 'oController')
    {
        return <<<JS

        new sap.m.CheckBox("{$this->getId()}", {
            {$this->buildJsProperties()}                
        })

JS;
    }
    
    protected function buildJsPropertyValue()
    {
        if ($this->isValueBoundToModel()) {
            $value = $this->buildJsValueBinding();
        } else {
            $value = $this->getWidget()->getValueWithDefaults() ? 'true' : 'false';
        }
        return ($value ? 'selected: ' . $value . ', ' : '');
    }
    
    public function buildJsValueGetterMethod()
    {
        return 'getSelected()';
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Interfaces\UI5ValueBindingInterface::buildJsValueBindingOptions()
     */
    public function buildJsValueBindingOptions()
    {
        return $this->getValueBindingFormatter()->buildJsBindingProperties();
    }
    
    /**
     *
     * @return UI5BindingFormatterInterface
     */
    protected function getValueBindingFormatter()
    {
        return $this->getFacade()->getDataTypeFormatterForUI5Bindings($this->getWidget()->getValueDataType());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::buildJsPropertyChange()
     */
    protected function buildJsPropertyChange()
    {
        // If data binding is used, it won't work together with the boolean formatter for some
        // reason. The value in the model simply never changes. This hack manually changes the
        // model every time the checkbox is checked or unchecked.
        // TODO restrict this to only two-way-binding somehow
        if ($this->isValueBoundToModel()) {
            if ($this->getWidget()->isInTable()) {
                $script = <<<JS

            var oCtxt = oEvent.getSource().getBindingContext();
            var path = oCtxt.sPath;
            var row = oCtxt.getModel().getProperty(path);
            row["{$this->getValueBindingPath()}"] = oEvent.getParameters().selected ? 1 : 0;
            oCtxt.getModel().setProperty(path, row);
            
JS;
            } else {
                $script = <<<JS
            
            var oSelect = oEvent.getSource();
            var sPath = oSelect.getBinding('selected').getPath();
            oSelect.getModel().setProperty(sPath, oEvent.getParameters().selected ? 1 : 0);
            
JS;
            }
                
            $this->getController()->addOnEventScript($this, self::EVENT_NAME_CHANGE, $script);
        }
        
        return 'select: ' . $this->getController()->buildJsEventHandler($this, self::EVENT_NAME_CHANGE, true) . ',';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::buildJsValueSetterMethod()
     */
    public function buildJsValueSetterMethod($value)
    {
        if ($value === '' || $value === null) {
            $value = 'false';
        }
        return "setSelected({$value} ? true : false).fireSelect({selected: ({$value} ? true : false)})";
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::buildJsValueBindingPropertyName()
     */
    public function buildJsValueBindingPropertyName() : string
    {
        return 'selected';
    }
    
    /**
     * Checkboxes cannot be required in UI5!
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::buildJsPropertyRequired()
     */
    protected function buildJsPropertyRequired()
    {
        return '';
    }
    
    /**
     * Returns JS code, that performs $onFailJs if the widget is required and has not value.
     *
     * @param string $valueJs
     * @param string $onFailJs
     *
     * @return string
     */
    protected function buildJsValidatorCheckRequired(string $valueJs, string $onFailJs) : string
    {
        // required_if check does not work for inTable widgets
        if ($this->getWidget()->isInTable()) {
            if ($this->getWidget()->isRequired() === true) {
                return <<<JS
                
                        if ($valueJs === undefined || $valueJs === null || $valueJs === '' || $valueJs === 0) { $onFailJs }
JS;
            }
        }
        if ($this->getWidget()->isRequired() === true || $this->getWidget()->getRequiredIf()) {
            return <<<JS
            
                        if ({$this->buildJsRequiredGetter()} == true) { if ($valueJs === undefined || $valueJs === null || $valueJs === '' || $valueJs === 0) { $onFailJs } }
JS;
        }
        return '';
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsValueGetter()
     */
    public function buildJsValueGetter()
    {
        $rawValueGetter = parent::buildJsValueGetter();
        return <<<JS
function() {
    return {$this->getFacade()->getDataTypeFormatter($this->getWidget()->getValueDataType())->buildJsFormatParser($rawValueGetter)};
}()
JS;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::buildJsCallFunction()
     */
    public function buildJsCallFunction(string $functionName = null, array $parameters = [], ?string $jsRequestData = null) : string
    {
        switch (true) {
            case $functionName === InputCheckBox::FUNCTION_CHECK:
                return "setTimeout(function(){ {$this->buildJsValueSetter(1)} }, 0);";
            case $functionName === InputCheckBox::FUNCTION_UNCHECK:
                return "setTimeout(function(){ {$this->buildJsValueSetter(0)} }, 0);";
        }
        return parent::buildJsCallFunction($functionName, $parameters, $jsRequestData);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::buildJsSetRequired()
     */
    protected function buildJsSetRequired(bool $required) : string
    {
        $val = $required ? 'true' : 'false';
        if ($this->isLabelRendered() === true || $this->getRenderCaptionAsLabel()) {
            if (! ($this->getWidget()->getHideCaption() === true || $this->getWidget()->isHidden())) {
                $requireLabelJs = "sap.ui.getCore().byId('{$this->getIdOfLabel()}')?.setRequired($val);";
            }
        }
        return <<<JS
        
var oElem = sap.ui.getCore().byId('{$this->getId()}');
if (oElem !== undefined && oElem !== null) {
    sap.ui.getCore().byId('{$this->getId()}')._exfRequired = {$val};
}
$requireLabelJs

JS;
    }
    
    protected function buildJsRequiredGetter() : string
    {
        $val = $this->getWidget()->isRequired() ? 'true' : 'false';
        return "sap.ui.getCore().byId('{$this->getId()}')?._exfRequired || {$val}";
    }
}
?>