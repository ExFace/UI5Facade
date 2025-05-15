<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Facades\AbstractAjaxFacade\Elements\ToastUIEditorTrait;

/**
 * Renders Markdown widgets as sap.ui.core.HTML with ToastUI Editor in viewer mode inside
 * 
 * @method \exface\Core\Widgets\Markdown getWidget()
 *
 * @author Andrej Kabachnik
 *        
 */
class UI5Markdown extends UI5Value
{
    use ToastUIEditorTrait;
    
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
                            {$this->buildJsValueSetter("sVal")}
                        }, 0);
                    });
                }
                
                oHtml._toastUiBinding = true;
            }
        })
JS;
    }

    protected function buildJsMarkdownInitEditor(bool $isViewer = false) : string
    {
        $widget = $this->getWidget();
        $contentJs = $this->escapeString($widget->getValueWithDefaults(), true, false);
        
        return <<<JS

            function(){
                var ed = toastui.Editor.factory({
                    el: document.querySelector('#{$this->getId()}'),
                    height: '100%',
                    initialValue: ($contentJs || ''),
                    autofocus: false,
                    viewer: true,
                    events: {
                        change: function(){
                            {$this->getOnChangeScript()} 
                        }    
                    }
                });
                
                return ed;
            }();
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
            return "'auto'";
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

    protected function buildJsImageDataSanitizer(string $value) : string
    {
        return '';
    }
}