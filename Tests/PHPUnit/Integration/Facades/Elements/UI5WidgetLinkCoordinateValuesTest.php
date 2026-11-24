<?php

namespace exface\UI5Facade\Tests\PHPUnit\Integration\Facades\Elements;

use exface\Core\CommonLogic\WidgetLinkCoordinate;
use exface\Core\Exceptions\Facades\FacadeRuntimeError;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Widgets\DataColumn;
use exface\Core\Widgets\DataTable;
use exface\UI5Facade\Facades\Elements\UI5DataTable;
use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Executes generated coordinate getters against raw rows and a separate logical selection.
 */
class UI5WidgetLinkCoordinateValuesTest extends TestCase
{
    /**
     * Builds real getter code without Workbench or a persisted metamodel.
     */
    private function createElement() : UI5DataTable
    {
        $attribute = $this->createStub(MetaAttributeInterface::class);
        $attribute->method('getValueListDelimiter')->willReturn('|');
        $column = $this->createStub(DataColumn::class);
        $column->method('getDataColumnName')->willReturn('Amount');
        $column->method('isBoundToAttribute')->willReturn(true);
        $column->method('getAttribute')->willReturn($attribute);
        $widget = $this->createStub(DataTable::class);
        $widget->method('getColumnByDataColumnName')->willReturnCallback(
            static fn(string $name) => $name === 'Amount' ? $column : null
        );
        $widget->method('getColumnByAttributeAlias')->willReturnCallback(
            static fn(string $name) => $name === 'AMOUNT' ? $column : null
        );
        $widget->method('getActionDataColumnNames')->willReturn(['Id']);
        $uidColumn = $this->createStub(DataColumn::class);
        $uidColumn->method('getDataColumnName')->willReturn('Id');
        $widget->method('hasUidColumn')->willReturn(true);
        $widget->method('getUidColumn')->willReturn($uidColumn);
        $controller = $this->createStub(UI5ControllerInterface::class);
        $eventScripts = [];
        $controller->method('addOnEventScript')->willReturnCallback(
            static function ($element, string $eventName, string $script) use (&$eventScripts, $controller) {
                $eventScripts[$eventName][] = $script;
                return $controller;
            }
        );
        $controller->method('buildJsEventHandler')->willReturnCallback(
            static function ($element, string $eventName) use (&$eventScripts) {
                return implode("\n", $eventScripts[$eventName] ?? []);
            }
        );
        $element = $this->getMockBuilder(UI5DataTable::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getWidget', 'getId', 'getController', 'buildJsGetRowsSelected', 'getEditableColumnNamesJson'])
            ->getMock();
        $element->method('getWidget')->willReturn($widget);
        $element->method('getId')->willReturn('coordinate-table');
        $element->method('buildJsGetRowsSelected')->willReturn('oTable.selected');
        $element->method('getController')->willReturn($controller);
        $element->method('getEditableColumnNamesJson')->willReturn('["Amount"]');
        return $element;
    }

    /**
     * Indexed values must retain zero and false; scoped aggregation must use the requested rows.
     */
    public function testRawRowsAndScopedAggregates() : void
    {
        $element = $this->createElement();
        $scripts = [];
        foreach ([
            '', 'Amount', 'AMOUNT$0', 'Amount$1', 'Amount$2', 'Amount$99', '$2', '$99',
            '$~loaded', 'Amount$~loaded', 'Amount$~selected',
            'Amount$~loaded:SUM', 'Amount$~selected:SUM', 'Amount$~loaded:LIST_DISTINCT',
            'Amount$~selected:COUNT', 'Amount$~selected:AVG', '~rowcount'
        ] as $coordinate) {
            $scripts[$coordinate] = $element->buildJsValueGetter(new WidgetLinkCoordinate($coordinate));
        }
        $element->addOnRefreshScript('oTable.unrelatedRefresh++;');
        $element->addOnChangeAtCoordinateScript(
            'oTable.scopedValue = oTable.readAggregate();',
            new WidgetLinkCoordinate('Amount$~loaded:SUM')
        );
        $watcher = new ReflectionMethod(UI5DataTable::class, 'buildJsEditableChangesWatcherUpdateMethod');
        $scripts['editWatcher'] = $watcher->invoke($element, 'oDataChanged');
        $process = proc_open(
            ['node', '--test-reporter=tap', dirname(__DIR__, 3) . '\\Support\\UI5WidgetLinkCoordinateValues.mjs'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        self::assertIsResource($process, 'Node must be available for the integration suite.');
        try {
            fwrite($pipes[0], json_encode($scripts, JSON_THROW_ON_ERROR));
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
        } finally {
            $exitCode = proc_close($process);
        }
        self::assertSame(0, $exitCode, $output . $errors);
        self::assertStringContainsString('# fail 0', $output, $output);
    }

    /**
     * Custom row selectors must not silently fall back to selected rows.
     */
    public function testUnknownRowScopeIsRejected() : void
    {
        $element = $this->createElement();
        $this->expectException(FacadeRuntimeError::class);
        $element->buildJsValueGetter('Amount$~custom');
    }

    /**
     * A numbered row cannot be passed through a multi-row aggregate.
     */
    public function testNumberedRowAggregationIsRejected() : void
    {
        $element = $this->createElement();
        $this->expectException(FacadeRuntimeError::class);
        $element->buildJsValueGetter('Amount$0:SUM');
    }
}