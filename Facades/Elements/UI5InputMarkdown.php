<?php

namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Facades\AbstractAjaxFacade\Elements\ToastUIEditorTrait;
use exface\Core\Widgets\InputMarkdown;
use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;

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
            content: {$this->escapeString("<div class=\"exf-inputmarkdown-wrapper\" style=\"height:{$this->buildCssHeight()}\"> {$this->buildHtmlMarkdownEditor()} </div>")},
            afterRendering: function(oEvent) {
                var oHtml = sap.ui.getCore().byId('{$this->getId()}');
                var sBindingPath = '{$this->getValueBindingPath()}';

                // overwrite error states for the editor, sap.ui.core.HTML has no native setValueState, 
                // so validation from UI5Input would otherwise have no effect for InputMarkdown.
                (function(oCtrl) {
                    if (oCtrl === undefined || oCtrl === null || oCtrl.setValueState !== undefined) {
                        return;
                    }

                    oCtrl._valueState = 'None';
                    oCtrl._valueStateText = '';

                    oCtrl.getValueState = function() {
                        return this._valueState || 'None';
                    };

                    oCtrl.getValueStateText = function() {
                        return this._valueStateText || '';
                    };

                    oCtrl.setValueStateText = function(sText) {
                        this._valueStateText = (sText === undefined || sText === null) ? '' : String(sText);
                        // Keep browser-native tooltip text in sync while invalid.
                        if (this.getValueState() === 'Error') {
                            var jqEditorMain = $('#{$this->getId()} .toastui-editor-defaultUI');
                            if (jqEditorMain.length === 0) {
                                jqEditorMain = $('#{$this->getId()} .toastui-editor-main');
                            }
                            jqEditorMain.attr('title', this._valueStateText);
                        }
                        return this;
                    };

                    oCtrl.setValueState = function(sState) {
                        // error or valid state
                        var sNormalizedState = (sState === undefined || sState === null || sState === '') ? 'None' : String(sState);
                        var bError = (sNormalizedState === 'Error');
                        var jqEditorRoot = $('#{$this->getId()} .toastui-editor-defaultUI');

                        this._valueState = sNormalizedState;
                        if (jqEditorRoot.length === 0) {
                            jqEditorRoot = $('#{$this->getId()} .toastui-editor-main');
                        }

                        // error css on toast editor 
                        jqEditorRoot.css('box-sizing', 'border-box');
                        jqEditorRoot.css('outline', bError ? '2px solid #bb0000' : '');
                        jqEditorRoot.css('outline-offset', bError ? '-2px' : '');
                        if (bError) {
                            // validtion error tooltip 
                            if (!this.getValueStateText()) {
                                this._valueStateText = {$this->escapeString($this->getValidationErrorText())};
                            }
                            jqEditorRoot.attr('title', this.getValueStateText());
                        } else {
                            jqEditorRoot.removeAttr('title');
                        }

                        // aria state for testing and accessibility
                        $('#{$this->getId()}').attr('aria-invalid', bError ? 'true' : 'false');

                        return this;
                    };
                })(oHtml);

                // Sometimes the DOM structure of ToastUI gets disrupted during initialization.
                // This also happens whenever the surrounding UI5 container is invalidated (e.g. because
                // a sibling widget toggles its visibility via hidden_if), because the sap.ui.core.HTML
                // content is re-injected on every re-render, wiping the ToastUI DOM.
                // We can detect if the DOM structure was disrupted and repeat initialization if necessary.
                if (($('#{$this->getId()}').find('.toastui-editor-contents').length === 0)) {
                    // Remember whether this is a genuine first init or a re-init after a wipe. Only re-inits
                    // should restore the value from the model - on first inits the PHP-time initialValue
                    // already reflects the prefill state and the model may not be populated yet.
                    var bIsReInit = oHtml && oHtml._toastUiInitialized === true;
                    {$this->buildJsMarkdownVar()} = {$this->buildJsMarkdownInitEditor()};
                    if (oHtml) {
                        oHtml._toastUiInitialized = true;
                    }

                    // The initialValue used by the init snippet is the value at PHP render time and
                    // is outdated after a re-render caused by container invalidation. Restore the current
                    // value from the model so user input entered before the re-render is not lost.
                    if (bIsReInit) {
                        (function(){
                            var oModelRestore = oHtml ? oHtml.getModel() : undefined;
                            if (oModelRestore === undefined) {
                                return;
                            }
                            var sVal = oModelRestore.getProperty(sBindingPath);
                            if (sVal === undefined || sVal === null || sVal === '') {
                                return;
                            }
                            {$this->buildJsValueSetter("sVal")}
                        })();
                    }
                }

                if (oHtml && "_toastUiBinding" in oHtml && oHtml._toastUiBinding) {
                    return;
                }
                
                var oModel = oHtml.getModel();
                // Restore the height of the editor every time the UI5 control is resized.
                sap.ui.core.ResizeHandler.register(oHtml, function(){
                    var jqHtml = $('#{$this->getId()}');
                    let oModel = oHtml.getModel();
                    // While in fullscreen mode, keep filling the whole overlay instead of the configured height.
                    var bIsFullScreen = jqHtml.closest('.exf-inputmarkdown-wrapper').parent().hasClass('fullscreen');
                    {$this->buildJsMarkdownVar()}.setHeight(bIsFullScreen ? '100%' : '{$this->getHeight()}');

                    //for some reason the markdown seems to loose its value after a resize (but only after the first resize?)
                    //so we load the value saved in the binding and call the value setter again
                    if(oModel !== undefined) {
                        var sBindingPath = '{$this->getValueBindingPath()}';
                        var sVal = oModel.getProperty(sBindingPath);
                        {$this->buildJsValueSetter("sVal")}
                    }
                });

                if(oModel !== undefined) {
                    var sBindingPath = '{$this->getValueBindingPath()}';
                    var oValueBinding = new sap.ui.model.Binding(oModel, sBindingPath, oModel.getContext(sBindingPath));
                    
                    oValueBinding.attachChange(function(oEvent){
                        
                        //for some reason it seems like the markdown gets it's value cleared during a prefill when opening a dialog,
                        //still unclear from where and why, but setting a small timeout for the value setting here seems to fix that
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
                        }, 10);
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
    public function registerExternalModules(UI5ControllerInterface $controller) : UI5AbstractElement
    {
        $controller->addExternalModule('libs.exface.toastUi', $this->getFacade()->buildUrlToSource('LIBS.TOASTUI.EDITOR.JS'), 'toastui');
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

                        // The wrapper carries the fixed configured height inline, so it has to be looked
                        // up via its class rather than the element id, which is not guaranteed to be unique
                        // here (sap.ui.core.HTML re-uses the control id on the wrapper's root tag).
                        var jqEditorWrapper = $('#{$this->getId()}').closest('.exf-inputmarkdown-wrapper');
                        var jqFullScreenContainer = jqEditorWrapper.parent();
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
                            // Remember the configured height and let the wrapper (and the editor inside it)
                            // fill the whole fullscreen overlay instead of keeping their normal fixed height.
                            oEditor._originalWrapperHeight = jqEditorWrapper[0].style.height;
                            jqEditorWrapper.css('height', '100%');
                            oEditor.setHeight('100%');
                            // A non-maximized Dialog is a modal sap.m.Dialog (sap.ui.core.Popup), which
                            // forces focus back into its own DOM as soon as something outside it gets
                            // focused. Since we are about to reparent the editor into #sap-ui-static (i.e.
                            // out of the Dialog's DOM), mark it so the Popup treats it as part of itself
                            // and does not steal focus back (see data-sap-ui-integration-popup-content in
                            // the sap.ui.core.Popup API docs, available since UI5 1.75).
                            jqFullScreenContainer.attr('data-sap-ui-integration-popup-content', '');
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
                            jqFullScreenContainer.removeAttr('data-sap-ui-integration-popup-content');
                            
                            oEditor.changePreviewStyle('tab');
                            jqFullScreenContainer.removeClass('fullscreen');
                            jqEditorWrapper.css('height', oEditor._originalWrapperHeight || '{$this->getHeight()}');
                            oEditor.setHeight('{$this->getHeight()}');
                        }
JS;
    }
}