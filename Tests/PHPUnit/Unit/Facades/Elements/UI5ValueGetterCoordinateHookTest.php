<?php
namespace exface\UI5Facade\Tests\PHPUnit\Unit\Facades\Elements;

use exface\Core\CommonLogic\Workbench;
use exface\Core\Exceptions\Widgets\WidgetCoordinateUnresolvableError;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Factories\WidgetLinkFactory;
use exface\Core\Widgets\InputButton;
use exface\UI5Facade\Facades\Elements\UI5Chart;
use exface\UI5Facade\Facades\Elements\UI5AbstractElement;
use exface\UI5Facade\Facades\Elements\UI5DataSpreadSheet;
use exface\UI5Facade\Facades\Elements\UI5DataTable;
use exface\UI5Facade\Facades\Elements\UI5ImageGallery;
use exface\UI5Facade\Facades\Elements\UI5InputButton;
use exface\UI5Facade\Facades\Elements\UI5Map;
use exface\UI5Facade\Facades\Elements\UI5Scheduler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Covers validation dispatch between UI5 data controls and their specialized traits.
 */
class UI5ValueGetterCoordinateHookTest extends TestCase
{
    /**
     * Specialized data traits keep their policies instead of the generic UI5 table policy.
     *
     * @return void
     */
    public function testMixedDataTraitsAcceptTheirSupportedCoordinates() : void
    {
        $this->expectNotToPerformAssertions();
        foreach ([
            [UI5Chart::class, 'AMOUNT$2:SUM'],
            [UI5Chart::class, '~legend_active'],
            [UI5ImageGallery::class, 'AMOUNT$2:SUM'],
            [UI5DataSpreadSheet::class, 'AMOUNT$2:SUM'],
            [UI5Map::class, 'NAME$~selected:LIST'],
            [UI5Scheduler::class, '~start_date']
        ] as [$class, $coordinate]) {
            $element = (new ReflectionClass($class))->newInstanceWithoutConstructor();
            (new ReflectionMethod($element, 'validateValueGetterCoordinate'))
                ->invoke($element, WidgetLinkFactory::createCoordinateFromAnything($coordinate));
        }
    }

    /**
     * Unsupported scopes are rejected before rendering or looking up widget columns.
     *
     * @param string $class
     * @param string $coordinate
     * @param string $exception
     * @return void
     */
    #[DataProvider('unsupportedCustomScopes')]
    public function testGetterRejectsUnsupportedCustomScopes(string $class, string $coordinate, string $exception) : void
    {
        $element = $this->element($class);
        $this->expectException($exception);
        $element->buildJsValueGetter($coordinate);
    }

    /**
     * Supplies scope restrictions specific to charts, maps and row-count values.
     *
     * @return array
     */
    public static function unsupportedCustomScopes() : array
    {
        return [
            [UI5Chart::class, '~legend_active$2:SUM', WidgetCoordinateUnresolvableError::class],
            [UI5Map::class, 'NAME$~loaded:LIST', WidgetCoordinateUnresolvableError::class],
            [UI5DataTable::class, '~rowcount$2', WidgetCoordinateUnresolvableError::class]
        ];
    }

    /**
     * Ordinary UI5 tables retain their restriction on numeric-row aggregation.
     *
     * @return void
     */
    public function testTableRejectsNumberedRowAggregation() : void
    {
        $element = $this->element(UI5DataTable::class);
        $this->expectException(WidgetCoordinateUnresolvableError::class);
        $element->buildJsValueGetter('AMOUNT$2:SUM');
    }

    /**
     * Button result-row support does not grant row support to its own scalar value.
     *
     * @return void
     */
    public function testInputButtonRejectsRowCoordinateForItsScalarValue() : void
    {
        $this->expectException(WidgetCoordinateUnresolvableError::class);
        $this->inputButton()->buildJsValueGetter('KEY$2');
    }

    /**
     * A named result column remains addressable by a numeric row.
     *
     * @return void
     */
    public function testInputButtonAcceptsNumberedResultColumn() : void
    {
        $this->expectNotToPerformAssertions();
        $element = $this->inputButton();
        (new ReflectionMethod($element, 'validateValueGetterCoordinate'))
            ->invoke($element, WidgetLinkFactory::createCoordinateFromAnything('RESULT$2'));
    }

    /**
     * Provides widget context for coordinate failures without constructing a UI5 facade.
     *
     * @param string $class
     * @return UI5AbstractElement
     */
    private function element(string $class) : UI5AbstractElement
    {
        $widget = new InputButton(UiPageFactory::createEmpty(new Workbench()));
        $element = $this->getMockBuilder($class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getWidget'])
            ->getMock();
        $element->method('getWidget')->willReturn($widget);
        return $element;
    }

    /**
     * Uses real widget metadata without constructing a UI5 facade.
     *
     * @return UI5InputButton
     */
    private function inputButton() : UI5InputButton
    {
        $element = $this->element(UI5InputButton::class);
        $element->getWidget()->setDataColumnName('KEY');
        return $element;
    }
}
