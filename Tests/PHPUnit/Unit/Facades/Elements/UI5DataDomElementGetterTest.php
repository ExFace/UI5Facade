<?php

namespace exface\UI5Facade\Tests\PHPUnit\Unit\Facades\Elements;

use exface\UI5Facade\Facades\Elements\UI5DataColumn;
use exface\UI5Facade\Facades\Elements\UI5DataTable;
use exface\UI5Facade\Facades\Elements\UI5DataTree;
use PHPUnit\Framework\TestCase;

/**
 * Covers shared data-widget DOM getters independently of the metamodel.
 */
class UI5DataDomElementGetterTest extends TestCase
{
    /**
     * Tables and trees prefer their outer wrappers over the inner control.
     */
    public function testDataWidgetsResolveOutermostRenderedWrapper() : void
    {
        foreach ([UI5DataTable::class, UI5DataTree::class] as $class) {
            $element = $this->getMockBuilder($class)->disableOriginalConstructor()
                ->onlyMethods(['getId'])->getMock();
            $element->method('getId')->willReturn('data_table');

            self::assertSame(
                '(document.getElementById("data_table_DynamicPageWrapper")'
                    . ' || document.getElementById("data_table_panel")'
                    . ' || $(\'#data_table\')[0])',
                $element->buildJsDomElementGetter()
            );
        }
    }

    /**
     * Explicit column targets keep the column's own DOM element.
     */
    public function testColumnKeepsItsOwnDomTarget() : void
    {
        $element = $this->getMockBuilder(UI5DataColumn::class)->disableOriginalConstructor()
            ->onlyMethods(['getId'])->getMock();
        $element->method('getId')->willReturn('progress_column');

        self::assertSame('$(\'#progress_column\')[0]', $element->buildJsDomElementGetter());
    }
}
