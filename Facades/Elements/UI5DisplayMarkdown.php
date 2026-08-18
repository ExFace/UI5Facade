<?php

namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Facades\AbstractAjaxFacade\Elements\ToastUIEditorTrait;
use exface\Core\Widgets\DisplayMarkdown;

/**
 * UI5 implementation of the corresponding widget.
 * 
 * @see DisplayMarkdown
 */
class UI5DisplayMarkdown extends UI5Value
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
        $markdownVar = $this->buildJsMarkdownVar();
        
        return <<<JS

        new sap.ui.core.HTML("{$this->getId()}", {
            content: {$this->escapeString("<div style=\"height:{$this->buildCssHeight()}\"> {$this->buildHtmlMarkdownEditor()} </div>")},
            afterRendering: function(oEvent) {
                var oHtml = sap.ui.getCore().byId('{$this->getId()}');
                var oModel = oHtml ? oHtml.getModel() : undefined;
                var sBindingPath = '{$this->getValueBindingPath()}';

                var bWasInitialized = oHtml && "_toastUiBinding" in oHtml && oHtml._toastUiBinding;

                // Sometimes the DOM structure of ToastUI gets disrupted during initialization
                // or gets wiped when the control is re-rendered (e.g. when switching tabs).
                // We can detect if the DOM structure was disrupted and repeat initialization if necessary.
                if (($('#{$this->getId()}').find('.toastui-editor-contents').length === 0)) {
                    {$markdownVar} = {$this->buildJsMarkdownInitViewer()};

                    // On a re-render (e.g. after a tab switch) the viewer is recreated empty,
                    // because its actual value came from the model at runtime and not from the
                    // initialValue rendered into the page. Re-apply the current model value here -
                    // otherwise the text would be lost, since the binding change handler below is
                    // only attached once and does not fire again if the model value is unchanged.
                    // This must NOT run on the very first initialization: at that point the model
                    // may still be loading and could hold an empty value that would clobber the
                    // correct initialValue. The initial value is handled by initialValue + binding.
                    if (bWasInitialized && oModel !== undefined) {
                        var sModelVal = oModel.getProperty(sBindingPath);
                        if (sModelVal !== undefined) {
                            {$this->buildJsValueSetter("sModelVal")}
                        }
                    }
                }
                
                if (oHtml && "_toastUiBinding" in oHtml && oHtml._toastUiBinding) {
                    return;
                }
                
                if(oModel !== undefined) {
                    var oValueBinding = new sap.ui.model.Binding(oModel, sBindingPath, oModel.getContext(sBindingPath));
                    
                    oValueBinding.attachChange(function(oEvent){
                        setTimeout(function(){
                            var sVal = oModel.getProperty(sBindingPath);
                            // Do not update if the model does not have this property
                            if (sVal === undefined) {
                                return;
                            }
                            {$this->buildJsValueSetter("sVal")}
                        }, 0);
                    });
                }
                
                oHtml._toastUiBinding = true;
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
        $controller->addExternalCss('vendor/npm-asset/toast-ui--editor/dist/toastui-editor.css');

        if ($this->getWidget()->hasRenderMermaidDiagrams()) {
            $controller->addExternalModule('libs.exface.mermaid', $this->getFacade()->buildUrlToSource('LIBS.MERMAID.JS'), 'mermaid');
        }
        
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
}