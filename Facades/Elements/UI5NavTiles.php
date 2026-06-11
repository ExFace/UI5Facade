<?php
namespace exface\UI5Facade\Facades\Elements;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Widgets\Tile;
use exface\Core\Widgets\Tiles;

/**
 * Renders a default container for NavTiles.
 * 
 * @method \exface\Core\Widgets\NavTiles getWidget()
 * 
 * @author Andrej Kabachnik
 *
 */
class UI5NavTiles extends UI5Container
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Container::buildJsConstructor()
     */
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {
        // If the NavTiles is the root widget of a view, it will have a header with the caption
        // of the first tile group - so just hide the caption of that group to avoid duplicates.
        $widget = $this->getWidget();
        if ($widget->hasParent() === false && $widget->hasWidgets()) {
            $widget->getWidgetFirst()->setHideCaption(true);
        }
        if ($widget->isHiddenIfEmpty() && $widget->countWidgetsVisible() === 0) {
            return '';
        }
        return parent::buildJsConstructor($oControllerJs);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Container::buildJsChildrenConstructors()
     */
    public function buildJsChildrenConstructors() : string
    {
        if ($this->getWidget()->isEmpty()) {
            return <<<JS

            new sap.m.FlexBox({
                height: "100%",
                width: "100%",
                justifyContent: "Center",
                alignItems: "Center",
                items: [
                    new sap.m.Text({
                        text: {$this->escapeString($this->getWidget()->getEmptyText())}
                    })
                ]
            })

JS;
        }
        if ($this->hasIconTabBar() === true) {
            $navbar = $this->buildJsIconTabBar();
        } else {
            '';
        }
        return $navbar . ', ' . parent::buildJsChildrenConstructors();
    }

    /**
     * Summary of buildJsIconTabBar
     * @return string
     */
    protected function buildJsIconTabBar() : string
    {
        // note sah: switched from flexbox to overflow toolbar because of layout issues
        // the page was otherwise rendering content/headings underneath the tabbar, and the overflow didnt work correctly
        return <<<JS

        new sap.m.OverflowToolbar("{$this->getId()}_navbox", {
            design: "Solid",
            content: [
                new sap.m.IconTabHeader("{$this->getId()}_iconTabHeader", {
                    mode: "Inline",
                    select: function(oEvent) {
                        setTimeout(function() {
                            // Get the selected key
                            var sKey = oEvent.getParameter("key");

                            // Find the corresponding panel that matches the key and scroll it into view
                            var oPanel = sap.ui.getCore().byId(sKey);
                            var oPanelDom = oPanel ? oPanel.getDomRef() : null;
                            if (!oPanelDom) {
                                return;
                            }
                            oPanelDom.scrollIntoView({ behavior: "smooth" , block: "start" });

                            // briefly flash the panel heading to give visual feedback
                            // (useful for short sections that don't actually scroll)
                            var oHeading = oPanelDom.querySelector(".sapMPanelHdr, .sapMPanelHeader, .sapUiPanelHdr");
                            if (oHeading) {
                                oHeading.classList.add("exf-navtiles-heading-flash");
                                setTimeout(function() {
                                    oHeading.classList.remove("exf-navtiles-heading-flash");
                                }, 800);
                            }
                        }, 0);
                    },
                    items: [
                        {$this->buildJsIconTabBarItems()}
                    ]
                })
                .addStyleClass('customHeader exf-navtiles-tab-header').setLayoutData(new sap.m.OverflowToolbarLayoutData({
                    priority: sap.m.OverflowToolbarPriority.Low,
                    shrinkable: true,
                    minWidth: "14rem"
                })),
                new sap.m.ToolbarSpacer(),
                new sap.m.SearchField({
                    placeholder: "{$this->translate('WIDGET.NAVTILES.SEARCH')}", 
                    liveChange: {$this->buildJsSearchTilesFunction()}
                })
                .addStyleClass('exf-navtiles-search')
                .setLayoutData(new sap.m.OverflowToolbarLayoutData({
                    priority: sap.m.OverflowToolbarPriority.NeverOverflow,
                    shrinkable: false
                }))
            ]
        }).addStyleClass('navTilesToolbar')
        .addEventDelegate({
            onAfterRendering: function() {
                // after rendering, auto-focus the search field 
                // (so the user can start typing immediately)
                var oToolbar = sap.ui.getCore().byId("{$this->getId()}_navbox");
                var oSearch = oToolbar.getContent().find(function(oCtrl) {
                    return oCtrl.isA("sap.m.SearchField");
                });
                if (oSearch) {
                    setTimeout(function() { oSearch.focus(); }, 0);
                }

                // scrollObserver: highlight the matching tab when the user scrolls to a section
                // (register this once per toolbar instance)
                if (oToolbar.data("_exfScrollObserverActive")) {
                    return;
                }
                oToolbar.data("_exfScrollObserverActive", true);

                // short delay so all child panels are rendered before we observe them
                setTimeout(function() {
                    var oTabHeader = sap.ui.getCore().byId("{$this->getId()}_iconTabHeader");
                    if (!oTabHeader || !oTabHeader.getItems().length) { return; }

                    var oToolbarDom = oToolbar.getDomRef();
                    var iToolbarHeight = oToolbarDom ? Math.round(oToolbarDom.getBoundingClientRect().height) : 44;

                    // find the nearest scrollable ancestor; null = viewport (required by IntersectionObserver)
                    var oScrollRoot = null;
                    var oDom = oToolbarDom;
                    while (oDom && oDom !== document.body) {
                        oDom = oDom.parentElement;
                        if (!oDom) { break; }
                        var sOverflow = window.getComputedStyle(oDom).overflowY;
                        if (sOverflow === "auto" || sOverflow === "scroll") {
                            oScrollRoot = oDom;
                            break;
                        }
                    }

                    // track which panel keys are currently inside the active viewport zone
                    var aVisibleKeys = [];

                    var oObserver = new IntersectionObserver(function(aEntries) {
                        aEntries.forEach(function(oEntry) {
                            var sId = oEntry.target.id;
                            if (oEntry.isIntersecting) {
                                if (aVisibleKeys.indexOf(sId) === -1) { aVisibleKeys.push(sId); }
                            } else {
                                aVisibleKeys = aVisibleKeys.filter(function(k) { return k !== sId; });
                            }
                        });

                        // always highlight the topmost visible panel if multiple are visible; 
                        var sActiveKey = null;
                        oTabHeader.getItems().forEach(function(oItem) {
                            if (sActiveKey === null && aVisibleKeys.indexOf(oItem.getKey()) !== -1) {
                                sActiveKey = oItem.getKey();
                            }
                        });

                        if (sActiveKey && oTabHeader.getSelectedKey() !== sActiveKey) {
                            oTabHeader.setSelectedKey(sActiveKey);
                        }
                    }, {
                        root: oScrollRoot,
                        // active zone: from just below the sticky toolbar down to 50% of the viewport
                        rootMargin: '-' + iToolbarHeight + 'px 0px -50% 0px',
                        threshold: 0
                    });

                    // register observer on panels
                    oTabHeader.getItems().forEach(function(oItem) {
                        var oPanel = sap.ui.getCore().byId(oItem.getKey());
                        if (oPanel && oPanel.getDomRef()) {
                            oObserver.observe(oPanel.getDomRef());
                        }
                    });
                }, 200);

            }
        }),

JS;
    }

    /**
     * Summary of buildJsIconTabBarItems
     * @return string
     */
    protected function buildJsIconTabBarItems() : string
    {
        $js = '';
        foreach ($this->getWidget()->getTiles() as $i => $tileGroup) {
            if ($i === 0) {
                // only add to icontabbar if visible, and we have more that 1 group
                if ($this->getWidget()->getDepth() === 1 || count($this->getWidget()->getTiles()) === 1 || $tileGroup->isHidden()) {
                    continue;
                }
            }
            $js .= $this->buildJsIconTabBarItem($tileGroup);
        }
        return $js;
    }

    protected function buildJsIconTabBarItem(Tiles $tileGroup) : string
    {
        $tabCaption = $tileGroup->getCaption();

        // only show the last part of the caption, (only if there is a parent path included (parent > child))
        if ($this->getWidget()->getShowParentPath() && strpos($tabCaption, ' > ') !== false) {
            $tabCaption = StringDataType::substringAfter($tabCaption, ' > ');
        }
        $tabElement = $this->getFacade()->getElement($tileGroup);
        return <<<JS

                new sap.m.IconTabFilter({
                    key: "{$tabElement->getId()}",
                    text: "{$tabCaption}"
                }),
JS;
    }

    protected function hasIconTabBar() : bool
    {
        return $this->getWidget()->hasNavBar();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildCssElementClass()
     */
    public function buildCssElementClass()
    {
        return 'exf-navtiles' . ($this->isFillingContainer() ? ' exf-panel-no-border' : '');
    }

    /**
     * JS function to filter tiles at runtime, by toggling them in-/visible
     * @return string
     */
    protected function buildJsSearchTilesFunction(){
        return <<<JS
        function(oEvent) {
            // normalize search query
            var sQuery = (oEvent.getParameter("newValue") || "").trim().toLowerCase();

            // get root control
            var oRootControl = sap.ui.getCore().byId("{$this->getId()}") || oEvent.getSource();
            if (!oRootControl) {
                return;
            }

            // get all panels that contain tiles 
            var aPanels = oRootControl.findAggregatedObjects(true, function(oControl) {
                return oControl.isA("sap.m.Panel");
            }).filter(function(oPanel) {
                // Exclude panels that were hidden from the beginning. 
                // for example when hiding the first panel (overview), we do not want to search it and suddenly set it visible here
                var bInitiallyVisible;
                if (oPanel.data) {
                    bInitiallyVisible = oPanel.data("_exfInitialVisible");
                    if (typeof bInitiallyVisible !== "boolean") {
                        bInitiallyVisible = oPanel.getVisible() !== false;
                        oPanel.data("_exfInitialVisible", bInitiallyVisible);
                    }
                } else {
                    bInitiallyVisible = oPanel.getVisible() !== false;
                }

                if (bInitiallyVisible === false) {
                    return false;
                }

                // Keep only tile group panels (contain at least one GenericTile).
                var aTiles = oPanel.getContent();
                return Array.isArray(aTiles) && aTiles.some(function(oTile) {
                    return oTile && oTile.isA && oTile.isA("sap.m.GenericTile");
                });
            });

            aPanels.forEach(function(oPanel) {

                var aTiles = oPanel.getContent() || [];
                var bAnyTileVisible = false;

                aTiles.forEach(function(oTile) {
                    if (!oTile || !oTile.isA || !oTile.isA("sap.m.GenericTile")) {
                        return;
                    }

                    // Keep tiles hidden when they were hidden initially.
                    var bTileInitiallyVisible;
                    if (oTile.data) {
                        bTileInitiallyVisible = oTile.data("_exfInitialVisible");
                        if (typeof bTileInitiallyVisible !== "boolean") {
                            bTileInitiallyVisible = oTile.getVisible() !== false;
                            oTile.data("_exfInitialVisible", bTileInitiallyVisible);
                        }
                    } else {
                        bTileInitiallyVisible = oTile.getVisible() !== false;
                    }
                    if (bTileInitiallyVisible === false) {
                        oTile.setVisible(false);
                        return;
                    }

                    // keep searchable text in data attribute to avoid rebuilding on every key stroke.
                    var sSearchText = oTile.data("_exfSearchText");
                    if (!sSearchText) {
                        var aParts = [];
                        if (oTile.getHeader) {
                            aParts.push(oTile.getHeader() || "");
                        }
                        if (oTile.getSubheader) {
                            aParts.push(oTile.getSubheader() || "");
                        }

                        (oTile.getTileContent() || []).forEach(function(oTileContent) {
                            var oContent = oTileContent && oTileContent.getContent ? oTileContent.getContent() : null;
                            if (!oContent) {
                                return;
                            }
                            if (oContent.getText) {
                                aParts.push(oContent.getText() || "");
                            }
                            if (oContent.getValue) {
                                aParts.push(oContent.getValue() || "");
                            }
                        });

                        sSearchText = aParts.join(" ").toLowerCase();
                        if (oTile.data) {
                            oTile.data("_exfSearchText", sSearchText);
                        }
                    }

                    // tile visibility
                    var bVisible = (sQuery === "") || (sSearchText.indexOf(sQuery) !== -1);
                    if (bVisible) {
                        bAnyTileVisible = true;
                    }
                    // Set visibility of the tile
                    oTile.setVisible(bVisible);
                });

                // hide entire panel if no tile visible
                oPanel.setVisible(bAnyTileVisible);
            });
    }
JS;
    }
}