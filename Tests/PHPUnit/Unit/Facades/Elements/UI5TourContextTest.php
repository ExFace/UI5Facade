<?php

namespace exface\UI5Facade\Tests\PHPUnit\Unit\Facades\Elements;

use exface\Core\Facades\AbstractAjaxFacade\Tours\DriverJsTourDriver;
use exface\Core\Interfaces\Tours\TourGuideInterface;
use exface\Core\Widgets\Dashboard;
use exface\UI5Facade\Facades\Elements\UI5Dashboard;
use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;
use exface\UI5Facade\Facades\UI5Facade;
use PHPUnit\Framework\TestCase;

/**
 * Covers shared tour-context registration on a non-data widget.
 */
class UI5TourContextTest extends TestCase
{
    /**
     * Explicit and implicit controllers both initialize the context for dashboard tours.
     */
    public function testDashboardTourRegistersContextWithoutDataWidget() : void
    {
        foreach ([true, false] as $passController) {
            $guide = $this->createMock(TourGuideInterface::class);
            $guide->method('getTours')->willReturn([]);
            $widget = $this->getMockBuilder(Dashboard::class)->disableOriginalConstructor()
                ->onlyMethods(['hasTourGuide', 'getTourGuide'])->getMock();
            $widget->method('hasTourGuide')->willReturn(true);
            $widget->method('getTourGuide')->willReturn($guide);
            $facade = $this->getMockBuilder(UI5Facade::class)->disableOriginalConstructor()
                ->onlyMethods(['getTourDriver'])->getMock();
            $facade->method('getTourDriver')->willReturn($this->createMock(DriverJsTourDriver::class));
            $controller = $this->createMock(UI5ControllerInterface::class);
            $controller->expects(self::once())->method('addOnInitScript')->with(
                self::callback(static function (string $js) : bool {
                    return str_contains($js, 'window.exfTourContext = window.exfTourContext ||')
                        && str_contains($js, 'setPendingTour(payload)')
                        && str_contains($js, 'consumePendingTour(targetTourId)');
                })
            );
            $element = $this->getMockBuilder(UI5Dashboard::class)->disableOriginalConstructor()
                ->onlyMethods(['getFacade', 'getController', 'registerDriverJsAsExternalModule'])->getMock();
            $element->method('getFacade')->willReturn($facade);
            $element->method('getController')->willReturn($controller);

            self::assertStringContainsString('new sap.m.MenuButton', $element->buildJsTourGuideDropdown(
                $widget,
                $passController ? $controller : null
            ));
        }
    }
}
