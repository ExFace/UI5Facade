<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Facades\AbstractAjaxFacade\Elements\HtmlBrowserTrait;

class UI5Browser extends UI5AbstractElement
{
    use HtmlBrowserTrait;
    
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {        
        $escapedHtml = json_encode($this->buildHtmlIFrame());
        $control = <<<JS
        
        new sap.ui.core.HTML("{$this->getId()}_wrapper", {
            content: {$escapedHtml},
            afterRendering: function(oEvent) {
                {$this->buildJsIFrameInit()}
            }
        })
        .addStyleClass("{$this->buildCssElementClass()} {$this->buildCssWidgetClass()}")
        {$this->buildJsPseudoEventHandlers()}
        
JS;
        if ($this->getWidget()->hasParent() === false) {
            return $this->buildJsPageWrapper($control, '', '', true);
        }
        
        return $control;
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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsBusyIconShow()
     */
    public function buildJsBusyIconShow($global = false)
    {
        if ($global) {
            return parent::buildJsBusyIconShow($global);
        } else {
            return 'sap.ui.getCore().byId("' . $this->getId() . '_wrapper").setBusyIndicatorDelay(0).setBusy(true);';
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsBusyIconHide()
     */
    public function buildJsBusyIconHide($global = false)
    {
        if ($global) {
            return parent::buildJsBusyIconHide($global);
        } else {
            return 'sap.ui.getCore().byId("' . $this->getId() . '_wrapper").setBusy(false);';
        }
    }
}