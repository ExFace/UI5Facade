<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\DataTypes\MessageTypeDataType;
use exface\UI5Facade\Facades\Interfaces\UI5ConfirmationElementInterface;

/**
 * Generates custom sap.m.Dialog for a ConfirmationMessage widget
 * 
 * @method \exface\Core\Widgets\ConfirmationDialog getWidget()
 *
 * @author Andrej Kabachnik
 *        
 */
class UI5ConfirmationDialog extends UI5Dialog implements UI5ConfirmationElementInterface
{
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
        $widget = $this->getWidget();
        
        return <<<JS

            (function(oController){
                var oDialog = function() {
                    var oDialog = oController.{$this->getController()->buildJsObjectName('editorPopup', $this)};
                    if (oDialog === undefined) {
                        oController.{$this->getController()->buildJsObjectName('editorPopup', $this)} 
                            = oDialog 
                            = {$this->buildJsDialog('oController')};
                        oController.getView().addDependent(oDialog);
                    }
                    return oDialog;
                }();
                var oData = {$this->getFacade()->getElement($widget->getInputWidget())->buildJsDataGetter($widget->getActionConfirmed())};
                oDialog.getModel().setData(oData);
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

    public function isMaximized() : bool
    {
        return false;
    }
}