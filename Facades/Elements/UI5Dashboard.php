<?php
namespace exface\UI5FAcade\Facades\Elements;

use exface\Core\Widgets\Dashboard;

/**
 * 
 * @author tmc
 *
 * @method Dashboard getWidget()
 */
class UI5Dashboard extends UI5Panel
{
    /**
     * 
     * @param string $oControllerJs
     * @return string
     */
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {   
        $dashboard = $this->buildJsConstructorForDashboard();
        
        return $dashboard;
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsConstructorForDashboard() : string
    {
        
       // $height = ($this->getWidget()->getHeight()->getValue()) ? "height : \"{$this->getWidget()->getHeight()->getValue()}\"," : 'height : "100%",';
        $width = ($this->getWidget()->getWidth()->getValue()) ? "width : \"{$this->getWidget()->getWidth()->getValue()}\"," : 'width : "100%",';
        
        return <<<JS
        new sap.f.GridContainer({
            {$width}
            allowDenseFill: true,
            snapToRow: true,
            layout: [
                {
                  //  minColumnSize: "300px",
                  //  columnSize: "calc((100% - 2 * 5px) / 3)",
                    gap: "5px"
                }
            ],
            items: [
                {$this->buildDashboardContentWrapper()}
            ]
        }).addStyleClass("dashboard_gridcontainer_layout")
        
JS;
    }
    
    /**
     * 
     * @return string
     */
    protected function buildDashboardContentWrapper() : string
    {
        $js = '';
        
        foreach ($this->getWidget()->getChildren() as $widget){
            
            $widthUnits = $this->getChildrenElementWidthUnitCount($widget);
            $heightUnits = $this->getChildrenElementHeightUnitCount($widget);
           
            $height = ($widget->getHeight()->isMax() || $widget->getHeight()->isUndefined()) ? "height : \"100%\"," : "height : \"{$widget->getHeight()->getValue()}\",";
            
            if ($widthUnits == ''){
                $width = ($widget->getWidth()->isMax() || $widget->getWidth()->isUndefined()) ? "width : \"100%\"," : "width : \"{$widget->getWidth()->getValue()}\",";
            } else {
                $width = '';
            }
            
            
            $widget->setHeight("100%");
            $widget->setWidth("100%");
            
            $js .= <<<JS
                    new sap.f.Card({
                        {$height}
                        {$width}
                        content: [
                            {$this->getFacade()->getElement($widget)->buildJsConstructor()}
                        ] 
                    }).setLayoutData(new sap.f.GridContainerItemLayoutData({
                                {$widthUnits}
                            })),

JS;
        }
        
        return $js;
    }
    
    /**
     * 
     * @param unknown $widget
     * @return string
     */
    protected function getChildrenElementWidthUnitCount($widget) : string
    {
        $width = $widget->getWidth()->getValue();
        
        switch (true){
            case (is_numeric($width)):
                $widthUnits = "columns: {$width}";
                break;
                
            default:
                $widthUnits = '';
        }
                
        return $widthUnits;
    }
    
    /**
     * 
     * @param unknown $widget
     * @return int
     */
    protected function getChildrenElementHeightUnitCount($widget) : int
    {
        $height = $widget->getHeight()->getValue();
        
        if (strpos($height, '%') !== false){
            $heightUnits = round((str_replace('%', '', $height) / 10));
            
        } else {
            $heightUnits = 6;
        }
        
        return $heightUnits;
    }
}