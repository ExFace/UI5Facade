<?php
namespace exface\UI5Facade\Tests\PHPUnit\Unit\Facades\Elements;

use exface\Core\DataTypes\MarkdownDataType;
use exface\Core\Widgets\DisplayMarkdown;
use exface\Core\Widgets\Parts\CodeFormatter;
use exface\UI5Facade\Facades\Elements\UI5DisplayMarkdown;
use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;
use exface\UI5Facade\Facades\UI5Facade;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Verifies that display widgets stay rendering-only and do not require input widget APIs.
 */
class UI5MarkianViewerTest extends TestCase
{
    /**
     * DisplayMarkdown has neither mentions nor image-editing settings; rendering must not ask for them.
     */
    public function testDisplayConfigUsesOnlyViewerOptions() : void
    {
        $element = $this->createElement();
        $method = new ReflectionMethod($element, 'buildJsMarkdownConfig');
        $config = $method->invoke($element);
        self::assertStringContainsString('MarkianAdapter.buildCodeRenderers(', $config);
        self::assertStringContainsString('softbreaks: false', $config);
        self::assertStringContainsString('mermaidSource: "assets\\/LIBS.PELE.JS"', $config);
        self::assertStringNotContainsString('buildTags', $config);
        self::assertStringNotContainsString('initialMode', $config);
    }

    /**
     * Viewer pages must not download the editing engine or editing-only Mermaid extension.
     */
    public function testDisplayRegistersIndependentViewerAssets() : void
    {
        $modules = [];
        $controller = $this->createMock(UI5ControllerInterface::class);
        $controller->method('addExternalModule')->willReturnCallback(
            static function (string $name, string $url) use (&$modules, $controller) : UI5ControllerInterface {
                $modules[$name] = $url;
                return $controller;
            }
        );
        $controller->method('addExternalCss')->willReturnSelf();
        $this->createElement()->registerExternalModules($controller);
        self::assertContains('assets/LIBS.MARKIAN.VIEWER.JS', $modules);
        self::assertContains('assets/LIBS.MARKIAN.VIEWER_MERMAID.JS', $modules);
        self::assertNotContains('assets/LIBS.MARKIAN.EDITOR.JS', $modules);
        self::assertNotContains('assets/LIBS.MARKIAN.MERMAID.JS', $modules);
    }

    /**
     * Keeps the actual viewer generator while omitting controller and metamodel initialization.
     */
    private function createElement() : UI5DisplayMarkdown
    {
        $type = $this->createStub(MarkdownDataType::class);
        $type->method('getEnableNewlines')->willReturn(false);
        $formatter = $this->createStub(CodeFormatter::class);
        $formatter->method('getColorize')->willReturn(true);
        $widget = $this->createStub(DisplayMarkdown::class);
        $widget->method('getValueDataType')->willReturn($type);
        $widget->method('getCodeFormatter')->willReturn($formatter);
        $widget->method('hasRenderMermaidDiagrams')->willReturn(true);
        $facade = $this->createStub(UI5Facade::class);
        $facade->method('buildUrlToSource')->willReturnCallback(static function (string $key) : string {
            return 'assets/' . $key;
        });
        $element = $this->getMockBuilder(UI5DisplayMarkdown::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getWidget', 'getFacade'])
            ->getMock();
        $element->method('getWidget')->willReturn($widget);
        $element->method('getFacade')->willReturn($facade);
        return $element;
    }
}
