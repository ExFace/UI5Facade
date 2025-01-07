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
                // Sometimes the DOM structure of ToastUI gets disrupted during initialization.
                // We can detect if the DOM structure was disrupted and repeat initialization if necessary.
                if (($('#{$this->getId()}').find('.toastui-editor-contents').length === 0)) {
                    {$markdownVar} = {$this->buildJsMarkdownInitViewer()};
                }
                
                var oHtml = sap.ui.getCore().byId('{$this->getId()}');
                if (oHtml && "_toastUiBinding" in oHtml && oHtml._toastUiBinding) {
                    return;
                }
                
                var oModel = oHtml.getModel();
                if(oModel !== undefined) {
                    var sBindingPath = '{$this->getValueBindingPath()}';
                    var oValueBinding = new sap.ui.model.Binding(oModel, sBindingPath, oModel.getContext(sBindingPath));
                    
                    oValueBinding.attachChange(function(oEvent){
                        setTimeout(function(){
                            var sVal = oModel.getProperty(sBindingPath);
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
}