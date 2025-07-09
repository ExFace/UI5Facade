<?php

namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Facades\AbstractAjaxFacade\Elements\ToastUIEditorTrait;
use exface\Core\Widgets\InputMarkdown;

/**
 * UI5 implementation of the corresponding widget.
 * 
 * @see InputMarkdown
 */
class UI5InputMarkdown extends UI5Input
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
                sap.ui.getCore().byId('{$this->getId()}').getModel().setProperty('{$this->getValueBindingPath()}', sVal);
            })({$this->buildJsValueGetter()})
JS);
        return <<<JS

        new sap.ui.core.HTML("{$this->getId()}", {
            content: {$this->escapeString("<div style=\"height:{$this->buildCssHeight()}\"> {$this->buildHtmlMarkdownEditor()} </div>")},
            afterRendering: function(oEvent) {
                // Sometimes the DOM structure of ToastUI gets disrupted during initialization.
                // We can detect if the DOM structure was disrupted and repeat initialization if necessary.
                if (($('#{$this->getId()}').find('.toastui-editor-contents').length === 0)) {
                    {$this->buildJsMarkdownVar()} = {$this->buildJsMarkdownInitEditor()};
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
                            // Do not update if the model does not have this property
                            /* But why not update? This seems to lead to changes remaining in the editor if you
                             * open a dialog, change the text, close it without saving and open the same dialog
                             * again for the same data.
                            if (sVal === undefined) {
                                return;
                            }*/
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

    /**
     *
     * @return string
     */
    protected function buildJsMarkdownVar() : string
    {
        return $this->getController()->buildJsDependentObjectGetter('editor', $this);
    }

    protected function buildJsRequiredGetter(): string
    {
        return $this->getWidget()->isRequired() ? 'true' : 'false';
    }

    protected function buildJsFullScreenToggleClickHandler() : string
    {
        $markdownVarJs = $this->buildJsMarkdownVar();
        $jsController = $this->getController()->buildJsControllerGetter($this);
        
        return <<<JS

                        var jqFullScreenContainer = $('#{$this->getId()}').parent();
                        {$jsController}.setZIndexToMax(jqFullScreenContainer);
                        
                        var oEditor = {$markdownVarJs};
                        var jqBtn = $('#{$this->getFullScreenToggleId()}');
                        var bExpanding = ! jqFullScreenContainer.hasClass('fullscreen');

                        jqBtn.find('i')
                            .removeClass('fa-expand')
                            .removeClass('fa-compress')
                            .addClass(bExpanding ? 'fa-compress' : 'fa-expand');
                        if (bExpanding) {
                            if (jqFullScreenContainer.innerWidth() > 800) {
                                oEditor.changePreviewStyle('vertical');
                            }
                            oEditor._originalParent = jqFullScreenContainer.parent();
                            oEditor._originalIndex = jqFullScreenContainer.index();
                            jqFullScreenContainer.appendTo($('#sap-ui-static')[0]);
                            jqFullScreenContainer.addClass('fullscreen');
                        } else {
                            var iChildCount = oEditor._originalParent.children().length;
                            if (iChildCount !== 0) {
                                var iTargetIndex = Math.min(oEditor._originalParent.children().length, oEditor._originalIndex);
                                oEditor._originalParent.children().eq(iTargetIndex).before(jqFullScreenContainer);
                            } else {
                                jqFullScreenContainer.appendTo(oEditor._originalParent);
                            }
                            
                            oEditor.changePreviewStyle('tab');
                            jqFullScreenContainer.removeClass('fullscreen');
                        }
JS;
    }
}