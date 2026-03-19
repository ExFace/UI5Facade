<?php
namespace exface\UI5Facade\Facades\Elements;

/**
 * Generates a sap.m.Panel intended to contain tiles (see. UI5Tile).
 * 
 * @method \exface\Core\Widgets\Pad getWiget()
 * 
 * @author Andrej Kabachnik
 *
 */
class UI5Pad extends UI5Container
{
    
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {
        $childConstructorsJs = $this->buildJsChildrenConstructors();
        
        if ($this->getWidget()->hasBackgroundImage()) {
            $this->registerCustomCSS("#{$this->getId()}_outer {background: url('{$this->getWidget()->getBackgroundImageURL()}'); background-position:center;}");
        }
        
        $panel = <<<JS
        
                new sap.m.Panel("{$this->getId()}", {
                    content: [
                        {$childConstructorsJs}
                    ],
                    {$this->buildJsProperties()}
                }).addStyleClass("{$this->buildCssElementClass()}")
                
JS;
        if ($this->getWidget()->getCenterContent() === true) {
            $panel = $this->buildJsCenterWrapper($panel);
        }
                
        if ($this->hasPageWrapper() === true) {
            return $this->buildJsPageWrapper($panel);
        }
        
        return $panel;
    }
    
    protected function buildJsCenterWrapper(string $content) : string
    {
        return <<<JS
        
                        new sap.m.FlexBox('{$this->getId()}_outer', {
                            {$this->buildJsPropertyHeight()}
                            {$this->buildJsPropertyVisibile()}
                            justifyContent: "Center",
                            alignItems: "Center",
                            items: [
                                {$content}
                            ]
                        })
                        
JS;
    }
    
    protected function buildJsPropertyHeaderText() : string
    {
        if ($caption = $this->getCaption()) {
            return <<<JS
            
                    headerText: "{$caption}",
                    
JS;
        }
        return '';
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildCssElementClass()
     */
    public function buildCssElementClass()
    {
        return 'exf-pad';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::needsContainerContentPadding()
     */
    public function needsContainerContentPadding() : bool
    {
        return false;
    }
}