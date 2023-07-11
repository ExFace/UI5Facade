<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Facades\AbstractAjaxFacade\Elements\HtmlBrowserTrait;

class UI5Browser extends UI5AbstractElement
{
    use HtmlBrowserTrait;
    
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {
        if ($this->isValueBoundToModel()) {
            $initPropsJs = <<<JS
            
            var oValueBinding = new sap.ui.model.Binding(sap.ui.getCore().byId('{$this->getId()}_wrapper').getModel(), '{$this->getValueBindingPath()}', sap.ui.getCore().byId('{$this->getId()}').getModel().getContext('{$this->getValueBindingPath()}'));
            oValueBinding.attachChange(function(oEvent){
                var mVal = sap.ui.getCore().byId('{$this->getId()}').getModel().getProperty('{$this->getValueBindingPath()}');
                {$this->buildJsValueSetter('mVal')};
            });
            
JS;
        } else {
            $initPropsJs = '';
        }
        
        $escapedHtml = json_encode($this->buildHtmlIFrame());
        $control = <<<JS
        
        new sap.ui.core.HTML("{$this->getId()}_wrapper", {
            content: {$escapedHtml},
            afterRendering: function() {
                {$initPropsJs}
            }
        })
        {$this->buildJsPseudoEventHandlers()}
        
JS;
        if ($this->getWidget()->hasParent() === false) {
            return $this->buildJsPageWrapper($control, '', '', true);
        }
        
        return $control;
    }
    
    public function buildJsValueSetter($value)
    {
        return "$('#{$this->getId()})[0].href = " . $value;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\HtmlBrowserTrait::buildCssElementStyle()
     */
    public function buildCssElementStyle()
    {
        return 'width: 100%; height: calc(100% - 5px); border: 0;';
    }
    
    /**
     * Wraps the given content in a sap.m.Page with back-button, that works with the iFrame.
     *
     * @param string $contentJs
     * @param string $footerConstructor
     * @param string $headerContentJs
     *
     * @return string
     */
    protected function buildJsPageWrapper(string $contentJs) : string
    {
        $caption = $this->getCaption();
        if ($caption === '' && $this->getWidget()->hasParent() === false) {
            $caption = $this->getWidget()->getPage()->getName();
        }
        
        return <<<JS
        
        new sap.m.Page({
            title: "{$caption}",
            showNavButton: true,
            navButtonPress: function(){window.history.go(-1);},
            content: [
                {$contentJs}
            ],
            headerContent: [
            
            ]
        })
        
JS;
    }
}