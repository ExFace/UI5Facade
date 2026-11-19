<?php
namespace exface\UI5Facade\Tests\PHPUnit\Unit\Facades\Elements;

use exface\Core\CommonLogic\Configuration;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\UI5Facade\Facades\Elements\ServerAdapters\OfflineServerAdapter;
use exface\UI5Facade\Facades\Elements\ServerAdapters\UI5FacadeServerAdapter;
use exface\UI5Facade\Facades\Elements\UI5InputUxon;
use exface\UI5Facade\Facades\UI5Facade;
use PHPUnit\Framework\TestCase;

/**
 * Covers the editor's online backend policy without starting a UI5 webapp.
 */
class UI5JsonEditorAvailabilityTest extends TestCase
{
    /**
     * The normal online backend must also work when a PWA would wrap it.
     */
    public function testFacadeBackendSupportsCustomRequests() : void
    {
        self::assertSame('function() { return true; }', $this->buildAvailability(UI5FacadeServerAdapter::class));
    }

    /**
     * Incompatible adapters must never send editor requests to the facade API.
     */
    public function testNonFacadeBackendDisablesCustomRequests() : void
    {
        self::assertSame('function() { return false; }', $this->buildAvailability(OfflineServerAdapter::class));
    }

    /**
     * A backend extending the facade adapter keeps the same endpoint contract.
     */
    public function testFacadeBackendSubclassSupportsCustomRequests() : void
    {
        $adapter = new class extends UI5FacadeServerAdapter {
            /**
             * No element is needed to test the adapter's class relationship.
             */
            public function __construct()
            {
            }
        };
        self::assertSame('function() { return true; }', $this->buildAvailability(get_class($adapter)));
    }

    /**
     * Supplies a real in-memory configuration and only omits facade initialization.
     */
    private function buildAvailability(string $adapterClass) : string
    {
        $config = new Configuration(new Workbench());
        $config->setOption('DEFAULT_SERVER_ADAPTER_CLASS', $adapterClass);
        $facade = $this->createStub(UI5Facade::class);
        $facade->method('getConfig')->willReturn($config);
        $element = new class(null, $facade) extends UI5InputUxon {
            private $testFacade;

            /**
             * Initializes only the configuration boundary of this generator fixture.
             */
            public function __construct(?WidgetInterface $widget = null, ?FacadeInterface $facade = null)
            {
                $this->testFacade = $facade;
            }

            /**
             * {@inheritDoc}
             * @see UI5InputUxon::getFacade()
             */
            public function getFacade()
            {
                return $this->testFacade;
            }

            /**
             * Exposes the inherited policy without rendering a full view.
             */
            public function availability() : string
            {
                return $this->buildJsJsonEditorServerAvailable();
            }
        };
        return $element->availability();
    }
}
