<?php

namespace exface\UI5Facade\Tests\PHPUnit\Unit\Facades\Elements;

use exface\UI5Facade\Facades\Elements\UI5Dashboard;
use exface\UI5Facade\Facades\Elements\UI5Input;
use PHPUnit\Framework\TestCase;

/**
 * Covers dashboard DOM targets without requiring the metamodel.
 */
class UI5DashboardDomElementGetterTest extends TestCase
{
    /**
     * Resolve the current wrapper at runtime, falling back to the unwrapped dashboard.
     */
    public function testDashboardResolvesWrapperWithItsCurrentId() : void
    {
        foreach (['dashboard', 'another_dashboard'] as $id) {
            $element = $this->getMockBuilder(UI5Dashboard::class)->disableOriginalConstructor()
                ->onlyMethods(['getId'])->getMock();
            $element->method('getId')->willReturn($id);

            self::assertSame(
                '(document.getElementById("' . $id . '_page") || $(\'#' . $id . '\')[0])',
                $element->buildJsDomElementGetter()
            );
        }
    }

    /**
     * Linking directly to a filter input still targets that input, not the dashboard.
     */
    public function testInputKeepsItsOwnDomTarget() : void
    {
        $element = $this->getMockBuilder(UI5Input::class)->disableOriginalConstructor()
            ->onlyMethods(['getId'])->getMock();
        $element->method('getId')->willReturn('dashboard_filter');

        self::assertSame('$(\'#dashboard_filter\')[0]', $element->buildJsDomElementGetter());
    }
}
