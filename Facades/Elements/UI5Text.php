<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\DataTypes\TextDataType;

/**
 * Generates sap.m.FormattedText controls for Text widgets
 * 
 * TODO wrap value in <b>, <i>, etc. depending on text formatting
 * 
 * @method \exface\Core\Widgets\Text getWidget()
 *
 * @author Andrej Kabachnik
 *        
 */
class UI5Text extends UI5Display
{
    protected $alignmentProperty = null;
    
    public function buildJsConstructorForMainControl($oControllerJs = 'oController')
    {
        if ($this->getWidget()->getVisibility() === EXF_WIDGET_VISIBILITY_PROMOTED) {
            $this->addElementCssClass('exf-promoted');
        }
        
        if ($this->getWidget()->isMultiLine() === false) {
            return parent::buildJsConstructorForMainControl($oControllerJs);
        }
        
        return <<<JS
        
        new sap.m.FormattedText("{$this->getId()}", {
            {$this->buildJsProperties()}
            {$this->buildJsPropertyValue()}
        }).addStyleClass("{$this->buildCssElementClass()}")
        
JS;
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
     * @see \exface\UI5Facade\Facades\Elements\UI5Display::getWrapping()
     */
    protected function getWrapping() : bool
    {
        return true;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Display::setAlignment()
     */
    public function setAlignment(string $propertyValueJs) : UI5Display
    {
        $this->alignmentProperty = $propertyValueJs;
        return $this;
    }
    
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Display::buildJsPropertyAlignment()
     */
    protected function buildJsPropertyAlignment() : string
    {
        if ($this->alignmentProperty === null) {
            $widgetAlign = $this->getWidget()->getAlign();
            switch ($widgetAlign) {
                case EXF_ALIGN_DEFAULT: $align = 'sap.ui.core.TextAlign.Begin'; break;
                case EXF_ALIGN_OPPOSITE: $align = 'sap.ui.core.TextAlign.End'; break;
                default:
                    $align = json_encode(ucfirst($widgetAlign));
            }
        } else {
            $align = $this->alignmentProperty;
        }
        return "textAlign: {$align},";
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Value::buildJsValue()
     */
    public function buildJsValue()
    {
        if ($this->getWidget()->isMultiLine() === false) {
            return parent::buildJsValue();
        }
        
        if (! $this->isValueBoundToModel()) {
            if ($this->getWidget()->hasValue() && $this->getWidget()->getValueExpression()->isReference()) {
                $value = '""';
            } else {
                $value = nl2br($this->getWidget()->getValue());
                $value = '"' . $this->escapeJsTextValue($value) . '"';
            }
        } else {
            $value = $this->buildJsValueBinding();
        }
        return $value;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Value::buildJsValueBindingPropertyName()
     */
    public function buildJsValueBindingPropertyName() : string
    {
        if ($this->getWidget()->isMultiLine() === false) {
            return parent::buildJsValueBindingPropertyName();
        }
        
        return 'htmlText';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Display::buildJsValueBindingOptions()
     */
    public function buildJsValueBindingOptions()
    {
        if ($this->getWidget()->isMultiLine() && ($this->getWidget()->getValueDataType() instanceof TextDataType)) {
            return <<<JS
            
                formatter: function(value) {
                    if (value == undefined || value == null) {
                        return '';
                    }
                    return ({$this->getValueBindingFormatter()->getJsFormatter()->buildJsFormatter('value')} + '').replace(/([^>\\r\\n]?)(\\r\\n|\\n\\r|\\r|\\n)/g, '$1<br>$2');
                },
                
JS;
        }
        return parent::buildJsValueBindingOptions();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Display::buildJsValueGetterMethod()
     */
    public function buildJsValueGetterMethod()
    {
        if ($this->getWidget()->isMultiLine() === false) {
            return parent::buildJsValueGetterMethod();
        }
        
        return "getHtmlText().replace(/<\\s*\\/?br\\s*[\\/]?>/gi, \"\\n\")";
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Display::buildJsValueSetterMethod()
     */
    public function buildJsValueSetterMethod($valueJs)
    {
        if ($this->getWidget()->isMultiLine() === false) {
            return parent::buildJsValueSetterMethod($valueJs);
        }
        
        return "setHtmlText(({$valueJs} || '').replace(/([^>\\r\\n]?)(\\r\\n|\\n\\r|\\r|\\n)/g, '$1<br>$2'))";
    }
}