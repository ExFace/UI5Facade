<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\DataTypes\NumberDataType;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JqueryInputValidationTrait;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Widgets\InputNumber;
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
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsValueGetter()
     */
    public function buildJsValueGetter()
    {
        // If not bound to a model, the control uses the native HTML5 `type="Number"` input
        // (see buildJsPropertyType()), which only ever holds a plain, unformatted number - so
        // parse it directly instead of via the formatter, which expects the locale-formatted
        // display value of a model-bound control and would otherwise mis-parse (or reject) it.
        // An empty string must yield `null` explicitly - `parseFloat('')` would produce `NaN`.
        if ($this->isValueBoundToModel() === false) {
            return <<<JS
(function(sVal){
    return (sVal === null || sVal === undefined || sVal === '') ? null : parseFloat(sVal);
})(sap.ui.getCore().byId('{$this->getId()}').getValue())
JS;
        }
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
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::buildJsCallFunction()
     */
    public function buildJsCallFunction(string $functionName = null, array $parameters = [], ?string $jsRequestData = null) : string
    {
        switch (true) {
            case $functionName === InputNumber::FUNCTION_ADD:
                return $this->buildJsCallFunctionAddSubtract($parameters);
        }
        return parent::buildJsCallFunction($functionName, $parameters, $jsRequestData);
    }
    
    /**
     * Adds (or subtracts) a number to the current value of the input.
     * 
     * @param array $parameters
     * @return string
     */
    protected function buildJsCallFunctionAddSubtract(array $parameters = []) : string
    {
        $jsFormatter = $this->getValueBindingFormatter()->getJsFormatter();
        $isBoundToModel = $this->isValueBoundToModel();
        // If the value is NOT bound to a model, the control uses the native HTML5
        // `type="Number"` input (see buildJsPropertyType()), which only ever holds a plain
        // number (no grouping/decimal-separator formatting, no prefix/suffix, no empty-format
        // sentinel) - so write it back directly instead of going through the formatter, which
        // is tailored towards the formatted display of a model-bound value.
        $newValueJs = $isBoundToModel ? $jsFormatter->buildJsFormatter('nNew') : 'String(nNew)';
        // `setValue()` alone does not reliably push the new value through the model's binding
        // type (e.g. `sap.ui.model.type.Float` uses locale-based parsing, which may not match
        // our custom formatting), so the model property is updated explicitly here too - the
        // same way it is done in buildJsValidatorConstraints().
        $modelSyncJs = '';
        if ($this->getUseWidgetId() === true && $isBoundToModel === true) {
            $modelSyncJs = <<<JS

    sap.ui.getCore().byId('{$this->getId()}').getModel().setProperty('{$this->getValueBindingPath()}', nNew);
    sap.ui.getCore().byId('{$this->getId()}').getBinding('value')?.refresh(true);
JS;
        }
        return <<<JS
(function(nStep){
    var nVal = {$this->buildJsValueGetter()};
    if (nVal === null || nVal === undefined || isNaN(nVal)) {
        nVal = 0;
    }
    var nNew = nVal + nStep;
    {$this->buildJsValueSetter($newValueJs)};{$modelSyncJs}
})(parseFloat('{$parameters[0]}'));

JS;
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
        
        // If the formatted value differs from that show in the control, update the control.
        // This makes only sense, if the control has an id. If it is an in-table control, we
        // will not know, which one of them to update.
        if ($this->getUseWidgetId() === true && $this->isValueBoundToModel() === true) {
            $constraintsJs .= <<<JS

                    (function(oInput, sValue){
                        // Don't bother if the control is not there anymore
                        if (oInput === undefined) {
                            return;
                        }
                        // sValue is already parsed at this point.
                        // Now get the unformatted value from the control
                        let inputValue = oInput.getValue();
                        if (! isNaN(sValue) && sValue !== inputValue) {
                            oInput.getModel().setProperty('{$this->getValueBindingPath()}', sValue);
                            // refresh(true) forces the widget to refresh its value
                            oInput.getBinding('value')?.refresh(true);
                        }
                    })(sap.ui.getCore().byId('{$this->getId()}'), $valueJs);
JS;
        }

        return $constraintsJs;
    }
}