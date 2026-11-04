<?php

namespace exface\UI5Facade\Tests\PHPUnit\Unit\Facades\Elements;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Widgets\DataTable;
use exface\UI5Facade\Facades\Elements\UI5DataTable;
use exface\UI5Facade\Facades\UI5Facade;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Keeps local table reloads independent of server-side selection restoration.
 */
class UI5DataTableLocalSelectionTest extends TestCase
{
    /**
     * Local reloads must not clear selections without a corresponding restoration.
     */
    public function testLocalReloadDoesNotResetSelection() : void
    {
        $data = $this->createStub(DataSheetInterface::class);
        $data->method('isFresh')->willReturn(true);
        $widget = $this->createStub(DataTable::class);
        $widget->method('getValuesDataSheet')->willReturn($data);
        $widget->method('prepareDataSheetToRead')->willReturn($data);
        $facade = $this->createStub(UI5Facade::class);
        $facade->method('buildResponseData')->willReturn(['rows' => [['Id' => 1]]]);
        $facade->method('encodeData')->willReturn('{"rows":[{"Id":1}]}');
        $element = $this->getMockBuilder(UI5DataTable::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getWidget',
                'getDataWidget',
                'getFacade',
                'getId',
                'shouldDestroyViewOnHide',
                'buildJsDataLoaderOnBeforeModelUpdate'
            ])
            ->getMock();
        $element->method('getWidget')->willReturn($widget);
        $element->method('getDataWidget')->willReturn($widget);
        $element->method('getFacade')->willReturn($facade);
        $element->method('getId')->willReturn('local-selection-table');
        $element->method('shouldDestroyViewOnHide')->willReturn(false);
        $element->expects(self::never())->method('buildJsDataLoaderOnBeforeModelUpdate');

        $loader = new ReflectionMethod(UI5DataTable::class, 'buildJsDataLoaderFromLocal');
        $loader->setAccessible(true);
        $script = $loader->invoke($element);

        self::assertStringContainsString('oCtrl.getModel().setData(', $script);
        self::assertStringNotContainsString('clearSelection', $script);
    }
}
