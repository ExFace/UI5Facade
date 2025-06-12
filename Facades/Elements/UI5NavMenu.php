<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\CommonLogic\Model\UiPageTreeNode;
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
        $menu = $this->getWidget()->getMenu();
        $output = <<<JS

new sap.m.ScrollContainer("{$this->getId()}_scrollContainer", {
    horizontal: false,
    vertical: true,
    height: '100%',
    content: [
        new sap.tnt.NavigationList("{$this->getId()}", {
            items: [{$this->buildNavigationListItems($menu)}]
        })
    ]
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
                // TODO why do font-awesome icons like `sap-icon://font-awesome/bug` not work here???
                // $icon = $node->getIcon() ? $this->getIconSrc($node->getIcon()) : "folder-blank";
                $icon = "folder-blank";
            } else {
                $icon = '';
            }
            if ($node->hasChildNodes() === true) {
                $icon = $icon === "folder-blank" ? "open-folder" : '';
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
