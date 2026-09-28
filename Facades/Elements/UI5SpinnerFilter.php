<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Facades\AbstractAjaxFacade\Elements\JsSpinnerFilterTrait;
use exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsValueValidityCheckerInterface;
use exface\Core\Widgets\InlineGroup;

/**
 * Creates and renders an InlineGroup with the filter input and +/- buttons.
 * 
 * @method \exface\Core\Widgets\RangeSpinner Filter getWidget();
 * 
 * @author Andrej Kabachnik
 *
 */
class UI5SpinnerFilter extends UI5Filter
{
    use JsSpinnerFilterTrait {
        getWidgetInlineGroup as private buildWidgetInlineGroupViaTrait;
    }
    
    protected function buildCssWidthOfStepButton() : string
    {
        return '';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\JsSpinnerFilterTrait::getWidgetInlineGroup()
     */
    protected function getWidgetInlineGroup() : InlineGroup
    {
        $isFirstBuild = $this->inlineGroup === null;
        $wg = $this->buildWidgetInlineGroupViaTrait();
        if ($isFirstBuild === true) {
            $this->registerAddValidityCheck($wg);
        }
        return $wg;
    }
    
    /**
     * Disables the +/- buttons whenever adding/subtracting the step would result in an invalid
     * value - only works if the input widget's facade element can check this (see
     * `JsValueValidityCheckerInterface`, e.g. implemented by `UI5InputComboTable`).
     * 
     * @param InlineGroup $wg
     * @return void
     */
    protected function registerAddValidityCheck(InlineGroup $wg) : void
    {
        $widget = $this->getWidget();
        $inputWidget = $widget->getInputWidget();
        $checkerElement = $this->getFacade()->getElement($inputWidget);
        if (! ($checkerElement instanceof JsValueValidityCheckerInterface)) {
            return;
        }
        $inputElement = $this->getFacade()->getElement($inputWidget);
        
        $groupWidgets = $wg->getWidgets();
        $prevElement = $this->getFacade()->getElement($groupWidgets[0]);
        $nextElement = $this->getFacade()->getElement($groupWidgets[2]);
        $step = $widget->getValueStep();
        
        $checkJs = <<<JS
(function(){
    var nVal = parseFloat({$inputElement->buildJsValueGetter()});
    if (nVal === null || nVal === undefined || isNaN(nVal)) {
        nVal = 0;
    }
    {$checkerElement->buildJsCheckValueValid('(nVal - ' . $step . ').toString()', "function(bValid){ var oBtn = sap.ui.getCore().byId('{$prevElement->getId()}'); if (oBtn) { oBtn.setEnabled(bValid); } }")}
    {$checkerElement->buildJsCheckValueValid('(nVal + ' . $step . ').toString()', "function(bValid){ var oBtn = sap.ui.getCore().byId('{$nextElement->getId()}'); if (oBtn) { oBtn.setEnabled(bValid); } }")}
})();
JS;
        
        $inputElement->addOnChangeScript($checkJs);
        $this->getController()->addOnShowViewScript($checkJs);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Filter::buildJsConstructor()
     */
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {
        return $this->getFacade()->getElement($this->getWidgetInlineGroup())->buildJsConstructor();
    }
    
    /**
     * adds the PseudoHandler to every element of the InlineGroup
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Filter::addPseudoEventHandler()
     */
    public function addPseudoEventHandler($event, $code)
    {
        $inlineGroupWidgets = $this->getFacade()->getElement($this->getWidgetInlineGroup())->getWidget()->getWidgets();
        
        foreach($inlineGroupWidgets as $widget){
            $this->getFacade()->getElement($widget)->addPseudoEventHandler($event, $code);
        }
        
        return $this;
    }
}