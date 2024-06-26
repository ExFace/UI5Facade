<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\DataTypes\MessageTypeDataType;

/**
 * Generates custom sap.m.MessageStrip for a Message widget
 * 
 * @method \exface\Core\Widgets\Message getWidget()
 *
 * @author Andrej Kabachnik
 *        
 */
class UI5Message extends UI5Value
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsConstructor()
     */
    public function buildJsConstructorForMainControl($oControllerJs = 'oController')
    {        
        $this->registerMessageStripCss();
        return <<<JS

        new sap.m.MessageStrip("{$this->getId()}", {
            text: {$this->buildJsValue()},
            {$this->buildJsProperties()}
			{$this->buildJsPropertyType()}
			showIcon: true,
            enableFormattedText: true,
		}).addStyleClass('sapUiResponsiveMargin').addStyleClass('exf-message-strip')

JS;
    }
    
    /**
     * @return void
     */
    protected function registerMessageStripCss()
    {
        $cssWidth = $this->buildCssWidth();
        if ($cssWidth === '100%') {
            return;
        }
        $cssId = $this->getId();
        if (! $this->getUseWidgetId()) {
            $this->setUseWidgetId(true);
            $cssId = $this->getId();
            $this->setUseWidgetId(false);
        }
        $css = "#{$cssId}.exf-message-strip { width: {$cssWidth} }";
        $cssId .= '_color_css';
        
        $this->getController()->addOnInitScript(<<<JS
            
(function(){
    var jqTag = $('#{$cssId}');
    if (jqTag.length === 0) {
        $('head').append($('<style type="text/css" id="{$cssId}"></style>').text('$css'));
    }
})();

JS, false);
        return;
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
            if ($this->getWidget()->hasValue() && $this->getWidget()->getValueExpression()->isReference()) {
                $value = '""';
            } else {
                $value = $this->getWidget()->getValue();
                $caption = $this->getCaption();
                if ($value && $caption) {
                    $value = '<strong>' . $caption . ': </strong> ' . $value;
                } elseif ($caption && ! $value) {
                    $value = $caption;
                }
                $value = '"' . nl2br($this->escapeJsTextValue($value)) . '"';
            }
        } else {
            $value = $this->buildJsValueBinding();
        }
        return $value;
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsPropertyType() : string
    {
        $widget = $this->getWidget();
        $customIcon = $widget->getIcon();
        
        switch ($widget->getType()) {
            case MessageTypeDataType::ERROR:
                $type = 'sap.ui.core.MessageType.Error';
                break;
            case MessageTypeDataType::WARNING:
                $type = 'sap.ui.core.MessageType.Warning';
                break;
            case MessageTypeDataType::SUCCESS:
                $type = 'sap.ui.core.MessageType.Success';
                break;
            case MessageTypeDataType::HINT:
                $type = 'sap.ui.core.MessageType.Information';
                $customIcon = $customIcon ? $customIcon : 'sap-icon://lightbulb';
                break;
            case MessageTypeDataType::INFO:
                $type = 'sap.ui.core.MessageType.Information';
                break;
            case MessageTypeDataType::QUESTION:
                $type = 'sap.ui.core.MessageType.Information';
                $customIcon = $customIcon ? $customIcon : 'sap-icon://lightbulb';
                break;
            default:
                $type = 'sap.ui.core.MessageType.None';
        }
        if ($customIcon !== null) {
            $customIcon = $this->getIconSrc($customIcon);
        }
        $customIconString = $customIcon ? "customIcon: '{$customIcon}'," : '';
        return "type: $type, {$customIconString}";
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Value::buildCssHeightDefaultValue()
     */
    protected function buildCssHeightDefaultValue()
    {
        return '';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Value::buildCssWidthDefaultValue()
     */
    protected function buildCssWidthDefaultValue() : string
    {
        return '';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Value::buildJsValueBindingPropertyName()
     */
    public function buildJsValueBindingPropertyName() : string
    {
        return 'text';
    }
    
    /**
     * No label required, as the caption is already part of the message!
     * 
     * @see \exface\UI5Facade\Facades\Elements\UI5Value::getRenderCaptionAsLabel()
     */
    protected function getRenderCaptionAsLabel(bool $default = false) : bool
    {
        return false;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Value::buildJsValueSetterMethod()
     */
    public function buildJsValueSetterMethod($valueJs)
    {
        return "setText({$valueJs} || '')";
    }
}