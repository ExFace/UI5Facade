<?php
namespace exface\UI5Facade\Facades\Elements\Traits;

use exface\Core\Exceptions\Facades\FacadeRuntimeError;
use exface\Core\Facades\AbstractAjaxFacade\Tours\DriverJsTourDriver;
use exface\Core\Interfaces\Tours\TourDriverInterface;
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
     * Builds a dropdown menu as sap MenuButton JS for the tour guide of the given widget, if it has one.
     * Each tour will be represented as a button in the dropdown,
     * which when clicked will trigger the corresponding tour using the tour driver.
     * 
     * If a controller is provided and the tour property "autorun" is set to true,
     * the tour will be automatically started when the view is loaded.
     *
     * @param WidgetInterface $widget
     * @param UI5ControllerInterface|null $controller
     * @return string
     */
    public function buildJsTourGuideDropdown(
        WidgetInterface $widget, 
        UI5ControllerInterface $controller = null,
    ): string
    {
        if (!$this->widgetHasTourGuide($widget)) {
            return '';
        }
        
        $driver = $this->getFacade()->getTourDriver($widget);
        $tours = $widget->getTourGuide()->getTours();
        $buttons = [];
        
        $this->registerDriverLibAsExternalModule($driver);

        foreach ($tours as $tour) {
            $startTourJs = $driver->buildJsStartTour($tour);
            $icon = $this->getIconSrc($tour->getIcon()) ?? '';

            $buttons[] = <<<JS

                new sap.m.MenuItem({
                    text: "{$tour->getTitle()}",
                    icon: "{$icon}",
                    press: function () {
                        {$startTourJs}
                    }
                })
JS;

            if ($controller !== null && $tour->getAutorun()) {
                $this->addTourOnShowView($controller, $startTourJs);
            }
        }

        $buttonsJs = implode(",\n", $buttons);

        return <<<JS

            new sap.m.MenuButton({
                icon: "sap-icon://travel-request",
                tooltip: "Tour guide",
                buttonMode: sap.m.MenuButtonMode.Regular,
                menu: new sap.m.Menu({
                    items: [
                        {$buttonsJs}
                    ]
                })
            }),
JS;
    }

    /**
     * Returns true if the given widget has a tour guide.
     * 
     * @param WidgetInterface $widget
     * @return bool
     */
    protected function widgetHasTourGuide(WidgetInterface $widget): bool
    {
        return (($widget instanceof IHaveTourGuideInterface) && ($widget->hasTourGuide()));
    }

    /**
     * Registers the necessary external library for the given tour driver, depending on its type.
     *
     * Add more TourDrivers here when they are implemented.
     *
     * @param TourDriverInterface $driver
     * @return void
     */
    private function registerDriverLibAsExternalModule(TourDriverInterface $driver) : void
    {
        switch (true) {
            case ($driver instanceof DriverJsTourDriver):
                $this->registerDriverJsAsExternalModule();
                break;
            default:
                throw new FacadeRuntimeError("The tour driver" . get_class($driver) . "is not supported for UI5 tour guides yet.");
        }
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