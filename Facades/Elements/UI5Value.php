<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\UI5Facade\Facades\Interfaces\UI5ValueBindingInterface;
use exface\UI5Facade\Facades\Interfaces\UI5CompoundControlInterface;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JqueryLiveReferenceTrait;
use exface\Core\Interfaces\Widgets\iTakeInput;
use exface\Core\Widgets\Input;
use exface\Core\Interfaces\Widgets\iShowDataColumn;

/**
 * Generates sap.m.Text controls for Value widgets
 * 
 * @method \exface\Core\Widgets\Value getWidget()
 *
 * @author Andrej Kabachnik
 *        
 */
class UI5Value extends UI5AbstractElement implements UI5ValueBindingInterface, UI5CompoundControlInterface
{
    use JqueryLiveReferenceTrait {
        registerLiveReferenceAtLinkedElement as registerLiveReferenceAtLinkedElementViaTrait;
    }
    
    private $valueBindingPath = null;
    
    private $valueBindingPrefix = '/';
    
    private $valueBindingDisabled = false;
    
    private $valueBoundToModel = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsConstructor()
     */
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {
        return $this->buildJsConstructorForMainControl($oControllerJs);
    }
    
    /**
     * Returns the constructor of the text/input control without the label
     * 
     * @return string
     */
    public function buildJsConstructorForMainControl($oControllerJs = 'oController')
    {
        if ($this->getWidget()->getVisibility() === EXF_WIDGET_VISIBILITY_PROMOTED) {
            $this->addElementCssClass('exf-promoted');
        }
        
        return <<<JS

        new sap.m.Text("{$this->getId()}", {
            {$this->buildJsProperties()}
            {$this->buildJsPropertyValue()}
        }).addStyleClass("{$this->buildCssElementClass()}")

JS;
    }
            
    public function buildJsProperties()
    {
        return parent::buildJsProperties() . <<<JS
            {$this->buildJsPropertyTooltip()}
            {$this->buildJsPropertyLayoutData()}
JS;
    }
    
    /**
     * Returns the value property with property name and value followed by a comma.
     * 
     * @return string
     */
    protected function buildJsPropertyValue()
    {
        return <<<JS
            text: {$this->buildJsValue()},
JS;
    }
    
    /**
     * Returns inline javascript code for the value of the value property (without the property name).
     * 
     * Possible results are a quoted JS string, a binding expression or a binding object.
     * 
     * @return string
     */
    public function buildJsValue()
    {
        if (! $this->isValueBoundToModel()) {
            $value = str_replace("\n", '', $this->getWidget()->getValue());
            $value = '"' . $this->escapeJsTextValue($value) . '"';
        } else {
            $value = $this->buildJsValueBinding();
        }
        return $value;
    }
    
    /**
     * Wraps the element constructor in a layout with a label.
     * 
     * @param string $element_constructor
     * @return string
     */
    protected function buildJsLabelWrapper($element_constructor)
    {
        $widget = $this->getWidget();
        
        $labelAppearance = '';
        if ($widget->getHideCaption() === true) {
            $labelAppearance .= 'visible: false,';
        } else {
            if ($widget instanceof iTakeInput) {
                if ($widget->isRequired()) {
                    $labelAppearance .= 'required: true,';
                }
            }
        }
        
        $label = <<<JS
        new sap.m.Label({
            text: "{$this->getCaption()}",
            {$this->buildJsPropertyTooltip()}
            {$labelAppearance}
        }),

JS;
        
        return $label . $element_constructor;
    }
    
    /**
     * Returns TRUE if the value of the control should be bound to the default model.
     * 
     * This actually depends on meny factors:
     * - you can force value binding programmatically via setValueBoundToModel(). For example,
     * table cell widgets MUST be bound to model, so table columns call this method on their
     * cell widgets.
     * - you can set binding options like setValueBindingPath() which means using a binding
     * implicitly
     * - same happens if the prefill model has a binding for this widget
     * - on the other hand, widgets, that have static values should not have a binding unless
     * any of the above forces it
     * 
     * If none of the above applies, the element is concidered to have a binding unless there
     * is a binding conflict in the model (i.e. other widgets use the same binding name). This
     * is mainly for historical reasons - not sure, if it's still required.
     * 
     * @return boolean
     */
    public function isValueBoundToModel()
    {
        if ($this->valueBoundToModel !== null) {
            return $this->valueBoundToModel;
        }
        
        $widget = $this->getWidget();
        $model = $this->getView()->getModel();
        
        // If the widget can be bound to a data column, but has no column name really
        if ($widget instanceof iShowDataColumn && ! $widget->isBoundToDataColumn()) {
            return false;
        }
        
        // If there is a model binding, obviously return true
        if ($model->hasBinding($widget, $this->getValueBindingWidgetPropertyName())) {
            return true;
        }
        
        // If the the binding was disabled explicitly, return false
        if ($this->isValueBindingDisabled() === true) {
            return false;
        }
        
        // Otherwise assume model binding unless the widget has an explicit value
        if ($widget->hasValue() === true) {
            $valueExpr = $widget->getValueExpression();
        } elseif ($widget instanceof Input && $widget->hasDefaultValue()) {
            $valueExpr = $widget->getDefaultValueExpression();
        } 
        
        if ($valueExpr && $valueExpr->isStatic() === true) {
            return false;
        }
        
        if ($model->hasBindingConflict($widget, $this->getValueBindingWidgetPropertyName())) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Forces value binding on or off for this control.
     * 
     * Note: `setValueBoundToModel(false)` and `setValueBindingDisabled(true)` have the same
     * effect, but not `setValueBoundToModel(true)` and `setValueBindingDisabled(false)`
     * because `setValueBindingDisabled(false)` does not force binding - it merely reinstantiates
     * the automatic detection algorithm if it was disabled previously.
     * 
     * @param bool $trueOrFalse
     * @return UI5Value
     */
    public function setValueBoundToModel(bool $trueOrFalse) : UI5Value
    {
        $this->valueBoundToModel = $trueOrFalse;
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Interfaces\UI5ValueBindingInterface::buildJsValueBindingOptions()
     */
    public function buildJsValueBindingOptions()
    {
        return '';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Interfaces\UI5ValueBindingInterface::buildJsValueBinding()
     */
    public function buildJsValueBinding($customOptions = '')
    {
        $js = <<<JS
            {
                path: "{$this->getValueBindingPath()}",
                {$this->buildJsValueBindingOptions()}
                {$customOptions}
            }
JS;
                return $js;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Interfaces\UI5ValueBindingInterface::setValueBindingPath()
     */
    public function setValueBindingPath($string)
    {
        $this->valueBindingPath = $string;
        $this->setValueBoundToModel(true);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Interfaces\UI5ValueBindingInterface::getValueBindingPath()
     */
    public function getValueBindingPath() : string
    {
        if ($this->valueBindingPath === null) {
            $widget = $this->getWidget();
            $model = $this->getView()->getModel();
            if ($model->hasBinding($widget, $this->getValueBindingWidgetPropertyName())) {
                return $model->getBindingPath($widget, $this->getValueBindingWidgetPropertyName());
            }
            return $this->getValueBindingPrefix() . $this->getWidget()->getDataColumnName();
        }
        return $this->valueBindingPath;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Interfaces\UI5ValueBindingInterface::getValueBindingPrefix()
     */
    public function getValueBindingPrefix() : string
    {
        return $this->valueBindingPrefix;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Interfaces\UI5ValueBindingInterface::setValueBindingPrefix()
     */
    public function setValueBindingPrefix(string $value) : UI5ValueBindingInterface
    {
        $this->valueBindingPrefix = $value;
        $this->setValueBoundToModel(true);
        return $this;
    }
    
    protected function buildJsPropertyWidth()
    {
        $dim = $this->getWidget()->getWidth();
        if ($dim->isFacadeSpecific() || $dim->isPercentual()) {
            $val = $dim->getValue();
        } else {
            // TODO add support for relative units
            $val = $this->buildCssWidthDefaultValue();
        }
        if (! is_null($val) && $val !== '') {
            return 'width: "' . $val . '",';
        } else {
            return '';
        }
    }
    
    protected function buildCssWidthDefaultValue() : string
    {
        return '100%';
    }
    
    protected function buildJsPropertyHeight()
    {
        $dim = $this->getWidget()->getHeight();
        if ($dim->isFacadeSpecific() || $dim->isPercentual()) {
            $val = $dim->getValue();
        } else {
            // TODO add support for relative units
            $val = $this->buildCssHeightDefaultValue();
        }
        if (! is_null($val) && $val !== '') {
            return 'height: "' . $val . '",';
        } else {
            return '';
        }
    }
    
    protected function buildCssHeightDefaultValue()
    {
        return '';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Interfaces\UI5ValueBindingInterface::buildJsValueBindingPropertyName()
     */
    public function buildJsValueBindingPropertyName() : string
    {
        return 'text';
    }

    /**
     * Returns the widget property, that is used for the value binding (i.e. "value" for value-widgets).
     * 
     * NOTE: this is different from buildJsValueBindingPropertyName()! While the latter returns the name
     * of the UI5 control property for the main value, this method returns the name of the widget property,
     * that is used in this binding. I.e. for a simple Value widget (sap.m.Text), the widget property `value`
     * is bound to the control property `text`.
     * 
     * @return string
     */
    protected function getValueBindingWidgetPropertyName() : string
    {
        return 'value';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Interfaces\UI5ValueBindingInterface::isValueBindingDisabled()
     */
    public function isValueBindingDisabled() : bool
    {
        return $this->valueBindingDisabled;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Interfaces\UI5ValueBindingInterface::setValueBindingDisabled()
     * 
     * Note: there is also setValueBoundToModel() with forces binding on or off regardless
     * of any other parameters.
     * 
     * Note: `setValueBoundToModel(false)` and `setValueBindingDisabled(true)` have the same
     * effect, but not `setValueBoundToModel(true)` and `setValueBindingDisabled(false)`
     * because `setValueBindingDisabled(false)` does not force binding - it merely reinstantiates
     * the automatic detection algorithm if it was disabled previously.
     * 
     * @see setValueBoundToModel()
     */
    public function setValueBindingDisabled(bool $value) : UI5ValueBindingInterface
    {
        $this->valueBindingDisabled = $value;
        return $this;
    }
    
    /**
     * {@inheritdoc}
     * @see JqueryLiveReferenceTrait::registerLiveReferenceAtLinkedElement()
     */
    public function registerLiveReferenceAtLinkedElement() 
    {
        $this->registerLiveReferenceAtLinkedElementViaTrait();
        // Also refresh the live reference each time the view is prefilled!
        $this->getController()->addOnViewPrefilledScript($this->buildJsLiveReference());
    }
    
    /**
     * @return void
     */
    protected function registerConditionalBehaviors()
    {
        $this->registerLiveReferenceAtLinkedElement();
        return;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsResetter()
     */
    public function buildJsResetter() : string
    {
        $widget = $this->getWidget();
        $js = '';
        
        if ($widget->getValueWidgetLink() !== null) {
            return '';
        }
        
        if (! $this->isValueBoundToModel()) {
            $staticDefault = $widget->getValueWithDefaults();
            $initialValueJs = json_encode($staticDefault);
            $js = $this->buildJsValueSetter($initialValueJs);
        } else {
            // FIXME #ui5-resetter reset properly if value is bound to model
        }
        
        return $js;
    }
}