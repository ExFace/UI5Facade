<?php
namespace exface\UI5Facade\Facades\Elements\Traits;

use exface\Core\Interfaces\Widgets\iHaveContextualHelp;

/**
 * This trait helps generate contextual help buttons. 
 * 
 * @author Andrej Kabachnik
 * 
 * @method iHaveContextualHelp getWidget()
 *
 */
trait UI5HelpButtonTrait {
    
    /**
     * 
     * @param string $oControllerJs
     * @param string $buttonType
     * @return string
     */
    protected function buildJsHelpButtonConstructor(string $oControllerJs = 'oController', string $buttonType = 'Default') : string
    {
        $widget = $this->getWidget();
        if (($widget instanceof iHaveContextualHelp) && false === $widget->getHideHelpButton()) {
            $helpBtnEl = $this->getFacade()->getElement($widget->getHelpButton());
            return <<<JS
            
                    new sap.m.OverflowToolbarButton('{$helpBtnEl->getId()}', {
                        type: sap.m.ButtonType.{$buttonType},
                        icon: "sap-icon://sys-help",
                        text: "{$helpBtnEl->getCaption()}",
                        tooltip: "{$helpBtnEl->getCaption()}",
                        layoutData: new sap.m.OverflowToolbarLayoutData({priority: sap.m.OverflowToolbarPriority.AlwaysOverflow}),
                        press: {$helpBtnEl->buildJsClickViewEventHandlerCall()}
                    }),
                    
JS;
        }
        return '';
    }
}