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
 * Covers distinct menu and automatic start modes without a persisted metamodel.
 */
class UI5TourStandaloneStartTest extends TestCase
{
    /**
     * Menu starts use standalone mode; URL, autorun and resume starts do not.
     */
    public function testStandaloneModeAppliesOnlyToManualStart() : void
    {
        [$menuJs, $showScripts, $initScripts, $modes] = $this->buildScripts();

        self::assertSame([false, true], $modes);
        self::assertStringContainsString('startStandaloneTour();', $menuJs);
        self::assertStringNotContainsString('startChainedTour();', $menuJs);
        self::assertStringContainsString('window.exfTourContext.clear();', $menuJs);
        self::assertCount(2, $showScripts);
        foreach ($showScripts as $js) {
            self::assertStringContainsString('startChainedTour();', $js);
            self::assertStringNotContainsString('startStandaloneTour();', $js);
        }
        self::assertStringContainsString('new URL(', $showScripts[0]);
        self::assertStringContainsString('startChainedTour();', $initScripts[1]);
        self::assertStringNotContainsString('startStandaloneTour();', $initScripts[1]);
    }

    /**
     * The start mode is passed per run rather than stored on the tour.
     */
    public function testRepeatedMenuGenerationKeepsSeparateStartModes() : void
    {
        foreach ([1, 2] as $run) {
            [$menuJs, , , $modes] = $this->buildScripts();

            self::assertSame([false, true], $modes);
            self::assertStringContainsString('startStandaloneTour();', $menuJs);
            self::assertStringNotContainsString('startChainedTour();', $menuJs);
        }
    }

    /**
     * Captures real menu and autostart wiring while replacing tour-code generation.
     *
     * @return array
     */
    private function buildScripts() : array
    {
        $tour = $this->createMock(Tour::class);
        $tour->method('getId')->willReturn('partial-tour');
        $tour->method('getTitle')->willReturn('Partial tour');
        $tour->method('getAutorun')->willReturn(true);
        $guide = $this->createMock(TourGuideInterface::class);
        $guide->method('getTours')->willReturn([$tour]);
        $widget = $this->getMockBuilder(Dashboard::class)->disableOriginalConstructor()
            ->onlyMethods(['hasTourGuide', 'getTourGuide'])->getMock();
        $widget->method('hasTourGuide')->willReturn(true);
        $widget->method('getTourGuide')->willReturn($guide);
        $modes = [];
        $driver = $this->createMock(DriverJsTourDriver::class);
        $driver->method('buildJsStartTour')->willReturnCallback(
            static function (TourInterface $tour, bool $isStandalone = false) use (&$modes) : string {
                $modes[] = $isStandalone;
                return $isStandalone ? 'startStandaloneTour();' : 'startChainedTour();';
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
