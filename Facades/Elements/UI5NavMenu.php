<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\CommonLogic\Model\UiPageTreeNode;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Model\UiPageInterface;
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
    // max depth of the menu that is being rendered
    private int $maxDepth = 3;

    private ?UiPageInterface $currentPage = null;

    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsConstructor()
     */
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {
        $menu = $this->getWidget()->setExpandAll(true)->getMenu();
        $this->currentPage = $this->getWidget()->getPage();
        $selectedKey = $this->currentPage->getAliasWithNamespace();
        $output = <<<JS

new sap.tnt.SideNavigation("{$this->getId()}_scrollContainer", {
    expanded: false,
    item: new sap.tnt.NavigationList("{$this->getId()}",{
        selectedKey: "{$selectedKey}",
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
            $isCurrentPage = $this->currentPage !== null && $node->isPage($this->currentPage);
            $isInCurrentPath = $this->currentPage !== null && ($isCurrentPage || $node->isAncestorOf($this->currentPage));
            if ($level === 1) {
                $icon = ($node->getIcon() && ! Icons::isIconSetSVG($node->getIconSet())) ? $this->getIconSrc($node->getIcon()) : "folder-blank";
            } else {
                $icon = '';
            }
            if ($node->hasChildNodes() === true && $level < $this->maxDepth) {
                $icon = $icon === "folder-blank" ? "open-folder" : $icon ;
                $expanded = $isInCurrentPath ? 'true' : 'false';
                $output .= <<<JS
            
        new exface.ui5Custom.MultiLevelNavItem({
            key: "{$node->getPageAlias()}",
            icon: "{$icon}",
            text: "{$node->getName()}",
            expanded: {$expanded},
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

        new exface.ui5Custom.MultiLevelNavItem({
            key: "{$node->getPageAlias()}",
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
