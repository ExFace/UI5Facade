<?php

namespace exface\UI5Facade\Tests\PHPUnit\Unit\Facades\Elements;

use exface\Core\Widgets\Dashboard;
use exface\UI5Facade\Facades\Elements\UI5AbstractElement;
use exface\UI5Facade\Facades\Elements\UI5Dashboard;
use exface\UI5Facade\Facades\Elements\UI5DynamicPage;
use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;
use exface\UI5Facade\Facades\UI5Facade;
use PHPUnit\Framework\TestCase;

/**
 * Covers the element registration used while generating dashboard header tours.
 */
class UI5DashboardWrapperRegistrationTest extends TestCase
{
    /**
     * The dynamic-page helper must not replace the dashboard when tours resolve their targets.
     */
    public function testDashboardIsRestoredBeforeHeaderGeneration() : void
    {
        require_once dirname((new \ReflectionClass(Dashboard::class))->getFileName(), 2)
            . DIRECTORY_SEPARATOR . 'CommonLogic' . DIRECTORY_SEPARATOR . 'Constants.php';

        $widget = $this->createMock(Dashboard::class);
        $widget->method('getTourSteps')->willReturn([]);
        $controller = $this->createMock(UI5ControllerInterface::class);
        $facade = $this->createMock(UI5Facade::class);
        $element = $this->getMockBuilder(UI5Dashboard::class)->disableOriginalConstructor()
            ->onlyMethods([
                'getWidget', 'getFacade', 'getController', 'isWrappedInDynamicPage',
                'buildJsHeaderButtons'
            ])->getMock();
        $element->method('getWidget')->willReturn($widget);
        $element->method('getFacade')->willReturn($facade);
        $element->method('getController')->willReturn($controller);
        $element->method('isWrappedInDynamicPage')->willReturn(true);

        $registrations = [];
        $facade->method('registerElement')->willReturnCallback(
            function ($registered) use (&$registrations, $controller, $facade, $element) {
                $registrations[] = $registered;
                if ($registered instanceof UI5DynamicPage) {
                    // Supply the rendering context without starting the Workbench lifecycle.
                    (new \ReflectionProperty(UI5AbstractElement::class, 'controller'))
                        ->setValue($registered, $controller);
                } else {
                    self::assertCount(2, $registrations);
                    self::assertInstanceOf(UI5DynamicPage::class, $registrations[0]);
                    self::assertSame($element, $registered);
                    throw new \RuntimeException('Dashboard registration restored');
                }
                return $facade;
            }
        );
        $element->expects(self::never())->method('buildJsHeaderButtons');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Dashboard registration restored');
        (new \ReflectionMethod(UI5Dashboard::class, 'buildJsPageWrapper'))
            ->invoke($element, 'content()');
    }
}
