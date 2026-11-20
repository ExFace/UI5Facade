<?php

namespace exface\UI5Facade\Tests\PHPUnit\Integration\Facades\Elements;

use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Widgets\DataColumn;
use exface\Core\Widgets\DataTable;
use exface\UI5Facade\Facades\Elements\UI5DataTable;
use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Executes generated live values and change detection across table pagination.
 */
class UI5DataTableSavedSelectionValuesTest extends TestCase
{
    /**
     * Creates code generators without a Workbench or persisted metamodel.
     */
    private function createElement(bool $multiSelect, bool $saveOnNavigation, bool $uiTable = true): UI5DataTable
    {
        $attribute = $this->createStub(MetaAttributeInterface::class);
        $attribute->method('getValueListDelimiter')->willReturn(',');
        $column = $this->createStub(DataColumn::class);
        $column->method('getDataColumnName')->willReturn('Id');
        $column->method('isBoundToAttribute')->willReturn(true);
        $column->method('getAttribute')->willReturn($attribute);
        $widget = $this->createStub(DataTable::class);
        $widget->method('getMultiSelect')->willReturn($multiSelect);
        $widget->method('isMultiSelectSavedOnNavigation')->willReturn($saveOnNavigation);
        $widget->method('getColumnByDataColumnName')->willReturn($column);
        $widget->method('hasUidColumn')->willReturn(true);
        $widget->method('getUidColumn')->willReturn($column);
        $controller = $this->createStub(UI5ControllerInterface::class);
        $controller->method('buildJsEventHandler')->willReturn('oTable.selectNotifications++;');
        $element = $this->getMockBuilder(UI5DataTable::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getWidget', 'getController', 'getId', 'isUiTable', 'isMList'])
            ->getMock();
        $element->method('getWidget')->willReturn($widget);
        $element->method('getController')->willReturn($controller);
        $element->method('getId')->willReturn('selection-table');
        $element->method('isUiTable')->willReturn($uiTable);
        $element->method('isMList')->willReturn(!$uiTable);
        return $element;
    }

    /**
     * Verifies logical selection values without changing native page-local selection semantics.
     */
    public function testSavedSelectionValuesAndChangeDetection(): void
    {
        $scripts = [];
        $selection = new ReflectionMethod(UI5DataTable::class, 'buildJsGetRowsSelected');
        $nativeSelection = new ReflectionMethod(UI5DataTable::class, 'buildJsGetRowsSelectedNative');
        foreach ([
            'saved' => [true, true, true],
            'responsive' => [true, true, false],
            'unsaved' => [true, false, true],
            'single' => [false, true, true]
        ] as $name => $options) {
            $element = $this->createElement(...$options);
            $scripts[$name] = [
                'rows' => $element->buildJsValueGetter(),
                'values' => $element->buildJsValueGetter('Id'),
                'rowCount' => $element->buildJsValueGetter('~rowcount'),
                'page' => $selection->invoke($element, 'oTable'),
                'all' => $selection->invoke($element, 'oTable', false),
                'native' => $nativeSelection->invoke($element, 'oTable'),
                'change' => $element->buildJsOnEventScript('change', 'oTable.changeNotifications++;', 'oEvent')
            ];
        }
        $process = proc_open(
            ['node', '--test-reporter=tap', dirname(__DIR__, 3) . '/Support/UI5DataTableSavedSelectionValues.mjs'],
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
}
