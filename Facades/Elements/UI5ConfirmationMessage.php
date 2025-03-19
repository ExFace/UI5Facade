<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\DataTypes\MessageTypeDataType;
use exface\UI5Facade\Facades\Interfaces\UI5ConfirmationElementInterface;

/**
 * Generates custom sap.m.Dialog for a ConfirmationMessage widget
 * 
 * @method \exface\Core\Widgets\ConfirmationMessage getWidget()
 *
 * @author Andrej Kabachnik
 *        
 */
class UI5ConfirmationMessage extends UI5Message implements UI5ConfirmationElementInterface
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsConstructor()
     */
    public function buildJsConstructorForMainControl($oControllerJs = 'oController', string $onContinueJs = '', string $onCancelJs = '')
    {        
        $widget = $this->getWidget();
        $this->registerMessageStripCss();
        
        return <<<JS
                new sap.m.Dialog({
				    type: sap.m.DialogType.Message,
                    title: {$this->escapeString($this->getCaption())},
                    content: new sap.m.Text({ text: {$this->escapeString($widget->getQuestionText())} }),
                    {$this->buildJsPropertyContentWidth()}
                    {$this->buildJsPropertyState()}
                    {$this->buildJsPropertyIcon()}
                    beginButton: new sap.m.Button({
                        type: sap.m.ButtonType.Emphasized,
                        text: {$this->escapeString($widget->getButtonContinue()->getCaption())},
                        tooltip: {$this->escapeString($widget->getButtonContinue()->getHint())},
                        press: function () {
                            oDialog.close().destroy();
                            {$onContinueJs}
                        }.bind(this)
                    }),
                    endButton: new sap.m.Button({
                        text: {$this->escapeString($widget->getButtonCancel()->getCaption())},
                        tooltip: {$this->escapeString($widget->getButtonCancel()->getHint())},
                        press: function () {
                            oDialog.close().destroy();
                            {$onCancelJs}
                        }.bind(this)
                    })
                })
JS;
    }

    
    protected function getCaption() : string
    {
        $caption = parent::getCaption();
        if (! $caption) {
            $caption = $this->getWidget()->getType()->getLabelOfValue();
        }
        return $caption;
    }

    protected function buildJsPropertyIcon() : string
    {
        $icon = $this->getWidget()->getIcon();
        if ($icon !== null) {
            $icon = $this->buildCssIconClass($icon);
        } else {
            $msgType = $this->getWidget()->getType()->__toString();
            switch ($msgType) {
                case MessageTypeDataType::QUESTION:
                    $icon = 'sap-icon://question-mark';
                    break;
            }
        }
        return $icon ? 'icon: ' . $this->escapeString($icon) . ',' : '';
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsPropertyContentWidth() : string
    {
        $width = '';
        
        $dim = $this->getWidget()->getWidth();
        switch (true) {
            case $dim->isPercentual():
            case $dim->isFacadeSpecific():
                $width = json_encode($dim->getValue());
                break;
            case $dim->isUndefined():
            case $dim->isRelative():
                $width = json_encode((($dim->getValue() ?? 1) * $this->getWidthRelativeUnit()) . 'px');
                break;
        }
        
        return $width ? 'contentWidth: ' . $width . ',' : '';
    }

    /**
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Interfaces\UI5ConfirmationElementInterface::buildJsConfirmation()
     */
    public function buildJsConfirmation(string $jsRequestData, string $onContinueJs, string $onCancelJs = '') : string
    {
        return <<<JS

            (function(oController){
                var oDialog = {$this->buildJsConstructorForMainControl('oController', $onContinueJs, $onCancelJs)};
                oDialog.open();
            })({$this->getController()->buildJsControllerGetter($this->getFacade()->getElement($this->getWidget()->getParent()))});
JS;
    }

    protected function buildJsPropertyState() : string
    {
        $msgType = $this->getWidget()->getType()->__toString();
        switch ($msgType) {
            case MessageTypeDataType::ERROR:
                $state = 'sap.ui.core.ValueState.Error';
                break;
            case MessageTypeDataType::WARNING:
                $state = 'sap.ui.core.ValueState.Warning';
                break;
            case MessageTypeDataType::SUCCESS:
                $state = 'sap.ui.core.ValueState.None';
                break;
            case MessageTypeDataType::INFO:
            default: $state = 'sap.ui.core.ValueState.Information';
        }
        return $state ? 'state: ' . $state . ',' : '';
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