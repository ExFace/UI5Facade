<?php
namespace exface\UI5Facade\Facades\Elements;

/**
 * Generates sap.ui.layout.DynamicSideContent for a Sidebar widget
 * 
 * @method \exface\Core\Widgets\Sidebar getWidget()
 *        
 * @author Andrej Kabachnik
 *        
 */
class UI5Sidebar extends UI5Panel
{
    /**
     * @return string
     */
    public function buildJsSidebarToggleButton() : string
    {
        $widget = $this->getWidget();
        $icon = $widget->getIcon();
        if ($icon !== null) {
            $icon = $this->buildCssIconClass($icon);
        } else {
            $icon = 'sap-icon://screen-split-one';
        }
        return <<<JS

                        new sap.m.Button({
                            icon: '{$icon}',
                            press: function(){
                                var oSidebar = sap.ui.getCore().byId('{$this->getIdOfDynamicSideContent()}');
                                oSidebar.setShowSideContent(! oSidebar.getShowSideContent());
                            }
                        }),
JS;
    }
    
    public function getIdOfDynamicSideContent() : string
    {
        return $this->getId() . '_sidecontent';
    }

    /**
     * @param string $mainContentJs
     * @param string $oControllerJs
     * @return string
     */
    public function buildJsConstructorForDynamicSideContent(string $mainContentJs, string $oControllerJs) : string
    {
        $sidebar = $this->getWidget();
        $this->registerConditionalProperties();
        
        
        return <<<JS

new sap.ui.layout.DynamicSideContent('{$this->getIdOfDynamicSideContent()}', {
    showSideContent: {$this->escapeBool($sidebar->isCollapsed() !== true)},
    sideContent: [
        {$this->buildJsConstructor($oControllerJs)}
    ],      
    mainContent: [
        $mainContentJs
    ]
})
JS;

    }
}