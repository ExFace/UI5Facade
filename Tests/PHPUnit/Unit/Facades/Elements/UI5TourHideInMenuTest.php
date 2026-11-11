<?php

namespace exface\UI5Facade\Tests\PHPUnit\Unit\Facades\Elements;

use exface\Core\Facades\AbstractAjaxFacade\Tours\DriverJsTourDriver;
use exface\Core\Interfaces\Tours\TourGuideInterface;
use exface\Core\Interfaces\Tours\TourInterface;
use exface\Core\Widgets\Dashboard;
use exface\Core\Widgets\Parts\Tours\Tour;
use exface\UI5Facade\Facades\Elements\UI5Dashboard;
use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;
use exface\UI5Facade\Facades\UI5Facade;
use PHPUnit\Framework\TestCase;

/**
 * Covers menu visibility without removing hidden tours from automatic start wiring.
 */
class UI5TourHideInMenuTest extends TestCase
{
    /**
     * Only visible tours get menu entries; hidden tours retain every automatic entry point.
     */
    public function testHiddenTourKeepsAutomaticStartsWithoutMenuEntry() : void
    {
        [$menuJs, $showScripts, $initScripts, $modes] = $this->buildScripts([true, false]);

        self::assertStringNotContainsString('Tour 0', $menuJs);
        self::assertStringNotContainsString('startTour("tour-0"', $menuJs);
        self::assertStringContainsString('Tour 1', $menuJs);
        self::assertStringContainsString('startTour("tour-1", true);', $menuJs);
        self::assertSame(['tour-0' => [false], 'tour-1' => [false, true]], $modes);
        self::assertCount(4, $showScripts);
        self::assertStringContainsString('consumePendingTour("tour-0")', $showScripts[0]);
        self::assertStringContainsString('new URL(', $showScripts[0]);
        self::assertStringContainsString('startTour("tour-0", false);', $showScripts[0]);
        self::assertStringContainsString('startTour("tour-0", false);', $showScripts[1]);
        self::assertStringContainsString('consumePendingTour("tour-0")', $initScripts[1]);
        self::assertStringContainsString('startTour("tour-0", false);', $initScripts[1]);
    }

    /**
     * Hiding all tours removes the empty menu, not the autostart or dialog-resume handlers.
     */
    public function testAllHiddenToursKeepAutomaticStartsWithoutEmptyMenu() : void
    {
        [$menuJs, $showScripts, $initScripts, $modes] = $this->buildScripts([true]);

        self::assertSame('', $menuJs);
        self::assertSame(['tour-0' => [false]], $modes);
        self::assertCount(2, $showScripts);
        self::assertStringContainsString('consumePendingTour("tour-0")', $showScripts[0]);
        self::assertStringContainsString('new URL(', $showScripts[0]);
        self::assertStringContainsString('startTour("tour-0", false);', $showScripts[1]);
        self::assertStringContainsString('window.exfTourContext', $initScripts[0]);
        self::assertStringContainsString('exf-tour-resume', $initScripts[1]);
        self::assertStringContainsString('startTour("tour-0", false);', $initScripts[1]);
    }

    /**
     * Captures real menu and lifecycle wiring, replacing only facade rendering collaborators.
     *
     * @param bool[] $hiddenFlags
     * @return array
     */
    private function buildScripts(array $hiddenFlags) : array
    {
        $tours = [];
        foreach ($hiddenFlags as $index => $hidden) {
            $tour = $this->createMock(Tour::class);
            $tour->method('getId')->willReturn('tour-' . $index);
            $tour->method('getTitle')->willReturn('Tour ' . $index);
            $tour->method('getHideInMenu')->willReturn($hidden);
            $tour->method('getAutorun')->willReturn(true);
            $tours[] = $tour;
        }
        $guide = $this->createMock(TourGuideInterface::class);
        $guide->method('getTours')->willReturn($tours);
        $widget = $this->getMockBuilder(Dashboard::class)->disableOriginalConstructor()
            ->onlyMethods(['hasTourGuide', 'getTourGuide'])->getMock();
        $widget->method('hasTourGuide')->willReturn(true);
        $widget->method('getTourGuide')->willReturn($guide);
        $modes = [];
        $driver = $this->createMock(DriverJsTourDriver::class);
        $driver->method('buildJsStartTour')->willReturnCallback(
            static function (TourInterface $tour, bool $isStandalone = false) use (&$modes) : string {
                $modes[$tour->getId()][] = $isStandalone;
                return 'startTour("' . $tour->getId() . '", ' . ($isStandalone ? 'true' : 'false') . ');';
            }
        );
        $facade = $this->getMockBuilder(UI5Facade::class)->disableOriginalConstructor()
            ->onlyMethods(['getTourDriver'])->getMock();
        $facade->method('getTourDriver')->willReturn($driver);
        $showScripts = [];
        $initScripts = [];
        $controller = $this->createMock(UI5ControllerInterface::class);
        $controller->method('addOnShowViewScript')->willReturnCallback(
            static function (string $js) use (&$showScripts, $controller) : UI5ControllerInterface {
                $showScripts[] = $js;
                return $controller;
            }
        );
        $controller->method('addOnInitScript')->willReturnCallback(
            static function (string $js) use (&$initScripts, $controller) : UI5ControllerInterface {
                $initScripts[] = $js;
                return $controller;
            }
        );
        $element = $this->getMockBuilder(UI5Dashboard::class)->disableOriginalConstructor()
            ->onlyMethods([
                'getFacade', 'getController', 'getIconSrc',
                'buildJsDomElementGetter', 'registerDriverJsAsExternalModule'
            ])->getMock();
        $element->method('getFacade')->willReturn($facade);
        $element->method('getController')->willReturn($controller);
        $element->method('getIconSrc')->willReturn('');
        $element->method('buildJsDomElementGetter')->willReturn('getHost()');

        $menuJs = $element->buildJsTourGuideDropdown($widget, $controller);
        return [$menuJs, $showScripts, $initScripts, $modes];
    }
}
