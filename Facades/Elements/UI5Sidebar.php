<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Interfaces\Widgets\iContainOtherWidgets;

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
    public function buildJsConstructorForSidebarToggleButton() : string
    {
        $widget = $this->getWidget();
        $icon = $widget->getIcon();
        if ($icon !== null) {
            $icon = $this->buildCssIconClass($icon);
        } else {
            $icon = 'sap-icon://screen-split-one';
        }
        
        $resizeJs = '';
        foreach ($widget->getWidgets() as $child) {
            $childEl = $this->getFacade()->getElement($child);
            $resizeJs .= $childEl->getOnResizeScript();
        }
        return <<<JS

                        new sap.m.Button({
                            icon: '{$icon}',
                            press: function(){
                                var oSidebar = sap.ui.getCore().byId('{$this->getIdOfDynamicSideContent()}');
                                oSidebar.setShowSideContent(! oSidebar.getShowSideContent());
                                $resizeJs
                            }
                        })
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

    public function buildcssElementClass()
    {
        return 'exf-sidebar';
    }

    protected function buildJsPropertyHeight() : string
    {
        $widget = $this->getWidget();
        if ($widget->getHeight()->isUndefined()) {
            return "height: '100%',";
        }
        return parent::buildJsPropertyHeight();
    }

    protected function buildCssHeightDefaultValue()
    {
        return '100%';
    }
}