<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Facades\AbstractAjaxFacade\Elements\JsSpinnerFilterTrait;

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
    use JsSpinnerFilterTrait;
    
    protected function buildCssWidthOfStepButton() : string
    {
        return '';
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