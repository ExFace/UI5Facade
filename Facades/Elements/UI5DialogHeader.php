<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Widgets\TextHeading;
use exface\Core\Widgets\WidgetGrid;
use exface\Core\Interfaces\Widgets\iHaveValue;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\Interfaces\Widgets\iDisplayValue;
use exface\Core\Widgets\Input;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\DataTypes\BooleanDataType;

/**
 * Generates the controls inside a sap.uxap.ObjectPageHeader.
 *
 * @author Andrej Kabachnik
 *        
 */
class UI5DialogHeader extends UI5Container
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Container::buildJsConstructor()
     */
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {
        $js = '';
        
        foreach ($this->getWidget()->getWidgets() as $widget) {
            $js .= $this->buildJsConstructorForChild($widget, $oControllerJs) . ",\n";
        }
        
        return $js;
    }   
    
    /**
     * 
     * @param WidgetInterface $widget
     * @param string $oControllerJs
     * @return string
     */
    protected function buildJsConstructorForChild(WidgetInterface $widget, string $oControllerJs) : string
    {
        switch (true) {
            // Render TextHeading widgets as sap.m.Label because no other headings are supported inside a dialog header
            case $widget instanceof TextHeading:
                $js = <<<JS

                    new sap.m.Label({
                        text: {$this->escapeString($widget->getText())}
                    }).addStyleClass('exf-textheading')
JS;
                break;
            // Render any custom value widget or input directly (i.e. if widget type is not generic Display)
            // Also do it for standard Displays with boolean values as they are ultimately rendered as icons
            case $widget instanceof iDisplayValue && ($widget->getWidgetType() !== 'Display' || ($widget->getValueDataType() instanceof BooleanDataType)):
            case $widget instanceof Input:
                $el = $this->getFacade()->getElement($widget);
                
                // Make sure, the sap.m.Label for the caption is placed in a sap.m.HBox if used insied
                // a header because otherwise they will be displayed as two absolutely independent controls 
                $el->setRenderCaptionAsLayout(true, true);
                
                $js = $el->buildJsConstructor($oControllerJs);
                break;
            // Render regular generic value widgets as sap.m.ObjectStatus
            case $widget instanceof iHaveValue:
                $element = new UI5ObjectStatus($widget, $this->getFacade());
                $js = $element->buildJsConstructor($oControllerJs);
                break;
            // Render widget groups as vertical layouts
            case $widget instanceof WidgetGrid:
                $js = $this->buildJsConstructorForVerticalLayout($widget, $oControllerJs);
                break;
        }
        return $js ?? '';
    }
        
    /**
     * 
     * @param iContainOtherWidgets $widget
     * @param string $oControllerJs
     * @return string
     */
    protected function buildJsConstructorForVerticalLayout(iContainOtherWidgets $widget, string $oControllerJs)
    {
        if ($widget->isHidden()){
            return '';
        }
        
        $title = $widget->getCaption() ? 'new sap.m.Title({text: "' . $this->escapeJsTextValue($widget->getCaption()) . '"}),' : '';
        foreach ($widget->getWidgets() as $w) {
            $content .= $this->buildJsConstructorForChild($w, $oControllerJs) . ",\n";
        }

        return <<<JS
        
            new sap.ui.layout.VerticalLayout('{$this->getFacade()->getElement($widget)->getId()}',{
                content: [
                    {$title}
                    {$content}
                ]
            })
            
JS;
    }
}
?>