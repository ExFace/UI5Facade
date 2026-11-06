<?php

namespace exface\UI5Facade\Tests\PHPUnit\Unit\Facades\Elements;

use exface\Core\Widgets\DataTable;
use exface\UI5Facade\Facades\Elements\UI5DataTable;
use PHPUnit\Framework\TestCase;

/**
 * Covers the pre-update selection hook independently of the application lifecycle.
 */
class UI5DataTableModelUpdateTest extends TestCase
{
    /**
     * Creates a code-generation fixture without a Workbench or persisted metamodel.
     */
    private function createElement(bool $uiTable, bool $multiSelect): UI5DataTable
    {
        $widget = $this->createStub(DataTable::class);
        $widget->method('getMultiSelect')->willReturn($multiSelect);
        $element = $this->getMockBuilder(UI5DataTable::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getWidget', 'isUiTable', 'getId'])
            ->getMock();
        $element->method('getWidget')->willReturn($widget);
        $element->method('isUiTable')->willReturn($uiTable);
        $element->method('getId')->willReturn('selection-table');
        return $element;
    }

    /**
     * Multi-select tables clear their binding under the existing selection guard.
     */
    public function testMultiSelectClearsBindingWithoutReplacingSavedSelection(): void
    {
        $script = $this->createElement(true, true)->buildJsDataLoaderOnBeforeModelUpdate();
        self::assertStringContainsString("getBinding('rows')", $script);
        self::assertStringContainsString('oTable.__modifyingSelection = true;', $script);
        self::assertStringContainsString('oTable.clearSelection();', $script);
        self::assertStringContainsString('finally', $script);
        self::assertStringContainsString('oTable.__modifyingSelection = bModifyingSelection;', $script);
        self::assertStringNotContainsString('setProperty', $script);
        self::assertStringContainsString("byId('selection-table')", $script);
    }

    /**
     * Single-select tables keep their current data-loading behavior.
     */
    public function testSingleSelectDoesNotClearBinding(): void
    {
        self::assertSame('', $this->createElement(true, false)->buildJsDataLoaderOnBeforeModelUpdate());
    }

    /**
     * Responsive tables do not use the TreeBinding selection reset.
     */
    public function testResponsiveTableDoesNotClearBinding(): void
    {
        self::assertSame('', $this->createElement(false, true)->buildJsDataLoaderOnBeforeModelUpdate());
    }
}
