<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Widgets\InputTime;
use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;
use exface\UI5Facade\Facades\Interfaces\UI5BindingFormatterInterface;

/**
 * Renders a sap.m.TimePicker for InputTime widgets.
 * 
 * @method InputTime getWidget()
 *
 * @author Andrej Kabachnik
 *
 */
class UI5InputTime extends UI5InputDate
{
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsConstructor()
     */
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {
        $onChangeScript = <<<JS

            var oTimePicker = oEvent.getSource();
            
			var sValue = oEvent.getParameter('value');
			var sValParsed = exfTools.time.parse(sValue);
			if (sValue !== sValParsed) {
				oTimePicker.setValue(sValParsed);
			}

JS;
        
        $this->addOnChangeScript($onChangeScript);       
        return parent::buildJsConstructor($oControllerJs);
    }
    
    /**
     *
     * @return UI5BindingFormatterInterface
     */
    protected function getDateBindingFormatter() : UI5BindingFormatterInterface
    {
        return $this->getFacade()->getDataTypeFormatterForUI5Bindings($this->getWidget()->getValueDataType());
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::registerExternalModules()
     */
    public function registerExternalModules(UI5ControllerInterface $controller) : UI5AbstractElement
    {
        $this->getDateBindingFormatter()->registerExternalModules($controller);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5InputDate::buildJsConstructorForMainControl()
     */
    public function buildJsConstructorForMainControl($oControllerJs = 'oController')
    {
        $this->registerExternalModules($this->getController());
        return <<<JS
        
        new sap.m.TimePicker("{$this->getId()}", {
            {$this->buildJsProperties()}
		}).setMaskMode('Off')
        {$this->buildJsInternalModelInit()}
        {$this->buildJsPseudoEventHandlers()}
		
JS;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5InputDate::buildJsValueBindingOptions()
     */
    public function buildJsValueBindingOptions()
    {
        // UI5-Upgrade - using custom data types with string declarations is no longer supported/throws warnings
        // so here we need to use the proper constructor now
        return <<<JS
        
                type: new exface.ui5Custom.dataTypes.MomentTimeType(
                    {$this->buildJsValueBindingFormatOptions()}
                ),
JS;
    }    
}