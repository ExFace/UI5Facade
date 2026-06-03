<?php
namespace exface\UI5Facade\Facades\Elements\Traits;

use exface\Core\Interfaces\Widgets\iHaveSidebar;
use exface\UI5Facade\Facades\Elements\UI5Sidebar;

/**
 * Use this trait to add a sidebar to a UI5 element
 *        
 * @author Andrej Kabachnik
 *        
 */
trait UI5SidebarTrait
{
    /**
     * @param string $mainContentJs
     * @param string $oControllerJs
     * @return string
     */
    protected function buildJsConstructorForSidebar(string $mainContentJs, string $oControllerJs = 'oController') : string
    {
        $widget = $this->getWidget();
        // Double-check if the widget has the interface because the trait is widely used
        if (($widget instanceof iHaveSidebar) && $this->getWidget()->hasSidebar()) {
            $sidebarEl = $this->getFacade()->getElement($this->getWidget()->getSidebar());
            if ($sidebarEl instanceof UI5Sidebar) {
                return $sidebarEl->buildJsConstructorForDynamicSideContent($mainContentJs, $oControllerJs);
            }
        }
        return '';
    }

    /**
     * @param bool $endWithComma
     * @return string
     */
    protected function buildJsSidebarToggleButton(bool $endWithComma = true) : string
    {
        $widget = $this->getWidget();
        // Double-check if the widget has the interface because the trait is widely used
        if (($widget instanceof iHaveSidebar) && $this->getWidget()->hasSidebar()) {
            $sidebarEl = $this->getFacade()->getElement($this->getWidget()->getSidebar());
            if ($sidebarEl instanceof UI5Sidebar) {
                return $sidebarEl->buildJsConstructorForSidebarToggleButton() . ($endWithComma ? ',' : '');
            }
        }
        return '';
    }
}