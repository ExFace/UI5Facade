<?php

namespace exface\UI5Facade\Tests\PHPUnit\Integration\Facades\Elements;

use exface\Core\Actions\ReadData;
use exface\Core\Actions\ReadPrefill;
use exface\Core\CommonLogic\Model\Expression;
use exface\Core\Interfaces\ConfigurationInterface;
use exface\Core\Interfaces\DataSheets\DataColumnListInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\ConditionGroupInterface;
use exface\Core\Interfaces\Model\ConditionInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Widgets\DataTable;
use exface\UI5Facade\Facades\Elements\ServerAdapters\OData2ServerAdapter;
use exface\UI5Facade\Facades\Elements\ServerAdapters\OfflineServerAdapter;
use exface\UI5Facade\Facades\Elements\ServerAdapters\PreloadServerAdapter;
use exface\UI5Facade\Facades\Elements\ServerAdapters\UI5FacadeServerAdapter;
use exface\UI5Facade\Facades\Elements\UI5AbstractElement;
use exface\UI5Facade\Facades\Elements\UI5DataTable;
use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;
use exface\UI5Facade\Facades\UI5Facade;
use exface\UI5Facade\Webapp;
use PHPUnit\Framework\TestCase;

/**
 * Executes real adapter code generation with local metadata and transport boundaries.
 */
class UI5DataTableAdapterModelScopeTest extends TestCase
{
    /**
     * Generates read and prefill scripts without a Workbench or persisted metamodel.
     *
     * Export metadata discovery is isolated; adapter routing and selection hooks remain real.
     *
     * @return array
     */
    private function buildScripts() : array
    {
        require_once dirname((new \ReflectionClass(ReadData::class))->getFileName(), 2) . '/CommonLogic/Constants.php';

        $tableWidget = $this->createStub(DataTable::class);
        $tableWidget->method('getMultiSelect')->willReturn(true);
        $table = $this->getMockBuilder(UI5DataTable::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getWidget', 'getId', 'isUiTable'])
            ->getMock();
        $table->method('getWidget')->willReturn($tableWidget);
        $table->method('getId')->willReturn('selection-table');
        $table->method('isUiTable')->willReturn(true);

        $uid = $this->createStub(MetaAttributeInterface::class);
        $uid->method('getAlias')->willReturn('Id');
        $uid->method('getDataAddress')->willReturn('Id');
        $object = $this->createStub(MetaObjectInterface::class);
        $object->method('getAlias')->willReturn('Selection');
        $object->method('getAliasWithNamespace')->willReturn('test.Selection');
        $object->method('getId')->willReturn('selection-object');
        $object->method('getDataAddress')->willReturn('Rows');
        $object->method('hasUidAttribute')->willReturn(true);
        $object->method('getUidAttribute')->willReturn($uid);
        $object->method('getUidAttributeAlias')->willReturn('Id');
        $expression = $this->createStub(Expression::class);
        $expression->method('isMetaAttribute')->willReturn(true);
        $expression->method('toString')->willReturn('Id');
        $expression->method('getAttribute')->willReturn($uid);
        $condition = $this->createStub(ConditionInterface::class);
        $condition->method('getExpression')->willReturn($expression);
        $quickSearch = $this->createStub(ConditionGroupInterface::class);
        $quickSearch->method('getConditions')->willReturn([$condition]);
        $widget = $this->createStub(DataTable::class);
        $widget->method('getMetaObject')->willReturn($object);
        $widget->method('getQuickSearchConditionGroup')->willReturn($quickSearch);
        $config = $this->createStub(ConfigurationInterface::class);
        $config->method('getOption')->willReturn('GET');
        $page = $this->createStub(UiPageInterface::class);
        $page->method('getAliasWithNamespace')->willReturn('test.Selection');
        $webapp = $this->createStub(Webapp::class);
        $webapp->method('getRootPage')->willReturn($page);
        $facade = $this->createStub(UI5Facade::class);
        $facade->method('getConfig')->willReturn($config);
        $facade->method('getWebapp')->willReturn($webapp);
        $facade->method('getUrlFilterPrefix')->willReturn('filter_');
        $controller = $this->createStub(UI5ControllerInterface::class);
        $controller->method('buildJsComponentGetter')->willReturn('component');
        $element = $this->createStub(UI5AbstractElement::class);
        $element->method('getWidget')->willReturn($widget);
        $element->method('getMetaObject')->willReturn($object);
        $element->method('getFacade')->willReturn($facade);
        $element->method('getController')->willReturn($controller);
        $element->method('getAjaxHeaders')->willReturn([]);
        $element->method('getAjaxUrl')->willReturn('/read');
        $element->method('buildJsShowError')->willReturn('');
        $element->method('buildJsDataLoaderOnBeforeModelUpdate')->willReturnCallback(
            static function (?string $model = null) use ($table) : string {
                return $table->buildJsDataLoaderOnBeforeModelUpdate($model);
            }
        );

        $columns = $this->createStub(DataColumnListInterface::class);
        $columns->method('getIterator')->willReturn(new \ArrayIterator());
        $sheet = $this->createStub(DataSheetInterface::class);
        $sheet->method('getColumns')->willReturn($columns);
        $odata = $this->getMockBuilder(OData2ServerAdapter::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getElement', 'getAttributeAliasesForLocalFilters', 'checkWidgetExportable',
                'getCompoundAttributePropertiesForObject', 'getDataSheetToRead',
                'getODataModelParams', 'buildJsServerResponseError'
            ])
            ->getMock();
        $odata->method('getElement')->willReturn($element);
        $odata->method('getAttributeAliasesForLocalFilters')->willReturn([]);
        $odata->method('getCompoundAttributePropertiesForObject')->willReturn([]);
        $odata->method('getDataSheetToRead')->willReturn($sheet);
        $odata->method('getODataModelParams')->willReturn('{}');
        $odata->method('buildJsServerResponseError')->willReturn('');

        $ajax = new UI5FacadeServerAdapter($element);
        $adapters = [
            'ajax' => $ajax,
            'odata' => $odata,
            'preload' => new PreloadServerAdapter($element, $ajax),
            'offline' => new OfflineServerAdapter($element, $ajax)
        ];
        $scripts = [];
        foreach ($adapters as $name => $adapter) {
            foreach (['read' => ReadData::class, 'prefill' => ReadPrefill::class] as $operation => $actionClass) {
                $scripts[$name][$operation] = $adapter->buildJsServerRequest(
                    (new \ReflectionClass($actionClass))->newInstanceWithoutConstructor(),
                    'targetModel',
                    'params',
                    'loaded++;',
                    'errors++;',
                    'offlineErrors++;'
                );
            }
        }
        $scripts['reset'] = $table->buildJsDataLoaderOnBeforeModelUpdate();
        return $scripts;
    }

    /**
     * Checks model isolation, prefill, failures and cache fallbacks in generated JavaScript.
     */
    public function testAdapterSelectionModelScope() : void
    {
        $scripts = json_encode($this->buildScripts(), JSON_THROW_ON_ERROR);
        $process = proc_open(
            ['node', '--test-reporter=tap', dirname(__DIR__, 3) . '/Support/UI5DataTableAdapterModelScope.mjs'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        self::assertIsResource($process, 'Node must be available for the integration suite.');
        try {
            fwrite($pipes[0], $scripts);
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
