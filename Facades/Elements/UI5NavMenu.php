<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\CommonLogic\Model\UiPageTreeNode;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\DataTypes\SvgDataType;
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
        $searchItemVisible = count($menu) > 0 ? 'true' : 'false';
        $output = <<<JS

new sap.tnt.SideNavigation("{$this->getId()}_scrollContainer", {
    expanded: false,
    item: new sap.tnt.NavigationList("{$this->getId()}",{
        selectedKey: "{$selectedKey}",
        items: [
            new sap.tnt.NavigationListItem("{$this->getId()}_queryItem", {
                icon: "sap-icon://clear-filter",
                text: "",
                tooltip: '{$this->translate("WIDGET.NAVMENU.RESET_SEARCH")}',
                enabled: true,
                visible: false,
                design: "Action",
                select: function() {
                    // reset current search
                    var oSideNav = sap.ui.getCore().byId("{$this->getId()}_scrollContainer");
                    if (oSideNav._searchField) {
                        oSideNav._searchField.setValue("");
                        oSideNav._searchField.fireLiveChange({ newValue: "" });
                    }
                }
            }),
            {$this->buildNavigationListItems($menu)}
        ]
    }),
    fixedItem: new sap.tnt.NavigationList({
        items: [
            new sap.tnt.NavigationListItem({
                icon: "sap-icon://search",
                text: "{$this->translate('WIDGET.NAVMENU.SEARCH')}",
                design: "Action",
                visible: {$searchItemVisible},
                select: function(oEvent) {
                    var oSideNav = sap.ui.getCore().byId("{$this->getId()}_scrollContainer");
                    var oItem = oEvent.getSource();
                    if (!oSideNav._searchPopover) {
                        oSideNav._searchField = new sap.m.SearchField({
                            liveChange: function(oEvent) {
                                var sQuery = oEvent.getParameter("newValue").toLowerCase();
                                var sRaw = oEvent.getParameter("newValue");
                                var oNavList = sap.ui.getCore().byId("{$this->getId()}");
                                var oQueryItem = sap.ui.getCore().byId("{$this->getId()}_queryItem");
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
                                filterItems(oNavList.getItems().filter(function(o) { return o !== oQueryItem; }));

                                // show active query as item at the top
                                if (oQueryItem) {
                                    if (sRaw !== "") {
                                        oQueryItem.setText('{$this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.SHOWLOOKUPDIALOG.NAME')}: "' + sRaw + '"');
                                        oQueryItem.setVisible(true);
                                    } else {
                                        oQueryItem.setVisible(false);
                                    }
                                }
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
            $isSvgIcon = $level === 1 && $node->getIcon() && Icons::isIconSetSVG($node->getIconSet());
            $tooltip = $node->getDescription() ? $this->escapeString($node->getDescription(), false) : '';

            if ($level === 1) {
                if ($isSvgIcon) {
                    $icon = 'sap-icon://background'; // placeholder for collapsed sidebar popup
                    $svgIcon = 'data:image/svg+xml;utf8,' . rawurlencode(SvgDataType::cast($node->getIcon()));
                } else {
                    $icon = ($node->getIcon()) ? $this->getIconSrc($node->getIcon()) : 'folder-blank';
                    $svgIcon = '';
                }
            } else {
                $icon = '';
                $svgIcon = '';
            }
            if ($node->hasChildNodes() === true && ($this->maxDepth === null || $level < $this->maxDepth)) {
                if (! $isSvgIcon) {
                    $icon = $icon === 'folder-blank' ? 'open-folder' : $icon;
                }
                $expanded = $isInCurrentPath ? 'true' : 'false';
                $output .= <<<JS
            
        new exface.ui5Custom.MultiLevelNavItem({
            key: "{$node->getPageAlias()}",
            icon: "{$icon}",
            svgIcon: "{$svgIcon}",
            text: "{$node->getName()}",
            tooltip: "{$tooltip}",
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
            svgIcon: "{$svgIcon}",
            text: "{$node->getName()}", 
            tooltip: "{$tooltip}",
            select: function(){sap.ui.core.BusyIndicator.show(0); window.location.href = '{$url}';} 
        }),

JS;
            }
        }
        return $output;
    }
}
