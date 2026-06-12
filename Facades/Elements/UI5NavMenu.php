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
        $queryActionLabel = $this->escapeString($this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.SHOWLOOKUPDIALOG.NAME'), false);
        $previousSearchesLabel = $this->escapeString($this->translate('WIDGET.NAVMENU.PREVIOUS_SEARCHES'), false);
        $output = <<<JS

new sap.tnt.SideNavigation("{$this->getId()}_scrollContainer", {
    expanded: false,
    item: new sap.tnt.NavigationList("{$this->getId()}",{
        selectedKey: "{$selectedKey}",
        items: [
            new sap.tnt.NavigationListItem("{$this->getId()}_searchItem", {
                icon: "sap-icon://search",
                text: "{$this->translate('WIDGET.NAVMENU.SEARCH')}",
                design: "Action",
                visible: {$searchItemVisible},
                select: function(oEvent) {
                    // when selecting the search item, open the SideNavigation
                    var oSideNav = sap.ui.getCore().byId("{$this->getId()}_scrollContainer");
                    if (oSideNav && oSideNav.setExpanded) {
                        oSideNav.setExpanded(true);
                    }

                    // instantiate the search popover and re-use it afterwards
                    var oItem = oEvent.getSource();
                    if (!oSideNav._searchPopover) {
                        oSideNav._searchHistoryStorageKey = 'exface.ui5.navmenu.previousSearches';

                        // Read and normalize at most 5 persisted search queries.
                        oSideNav._getStoredSearchQueries = function() {
                            try {
                                var sStoredQueries = window.localStorage.getItem(oSideNav._searchHistoryStorageKey);
                                var aStoredQueries = sStoredQueries ? JSON.parse(sStoredQueries) : [];
                                if (!Array.isArray(aStoredQueries)) {
                                    return [];
                                }
                                return aStoredQueries.filter(function(sStoredQuery) {
                                    return typeof sStoredQuery === "string" && sStoredQuery.trim() !== "";
                                }).slice(0, 5);
                            } catch (oError) {
                                return [];
                            }
                        };

                        // Persist normalized query history back to localStorage.
                        oSideNav._storeSearchQueries = function(aQueries) {
                            try {
                                window.localStorage.setItem(oSideNav._searchHistoryStorageKey, JSON.stringify(aQueries.slice(0, 5)));
                            } catch (oError) {
                                // Ignore storage errors and keep search functional.
                            }
                        };

                        // Rebuild the history list controls from persisted data.
                        oSideNav._refreshSearchHistory = function() {
                            if (!oSideNav._searchHistoryList || !oSideNav._searchHistoryToolbar) {
                                return;
                            }

                            var aStoredQueries = oSideNav._getStoredSearchQueries();
                            oSideNav._searchHistoryList.destroyItems();
                            aStoredQueries.forEach(function(sStoredQuery) {

                                // add each stored query as an item to the history list with the query as custom data for later retrieval
                                oSideNav._searchHistoryList.addItem(new sap.m.StandardListItem({
                                    title: sStoredQuery,
                                    type: sap.m.ListType.Active,
                                    customData: [
                                        new sap.ui.core.CustomData({
                                            key: "query",
                                            value: sStoredQuery
                                        })
                                    ]
                                }));
                            });

                            // set history controls hidden if there are no stored queries to show
                            var bHasHistory = aStoredQueries.length > 0;
                            oSideNav._searchHistoryToolbar.setVisible(bHasHistory);
                            oSideNav._searchHistoryList.setVisible(bHasHistory);
                            if (oSideNav._searchHistoryContainer) {
                                oSideNav._searchHistoryContainer.setVisible(bHasHistory);
                            }
                        };

                        // Insert query at top, remove duplicates and keep max history size.
                        oSideNav._saveSearchQuery = function(sQuery) {
                            var sNormalizedQuery = typeof sQuery === "string" ? sQuery.trim() : "";
                            if (sNormalizedQuery === "") {
                                return;
                            }

                            var aStoredQueries = oSideNav._getStoredSearchQueries().filter(function(sStoredQuery) {
                                return sStoredQuery !== sNormalizedQuery;
                            });
                            aStoredQueries.unshift(sNormalizedQuery);
                            oSideNav._storeSearchQueries(aStoredQueries);
                            oSideNav._refreshSearchHistory();
                        };

                        // Apply query to the search field
                        oSideNav._applySearchQuery = function(sQuery, bRemember) {
                            if (!oSideNav._searchField) {
                                return;
                            }

                            oSideNav._searchField.setValue(sQuery);
                            oSideNav._searchField.fireLiveChange({ newValue: sQuery });
                            if (bRemember === true) {
                                oSideNav._saveSearchQuery(sQuery);
                            }
                        };

                        // Create the search popover with history and controls.
                        oSideNav._createSearchPopover = function() {

                            // toolbar with title and clear history button
                            oSideNav._searchHistoryToolbar = new sap.m.Toolbar({
                                visible: false,
                                content: [
                                    new sap.m.Title({ text: '{$previousSearchesLabel}', level: 'H6', titleStyle: 'H6' }),
                                    new sap.m.ToolbarSpacer(),
                                    new sap.m.Button({
                                        icon: "sap-icon://delete",
                                        type: sap.m.ButtonType.Transparent,
                                        press: function() {
                                            oSideNav._storeSearchQueries([]);
                                            oSideNav._refreshSearchHistory();
                                        }
                                    })
                                ]
                            });

                            // list to show previous search queries, initially hidden
                            oSideNav._searchHistoryList = new sap.m.List({
                                visible: false,
                                mode: sap.m.ListMode.None,
                                itemPress: function(oPressEvent) {
                                    // when pressing a history item, apply the stored query
                                    var oListItem = oPressEvent.getParameter("listItem");
                                    var sStoredQuery = oListItem && oListItem.data("query");
                                    if (sStoredQuery) {
                                        oSideNav._applySearchQuery(sStoredQuery, true);
                                    }
                                }
                            });
                            oSideNav._searchHistoryContainer = new sap.m.VBox({
                                visible: false,
                                items: [oSideNav._searchHistoryToolbar, oSideNav._searchHistoryList]
                            }).addStyleClass("sapUiSmallMarginBegin sapUiSmallMarginEnd sapUiSmallMarginBottom");

                            // the popover, containing the search field and the history controls
                            return new sap.m.Popover({
                                showHeader: false,
                                placement: sap.m.PlacementType.Auto,
                                beforeClose: function() {
                                    // when closing the popover, save the current query to the history
                                    if (oSideNav._searchField) {
                                        oSideNav._saveSearchQuery(oSideNav._searchField.getValue());
                                    }
                                },
                                content: [
                                    new sap.m.VBox({
                                        items: [oSideNav._searchField]
                                    }).addStyleClass("sapUiSmallMarginBegin sapUiSmallMarginEnd sapUiSmallMarginTop sapUiSmallMarginBottom"),
                                    oSideNav._searchHistoryContainer
                                ]
                            });
                        };

                        // search field with fitlering logic
                        oSideNav._searchField = new sap.m.SearchField({
                            liveChange: function(oEvent) {
                                var sQuery = oEvent.getParameter("newValue").toLowerCase();
                                var sRaw = oEvent.getParameter("newValue");
                                var oNavList = sap.ui.getCore().byId("{$this->getId()}");
                                var oQueryItem = sap.ui.getCore().byId("{$this->getId()}_queryItem");
                                var oSearchItem = sap.ui.getCore().byId("{$this->getId()}_searchItem");
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

                                // exclude search field and query item from filtering
                                // (they should always be visible)
                                filterItems(oNavList.getItems().filter(function(o) {
                                    return o !== oQueryItem && o !== oSearchItem;
                                }));

                                // show active query as action item
                                if (oQueryItem) {
                                    if (sRaw !== "") {
                                        oQueryItem.setText('{$queryActionLabel}: "' + sRaw + '"');
                                        oQueryItem.setVisible(true);
                                    } else {
                                        oQueryItem.setVisible(false);
                                    }
                                }
                            },
                            search: function(oEvent) {
                                var sValue = oEvent.getParameter("query") || oEvent.getSource().getValue();
                                if (oEvent.getParameter("clearButtonPressed") === true) {
                                    // reset search query
                                    oSideNav._applySearchQuery("", false);
                                    return;
                                }
                                // Apply the entered query and close the popover on Enter.
                                oSideNav._applySearchQuery(sValue, true);
                                if (oSideNav._searchPopover) {
                                    oSideNav._searchPopover.close();
                                }
                            }
                        });
                        oSideNav._searchPopover = oSideNav._createSearchPopover();
                    }

                    // Refresh each time before opening in case history changed in another tab
                    oSideNav._refreshSearchHistory();
                    oSideNav._searchPopover.openBy(oItem.getDomRef() || oItem);
                }
            }),
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
                    if (oSideNav._applySearchQuery) {
                        oSideNav._applySearchQuery("", false);
                    }
                }
            }),
            {$this->buildNavigationListItems($menu)}
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

            // either use the description as the tooltip or, if there is no description and it's a top level item, use the name as tooltip
            $tooltip = $node->getDescription() 
                ? $this->escapeString($node->getDescription(), false) 
                : ($level === 1 ? $this->escapeString($node->getName(), false) : '');
            $menuItemText = $this->escapeString($node->getName(), false);
            $menuItemKey = $this->escapeString($node->getPageAlias(), false);
            
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
            key: "{$menuItemKey}",
            icon: "{$icon}",
            svgIcon: "{$svgIcon}",
            text: "{$menuItemText}",
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
            key: "{$menuItemKey}",
            icon: "{$icon}", 
            svgIcon: "{$svgIcon}",
            text: "{$menuItemText}", 
            tooltip: "{$tooltip}",
            select: function(){sap.ui.core.BusyIndicator.show(0); window.location.href = '{$url}';} 
        }),

JS;
            }
        }
        return $output;
    }
}
