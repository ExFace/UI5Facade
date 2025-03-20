<?php
namespace exface\UI5Facade\Facades\Elements;

/**
 * Renders a sap.m.IconTabBar for the Tabs widget
 * 
 * @method \exface\Core\Widgets\Tabs getWidget() 
 * 
 * @author andrej.kabachnik
 *
 */
class UI5Tabs extends UI5Container
{

    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Container::buildJsConstructor()
     */
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {
        $iconTabBar = $this->buildJsIconTabBar();
        
        if ($this->hasPageWrapper() === true) {
            return $this->buildJsPageWrapper($iconTabBar);
        }
        
        return $iconTabBar;
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsIconTabBar()
    {
        $widget = $this->getWidget();
        $options = '';
        $selectedTab = $widget->getTab($widget->getActiveTabIndex());
        if ($selectedTab->isFilledBySingleWidget() === true) {
            $options .= 'applyContentPadding: false,';
        }
        if ($widget->getActiveTabIndex() > 0) {
            $options .= 'selectedKey: ' . $this->escapeString($this->getFacade()->getElement($selectedTab)->getId()) . ',';
        }
        foreach ($widget->getTabs() as $tab) {
            if ($tab->isFilledBySingleWidget() === true) {
                $tabEl = $this->getFacade()->getElement($tab);
                $resizeContentJs .= <<<JS

                    if (sKey === '{$tabEl->getId()}') {
                        {$this->getFacade()->getElement($tab->getFillerWidget())->getOnResizeScript()}
                    }
JS;
            } 
        }
        
        return <<<JS

    new sap.m.IconTabBar("{$this->getId()}", {
        showOverflowSelectList: true,
        expandable: false,
        {$this->buildJsPropertyStretchContent()}
        $options
        items: [
            {$this->buildJsChildrenConstructors()}
        ],
        select: function(oEvent) {
            {$this->buildJsOnChangeScript('oEvent')}

            setTimeout(function(sKey){
                {$resizeContentJs}
            }, 0, oEvent.getParameters().key);
        }
    })
    {$this->buildJsPseudoEventHandlers()}
JS;
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsPropertyStretchContent() : string
    {
        $widget = $this->getWidget();
        if ($widget->hasParent()) {
            $parentWidget = $widget->getParent();
            $parentEl = $this->getFacade()->getElement($parentWidget);
        }
        switch (true) {
            /* If the tabs are inside a non-maximized dialog with an ObjectPageLayout and a header,
             * using `stretchContentHeight:true` in this case makes the header disappear behind
             * the content. 
             * @var \exface\Core\Widgets\Dialog $parentWidget 
             */
            case ($parentEl instanceof UI5Dialog) 
            && $parentEl->isMaximized() === false 
            && $parentEl->isObjectPageLayout() 
            && $parentEl->hasHeader():
                $valJs = 'false';
                break;
            default:
                $valJs = 'true';
        }
        return "stretchContentHeight: {$valJs},";
    }
    
    /**
     * 
     * @param string $oEventJs
     * @return string
     */
    protected function buildJsOnChangeScript(string $oEventJs) : string
    {
        $filledTabIds = [];
        /* @var $tab \exface\Core\Widgets\Tab */
        foreach ($this->getWidget()->getWidgets() as $tab) {
            if ($tab->isFilledBySingleWidget() === true) {
                $filledTabIds[] = $this->getFacade()->getElement($tab)->getId();
            }
        }
        $filledTabIdsJSON = json_encode($filledTabIds);
        
        return <<<JS
            
            var oParams = $oEventJs.getParameters();
            var oTabBar = $oEventJs.getSource();
            var sKey = oParams.selectedKey;
            var aTabIdsNoPadding = $filledTabIdsJSON;
            if (aTabIdsNoPadding.indexOf(sKey) !== -1) {
                oTabBar.setApplyContentPadding(false);
            } else {
                oTabBar.setApplyContentPadding(true);
            }
JS . $this->getOnChangeScript();
    }
}
?>
