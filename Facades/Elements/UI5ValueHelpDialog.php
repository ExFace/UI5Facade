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
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5DataTable::buildJsConstructor()
     */
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {
        $tableConstructorJs = parent::buildJsConstructor($oControllerJs);
        
        $body = <<<JS
        sap.ui.getCore().byId("{$this->getId()}__ValueHelpDialog").destroy();
JS;
        $this->getController()->addMethod("handleCancelVHelp", $this, "oEvent", $body);
        
        $body = <<<JS
        alert("OK Pressed!");
JS;
        $this->getController()->addMethod("handleOkayVHelp", $this, "oEvent", $body);
        
        return $this->buildJsValueHelpDialogConstructor($tableConstructorJs, $oControllerJs);
    }   
    
    /**
     * 
     * @param string $tableConstructorJs
     * @param string $oControllerJs
     * @return string
     */
    protected function buildJsValueHelpDialogConstructor(string $tableConstructorJs, string $oControllerJs) : string
    {
        $functionNameExtension = str_replace('_', '', $this->getId());
        $functionNameExtension = ucfirst($functionNameExtension);
        
        return <<<JS
            new sap.m.Dialog( "{$this->getId()}_ValueHelpDialog" ,{
                content: [
                    {$this->buildJsValueHelpTableWrapper($oControllerJs, $tableConstructorJs)},
                ],
                buttons: [
                    new sap.m.Button({
                        text: "OK",
                        press: function () {
                            oController.handleOkayVHelp{$functionNameExtension}();
                        }
                    }),
                    new sap.m.Button({
                        text: "Cancel",
                        press: function () {
                            oController.handleCancelVHelp{$functionNameExtension}();
                        }
                    }),
                ],
                afterClose: {$this->getController()->buildJsEventHandler($this, 'close', true)}
            })

JS;
    }
    
    /**
     * 
     * @param string $oControllerJs
     * @param string $tableConstructorJs
     * @return string
     */
    protected function buildJsValueHelpTableWrapper(string $oControllerJs, string $tableConstructorJs) : string
    {
        return $tableConstructorJs;
    }
    
    /**
     * 
     * @param string $tableConstructorJs
     * @return mixed
     */
    protected function changeDataTableIds(string $tableConstructorJs)
    {
        return str_replace("__DataTable", "__ValueHelpDialog__DataTable", $tableConstructorJs);
    }
    
    /**
     * 
     * @param string $js
     * @return UI5ValueHelpDialog
     */
    public function addOnCloseScript(string $js) : UI5ValueHelpDialog
    {
        $this->getController()->addOnEventScript($this, 'close', $js);
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    protected function getLoadDataOnShowView() : bool
    {
        return false;
    }
}