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
     * If a controller is provided and the tour property "autorun" is set to true,
     * the tour will be automatically started when the view is loaded.
     *
     * @param WidgetInterface $widget
     * @return UxonObject|null
     */
    public function buildTourGuideDropDownAsUxonObject(WidgetInterface $widget, UI5ControllerInterface $controller = null): ?UxonObject
    {
        if (! ($widget instanceof IHaveTourGuideInterface) || ! $widget->hasTourGuide()) {
            return null;
        }
        
        $tours = $widget->getTourGuide()->getTours();
        $driver = $this->getFacade()->getTourDriver($widget);

        $buttons = [];

        foreach ($tours as $tour) {
            $startTourJs = $driver->buildJsStartTour($tour);

            $buttons[] = [
                'caption' => $tour->getTitle(),
                'action'  => [
                    'alias'  => 'exface.Core.CustomFacadeScript',
                    'icon' => $tour->getIcon() ?? '',
                    'script' => $startTourJs
                ],
            ];

            if ($controller !== null && $tour->getAutorun()) {
                $this->addTourOnShowView($controller, $startTourJs);
            }
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

    /**
     * adds the given startTourJs to the controller that will automatically start the tour when the view is shown.
     * 
     * @param UI5ControllerInterface $controller
     * @param string $startTourJs
     * @return void
     */
    private function addTourOnShowView(UI5ControllerInterface $controller, string $startTourJs): void
    {
        $autorunJs = <<<JS
            setTimeout(function(){                       
                    {$startTourJs}
            },500);
JS;
        $controller->addOnShowViewScript($autorunJs);
    }
}