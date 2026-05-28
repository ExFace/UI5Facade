<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;

/**
 * Renders a numeric input widget as a custom version of sap.m.StepInput.
 * 
 * A custom version of sap.m.StepInput is used because the original does not allow
 * empty values, which is unexceptable in various situation like in filters (no
 * way to deaktivate the filter) or optional inputs (empty inputs are treated as 
 * zero!).
 *
 * @method InputNumberSpinner getWidget()
 * 
 * @author Andrej Kabachnik
 *        
 */
class UI5InputNumberSpinner extends UI5Input
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::buildJsConstructorForMainControl()
     */
    public function buildJsConstructorForMainControl($oControllerJs = 'oController')
    {
        $this->registerExternalModules($this->getController());
        
        return <<<JS

            new exface.ui5Custom.StepInputCustom("{$this->getId()}", {
                {$this->buildJsProperties()}
                {$this->buildJsPropertiesMinMax()}
                {$this->buildJsPropertyStep()}
                {$this->buildJsPropertyPrecision()}
            })
            {$this->buildJsPseudoEventHandlers()}

JS;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::registerExternalModules()
     */
    public function registerExternalModules(UI5ControllerInterface $controller) : UI5AbstractElement
    {
        $controller->addExternalModule('libs.exface.ui5Custom.StepInputCustom', 'vendor/exface/ui5facade/Facades/js/ui5Custom/StepInputCustom');
        return $this;
    }
    
    /**
     * @return string
     */
    protected function buildJsPropertyStep() : string
    {
        $widget = $this->getWidget();
        if (! is_null($widget->getStep())) {
            $value = $widget->getStep();
        } else {
            return '';
        }
        
        return 'step: ' . $value . ',';
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsPropertyPrecision() : string
    {
        if (is_null($val = $this->getWidget()->getPrecisionMax())) {
            $val = 2;
        }
        
        return 'displayValuePrecision: ' . $val . ',';
    }
    
    protected function buildJsPropertiesMinMax() : string
    {
        $widget = $this->getWidget();
        $options = '';
        if (! is_null($widget->getMinValue())) {
            $options .= 'min: ' . $widget->getMinValue() . ',';
        }
        if (! is_null($widget->getMaxValue())) {
            $options .= 'max: ' . $widget->getMaxValue() . ',';
        }
        return $options;
    }
}