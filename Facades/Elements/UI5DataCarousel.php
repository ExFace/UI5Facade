<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Interfaces\Widgets\iShowSingleAttribute;
use exface\UI5Facade\Facades\Interfaces\UI5ValueBindingInterface;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JqueryDataCarouselTrait;
use exface\Core\Factories\ActionFactory;
use exface\Core\Actions\ShowDialog;

/**
 * Generates OpenUI5 data carousels
 *
 * @author Andrej Kabachnik
 * 
 * @method \exface\Core\Widgets\DataCarousel getWidget()
 *        
 */
class UI5DataCarousel extends UI5Container
{
    use JqueryDataCarouselTrait;
    
    protected function init()
    {
        parent::init(); 
        $this->registerSyncOnMaster();
    }
    
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {
        return <<<JS
        
    new sap.f.FlexibleColumnLayout("{$this->getId()}", {
        layout: sap.f.LayoutType.OneColumn,
        beginColumnPages: [
            {$this->getFacade()->getElement($this->getWidget()->getDataWidget())->buildJsConstructor($oControllerJs)}
        ],
        midColumnPages: [
            {$this->getFacade()->getElement($this->getWidget()->getDetailsWidget())->buildJsConstructor($oControllerJs)}
        ]
    })

JS;
    }
    
    protected function registerSyncOnMaster()
    {
        $dataIsEditable = $this->getDataElement()->isEditable();
        foreach ($this->getWidget()->getDetailsWidget()->getChildrenRecursive() as $child) {
            if (! ($child instanceof iShowSingleAttribute) || ! $child->isBoundToAttribute()) {
                continue;
            }
            if (! $dataIsEditable) {
                $this->getDataElement()->setEditable(true);
            }
            $childElement = $this->getFacade()->getElement($child);
            if ($childElement instanceof UI5ValueBindingInterface) {
                $childElement->setValueBoundToModel(true);
            }
            if ($childElement instanceof UI5ValueBindingInterface) {
                $bindings .= <<<JS

            oControl = sap.ui.getCore().byId("{$childElement->getId()}");
            oBindingInfo = oControl.getBindingInfo("{$childElement->buildJsValueBindingPropertyName()}");
            oBindingInfo.parts[0].path = sPath + "{$childElement->getValueBindingPath()}";
            oControl.setModel(oModel).bindProperty("{$childElement->buildJsValueBindingPropertyName()}", oBindingInfo);
            oControl.setBindingContext(new sap.ui.model.Context(oModel, sPath + "{$childElement->getValueBindingPath()}"));
JS;
            }
        }
        
        // Determine the currently selected row and replace the binding path of each
        // details widget with the path to the selected row in the model of the data
        // widget. This way, they will be bound to each-other.
        // Use a fake show-dialog-action to make the data getter behave as required
        $action = ActionFactory::createFromString($this->getWorkbench(), ShowDialog::class);
        $bindingScript = <<<JS

        (function() {
            var oTable = sap.ui.getCore().byId('{$this->getDataElement()->getId()}');
            var oRowSelected = {$this->getDataElement()->buildJsDataGetter($action)}.rows[0];
            var oModel = oTable.getModel();
            var iRowIdx = oModel.getData().rows.indexOf(oRowSelected);
            var sPath = '/rows/' + iRowIdx;
            var oControl, oBindingInfo;
            {$bindings}
        })();
        
JS;
        
        $this->getDataElement()->addOnChangeScript($bindingScript);
        return $this;
    }
}
?>