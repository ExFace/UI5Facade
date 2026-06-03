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
    // (optional, null = unlimited)
    private ?int $maxDepth = null;

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
    }),
    fixedItem: new sap.tnt.NavigationList({
        items: [
            new sap.tnt.NavigationListItem({
                icon: "sap-icon://search",
                text: "{$this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.SHOWLOOKUPDIALOG.NAME')}",
                design: "Action",
                select: function(oEvent) {
                    var oSideNav = sap.ui.getCore().byId("{$this->getId()}_scrollContainer");
                    var oItem = oEvent.getSource();
                    if (!oSideNav._searchPopover) {
                        oSideNav._searchField = new sap.m.SearchField({
                            liveChange: function(oEvent) {
                                var sQuery = oEvent.getParameter("newValue").toLowerCase();
                                var oNavList = sap.ui.getCore().byId("{$this->getId()}");
                                if (!oNavList) return;
                                // set items invisible if they dont match query
                                // keep parent items visible, if they have visible children or match query 
                                function filterItems(aItems) {
                                    var bAnyVisible = false;
                                    aItems.forEach(function(oItem) {
                                        var sText = oItem.getText().toLowerCase();
                                        var aSubItems = oItem.getItems ? oItem.getItems() : [];
                                        if (sQuery === "") {
                                            oItem.setVisible(true);
                                            oItem.setExpanded(false);
                                            if (aSubItems.length) filterItems(aSubItems);
                                            bAnyVisible = true;
                                        } else {
                                            var bChildVisible = aSubItems.length > 0 ? filterItems(aSubItems) : false;
                                            var bMatch = sText.includes(sQuery) || bChildVisible;
                                            oItem.setVisible(bMatch);
                                            // expand items in path (parents)
                                            if (bMatch && aSubItems.length > 0) oItem.setExpanded(true);
                                            if (bMatch) bAnyVisible = true;
                                        }
                                    });
                                    return bAnyVisible;
                                }
                                filterItems(oNavList.getItems());
                            }
                        });
                        oSideNav._searchPopover = new sap.m.Popover({
                            showHeader: false,
                            placement: sap.m.PlacementType.Auto,
                            content: [oSideNav._searchField]
                        });
                    }
                    oSideNav._searchPopover.openBy(oItem.getDomRef() || oItem);
                }
            })
        ]
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
            if ($node->hasChildNodes() === true && ($this->maxDepth === null || $level < $this->maxDepth)) {
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
