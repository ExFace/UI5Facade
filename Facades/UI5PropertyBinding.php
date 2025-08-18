<?php
namespace exface\UI5FAcade\Facades;

use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\WidgetPropertyBindingInterface;
use exface\Core\Widgets\Parts\WidgetPropertyBinding;
use exface\UI5Facade\Facades\Elements\UI5AbstractElement;
use exface\UI5Facade\Facades\Interfaces\UI5ValueBindingInterface;

/**
 * A helper class to generate JS-code for UI5 model bindings from widget property bindings
 * 
 * @author Andrej Kabacnik
 */
class UI5PropertyBinding
{
    private $widgetBinding = null;

    private $element = null;

    private $ui5BindingName = null;

    private $modelBindingPath = null;

    private $forceModelBinding = null;

    private $modelBindingDisabled = false;

    private $modelBindingPrefix = null;

    /**
     * 
     * @param \exface\UI5Facade\Facades\Elements\UI5AbstractElement $element
     * @param string $ui5BindingName
     * @param \exface\Core\Widgets\Parts\WidgetPropertyBinding $binding
     */
    public function __construct(UI5AbstractElement $element, string $ui5BindingName, WidgetPropertyBinding $binding)
    {
        $this->widgetBinding = $binding;
        $this->element = $element;
        $this->ui5BindingName = $ui5BindingName;
    }

    /**
     * 
     * @return \exface\Core\Interfaces\Widgets\WidgetPropertyBindingInterface
     */
    public function getWidgetBinding() : WidgetPropertyBindingInterface
    {
        return $this->widgetBinding;
    }

    /**
     * 
     * @return \exface\Core\Interfaces\WidgetInterface
     */
    protected function getWidget() : WidgetInterface
    {
        return $this->getWidgetBinding()->getWidget();
    }

    /**
     * 
     * @return string
     */
    public function buildJsValue() : string
    {
        if (! $this->isBoundToModel()) {
            $widgetBinding = $this->getWidgetBinding();
            if ($widgetBinding->hasValue() && $widgetBinding->getValueExpression()->isReference()) {
                $valueJs = '""';
            } else {
                $valueJs = str_replace("\n", '', $widgetBinding->getValue());
                $valueJs = json_encode($valueJs);
            }            
        } else {
            $valueJs = $this->buildJsModelBinding();
        }
        return $valueJs;
    }

    /**
     * Returns TRUE if this UI5 property is bound to the model and FALSE if it is bound to a static value
     * 
     * @return bool
     */
    public function isBoundToModel() : bool
    {
        if ($this->forceModelBinding !== null) {
            return $this->forceModelBinding;
        }
        
        $widget = $this->getWidget();
        $widgetBinding = $this->getWidgetBinding();
        $model = $this->element->getView()->getModel();
        
        // If the widget can be bound to a data column, but has no column name really
        if ($widget instanceof iShowDataColumn && ! $widget->isBoundToDataColumn()) {
            return false;
        }
        
        // If there is a model binding, obviously return true
        if ($model->hasBinding($widget, $widgetBinding->getPropertyName())){
            return true;
        }
        
        // If the the binding was disabled explicitly, return false
        if ($this->isModelBindingDisabled() === true) {
            return false;
        }
        
        // Otherwise assume model binding unless the widget has an explicit value
        if ($widgetBinding->hasValue() === true) {
            $valueExpr = $widgetBinding->getValueExpression();
        } elseif ($widget instanceof Input && $widget->hasDefaultValue()) {
            // FIXME depends of widget type!
            $valueExpr = $widget->getDefaultValueExpression();
        }
        
        // If the binding is empty (not bound to anything), we obviously not bound to the model.
        // Actually, this should not happen, but it did when binding configs were broken.
        if ($widgetBinding->isEmpty()) {
            return false;
        }
        
        if ($valueExpr && $valueExpr->isStatic() === true) {
            return false;
        }
        
        if ($model->hasBindingConflict($widget, $widgetBinding->getPropertyName()) === true) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Forces value binding on or off for this control.
     * 
     * Note: `forceModelBinding(false)` and `disableModelBinding(true)` have the same
     * effect, but not `forceModelBinding(true)` and `disableModelBinding(false)`
     * because `disableModelBinding(false)` does not force binding - it merely reinstantiates
     * the automatic detection algorithm if it was disabled previously.
     * 
     * @param bool $trueOrFalse
     * @return UI5PropertyBinding
     */
    public function forceModelBinding(bool $trueOrFalse) : UI5PropertyBinding
    {
        $this->forceModelBinding = $trueOrFalse;
        return $this;
    }

    /**
     * 
     * @return bool
     */
    public function isModelBindingForced() : bool
    {
        return $this->forceModelBinding;
    }
    
    /**
     * 
     * @see \exface\UI5Facade\Facades\Interfaces\UI5ValueBindingInterface::isValueBindingDisabled()
     * @return bool
     */
    public function isModelBindingDisabled() : bool
    {
        return $this->modelBindingDisabled;
    }
    
    /**
     * Disables binding this property to the UI5 model
     * 
     * Note: there is also forceModelBinding() with forces binding on or off regardless
     * of any other parameters.
     * 
     * Note: `forceModelBinding(false)` and `disableModelBinding(true)` have the same
     * effect, but not `forceModelBinding(true)` and `disableModelBinding(false)`
     * because `disableModelBinding(false)` does not force binding - it merely reinstantiates
     * the automatic detection algorithm if it was disabled previously.
     * 
     * @see forceModelBinding()
     * @see \exface\UI5Facade\Facades\Interfaces\UI5ValueBindingInterface::setValueBindingDisabled()
     */
    public function disableModelBinding(bool $value) : UI5PropertyBinding
    {
        $this->modelBindingDisabled = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Interfaces\UI5ValueBindingInterface::buildJsValueBinding()
     */
    public function buildJsModelBinding(string $formatter = '', string $customOptions = '')
    {
        $path = $this->getModelBindingPath();
        if ($path === '' || $path === null || $path === '/') {
            throw new WidgetConfigurationError($this->getWidget(), 'Cannot determine model binding for color property of ' . $this->getWidget()->getWidgetType());
        }
        $js = <<<JS
            {
                path: "{}",
                {$formatter}
                {$customOptions}
            }
JS;
                return $js;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Interfaces\UI5ValueBindingInterface::getValueBindingPath()
     */
    public function getModelBindingPath() : string
    {
        if ($this->modelBindingPath === null) {
            $widget = $this->getWidget();
            $widgetProp = $this->getWidgetBinding()->getPropertyName();
            $model = $this->element->getView()->getModel();
            if ($model->hasBinding($widget, $widgetProp)) {
                return $model->getBindingPath($widget, $widgetProp);
            }
            return $this->getModelBindingPrefix() . $this->getWidgetBinding()->getDataColumnName();
        }
        return $this->modelBindingPath;
    }
    
    /**
     * 
     * @see \exface\UI5Facade\Facades\Interfaces\UI5ValueBindingInterface::getModelBindingPrefix()
     *
     * @return string
     */
    public function getModelBindingPrefix() : string
    {
        if ($this->modelBindingPrefix === null && ($this->element instanceof UI5ValueBindingInterface)) {
            return $this->element->getValueBindingPrefix();
        }
        return $this->modelBindingPrefix ?? '/';
    }
    
    /**
     * 
     * @see \exface\UI5Facade\Facades\Interfaces\UI5ValueBindingInterface::buildJsValueBindingPropertyName()
     *
     * @return string
     */
    public function buildJsModelBindingPropertyName() : string
    {
        return $this->ui5BindingName;
    }
}