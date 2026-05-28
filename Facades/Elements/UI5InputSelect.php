<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Interfaces\DataTypes\DataTypeInterface;

/**
 * Generates OpenUI5 CobmoBox or MultiComboBox to represent a select widget
 *
 * @method \exface\Core\Widgets\InputSelect getWidget()
 * 
 * @author Andrej Kabachnik
 *        
 */
class UI5InputSelect extends UI5Input
{
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::buildJsConstructorForMainControl()
     */
    public function buildJsConstructorForMainControl($oControllerJs = 'oController')
    {
        if ($this->getWidget()->getMultiSelect() === true) {
            $control = 'sap.m.MultiComboBox';
        } else {
            $control = 'sap.m.ComboBox';
        }
        
        // open the selectable options when space bar ist pressed
        $js = <<<JS
    var combobox = sap.ui.getCore().byId("{$this->getId()}");
    setTimeout(function(){combobox.open()},0);
    
JS;
        $this->addPseudoEventHandler('onsapspace', $js);
        
                return <<<JS
        new {$control}("{$this->getId()}", {
			{$this->buildJsProperties()}
        }){$this->buildJsPseudoEventHandlers()}
JS;
    }
			
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsProperties()
     */
    public function buildJsProperties()
    {
        return parent::buildJsProperties() . $this->buildJsPropertyItems();
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsPropertyItems() : string
    {
        $widget = $this->getWidget();
        $items = '';
        foreach ($widget->getSelectableOptions() as $key => $value) {
            if ($widget->getMultiSelect() && $key === '') {
                continue;
            }
            $items .= <<<JS
                new sap.ui.core.Item({
                    key: {$this->escapeString($key)},
                    text: {$this->escapeString($value)},
                }),
JS;
        }
        
        return <<<JS
            items: [
                {$items}
            ],
JS;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::buildJsPropertyValue()
     */
    protected function buildJsPropertyValue()
    {
        $widget = $this->getWidget();
        
        if ($this->isValueBoundToModel()) {
            $value = $this->buildJsValueBinding();
        } else {
            if ($widget->getMultiSelect() === true) {
                $val = $widget->getValueWithDefaults();
                switch (true) {
                    case is_array($val) === true:
                        $value = json_encode($val);
                        break;
                    case $val === null:
                        $value = '[]';
                        break;
                    case (stripos($val, $widget->getMultiSelectValueDelimiter()) !== false):
                        $vals = explode($widget->getMultiSelectValueDelimiter(), $val);
                        $value = json_encode($vals);
                        break;
                    default:
                        $value = '["' . $this->escapeJsTextValue($val) . '"]';
                        break;
                }
            } else {
                $value = '"' . $this->escapeJsTextValue($widget->getValueWithDefaults()) . '"';
            }
        }
        
        $property = $widget->getMultiSelect() ? 'selectedKeys' : 'selectedKey';
        return ($value ? $property . ': ' . $value . ',' : '');
    }

    public function buildJsValueBindingOptions(): string
    {
        if(!$this->getWidget()->getMultiSelect()) {
            return '';
        }
        
        return <<<JS

formatter: function(value) {
        if (value === undefined || typeof value !== 'string' ) {
            return value;
        }
    
        return value.split('{$this->getWidget()->getMultiSelectValueDelimiter()}').filter(value => value);
    },
    
JS;

    }


    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsValueGetterMethod()
     */
    public function buildJsValueGetterMethod()
    {
        if ($this->getWidget()->getMultiSelect()) {
            return "getSelectedKeys().join('" . $this->getWidget()->getMultiSelectValueDelimiter() . "')";
        } else {
            return "getSelectedKey()";
        }
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsValueGetterMethod()
     */
    public function buildJsValueSetterMethod($value)
    {
        if ($this->getWidget()->getMultiSelect()) {
            return "setSelectedKeys(function(){
    var val = ({$value} || '');
    if (Array.isArray(val)) {
        return val;
    } else if (val === undefined || val === null || val === '') {
        return [];
    } else if (val.toString().indexOf('{$this->getWidget()->getMultiSelectValueDelimiter()}') > -1) {
        return val.toString().split('{$this->getWidget()->getMultiSelectValueDelimiter()}');
    } else {
        return [val]
    }
}()).fireSelectionChange()";
        } else {
            return "setSelectedKey(({$value} || '')).fireChange({value: ({$value} || '')})";
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::buildJsValueBindingPropertyName()
     */
    public function buildJsValueBindingPropertyName() : string
    {
        return $this->getWidget()->getMultiSelect() ? 'selectedKeys' : 'selectedKey';
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::buildJsValidatorConstraints()
     */
    protected function buildJsValidatorConstraints(string $valueJs, string $onFailJs, DataTypeInterface $type) : string
    {
        $widget = $this->getWidget();
        if ($widget->getMultiSelect() === false) {
            return parent::buildJsValidatorConstraints($valueJs, $onFailJs, $type);
        } else {
            $partValidator = parent::buildJsValidatorConstraints('part', $onFailJs, $type);
            return <<<JS
if ($valueJs !== undefined && $valueJs !== null) {
    $valueJs.toString().split("{$widget->getMultiSelectValueDelimiter()}").forEach(function(part){
        $partValidator
    });
}

JS;
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::buildJsPropertyChange()
     */
    protected function buildJsPropertyChange()
    {
        $eventName = $this->getWidget()->getMultiSelect() === true ? 'selectionChange' : 'change';
        return $eventName . ': ' . $this->getController()->buildJsEventHandler($this, self::EVENT_NAME_CHANGE, true) . ',';
    }
}