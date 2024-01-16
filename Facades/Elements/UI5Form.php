<?php
namespace exface\UI5Facade\Facades\Elements;

/**
 * Generates OpenUI5 inputs
 *
 * @author Andrej Kabachnik
 * 
 * @method \exface\Core\Widgets\Form getWidget()
 *        
 */
class UI5Form extends UI5Panel
{
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Panel::buildJsConstructor()
     */
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {
        $widget = $this->getWidget();
        
        if ($widget->hasButtons() === true) {
            $this->registerSubmitOnEnter($oControllerJs);
            $toolbar = $this->buildJsFloatingToolbar();
        } else {
            $toolbar = '';
        }
        
        if ($widget->hasParent() === true) {
            return $this->buildJsLayoutForm($this->getWidget()->getWidgets(), $toolbar, $this->getId());
        } else {
            $headerContent = $widget->getHideHelpButton() === false ? $this->buildJsHelpButtonConstructor($oControllerJs) : '';
            return $this->buildJsPageWrapper($this->buildJsLayoutForm($this->getWidget()->getWidgets(), '', $this->getId()), $toolbar, $headerContent);
        }
    }
    
    /**
     * Adds handlers for the pseudo event `onsapenter` to all input widget of the form if the form
     * has a primary action and the input widget does not have the custom facade option `advance_focus_on_enter`.
     * 
     * @see \exface\Core\Widgets\Form::getButtonWithPrimaryAction()
     * 
     * @param string $oControllerJs
     * @return UI5Form
     */
    protected function registerSubmitOnEnter(string $oControllerJs) : UI5Form
    {
        $widget = $this->getWidget();
        if ($primaryBtn = $widget->getButtonWithPrimaryAction()) {
            $primaryBtnEl = $this->getFacade()->getElement($primaryBtn);
            if (! ($primaryBtnEl instanceof UI5Button)) {
                return $this;
            }
            $primaryActionCall = $primaryBtnEl->buildJsClickEventHandlerCall($oControllerJs);
            if ($primaryActionCall === '') {
                return $this;
            }
            foreach ($widget->getInputWidgets() as $input) {
                $inputEl = $this->getFacade()->getElement($input);
                // Trigger the primary action by enter on any input, but with some exceptions
                // @see similar logic in UI5DataConfigurator::buildJsFilter()
                
                // If the control has an explicit setting for focus management, pay attention to it
                if (method_exists($inputEl, 'getAdvanceFocusOnEnter') && $inputEl->getAdvanceFocusOnEnter() === true) {
                    continue;
                }
                // sap.m.Input fires enter events on itself when an autosuggest item is
                // selected via enter, so we need to wrap the primary action call in an
                // IF here and find out if the event was triggered in the autosuggest.
                // Fortunately the Input loses its focus-frame (CSS class `sapMFocus`)
                // when navigating to the autosuggest, so we check for its presence. If
                // the control does not have the class, we don't trigger the primary action
                // but return the focus to the Input with a little hack. Now if the user
                // presses enter again, the primary action will be triggered
                if ($inputEl instanceof UI5InputComboTable) {
                    $primaryActionCall = <<<JS

(function(){
    var oInput = oEvent.srcControl;
    if (! oInput.$().hasClass('sapMFocus')){
        oInput.$().find('input').focus();
        return;
    }
    $primaryActionCall
})();

JS;
                }
                
                $inputEl->addPseudoEventHandler('onsapenter', $primaryActionCall);
            }
        }
        return $this;
    }
    
    /**
     * Returns the constructor for an OverflowToolbar representing the main toolbar of the dialog.
     *
     * @return string
     */
    protected function buildJsFloatingToolbar()
    {
        return $this->getFacade()->getElement($this->getWidget()->getToolbarMain())->buildJsConstructor();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildCssElementClass()
     */
    public function buildCssElementClass()
    {
        return 'exf-form';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Container::buildJsChangesGetter()
     */
    public function buildJsChangesGetter() : string
    {
        $checks = [];
        foreach ($this->getWidget()->getInputWidgets() as $w) {
            $el = $this->getFacade()->getElement($w);
            $check = $el->buildJsChangesGetter();
            if ($check !== '' && $check !== '[]') {
                $checks[] = $check;
            }
        }
        if (empty($checks)) {
            return '[]';
        }
        
        return "([]).concat(\n" . implode(",\n", $checks) . "\n)";
    }
}