<?php

namespace exface\UI5Facade\Tests\PHPUnit\Unit\Facades\Elements;

use exface\Core\Facades\AbstractAjaxFacade\Tours\DriverJsTourDriver;
use exface\Core\Interfaces\Tours\TourGuideInterface;
use exface\Core\Widgets\Dashboard;
use exface\Core\Widgets\Parts\Tours\Tour;
use exface\UI5Facade\Facades\Elements\UI5Dashboard;
use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;
use exface\UI5Facade\Facades\UI5Facade;
use PHPUnit\Framework\TestCase;

/**
 * Covers pending-tour checks without replaying the view's lifecycle.
 */
class UI5TourResumeTest extends TestCase
{
    /**
     * Visible tour hosts listen for dialog closure and clean up their subscription.
     */
    public function testDialogResumeChecksOnlyPendingTours() : void
    {
        [$initScripts, $showScripts] = $this->buildTourScripts(false);
        $resumeJs = $initScripts[1];

        self::assertStringContainsString("window.addEventListener('exf-tour-resume'", $resumeJs);
        self::assertStringContainsString("window.removeEventListener('exf-tour-resume'", $resumeJs);
        self::assertStringContainsString('oView.attachBeforeExit', $resumeJs);
        self::assertStringContainsString("!element || !jQuery(element).is(':visible')", $resumeJs);
        self::assertStringContainsString('const element = getTourHost()', $resumeJs);
        self::assertStringContainsString('consumePendingTour("next-tour")', $resumeJs);
        self::assertStringContainsString('if (tourIntent || tourIdInURL)', $resumeJs);
        self::assertStringContainsString('startTour()', $resumeJs);
        self::assertStringNotContainsString('new URL(', $resumeJs);
        self::assertStringNotContainsString('onBeforeShow', $resumeJs);
        self::assertCount(1, $showScripts);
        self::assertStringContainsString('new URL(window.location.href)', $showScripts[0]);
    }

    /**
     * Unconditional autorun remains a view-show feature, not a dialog-close feature.
     */
    public function testAutorunIsNotRepeatedWhenDialogCloses() : void
    {
        [$initScripts, $showScripts] = $this->buildTourScripts(true);

        self::assertCount(2, $showScripts);
        self::assertSame(1, substr_count($initScripts[1], 'startTour()'));
        self::assertStringContainsString('consumePendingTour("next-tour")', $initScripts[1]);
        self::assertStringNotContainsString('consumePendingTour', $showScripts[1]);
    }

    /**
     * Captures real tour integration output at the facade and controller boundaries.
     *
     * @param bool $autorun
     * @return array
     */
    private function buildTourScripts(bool $autorun) : array
    {
        $tour = $this->createMock(Tour::class);
        $tour->method('getId')->willReturn('next-tour');
        $tour->method('getTitle')->willReturn('Next tour');
        $tour->method('getAutorun')->willReturn($autorun);
        $guide = $this->createMock(TourGuideInterface::class);
        $guide->method('getTours')->willReturn([$tour]);
        $widget = $this->getMockBuilder(Dashboard::class)->disableOriginalConstructor()
            ->onlyMethods(['hasTourGuide', 'getTourGuide'])->getMock();
        $widget->method('hasTourGuide')->willReturn(true);
        $widget->method('getTourGuide')->willReturn($guide);
        $driver = $this->createMock(DriverJsTourDriver::class);
        $driver->method('buildJsStartTour')->willReturn('startTour();');
        $facade = $this->getMockBuilder(UI5Facade::class)->disableOriginalConstructor()
            ->onlyMethods(['getTourDriver'])->getMock();
        $facade->method('getTourDriver')->willReturn($driver);
        $initScripts = [];
        $showScripts = [];
        $controller = $this->createMock(UI5ControllerInterface::class);
        $controller->method('addOnInitScript')->willReturnCallback(
            static function (string $js) use (&$initScripts, $controller) : UI5ControllerInterface {
                $initScripts[] = $js;
                return $controller;
            }
        );
        $controller->method('addOnShowViewScript')->willReturnCallback(
            static function (string $js) use (&$showScripts, $controller) : UI5ControllerInterface {
                $showScripts[] = $js;
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
        $element->method('buildJsDomElementGetter')->willReturn('getTourHost()');
        $element->buildJsTourGuideDropdown($widget, $controller);
        return [$initScripts, $showScripts];
    }
}
