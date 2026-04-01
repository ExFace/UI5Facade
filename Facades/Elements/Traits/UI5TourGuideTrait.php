<?php
namespace exface\UI5Facade\Facades\Elements\Traits;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\IHaveTourGuideInterface;
use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;
use exface\UI5Facade\Facades\UI5Facade;

/**
 * This trait provides methods to integrate tour guides into UI5 widgets.
 *
 * Trait UI5TourGuideTrait
 * @package exface\UI5Facade\Facades\Elements\Traits
 * 
 * @method UI5Facade getFacade()
 *
 * @author Sergej Riel
 */
trait UI5TourGuideTrait {

    /**
     * Builds a dropdown menu as a UxonObject for the tour guide of the given widget, if it has one. 
     * Each tour will be represented as a button in the dropdown, 
     * which when clicked will trigger the corresponding tour using the tour driver.
     * 
     * @param WidgetInterface $widget
     * @return UxonObject|null
     */
    public function buildTourGuideDropDownAsUxonObject(WidgetInterface $widget): ?UxonObject
    {
        if (! ($widget instanceof IHaveTourGuideInterface) || ! $widget->hasTourGuide()) {
            return null;
        }
        
        $tours = $widget->getTourGuide()->getTours();
        $driver = $this->getFacade()->getTourDriver($widget);

        $buttons = [];

        foreach ($tours as $tour) {
            $buttons[] = [
                'caption' => $tour->getTitle(),
                'action'  => [
                    'alias'  => 'exface.Core.CustomFacadeScript',
                    'icon' => $tour->getIcon() ?? '',
                    'script' => $driver->buildJsStartTour($tour)
                ],
            ];
        }

        return new UxonObject([
            'widget_type' => 'MenuButton',
            'icon' => 'sap-icon://travel-request',
            'caption' => 'Tour guide',
            'hide_caption' => true,
            'buttons' => $buttons
        ]);
    }


    /**
     * imports the driver.js library and adds the necessary CSS for the tours to work.
     * 
     * @param UI5ControllerInterface $controller
     * @return void
     */
    protected function registerDriverJsAsExternalModule() : void
    {
        $controller = $this->getController();
        $facade = $this->getFacade();
        $controller->addExternalModule('libs.exface.Driver', $facade->buildUrlToSource("LIBS.DRIVER.JS"), null, 'driver');
        $controller->addExternalCss($facade->buildUrlToSource("LIBS.DRIVER.CSS"));
    }
}