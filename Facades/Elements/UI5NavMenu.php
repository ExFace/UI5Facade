<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\CommonLogic\Model\UiPageTreeNode;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Model\UiPageTreeNodeInterface;

/**
 *
 * @method \exface\Core\Widgets\NavMenu getWidget()
 * @method UI5ControllerInterface getController()
 *
 * @author Ralf Mulansky
 *
 */
class UI5NavMenu extends UI5AbstractElement
{

    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsConstructor()
     */
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {
        $menu = $this->getWidget()->setExpandAll(true)->getMenu();
        $output = <<<JS

new sap.tnt.SideNavigation("{$this->getId()}_scrollContainer", {
    expanded: false,
    item: new sap.tnt.NavigationList("{$this->getId()}",{
        items: [{$this->buildNavigationListItems($menu)}]
    })
});

JS;
        
        return $output;
    }
    
    /**
     * 
     * @param UiPageTreeNodeInterface[] $menu
     * @return string
     */
    protected function buildNavigationListItems(array $menu, int $level = 1) : string
    {
        $output = '';
        foreach ($menu as $node) {
            $url = $this->getFacade()->buildUrlToPage($node->getPageAlias());
            if ($level === 1) {
                $icon = ($node->getIcon() && ! Icons::isIconSetSVG($node->getIconSet())) ? $this->getIconSrc($node->getIcon()) : "folder-blank";
            } else {
                $icon = '';
            }
            if ($node->hasChildNodes() === true) {
                $icon = $icon === "folder-blank" ? "open-folder" : $icon ;
                $output .= <<<JS
            
        new sap.tnt.NavigationListItem({
            icon: "{$icon}",
            text: "{$node->getName()}",
            items: [
                // BOF {$node->getName()} SubMenu
                
                {$this->buildNavigationListItems($node->getChildNodes(), $level + 1)}
                
                // EOF {$node->getName()} SubMenu
                ],
            select: function(){sap.ui.core.BusyIndicator.show(0); window.location.href = '{$url}';}
        }),

JS;
            } else {
                $output .= <<<JS

        new sap.tnt.NavigationListItem({
            icon: "{$icon}", 
            text: "{$node->getName()}", 
            select: function(){sap.ui.core.BusyIndicator.show(0); window.location.href = '{$url}';} 
        }),

JS;
            }
        }
        return $output;
    }
}
