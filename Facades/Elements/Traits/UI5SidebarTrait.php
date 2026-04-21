<?php
namespace exface\UI5Facade\Facades\Elements\Traits;

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
        if ($this->getWidget()->hasSidebar()) {
            $sidebarEl = $this->getFacade()->getElement($this->getWidget()->getSidebar());
            if ($sidebarEl instanceof UI5Sidebar) {
                return $sidebarEl->buildJsConstructorForDynamicSideContent($mainContentJs, $oControllerJs);
            }
        }
        return '';
    }

    /**
     * @return string
     */
    protected function buildJsSidebarToggleButton() : string
    {
        if ($this->getWidget()->hasSidebar()) {
            $sidebarEl = $this->getFacade()->getElement($this->getWidget()->getSidebar());
            if ($sidebarEl instanceof UI5Sidebar) {
                return $sidebarEl->buildJsSidebarToggleButton();
            }
        }
        return '';
    }
}