<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Widgets\WizardButton;
use exface\Core\Interfaces\Actions\ActionInterface;

/**
 *
 * @author Andrej Kabachnik
 *        
 * @method WizardButton getWidget()
 *        
 */
class UI5WizardButton extends UI5Button
{
    /**
     * A WizardButton validates it's step, performs it's action and navigates to another step:
     * 
     * 1) validate the button's wizard step first if we are going to leave it
     * 2) perform the regular button's action
     * 3) navigate to the target wizard step
     * 
     * Note, that the action JS will perform step validation in any case - even if the
     * button does not navigate to another step.
     * 
     * {@inheritdoc}
     * @see UI5Button::buildJsClickFunction()
     */
    public function buildJsClickFunction(ActionInterface $action = null, string $jsRequestData = null) : string
    {
        $widget = $this->getWidget();
        $tabsElement = $this->getFacade()->getElement($widget->getWizardStep()->getParent());
        
        $goToStepJs = '';
        $validateJs = '';
        /* @var $nextStep \exface\Core\Widgets\WizardStep */
        if (($nextStepIdx = $widget->getGoToStepIndex()) !== null) {
            $nextStep = $widget->getWizard()->getStep($nextStepIdx);
            $firstInputFocusJs = $this->getFacade()->getElement($nextStep)->buildJsFocusFirstInput();
            $thisStepElement = $this->getFacade()->getElement($widget->getWizardStep());
            
            if ($widget->getValidateCurrentStep() === true) {
                $validateJs = <<<JS
            
                    if({$thisStepElement->buildJsValidator()} === false) {
                        {$thisStepElement->buildJsValidationError()}
                        return;
                    }
                    
JS;
            }
                        $goToStepJs = <<<JS
                    var wizard = sap.ui.getCore().byId('{$tabsElement->getId()}');

                    if ($nextStepIdx < wizard.getProgress()){
                        var destStep = wizard.getSteps()[{$nextStepIdx}];
                        wizard.goToStep(destStep);
                    } else {
                        while (wizard.getProgress() <= $nextStepIdx){
                            wizard.nextStep();
                        }
                    }
                    {$firstInputFocusJs}
                    
JS;
                        
        }
        
        // If the button has an action, the step navigation should only happen once
        // the action is complete!
        if ($this->getWidget()->hasAction() === true) {
            $this->addOnSuccessScript($goToStepJs);
            $actionJs = parent::buildJsClickFunction($action, $jsRequestData);
            $goToStepJs = '';
        } else {
            $actionJs = '{}';
        }
        
        // Make sure to start the JS without a blank line to make sure it can be
        // used with assignments: e.g. `return {$this->buildJsClickFunction()}`
        return <<<JS
            (function(requestData){
                var jqTabs = $('#{$tabsElement->getId()}');
                var oResult;
                {$validateJs}
                
                oResult = {$actionJs}
                {$goToStepJs}

                return oResult;
            })({$jsRequestData});
                    
JS;

    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildCssElementClass()
     */
    public function buildCssElementClass(){
        return 'sapMWizardNextButtonVisible';
    }
   
}