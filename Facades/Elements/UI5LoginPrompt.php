<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Widgets\Form;

class UI5LoginPrompt extends UI5Container
{

    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Container::buildJsConstructor()
     */
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {
        // Disable value binding for all inputs - otherwise common fields (like username/password) 
        // of all forms will be filled simultanuously because of being bound to the same model.
        $widget = $this->getWidget();
        foreach ($this->getWidget()->getInputWidgets() as $input) {
            $this->getFacade()->getElement($input)->setValueBindingDisabled(true);
        }
        
        $this->registerFocusFirstInput();
        
        $iconTabBar = $this->buildJsIconTabBar($oControllerJs);
        $captionJs = json_encode($this->getCaption());
        
        $messageContent = '';
        if ($widget->hasMessages()) {
            foreach ($widget->getMessageList()->getMessages() as $message) {
                if ($this->isStandalone()) {
                    $message->setWidth("{$this->getWidthRelativeUnit()}px");
                }
                switch ($widget->getVisibility()) {
                    case EXF_WIDGET_VISIBILITY_HIDDEN:
                        $message->setHidden(true);
                        break;
                }
                $messageEl = $this->getFacade()->getElement($message);
                $messageContent .= $messageEl->buildJsConstructorForMainControl($oControllerJs) . ',';
            }
        }
        
        $panel = <<<JS

    new sap.m.Panel({
        headerText: $captionJs,
        content: [
            $messageContent
            $iconTabBar
        ]
    }).addStyleClass('sapUiNoContentPadding exf-loginprompt-panel')

JS;
        if ($this->getView()->isWebAppRoot() === true) {
            return $this->buildJsCenterWrapper($panel);
        } else {
            return $panel;
        }
    }
            
    /**
     * Gives keyboard focus to the first visible input (e.g. the "User Name" field) of the
     * first login form once the login prompt has been shown, so the user can start typing
     * right away.
     * 
     * @return void
     */
    protected function registerFocusFirstInput()
    {
        $forms = $this->getWidget()->getWidgets();
        $firstForm = $forms[0] ?? null;
        if ($firstForm === null) {
            return;
        }
        if ($firstForm instanceof Form && $firstForm->getAutofocusFirstInput() === false) {
            return;
        }
        foreach ($firstForm->getInputWidgets() as $input) {
            if ($input->isHidden() === true) {
                continue;
            }
            $firstInputEl = $this->getFacade()->getElement($input);
            if ($firstInputEl instanceof UI5Input) {
                $this->getController()->addOnShowViewScript("setTimeout(function(){ {$firstInputEl->buildJsSetFocus()}; }, 0);", false);
            }
            break;
        }
        return;
    }
    
    /**
     * 
     * @param string $oControllerJs
     * @return string
     */
    protected function buildJsIconTabBar(string $oControllerJs) : string
    {
        return <<<JS
            new sap.m.IconTabBar("{$this->getId()}", {
                showOverflowSelectList: true,
                stretchContentHeight: false,
                items: [
                    {$this->buildJsChildrenConstructors($oControllerJs)}
                ]
            })
JS;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Container::buildJsChildrenConstructors()
     */
    public function buildJsChildrenConstructors(string $oControllerJs = 'oController') : string
    {
        $js = '';
        foreach ($this->getWidget()->getWidgets() as $loginForm) {
            $formEl = $this->getFacade()->getElement($loginForm);
            $js .= $this->buildJsIconTabFilter($formEl, $oControllerJs) . ',';
        }
        return $js;
    }
    
    
    
    /**
     *
     * @return string
     */
    protected function buildJsIconTabFilter(UI5Form $loginFormElement, string $oControllerJs) : string
    {
        $caption = json_encode($loginFormElement->getCaption());
        return <<<JS
                    new sap.m.IconTabFilter({
                        text: {$caption},
                        content: [
                            {$loginFormElement->buildJsConstructor($oControllerJs)}
                        ]
                    })
JS;
    }
    
    /**
     * 
     * @param string $content
     * @return string
     */
    protected function buildJsCenterWrapper(string $content) : string
    {
        return <<<JS
        
                        new sap.m.FlexBox({
                            height: "100%",
                            width: "100%",
                            justifyContent: "Center",
                            alignItems: "Center",
                            items: [
                                {$content}
                            ]
                        }).addStyleClass('exf-loginprompt-flexbox')
                        
JS;
    }
    
    protected function isStandalone() : bool
    {
        return $this->getWidget()->hasParent() === false;
    }
}
