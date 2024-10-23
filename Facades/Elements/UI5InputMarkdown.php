<?php

namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Facades\AbstractAjaxFacade\Elements\ToastUIEditorTrait;

class UI5InputMarkdown extends UI5Input
{
    use ToastUIEditorTrait;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Text::buildJsConstructorForMainControl()
     */
    public function buildJsConstructorForMainControl($oControllerJs = 'oController')
    {
        $this->registerExternalModules($this->getController());
        return <<<JS

        new sap.ui.core.HTML("{$this->getId()}", {
            content: {$this->escapeString($this->buildHtmlMarkdownEditor())},
            afterRendering: function(oEvent) {
                var {$this->buildJsMarkdownVar()} = {$this->buildJsMarkdownInitEditor()}
            }
        })
JS;
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::registerExternalModules()
     */
    public function registerExternalModules(\exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface $controller) : UI5AbstractElement
    {
        $controller->addExternalModule('libs.exface.custom.toastUi', $this->getFacade()->buildUrlToSource('LIBS.TOASTUI.EDITOR.JS'), 'toastui');
        //$controller->addExternalModule('libs.exface.custom.mermaid', $this->getFacade()->buildUrlToSource('LIBS.MERMAID.JS'), 'mermaid');
        $controller->addExternalCss('vendor/npm-asset/toast-ui--editor/dist/toastui-editor.css');
        return $this;
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::getHeight()
     */
    public function getHeight()
    {
        if ($this->getWidget()->getHeight()->isUndefined()) {
            return (2 * $this->getHeightRelativeUnit()) . 'px';
        }
        return parent::getHeight();
    }

    public function buildJsDataGetter(\exface\Core\Interfaces\Actions\ActionInterface $action = null)
    {
        $a = 0;
        if ($this->getWidget()->isDisplayOnly()) {
            return '{}';
        } else {
            return parent::buildJsDataGetter($action);
        }
    }
}