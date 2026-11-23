<?php
namespace exface\UI5Facade\Tests\PHPUnit\Unit\Facades\Elements;

use exface\Core\CommonLogic\Configuration;
use exface\Core\CommonLogic\ContextManager;
use exface\Core\CommonLogic\Workbench;
use exface\Core\DataTypes\MarkdownDataType;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Widgets\InputMarkdown;
use exface\Core\Widgets\Parts\CodeFormatter;
use exface\UI5Facade\Facades\Elements\UI5InputMarkdown;
use exface\UI5Facade\Facades\UI5Facade;
use PHPUnit\Framework\TestCase;

/**
 * Covers source and configuration generation without starting a webapp or mocking Workbench.
 */
class UI5MarkianTest extends TestCase
{
    /**
     * Markdown newlines and braces must survive the native UI5 property literal.
     */
    public function testLiteralSourcePreservesNewlinesAndCannotCloseInlineScripts() : void
    {
        $source = "# Notes\n\n{not a binding}\n</script><script>unsafe()</script>";
        $literal = $this->createElement($source)->value();
        self::assertStringNotContainsString('</script>', $literal);
        self::assertStringContainsString('\\n\\n', $literal);
        self::assertStringContainsString('\\\\{not a binding\\\\}', $literal);
        self::assertStringContainsString('\\u003C/script>', $literal);
    }

    /**
     * Data-bound documents must use a binding object rather than a PHP-time snapshot.
     */
    public function testBoundSourceUsesTheNativeValueProperty() : void
    {
        $element = $this->createElement('old snapshot', true);
        self::assertSame('{path: "/rows/0/markdown"}', $element->value());
        self::assertSame('value', $element->buildJsValueBindingPropertyName());
    }

    /**
     * UI5 uses the same editor configuration while keeping Mermaid and image permissions explicit.
     */
    public function testEditorConfigRetainsSourceAndOptionalExtensions() : void
    {
        $source = "# Notes\n\n</script>";
        $config = $this->createElement($source)->config();
        self::assertStringContainsString('"initialMode":"visual"', $config);
        self::assertStringContainsString('"markdown":' . json_encode($source, JSON_HEX_TAG), $config);
        self::assertStringContainsString('MarkianAdapter.buildCodeConfig(', $config);
        self::assertStringContainsString('MarkianAdapter.buildTags(', $config);
        self::assertStringContainsString('mermaidSource: ' . json_encode('assets/LIBS.PELE.JS'), $config);
        self::assertStringContainsString('allowImages: false', $config);
        self::assertStringNotContainsString('toastui', $config);
    }

    /**
     * Source setters must remain native control updates, without synthetic change events.
     */
    public function testSourceSettersDoNotFireUserChanges() : void
    {
        $element = $this->createElement('');
        self::assertStringContainsString('oControl.setValue(mValue)', $element->buildJsValueSetter('nextSource'));
        self::assertStringNotContainsString('fireChange', $element->buildJsValueSetter('nextSource'));
        self::assertStringContainsString('oControl.getValue()', $element->buildJsValueGetter());
    }

    /**
     * The active regional locale loads its installed language file through the facade.
     */
    public function testEditorLocaleUsesResolvedTranslationAsset() : void
    {
        $config = $this->createElement('Unchanged source', false, 'de_DE')->config();
        self::assertStringContainsString('"translations":' . json_encode(['assets/axenox/markian/Translations/de.json?v-test']), $config);
        self::assertStringContainsString('"label":"EDITOR.LABEL"', $config);
        self::assertStringContainsString('"markdown":"Unchanged source"', $config);
    }

    /**
     * Unsupported languages keep embedded English without requesting a missing file.
     */
    public function testUnsupportedLocaleDoesNotRequestMissingTranslation() : void
    {
        self::assertStringContainsString('"translations":[]', $this->createElement('', false, 'zz_ZZ')->config());
    }

    /**
     * Omits facade initialization only; configuration and the generator remain real.
     */
    private function createElement(string $source, bool $bound = false, string $locale = 'en') : UI5InputMarkdown
    {
        $type = $this->createStub(MarkdownDataType::class);
        $type->method('getEnableNewlines')->willReturn(true);
        $formatter = $this->createStub(CodeFormatter::class);
        $formatter->method('getColorize')->willReturn(true);
        $widget = $this->createStub(InputMarkdown::class);
        $widget->method('getValueWithDefaults')->willReturn($source);
        $widget->method('getValueDataType')->willReturn($type);
        $widget->method('getCodeFormatter')->willReturn($formatter);
        $widget->method('hasRenderMermaidDiagrams')->willReturn(true);
        $widget->method('getAllowImages')->willReturn(false);
        $workbench = new Workbench();
        (new \ReflectionProperty(Workbench::class, 'context'))->setValue($workbench, new ContextManager($workbench));
        $workbench->getContext()->getScopeSession()->setSessionLocale($locale);
        $config = new Configuration($workbench);
        $config->setOption('LIBS.MARKIAN.MARKDOWN.CSS_CLASSES', ['markdown-body']);
        $config->setOption('LIBS.MARKIAN.TRANSLATIONS', 'axenox/markian/Translations/');
        $facade = $this->createStub(UI5Facade::class);
        $facade->method('getConfig')->willReturn($config);
        $facade->method('getWorkbench')->willReturn($workbench);
        $facade->method('buildUrlToVendorFile')->willReturnCallback(static function (string $path) : string {
            return 'assets/' . $path . '?v-test';
        });
        $facade->method('buildUrlToSource')->willReturnCallback(static function (string $key) : string {
            return 'assets/' . $key;
        });
        return new class($widget, $facade, $bound) extends UI5InputMarkdown {
            private $testWidget;
            private $testFacade;
            private $bound;

            /**
             * Supplies only boundaries needed by the source/configuration generator.
             */
            public function __construct(WidgetInterface $widget, FacadeInterface $facade, bool $bound = false)
            {
                $this->testWidget = $widget;
                $this->testFacade = $facade;
                $this->bound = $bound;
            }

            /**
             * {@inheritDoc}
             * @see UI5InputMarkdown::getWidget()
             */
            public function getWidget()
            {
                return $this->testWidget;
            }

            /**
             * {@inheritDoc}
             * @see UI5InputMarkdown::getFacade()
             */
            public function getFacade()
            {
                return $this->testFacade;
            }

            /**
             * {@inheritDoc}
             * @see UI5InputMarkdown::getId()
             */
            public function getId() : string
            {
                return 'markdown-fixture';
            }

            /**
             * {@inheritDoc}
             * @see UI5InputMarkdown::isValueBoundToModel()
             */
            public function isValueBoundToModel() : bool
            {
                return $this->bound;
            }

            /**
             * {@inheritDoc}
             * @see UI5InputMarkdown::buildJsValueBinding()
             */
            public function buildJsValueBinding($customOptions = '')
            {
                return '{path: "/rows/0/markdown"}';
            }

            /**
             * Exposes source serialization independently of a controller.
             */
            public function value() : string
            {
                return $this->buildJsMarkdownValue();
            }

            /**
             * Exposes widget translation independently of a controller.
             */
            public function config() : string
            {
                return $this->buildJsMarkdownConfig();
            }
        };
    }
}
