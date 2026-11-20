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
     * Each tour with `hide_in_menu` set to false will be represented as a button in the dropdown,
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

        // Every tour host needs the shared context, including containers and dialogs without data widgets.
        ($controller ?? $this->getController())->addOnInitScript($this->buildJsWindowTourContent());
        
        $driver = $this->getFacade()->getTourDriver($widget);
        $tours = $widget->getTourGuide()->getTours();
        $buttons = [];
        $pendingTourChecks = [];
        
        $this->registerDriverLibAsExternalModule($driver);

        foreach ($tours as $tour) {
            $startTourJs = $driver->buildJsStartTour($tour);
            // Hide only the menu entry: this tour must still be available to automatic starts below.
            if (!$tour->getHideInMenu()) {
                // Menu starts skip only actions marked skip_if_standalone; automatic starts run all actions.
                $manualStartTourJs = $driver->buildJsStartTour($tour, true);
                $icon = $this->getIconSrc($tour->getIcon()) ?? '';

                $buttons[] = <<<JS

                new sap.m.MenuItem({
                    text: "{$tour->getTitle()}",
                    icon: "{$icon}",
                    press: function () {
                        // A new manual start replaces any unfinished continuation request.
                        window.exfTourContext.clear();
                        {$manualStartTourJs}
                    }
                })
JS;
            }
            
            if ($controller !== null) {
                if ($tour->getId() !== null)
                {
                    // Autostart checks may continue a tour at the step stored by a tour subroute.
                    $resumeTourJs = $driver->buildJsStartTour($tour, false, '(tourIntent && tourIntent.startStepIndex) || 0');
                    $this->addTourOnShowView($controller, $this->buildJsTourAutostartCheck(
                        $tour->getId(), $resumeTourJs, true
                    ));
                    $pendingTourChecks[] = $this->buildJsTourAutostartCheck($tour->getId(), $resumeTourJs);
                }

                if ($tour->getAutorun()) {
                    $this->addTourOnShowView($controller, $startTourJs);
                }
            }
        }

        if ($controller !== null && $pendingTourChecks !== []) {
            $checksJs = implode("\n", $pendingTourChecks);
            // After a dialog closes, the page underneath stays loaded and receives no new show event.
            // Listen for dialog closure so a tour can continue on that page without reloading its data.
            $controller->addOnInitScript(<<<JS
                (function () {
                    let resumeTimer;
                    const resumePendingTour = function () {
                        // Wait briefly for the widgets to become visible; repeated notifications share one timer.
                        clearTimeout(resumeTimer);
                        resumeTimer = setTimeout(function () {
                            const element = {$this->buildJsDomElementGetter()};
                            if (!element || !jQuery(element).is(':visible')) {
                                return;
                            }
                            {$checksJs}
                        }, 500);
                    };
                    window.addEventListener('exf-tour-resume', resumePendingTour);
                    oView.attachBeforeExit(function () {
                        // A destroyed view must neither start a tour nor keep listening for closed dialogs.
                        clearTimeout(resumeTimer);
                        window.removeEventListener('exf-tour-resume', resumePendingTour);
                    });
                })();
JS);
        }

        if ($tours !== [] && $buttons === []) {
            return '';
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
     * Builds JavaScript that starts this tour if an earlier step requested it.
     *
     * The earlier step stores its `autostart_tour_id` in `window.exfTourContext`.
     * This check takes that request only if its ID matches this tour and starts it once.
     * Run the check when a view is shown or when a dialog closes to continue on the page underneath.
     * It does not reload the page or run its other show handlers.
     *
     * When $checkUrl is true, `?tour=<tourId>` can also start the tour. The parameter is
     * then removed to avoid starting it again. Dialog-close checks ignore URL parameters.
     *
     * @param string $tourId ID of the tour to check for.
     * @param string $startTourJs JavaScript that starts this tour.
     * @param bool $checkUrl Whether to also accept a tour request from the URL.
     * @return string JavaScript that checks for a request and starts the matching tour.
     */
    protected function buildJsTourAutostartCheck(string $tourId, string $startTourJs, bool $checkUrl = false) : string
    {
        $tourIdJs = $this->escapeString($tourId);
        $urlCheckJs = $checkUrl ? <<<JS
            const tourUrl = new URL(window.location.href);
            tourIdInURL = tourUrl.searchParams.get('tour') === {$tourIdJs};
            if (tourIdInURL) {
                tourUrl.searchParams.delete('tour');
                window.history.replaceState(
                    window.history.state, document.title,
                    tourUrl.pathname + tourUrl.search + tourUrl.hash
                );
            }
JS : '';

        return <<<JS
            {
                let tourIdInURL = false;
                {$urlCheckJs}
                const tourIntent = window.exfTourContext.consumePendingTour({$tourIdJs});
                if (tourIntent || tourIdInURL) {
                    {$startTourJs}
                }
            }
JS;
    }

    /**
     * Builds the shared JavaScript storage for a request to start another tour.
     *
     * A step stores the target tour ID before opening or closing a dialog or navigating.
     * The target tour can then retrieve the request when its widget is visible.
     * Retrieving a matching request removes it, so it is used only once; requests expire after 60 seconds.
     * Repeated initialization preserves an existing request. This storage does not start tours itself.
     * 
     * Tour subroutes leaving their tour (e.g. to open a lookup dialog) store a return point with the
     * tour id and the index of the next step. The step finishing the subroute turns it into a pending
     * tour request with `startStepIndex`, so the original tour continues where it left off. Return
     * points expire after 10 minutes because users may spend more time in the subroute.
     * 
     * @return string
     */
    public function buildJsWindowTourContent(): string
    {
        return <<<JS
        
            window.exfTourContext = window.exfTourContext || (function () {
                let pendingTour = null;
                let returnPoint = null;
            
                return {
                    setPendingTour(payload) {
                        pendingTour = {
                            ...payload,
                            createdAt: Date.now()
                        };
                    },

                    setReturnPoint(payload) {
                        returnPoint = {
                            ...payload,
                            createdAt: Date.now()
                        };
                    },

                    resumeReturnPoint() {
                        const point = returnPoint;
                        returnPoint = null;
                        if (!point || (Date.now() - point.createdAt) > 600000) {
                            return false;
                        }
                        this.setPendingTour({
                            targetTourId: point.tourId,
                            startStepIndex: point.stepIndex
                        });
                        return true;
                    },
            
                    consumePendingTour(targetTourId) {
                        if (!pendingTour) {
                            return null;
                        }
                        
                        const tooOld = (Date.now() - pendingTour.createdAt) > 60000;
                        const matchesTour = pendingTour.targetTourId === targetTourId;
                        
                        if (tooOld) {
                            pendingTour = null;
                            return null;
                        }

                        if (!matchesTour) {
                            return null;
                        }
                    
                        const result = pendingTour;
                        pendingTour = null;
                        return result;
                    },
                
                    clear() {
                        pendingTour = null;
                        returnPoint = null;
                    }
              };
            })();

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