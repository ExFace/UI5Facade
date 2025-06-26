<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\DataTypes\NumberDataType;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JqueryInputValidationTrait;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\UI5Facade\Facades\Interfaces\UI5BindingFormatterInterface;
use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;

/**
 * Renders a sap.m.Input with for numbers.
 * 
 * @method \exface\Core\Widgets\InputNumber getWidget()
 * 
 * @author Andrej Kabachnik
 *        
 */
class UI5InputNumber extends UI5Input
{    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::buildJsPropertyType()
     */
    protected function buildJsPropertyType()
    {
        // Note: `type: sap.m.InputType.Number` does not work properly with model binding. The control remains
        // empty. The number type also does not allow binding formatting like min/max fraction digits.
        // TODO how to handle InputNumber NOT bound to the model properly? Using the built-in InputType.Number
        // does not allow precision customizing.
        if ($this->isValueBoundToModel() === false) {
            return 'type: sap.m.InputType.Number,';
        }
        return parent::buildJsPropertyType();
    }
        
    /**
     * Returns the initial value defined in UXON as number or an quoted empty string
     * if not initial value was set.
     * 
     * @return string|NULL
     */
    protected function buildJsInitialValue() : string
    {
        $val = $this->getWidget()->getValueWithDefaults();
        return (is_null($val) || $val === '') ? '""' : $val;
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsValueGetter()
     */
    public function buildJsValueGetter()
    {
        $jsFormatter = $this->getValueBindingFormatter()->getJsFormatter();
        return <<<JS
(function(oInput){
    var sVal = oInput.getValue();
    var nVal = {$jsFormatter->buildJsFormatParser('sVal')};
    return nVal;
})(sap.ui.getCore().byId('{$this->getId()}'))
JS;

    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::buildJsProperties()
     */
    public function buildJsProperties()
    {
        return parent::buildJsProperties() . <<<JS
            textAlign: sap.ui.core.TextAlign.Right,
JS;
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Value::buildJsValueBindingOptions()
     */
    public function buildJsValueBindingOptions()
    {
        return $this->getValueBindingFormatter()->buildJsBindingProperties();
    }

    /**
     *
     * @return UI5BindingFormatterInterface
     */
    protected function getValueBindingFormatter() : UI5BindingFormatterInterface
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
        parent::registerExternalModules($controller);
        $this->getValueBindingFormatter()->registerExternalModules($controller);
        return $this;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see JqueryInputValidationTrait::buildJsValidatorConstraints()
     */
    protected function buildJsValidatorConstraints(string $valueJs, string $onFailJs, DataTypeInterface $type) : string
    {
        $widget = $this->getWidget();
        $constraintsJs = parent::buildJsValidatorConstraints($valueJs, $onFailJs, $type);
        // If the widget has other min/max values than the data type, validate them separately
        // Do it by creating a data type with these constraints and letting it render the validator
        // Place this validator AFTER the regular validation of the data type because if the
        // data type has more severe constraints, the whole thing should still fail!
        if ((null !== $min = $widget->getMinValue()) || (null !== $max = $widget->getMaxValue())) {
            $numberType = DataTypeFactory::createFromString($this->getWorkbench(), NumberDataType::class);
            if ($min !== null) {
                $numberType->setMin($min);
            }
            if ($max !== null) {
                $numberType->setMax($max);
            }
            $numberValidator = $this->getFacade()->getDataTypeFormatter($numberType)->buildJsValidator($valueJs);
            $constraintsJs .= <<<JS

                    if($numberValidator !== true) {$onFailJs};
            JS;
        }

        $constraintsJs .= <<<JS
                    (
                        // sValue is already formatted input at this point.
                        function(oInput, sValue){
                          // unformatted value:
                          let inputValue = oInput.getValue();
                          if (!isNaN(sValue) && sValue !== inputValue) {
                            sap.ui.getCore().byId('{$this->getId()}').getModel().setProperty('{$this->getValueBindingPath()}', sValue);
                          }
                        }
                    )(
                        sap.ui.getCore().byId('{$this->getId()}'), 
                        $valueJs
                     )
        JS;

        return $constraintsJs;
    }
}