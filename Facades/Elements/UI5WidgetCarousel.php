<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Widgets\WidgetCarousel;

/**
 * Generates OpenUI5 widget carousels
 *
 * @author Andrej Kabachnik
 * 
 * @method DataCarousel getWidget()
 *        
 */
class UI5WidgetCarousel extends UI5Tabs
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::init()
     */
    protected function init()
    {
        parent::init(); 
       // $this->registerSyncOnMaster();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Tabs::buildJsConstructor()
     */
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {
        $widgetCarousel = $this->buildJsWidgetCarousel();
        
        if ($this->hasPageWrapper() === true) {
            return $this->buildJsPageWrapper($widgetCarousel);
        }
        
        return $widgetCarousel;
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsWidgetCarousel()
    {
        return <<<JS
    new sap.m.Carousel("{$this->getId()}", {
        pages: [
            {$this->buildJsChildrenConstructors()}
        ]
    })
JS;
    }
}
?>