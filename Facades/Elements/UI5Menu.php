<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Widgets\Menu;
use exface\Core\Widgets\Button;

/**
 * Generates OpenUI5 controls for menu widgets: 
 * 
 * @method Menu getWidget()
 *
 * @author Andrej Kabachnik
 *        
 */
class UI5Menu extends UI5AbstractElement
{    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsConstructor()
     */
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {
        return <<<JS

    new sap.m.List({
        {$this->buildJsProperties()}
		items: [
			{$this->buildJsButtonsListItems()}
		]
	}).addStyleClass("exf-menu")

JS;
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsButtonsListItems()
    {
        $js = '';
        $last_parent = null;
        
        foreach ($this->getWidget()->getButtons() as $i => $b) {
            if (is_null($last_parent)){
                $last_parent = $b->getParent();
            }
            
            if (($i === 0 && count($this->getWidget()->getButtonGroups()) > 1) || $b->getParent() !== $last_parent){
                $js .= <<<JS

            new sap.m.StandardListItem({
				title: "{$b->getParent()->getCaption()}"
			}).addStyleClass('sapMGHLI'),

JS;
                $last_parent = $b->getParent();
            }
            
            $js .= $this->buildJsButtonListItem($b);
            
        }
        return $js;
    }
    
    /**
     * 
     * @param Button $button
     * @return string
     */
    protected function buildJsButtonListItem(Button $button) : string
    {
        /* @var $btn_element \exface\UI5Facade\Facades\Elements\UI5Button */
        $btn_element = $this->getFacade()->getElement($button);
        
        if ($button->getIcon() && $button->getShowIcon(true) === true) {
            $icon = 'icon: "' . $btn_element->buildCssIconClass($button->getIcon()) . '",';
        } else {
            $icon = '';
        }
        
        $handler = $btn_element->buildJsClickViewEventHandlerCall();
        $press = $handler !== '' ? 'press: ' . $handler . ',' : '';
        $caption = $this->escapeJsTextValue($btn_element->getCaption());
        
        return <<<JS

            new sap.m.StandardListItem({
				title: "{$caption}",
                tooltip: "{$caption}",
                iconDensityAware: true,
				iconInset: true,
                type: "Active",
				{$icon}
				{$press}
			}),

JS;
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsPropertyCaption()
    {
        return ! $this->getCaption() ? '' : <<<JS

        headerText: "{$this->getCaption()}", 

JS;
    }
}
?>