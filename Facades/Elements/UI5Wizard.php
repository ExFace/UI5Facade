<?php
namespace exface\UI5Facade\Facades\Elements;

/**
 * Creates a sap.m.Wizard for a Wizard widget. 
 * 
 * The wizard control is wrapped in a sap.m.Page to allow a global toolbar at the bottom.
 * 
 * If the wizard is placed inside another control, the wrapper page becomes invisible
 * (height=0) for some reason. In this case, the height is set explicitly to the height
 * of the first step (see `registerHeightFix()`) to make sure the first step does not
 * need scrolling, while the scroll-behavior of the next/previous buttons still works.
 * 
 * @method \exface\Core\Widgets\Wizard getWidget()
 * @author tmc
 * @author Andrej Kabachnik
 *
 */
class UI5Wizard extends UI5Container
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Container::buildJsConstructor()
     */
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {   
        $this->registerHeightFix();
        $wizardConstructorJs = $this->buildJsConstructorForWizard($oControllerJs);
        $this->getController()->addOnHideViewScript($this->buildJsResetter());
        
        if ($this->hasPageWrapper() === true) {
            return $this->buildJsPageWrapper($wizardConstructorJs);
        }
        return $wizardConstructorJs;
    }
    
    /**
     * This function creates and returns the JS-code for the UI5 `sap.m.Wizard`.
     * This includes an instance of `sap.m.Page` the wizard is wrapped in and an
     * `sap.m.OverflowToolbar` for some buttons which may be used in the wizard.
     * The function then calls the constructor for the `WizardPages`, which are packed
     * into the wizards content.
     * 
     * @param string $oControllerJs
     * @return string
     */
    protected function buildJsConstructorForWizard(string $oControllerJs) : string
    {           
        $this->checkWizardStepIconAttributes();
        $this->registerStepHints();
        
        if ($this->getWidget()->hasButtons() === true) {
            $wizardTbEl = $this->getFacade()->getElement($this->getWidget()->getToolbarMain());
            $toolbar = $wizardTbEl->buildJsConstructor();
        }
        
        $title = $this->getCaption() ? "title: '{$this->getCaption()}'," : 'showHeader: false,';
        
        return <<<JS

        new sap.m.Page('{$this->getId()}-page', {
            {$title}
            content: [
                new sap.m.Wizard("{$this->getId()}", {
                    showNextButton: false,
                    renderMode: "Page",
                    steps: [
                        {$this->buildJsChildrenConstructors()}
                    ]
                })
            ],
            footer: [
                {$toolbar}
            ]
        })

JS;
    }
 
    /**
     * Adds native tooltips (from each step's `hint`) to the steps in the progress navigator.
     * 
     * The `sap.m.WizardStep` control does not expose its tooltip on the numbered step in the
     * progress navigator (the timeline at the top). The navigator renders every step as an
     * `<li data-sap-ui-wpn-step="N">`, so we set the `title` attribute on those elements after
     * every render of the view.
     * 
     * @return UI5Wizard
     */
    protected function registerStepHints() : UI5Wizard
    {
        $hints = [];
        foreach ($this->getWidget()->getSteps() as $step) {
            $hint = $step->getHint();
            if ($hint !== null && $hint !== '') {
                $hints[$step->getStepNumber()] = $hint;
            }
        }
        if (empty($hints)) {
            return $this;
        }
        $hintsJson = $this->escapeString($hints, false);
        $js = <<<JS

            (function(){
                var oHints = {$hintsJson};
                var fnApplyHints = function(){
                    var jqNav = $('#{$this->getId()}-progressNavigator');
                    Object.keys(oHints).forEach(function(sStep){
                        jqNav.find('li[data-sap-ui-wpn-step="' + sStep + '"]').attr('title', oHints[sStep]);
                    });
                };
                // Defer to let UI5 finish rendering the navigator before patching titles
                setTimeout(fnApplyHints, 0);
                var oWizard = sap.ui.getCore().byId('{$this->getId()}');
                // The navigator regenerates its <li> elements on step changes, dropping our titles - reapply
                if (oWizard && !oWizard.data('exfHintsBound')) {
                    oWizard.data('exfHintsBound', true);
                    oWizard.attachStepActivate(function(){ setTimeout(fnApplyHints, 0); });
                }
            })();

JS;
        $this->getController()->addOnShowViewScript($js, false);
        return $this;
    }
 
    /**
     * This function checks the UXON icon parameters of all the `WizardPages` and throws an exeption
     * if some, but not all of the `WizardPages` have an icon parameter assigned. 
     * This is checked to prevent the user not recognizing UI5's behaviour with icons on `sap.m.WizardPage`'s,
     * which are not being rendered if not all instances of the `WizardPages` got an icon parameter.
     * 
     * @return UI5Wizard
     */
    protected function checkWizardStepIconAttributes() : UI5Wizard
    {
        //count WizardSteps with icons 
        $wizardSteps = $this->getWidget()->getSteps();
        $iconCounter = 0;
        foreach ($wizardSteps as $step) {
            if ($step->getIcon() != null){
                $iconCounter++;
            }
        }
        //throw exception if there are not enough icons
        if ($iconCounter != sizeof($wizardSteps) && $iconCounter != 0){
            $this->getWorkbench()->getLogger()->logException(new \InvalidArgumentException('Cannot render icons for wizard steps: UI5 requires, that ALL steps have icons - this is not the case!'));
        }
        
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Interfaces\AjaxFacadeElementInterface::buildJsResetter()
     */
    public function buildJsResetter() : string
    {
        return parent::buildJsResetter() . <<<JS

        (function(){
            var oWizard = sap.ui.getCore().byId('{$this->getId()}');
            oWizard.discardProgress(oWizard.getSteps()[0]);
            {$this->getFacade()->getElement($this->getWidget()->getStep(0))->buildJsFocusFirstInput()}
        }());

JS;
    }

    /**
     * Fixes height=0 problems with the wrapping page if the widget is placed inside a parent.
     * 
     * Sets the height of the wrapper page every time the view is shown according
     * to the following calculation: `page height` = `progress bar height` + `first step height`.
     * All summands include paddings and margins!
     * 
     * @return void
     */
    protected function registerHeightFix()
    {
        if ($this->getWidget()->hasParent()) {
            $fixHeightJs = <<<JS
            
            setTimeout(function(){
                var jqWizardNav = $('#{$this->getId()}-progressNavigator');
                var jqFirstStep = $('#{$this->getFacade()->getElement($this->getWidget()->getStep(0))->getId()}');
                $('#{$this->getId()}-page').css('min-height', 'calc(' + jqWizardNav.outerHeight() + 'px + ' + jqFirstStep.outerHeight() + 'px + 32px)');
            }, 0);
            
JS;
            $this->getController()->addOnShowViewScript($fixHeightJs, false);
        }
    }
}