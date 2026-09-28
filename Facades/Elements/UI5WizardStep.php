<?php
namespace exface\UI5Facade\Facades\Elements;

/**
 * A special form to be used within `UI5Wizard` widgets.
 * 
 * @method \exface\Core\Widgets\WizardStep getWidget()
 * @author tmc
 *
 */
class UI5WizardStep extends UI5Form
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Form::buildJsConstructor()
     */
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {
        return $this->buildJsWizardStep();
    }
    
    /**
     * This function creates the code for a `WizardStep` instanciated in the parent `Wizard`,
     * taking care of all its attributes and their settings, whilst the content of the `WizardStep`s is
     * being build with a call of the childrens LayoutConstructor.
     * 
     * @return string
     */
    protected function buildJsWizardStep()
    {
        $widget = $this->getWidget();
        $cssClasses = '';
        $caption = $this->getCaption();
        // `hide_caption` should not hide the text in the IconTabBar! It should rather hide the <h3> DOM
        // element, that is displayed on the top of each step. This is why we add a CSS class to the entire
        // step, that will set `visibility:hidden`. NOTE: `display:none` did not work because if set for
        // the first step, the second steps title would get the number `1.` instead of `2.`!
        if ($widget->getHideCaption()) {
            $cssClasses .= ' exf-wizard-step-hide-caption';
        }
        $toolbar = $widget->getToolbarMain();
        $icon = $widget->getIcon() && $widget->getShowIcon(true) ? $this->getIconSrc($widget->getIcon()) : '';
        $optional = $widget->isOptional() === true ? "optional: true," : '';
        
        if ($widget->getAutofocusFirstInput() === false) {
            $focusFirstInputJs = 'document.activeElement.blur()';
        } else {
            $firstVisibleInput = null;
            foreach ($widget->getInputWidgets() as $input) {
                if ($input->isHidden() === false) {
                    $firstVisibleInput = $input;
                    break;
                }
            }
            if ($firstVisibleInput !== null) {
                $firstVisibleInputEl = $this->getFacade()->getElement($firstVisibleInput);
                if ($firstVisibleInputEl instanceof UI5Input) {
                    $focusFirstInputJs = $firstVisibleInputEl->buildJsSetFocus() . ';';
                }
            }
        }
        
        $introText = str_replace("\n", '', nl2br($widget->getIntro()));
        
        if ($introText !== null){
            $introText = <<<JS
new sap.m.FormattedText({
                htmlText: "{$introText}" 
            }),
JS;
        }
                
        return <<<JS
    new sap.m.WizardStep("{$this->getId()}", {
        title: {$this->escapeString($caption)},
        icon: "{$icon}",
        {$optional}
        activate: function(oEvent) {
            setTimeout(function(){
                $focusFirstInputJs
            }, 500);
        },
        content: [
            {$introText}
            {$this->buildJsLayoutConstructor()},
            {$this->getFacade()->getElement($toolbar)->buildJsConstructor()}.setStyle('Clear').addStyleClass('exf-wizard-step-toolbar-sticky')
        ]
    }).addStyleClass('{$cssClasses}')
JS;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::getCaption()
     */
    protected function getCaption() : string
    {
        return $this->getWidget()->getCaption() ?? '';
    }
}