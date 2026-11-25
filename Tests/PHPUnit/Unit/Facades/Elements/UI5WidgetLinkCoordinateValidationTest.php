<?php

namespace exface\UI5Facade\Tests\PHPUnit\Unit\Facades\Elements;

use exface\Core\CommonLogic\WidgetLinkCoordinate;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Exceptions\Widgets\WidgetCoordinateUnresolvableError;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Widgets\InputNumber;
use exface\UI5Facade\Facades\Elements\UI5InputButton;
use exface\UI5Facade\Facades\Elements\UI5InputComboTable;
use exface\UI5Facade\Facades\Elements\UI5InputNumber;
use exface\UI5Facade\Facades\Elements\UI5Scheduler;
use PHPUnit\Framework\TestCase;

/**
 * Covers unsupported coordinate validation before controls need a Workbench or widget model.
 */
class UI5WidgetLinkCoordinateValidationTest extends TestCase
{
    /**
     * Scalar controls cannot read a row scope even when it is supplied as a string.
     */
    public function testScalarRejectsStringRowScope() : void
    {
        $widget = $this->createStub(InputNumber::class);
        $widget->method('getWidgetType')->willReturn('InputNumber');
        $element = $this->getMockBuilder(UI5InputNumber::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getWidget'])
            ->getMock();
        $element->method('getWidget')->willReturn($widget);
        $this->expectException(WidgetCoordinateUnresolvableError::class);
        $element->buildJsValueGetter('Amount$~loaded');
    }

    /**
     * Combo-table values cannot aggregate autosuggest rows.
     */
    public function testComboRejectsAggregationObject() : void
    {
        $element = $this->element(UI5InputComboTable::class);
        $this->expectException(WidgetCoordinateUnresolvableError::class);
        $element->buildJsValueGetter(new WidgetLinkCoordinate('Amount$~selected:SUM'));
    }

    /**
     * Input-button result getters cannot silently ignore all-row requests.
     */
    public function testInputButtonRejectsAllRows() : void
    {
        $element = $this->element(UI5InputButton::class);
        $this->expectException(WidgetCoordinateUnresolvableError::class);
        $element->buildJsValueGetter('Amount$~loaded');
    }

    /**
     * Scheduler custom values are not data columns that can be aggregated.
     */
    public function testSchedulerRejectsScopedCustomCoordinate() : void
    {
        $element = $this->element(UI5Scheduler::class);
        $this->expectException(WidgetCoordinateUnresolvableError::class);
        $element->buildJsValueGetter('~start_date$~selected:MIN');
    }

    /**
     * Provides a real widget for error details without starting the facade lifecycle.
     *
     * @param string $class
     * @return UI5InputComboTable|UI5InputButton|UI5Scheduler
     */
    private function element(string $class) : UI5InputComboTable|UI5InputButton|UI5Scheduler
    {
        $widget = new InputNumber(UiPageFactory::createEmpty(new Workbench()));
        $element = $this->getMockBuilder($class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getWidget'])
            ->getMock();
        $element->method('getWidget')->willReturn($widget);
        return $element;
    }
}
