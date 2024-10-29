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

    protected function buildJsRequiredGetter(): string
    {
        return $this->getWidget()->isRequired() ? 'true' : 'false';
    }

    protected function buildJsFullScreenToggleClickHandler() : string
    {
        $markdownVarJs = $this->buildJsMarkdownVar();
        
        return <<<JS

                        var jqFullScreenContainer = $('#{$this->getId()}').parent().parent();
                        //set the z-index of the fullscreen dynamically so it works with popovers
                        var iZIndex = 0;
                        var iMaxZIndex = 0;
                        var parent = jqFullScreenContainer.parent();
                        if (isNaN(jqFullScreenContainer.css('z-index'))) {
                            //get the maximum z-index of parent elements of the data element
                            while (parent.length !== 0 && parent[0].tagName !== "BODY") {
                                iZIndex = parseInt(parent.css("z-index"));
                                
                                if (!isNaN(iZIndex) && iZIndex > iMaxZIndex) {
                                    iMaxZIndex = iZIndex;
                                }    
                                parent = parent.parent();
                            }
                        
                            //check if the currently found maximum z-index is bigger than the z-index of the app header 
                            var jqHeaderElement = $('.sapUiUfdShellHead');
                            iZIndex = parseInt(jqHeaderElement.css("z-index"));
                            if (!isNaN(iZIndex) && iZIndex > iMaxZIndex) {
                                iMaxZIndex = iZIndex;
                            }
                            
                            iMaxZIndex = iMaxZIndex + 1;
                            jqFullScreenContainer.css('z-index', iMaxZIndex);
                        }
                        
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
                            jqFullScreenContainer.appendTo($('#sap-ui-static')[0]);
                            jqFullScreenContainer.addClass('fullscreen');
                        } else {
                            oEditor.changePreviewStyle('tab');
                            jqFullScreenContainer.appendTo(oEditor._originalParent);
                            jqFullScreenContainer.removeClass('fullscreen');
                        }
JS;
    }
}