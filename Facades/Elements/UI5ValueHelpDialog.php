<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Widgets\DataTable;

/**
 * Generates a sap.m.Dialog for a Value-Help-Dialog.
 * 
 * ## How to use
 * 
 * Instantiate the dialog in the handler script of the button-press-event
 * for the button, that should open the dialog.
 * 
 * ```php
 * 
 * $vhpd = new UI5ValueHelpDialog($tableWidgetToRenderInTheDialog, $facade);
 * $vhpd->addOnCloseScript('alert("You have selected: "' . $vhpd->buildJsValueGetter() . ')');
 * $vhpdConstrJs = $vhpd->buildJsConstructor($oControllerJs);
 * $resultingJS = <<<JS
 *      
 *      var oDialog = {$vhpdConstrJs};
 *      oDialog.open();
 *  
 * JS;
 * 
 * ```
 * 
 * @method DataTable getWidget()
 *
 * @author Andrej Kabachnik
 *        
 */
class UI5ValueHelpDialog extends UI5DataTable
{
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {
        $this->getPaginatorElement()->registerControllerMethods();
        return $this->buildJsValueHelpDialogConstructor($oControllerJs);
    }   
                    
    protected function buildJsValueHelpDialogConstructor(string $oControllerJs) : string
    {
        return <<<JS

            new sap.m.Dialog({
                content: [
                    todo: "Title, etc.",
                    {$this->buildJsValueHelpTableWrapper($oControllerJs, $this->buildJsConstructorForControl($oControllerJs))},
                ],
                todo: "toolbar",
                close: {$this->getController()->buildJsEventHandler($this, 'close', true)}
            })

JS;
    }
    
    protected function buildJsValueHelpTableWrapper(string $oControllerJs, string $tableConstructorJs) : string
    {
        // TODO add a special table wrapper instead of UI5DataElementTrait::buildJsPage().
        return $tableConstructorJs;
    }
    
    /**
     * 
     * @param string $js
     * @return UI5ValueHelpDialog
     */
    public function addOnCloseScript(string $js) : UI5ValueHelpDialog
    {
        $this->getController()->addOnEventScript($this, 'close', $js);
    }
}