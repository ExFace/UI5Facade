<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Widgets\Tiles;

/**
 * Generates a sap.m.Panel intended to contain tiles (see. UI5Tile).
 * 
 * @method Tiles getWiget()
 * 
 * @author Andrej Kabachnik
 *
 */
class UI5Tiles extends UI5Container
{
    
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {
        $tiles = $this->buildJsChildrenConstructors();        
        if ($this->getWidget()->getCenterContent(false) === true) {
            $tiles = $this->buildJsCenterWrapper($tiles);
        }
        
        $panel = <<<JS

                new sap.m.Panel("{$this->getId()}", {
                    {$this->buildJsPropertyHeight()}
                    {$this->buildJsPropertyVisibile()}
                    content: [
                        {$tiles}
                    ],
                    {$this->buildJsProperties()}
                }).addStyleClass("{$this->buildCssElementClass()}")

JS;
                
        if ($this->hasPageWrapper() === true) {
            return $this->buildJsPageWrapper($panel);
        }
        
        return $panel;
    }
    
    protected function buildJsCenterWrapper(string $content) : string
    {
        return <<<JS
        
                        new sap.m.FlexBox({
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
                    
    public function buildJsProperties()
    {
        return parent::buildJsProperties() . $this->buildJsPropertyHeaderText();
    }
    
    public function isStretched() : bool
    {
        $lastWidth = null;
        foreach ($this->getWidget()->getTiles() as $tile) {
            $w = $tile->getWidth();
            if ($w->isUndefined() === true || $w->isPercentual() === false || ($lastWidth !== null && $lastWidth !== $w->getValue())) {
                return false;
            } else {
                $lastWidth = $w->getValue();
            }
        }
        
        return true;
    }
                    
    protected function buildJsPropertyHeaderText() : string
    {
        if ($caption = $this->getCaption()) {
            $escapedCaption = $this->escapeString($caption, false, false);
            return <<<JS

                    headerText: "{$escapedCaption}",

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
        return 'exf-tiles' . ($this->isFillingContainer() ? ' exf-panel-no-border' : '');
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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsPropertyVisibile()
     */
    protected function buildJsPropertyVisibile() : string
    {
        if ($this->getWidget()->isHiddenIfEmpty() && $this->getWidget()->countWidgetsVisible() === 0) {
            return 'visible: false,';
        }
        return parent::buildJsPropertyVisibile();
    }
}