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
                        text: "{$this->getWidget()->getEmptyText()}"
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
        $this->getController()->addOnEventScript($this, 'TabSelect', <<<JS

            // Get the selected key
            var sKey = oEvent.getParameter("key");

             // Find the corresponding panel that matches the key dynamically
            var oView = this.getView();
            var oPanel = sap.ui.getCore().byId(sKey);
            if (oPanel && oPanel.getDomRef()) {
                oPanel.getDomRef().scrollIntoView({ behavior: "smooth" });
            }
JS);

        $this->getController()->addOnEventScript($this, 'FilterTiles', <<<JS
            
            // Get search query
            const sQuery = oEvent.getParameter("newValue");
        
            // Retrieve all panel IDs and slice to remove the first two IDs
            var aPanelIds = this.getView().findAggregatedObjects(true, function(oControl) {
            return oControl.isA("sap.m.Panel");
            }).map(function(oPanel) {
            return oPanel.getId();
            }).slice(2);
        
            // Looping through each panel and filter based on tiles header names and content text
            aPanelIds.forEach(function(sPanelId) {
                // Retrieving the panel control based on the ID
                var oPanel = sap.ui.getCore().byId(sPanelId)
                var aTiles = oPanel.getContent();
                var bAnyTileVisible = false;
        
                aTiles.forEach(function(oTile) {
                    var sTileHeader = oTile.getHeader();
                    var aTileContent = oTile.getTileContent();
                    var sContentText = "";

                    // Loop through the TileContent array to retrieve text
                    aTileContent.forEach(function(oTileContent) {
                           var oContent = oTileContent.getContent();
                           if (oContent && oContent.isA("sap.m.FeedContent")) {
                               sContentText = oContent._oContentText.mProperties.text;
                           }
                       });
                    
                       // If the header text or content text contains the search query, set the tile to visible
                       var bVisible = !!(sTileHeader.toLowerCase().indexOf(sQuery.toLowerCase()) !== -1 || (sContentText && sContentText.toLowerCase().indexOf(sQuery.toLowerCase()) !== -1));
                       
                       // If at least one tile is visible, set the panel to visible
                       if (bVisible) {
                           bAnyTileVisible = true;
                       }
                       // Set visibility of the tile
                       oTile.setVisible(bVisible);
                });
                oPanel.setVisible(bAnyTileVisible);
            }.bind(this));
            
    JS);

        return <<<JS

        new sap.m.FlexBox("{$this->getId()}_navbox", {
            backgroundDesign: "Solid",
            items: [
                new sap.m.IconTabHeader("{$this->getId()}_iconTabHeader", {
                    mode: "Inline",
                    select: {$this->getController()->buildJsEventHandler($this, 'TabSelect', true)},
                    items: [
                        {$this->buildJsIconTabBarItems()}
                    ]
                }).addStyleClass('customHeader').setLayoutData(new sap.m.FlexItemData({
            growFactor: 1,
            shrinkFactor: 1
        })),
                new sap.m.SearchField({
                    placeholder: "Search...",
                    liveChange: {$this->getController()->buildJsEventHandler($this, 'FilterTiles', true)},
                }).setLayoutData(new sap.m.FlexItemData({
            growFactor: 0
        }))
            ]
        }).addStyleClass('responsiveFlexbox'),

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
                $tileGroup->setHidden(false);
                continue;
            }
            $js .= $this->buildJsIconTabBarItem($tileGroup);
        }
        return $js;
    }

    protected function buildJsIconTabBarItem(Tiles $tileGroup) : string
    {
        $tabCaption = StringDataType::substringAfter($tileGroup->getCaption(), ' > ');
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
        //return $this->getWidget()->getDepth() > 1;
        return true;
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
}