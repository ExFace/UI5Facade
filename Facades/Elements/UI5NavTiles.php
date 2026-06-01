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
                        // Get the selected key
                        var sKey = oEvent.getParameter("key");

                        // Find the corresponding panel that matches the key and scroll it into view
                        var oPanel = sap.ui.getCore().byId(sKey);
                        if (oPanel && oPanel.getDomRef()) {
                            oPanel.getDomRef().scrollIntoView({ behavior: "smooth" , block: "start" });
                        }
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
                    placeholder: "{$this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.SHOWLOOKUPDIALOG.NAME')}", 
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

                // hide the first tile group (the overview), but only if the depth is not 1,
                // otherwise we get issues with landing pages etc, as they dont display any data then
                if ($this->getWidget()->getDepth() === 1 || count($this->getWidget()->getTiles()) === 1 || $this->getWidget()->getShowOverviewGroup() === true) {
                    $tileGroup->setHidden(false);
                    continue;
                }

                $tileGroup->setHidden(true);
                continue;
            }
            $js .= $this->buildJsIconTabBarItem($tileGroup);
        }
        return $js;
    }

    protected function buildJsIconTabBarItem(Tiles $tileGroup) : string
    {
        $tabCaption = $tileGroup->getCaption();

        // only show the last part of the caption, if there is a parent path included (parent > child)
        if ($this->getWidget()->getShowParentPath()) {
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