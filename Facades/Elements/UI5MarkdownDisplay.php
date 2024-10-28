<?php

namespace exface\UI5Facade\Facades\Elements;

use exface\UI5Facade\Facades\Elements\UI5Value;

class UI5MarkdownDisplay extends UI5Value
{
    use ToastUIEditorTrait;

    /**
     * @return void
     */
    protected function init()
    {
        parent::init();

        // Make sure to register the controller var as early as possible because it is needed in buildJsValidator(),
        // which is called by the outer Dialog or Form widget
        $this->getController()->addDependentObject('editor', $this, 'null');
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Text::buildJsConstructorForMainControl()
     */
    public function buildJsConstructorForMainControl($oControllerJs = 'oController')
    {
        $this->registerExternalModules($this->getController());
        $this->addOnChangeScript(<<<JS

            (function(sVal){
                sap.ui.getCore().byId('{$this->getId()}').getModel().setProperty(sVal);
            })({$this->buildJsValueGetter()})
JS);
        return <<<JS

        new sap.ui.core.HTML("{$this->getId()}", {
            content: {$this->escapeString("<div style=\"height:{$this->buildCssHeight()}\"> {$this->buildHtmlMarkdownEditor()} </div>")},
            afterRendering: function(oEvent) {
                var oModel = sap.ui.getCore().byId('{$this->getId()}').getModel();
                var sBindingPath = '{$this->getValueBindingPath()}';
                var oValueBinding = new sap.ui.model.Binding(oModel, sBindingPath, oModel.getContext(sBindingPath));
                oValueBinding.attachChange(function(oEvent){
                    var sVal = oModel.getProperty(sBindingPath);
                    {$this->buildJsValueSetter("sVal")}
                });
                
                {$this->buildJsMarkdownVar()} = {$this->buildJsMarkdownInitEditor()}
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

    /**
     *
     * @return string
     */
    protected function buildJsMarkdownVar() : string
    {
        return $this->getController()->buildJsDependentObjectGetter('editor', $this);
    }
}