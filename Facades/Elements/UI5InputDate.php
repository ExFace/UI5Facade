<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Widgets\InputDate;
use exface\Core\DataTypes\DateDataType;
use exface\Core\DataTypes\TimeDataType;
use exface\Core\DataTypes\TimestampDataType;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JqueryInputDateTrait;
use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;
use exface\UI5Facade\Facades\Interfaces\UI5BindingFormatterInterface;

/**
 * Generates sap.m.DatePicker for InputDate widgets
 *
 * @method InputDate getWidget()
 *
 * @author Andrej Kabachnik
 *
 */
class UI5InputDate extends UI5Input
{
    use JqueryInputDateTrait;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsConstructor()
     */
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {
        $onAfterRendering = <<<JS
        
        sap.ui.getCore().byId("{$this->getId()}").$().find('.sapMInputBaseInner').on('keypress', function(e){
			//console.log('keypress');
			e.stopPropagation();
		});
JS;
        $this->addPseudoEventHandler('onAfterRendering', $onAfterRendering);
        return parent::buildJsConstructor($oControllerJs);
    }
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::buildJsConstructorForMainControl()
     */
    public function buildJsConstructorForMainControl($oControllerJs = 'oController')
    {
        $this->registerExternalModules($this->getController());
        return <<<JS
        
        new sap.m.DatePicker("{$this->getId()}", {
            {$this->buildJsProperties()}
		})
        {$this->buildJsInternalModelInit()}
        {$this->buildJsPseudoEventHandlers()}
		
JS;
    }
    
    protected function buildJsPropertyValue()
    {
        return parent::buildJsPropertyValue();   
    }
    
    protected function buildJsInternalModelInit() : string
    {
        if ($this->hasInternalDateModel() === true) {
            $prop = $this->getWidget()->getDataColumnName();
            $defaultValue = $this->getWidget()->getValueWithDefaults();
            if ($defaultValue !== '' && $defaultValue !== null) {
                $initialModel = "{ $prop : {$this->getDateFormatter()->buildJsFormatParserToJsDate("'$defaultValue'")} }";
            }
            return ".setModel(new sap.ui.model.json.JSONModel($initialModel), '{$this->getInternalDateModelName()}')";
        } else {
            return '';
        }
        
        
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::buildJsProperties()
     */
    public function buildJsProperties()
    {
        $options = parent::buildJsProperties() . <<<JS

            valueFormat: '{$this->buildJsValueFormat()}',
            displayFormat: '{$this->getDisplayFormat()}',
            placeholder: " ",
JS;
        return $options;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Value::buildJsValueBindingOptions()
     */
    public function buildJsValueBindingOptions()
    {
        return <<<JS

                type: 'exface.ui5Custom.dataTypes.MomentDateType',
                {$this->buildJsValueBindingFormatOptions()}
JS;
    }
    
    /**
     *
     * @return string
     */
    protected function getDisplayFormat() : string
    {
        return $this->getWidget()->getFormat();           
    }
    
   /**
    * 
    * @return string
    */
    protected function buildJsValueBindingFormatOptions() : string
    {
        return <<<JS
        
                    formatOptions: {
                        dateFormat: '{$this->getDisplayFormat()}'
                    },
JS;
        
    }
    
    /**
     *
     * @return string
     */
    protected function buildJsValueFormat() : string
    {
        return $this->getDateFormatter()->getDataType()->getFormatToParseTo();
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
     * @return boolean
     */
    protected function isValueBoundToModel()
    {        
        return true;
    }
    
    public function getValueBindingPath() : string
    {
        if ($this->hasInternalDateModel() === true) {
            return $this->getInternalDateModelName() . '>'  . $this->getValueBindingPrefix() . $this->getWidget()->getDataColumnName();
        } else {
            return parent::getValueBindingPath();
        }
    }
        
    protected function hasInternalDateModel() : bool
    {
        return parent::isValueBoundToModel() === false;
    }
    
    protected function getInternalDateModelName() : string
    {
        return 'internalDate';
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
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::buildJsCallFunction()
     */
    public function buildJsCallFunction(string $functionName = null, array $parameters = []) : string
    {
        switch (true) {
            case $functionName === InputDate::FUNCTION_ADD:
                return $this->buildJsCallFunctionAddSubtract($parameters);
        }
        return parent::buildJsCallFunction($functionName, $parameters);
    }
}