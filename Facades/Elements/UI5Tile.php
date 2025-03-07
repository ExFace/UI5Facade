<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Widgets\Display;
use exface\Core\DataTypes\NumberDataType;

/**
 * Tile widget for OpenUI5-Facade.
 * 
 * @method \exface\Core\Widgets\Tile getWidget()
 * 
 * @author SFL
 *
 */
class UI5Tile extends UI5Button
{

    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Button::buildJsConstructor()
     */
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {
        $widget = $this->getWidget();
        
        $this->registerExternalModules($this->getController());
        
        $header = $this->getCaption() ? 'header: ' . $this->escapeString($widget->getTitle(), true) . ',' : '';
        $handler = $this->buildJsClickViewEventHandlerCall();
        $press = $handler !== '' ? 'press: ' . $handler . ',' : '';
        $tileClass = '';
        
        if ($widget->getWidth()->isUndefined() === false) {
            $container = $this->getFacade()->getElement($widget->getParent());
            if (($container instanceof UI5Tiles) && $container->isStretched() === true) {
                $tileClass .= ' exf-stretched';
                switch ($widget->getWidth()->getValue()) {
                    case '25%': $tileClass .= ' exf-col-3'; break;
                    case '33%': $tileClass .= ' exf-col-4'; break;
                    case '50%': $tileClass .= ' exf-col-6'; break;
                    case '100%': $tileClass .= ' exf-col-12'; break;
                }
            }
        }
        
        if ($widget->hasDisplayWidget()) {
            // If we have a content widget defined, render it.
            $elem = $this->getFacade()->getElement($widget->getDisplayWidget());
            $tileContentConstructor = $this->buildJsTileContentConstructor($elem, $oControllerJs);
            if (($elem instanceof UI5Icon) && ($icon = $elem->buildJsConstructorForIcon())) {
                // If the content is an icon, don't use header/subheader of the tile, but only 
                // display the icon.
                $header = '';
                $tileClass .= " exf-icon-tile";
            } else {
                // for all other content widgets, add a subheader, if it is not empty
                $subheader = $widget->getSubtitle() ? 'subheader: ' . $this->escapeString($widget->getSubtitle(), true) . ',' : '';
            }
        } else {
            // If there is no content widget, see if we can create tile content from the subtitle and the icon
            $subtitle = $widget->getSubtitle();
            // NOTE: tiles in UI5 don't have an icon by default! If multiple tiles have the same icon, it simply
            // doesn't look good and that's exactly what happens in NavTiles.
            $icon = $widget->getShowIcon(false) ? $widget->getIcon() : null;
            if ($subtitle && ! $icon) {
                // If we have a subtitle and no icon, use sap.m.FeedContent
                $tileContentConstructor = <<<JS
    
                    new sap.m.FeedContent({
    					contentText: "{$this->escapeJsTextValue($subtitle)}"
    				}),
    
JS;
            } elseif ($icon) {
                // Otherwise put the subtitle into the subheader and use the icon as content
                $subheader = 'subheader: ' . $this->escapeString($subtitle,true) . ',';
                $tileContentConstructor = $this->buildJsTileContentConstructorForIcon($icon);
            }
        } 
        
        if ($widget->isHidden()) {
            $visible = 'visible: false,';
        }
        
        $footerText = $widget->getFooterText() !== null ? $widget->getFooterText() : '';
        
        return <<<JS

new sap.m.GenericTile("{$this->getId()}", {
    {$header}
    {$subheader}
    {$press}
    {$visible}
    tileContent: [
        new sap.m.TileContent({
            footer: "{$this->escapeJsTextValue($footerText)}",
            content: [
                {$tileContentConstructor}
            ]
        })
    ]
}).addStyleClass("sapUiTinyMarginBegin sapUiTinyMarginTop tileLayout {$tileClass} {$this->buildCssElementClass()} {$this->buildCssWidgetClass()}")
JS;
    }
     
    /**
     * 
     * @param UI5AbstractElement $element
     * @param string $oControllerJs
     * @return string
     */
    protected function buildJsTileContentConstructor(UI5AbstractElement $element, string $oControllerJs = 'oController') : string
    {
        $widget = $this->getWidget();
        switch (true) {
            case ($element instanceof UI5Icon) && ($icon = $element->buildJsConstructorForIcon()):
                return <<<JS
                
                    new sap.m.VBox({
                        width: "100%",
                        alignItems: "Center",
                        items: [
                            {$icon}.addStyleClass("sapUiSmallMargin"),
                            new sap.m.Title({
                                text: "{$this->escapeJsTextValue($widget->getCaption())}"
                            })
                        ]
                    })
                    
JS;
            case $element->getWidget() instanceof Display && $element->getWidget()->getValueDataType() instanceof NumberDataType:
                $contentElem = new UI5KPI($element->getWidget(), $this->getFacade());
                $contentElem->getWidget()->setHideCaption(true);
                if ($widget->getShowIcon(false) && $widget->getIcon()) {
                    $contentElem->setIcon($widget->getIcon());
                }
                return $contentElem->buildJsConstructor($oControllerJs);
        }
        return $element->buildJsConstructorForMainControl($oControllerJs);
    }
    
    /**
     * 
     * @param string $icon
     * @return string
     */
    protected function buildJsTileContentConstructorForIcon(string $icon) : string
    {
        if ($icon) {
            return <<<JS
        
                new sap.m.ImageContent({
            		src: "{$this->getIconSrc($icon)}"
            	})

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
        return 'exf-tile';
    }
}
