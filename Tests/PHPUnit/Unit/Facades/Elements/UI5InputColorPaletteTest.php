<?php

namespace exface\UI5Facade\Tests\PHPUnit\Unit\Facades\Elements;

use exface\UI5Facade\Facades\Elements\UI5InputColorPalette;
use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\ColorDataType;
use exface\Core\DataTypes\StringEnumDataType;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Interfaces\Widgets\WidgetPropertyDataTypeBindingInterface;
use exface\Core\Interfaces\Widgets\WidgetPropertyScaleInterface;
use exface\Core\Widgets\InputColorPalette;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use UnexpectedValueException;

/**
 * Tests color normalization independently of widget rendering and the metamodel.
 */
class UI5InputColorPaletteTest extends TestCase
{
    /**
     * Creates an element for pure color translation without initializing a facade.
     */
    private function createElement(): UI5InputColorPalette
    {
        return (new ReflectionClass(UI5InputColorPalette::class))->newInstanceWithoutConstructor();
    }

    /**
     * All four semantic presets in exface.Core.Color must resolve to CSS colors.
     */
    public function testCoreSemanticPresets(): void
    {
        self::assertSame(
            ['#107e3e', '#0a6ed1', '#e9730c', '#bb0000'],
            $this->createElement()->translateSemanticColors(['~OK', '~INFO', '~WARNING', '~ERROR'])
        );
    }

    /**
     * A missing entry must not silently replace the requested INFO color.
     */
    public function testMissingPaletteEntryIsReported(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Invalid color preset at key 1: NULL');
        $this->createElement()->translateSemanticColors(['~OK', null, '~WARNING', '~ERROR']);
    }

    /**
     * An empty color is invalid rather than an implicit transparent selection.
     */
    public function testEmptyColorIsReported(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->createElement()->translateSemanticColors(['']);
    }

    /**
     * Unknown semantic tokens must not silently become a different color.
     */
    public function testUnknownSemanticColorIsReported(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Unknown semantic color preset: ~UNKNOWN');
        $this->createElement()->translateSemanticColors(['~UNKNOWN']);
    }

    /**
     * INFO blue is preserved when supplied directly as a hexadecimal color.
     */
    public function testExplicitInfoBlueIsPreserved(): void
    {
        self::assertSame(
            ['#107e3e', '#0a6ed1', '#e9730c', '#bb0000'],
            $this->createElement()->translateSemanticColors(['#107e3e', '#0a6ed1', '#e9730c', '#bb0000'])
        );
    }

    /**
     * CSS colors are preserved and keyed scales become sequential lists.
     */
    public function testCustomCssColorsArePreserved(): void
    {
        self::assertSame(
            ['red', '#123abc', 'rgb(10, 20, 30)', 'transparent'],
            $this->createElement()->translateSemanticColors([
                'first' => 'red',
                'second' => '#123abc',
                'third' => 'rgb(10, 20, 30)',
                'fourth' => 'transparent'
            ])
        );
    }

    /**
     * The scalar translation contract is still used by the defaultColor property.
     */
    public function testSingleDefaultColorRemainsScalar(): void
    {
        self::assertSame('#0a6ed1', $this->createElement()->translateSemanticColors(['~INFO']));
        self::assertSame('transparent', $this->createElement()->translateSemanticColors(['transparent']));
    }

    /**
     * An empty palette stays an empty list.
     */
    public function testEmptyPaletteRemainsArray(): void
    {
        self::assertSame([], $this->createElement()->translateSemanticColors([]));
    }

    /**
     * Runs the pure tooltip formatter independently of a rendered widget.
     */
    private function formatTooltips(array $colors, array $hints = []): array
    {
        $element = $this->createElement();
        $method = (new ReflectionClass($element))->getMethod('formatColorTooltips');
        $method->setAccessible(true);
        return $method->invoke($element, $colors, $hints);
    }

    /**
     * Custom CSS colors show their actual values rather than UI5's generic name.
     */
    public function testTooltipsShowCssValues(): void
    {
        self::assertSame(
            ['#107e3e', 'red', 'rgb(10, 20, 30)', 'transparent'],
            $this->formatTooltips(['#107e3e', 'red', 'rgb(10, 20, 30)', 'transparent'])
        );
    }

    /**
     * Both the visible CSS color and its original semantic token remain available.
     */
    public function testSemanticTooltipIncludesOriginalValue(): void
    {
        self::assertSame(['#0a6ed1 (~INFO)'], $this->formatTooltips(['~INFO']));
    }

    /**
     * Enum value_hints replace the tooltip only for the configured option.
     */
    public function testEnumValueHintsReplaceTooltips(): void
    {
        $type = DataTypeFactory::createFromPrototype(new Workbench(), StringEnumDataType::class);
        $type->importUxonObject(new UxonObject([
            'values' => ['~INFO' => 'Information', '~OK' => 'Success'],
            'value_hints' => ['~INFO' => 'Additional information']
        ]));

        self::assertSame(
            ['Additional information', '#107e3e (~OK)'],
            $this->formatTooltips(['~INFO', '~OK'], $type->getValueHints())
        );
    }

    /**
     * Keyed scales keep the hint of each original value, even for duplicate colors.
     */
    public function testColorScaleTooltipsKeepDistinctHints(): void
    {
        self::assertSame(
            ['Failed validation', 'Waiting for approval'],
            $this->formatTooltips(
                ['FAILED' => 'red', 'BLOCKED' => 'red'],
                ['FAILED' => 'Failed validation', 'BLOCKED' => 'Waiting for approval']
            )
        );
    }

    /**
     * A color-scale hint takes priority over hints shared by its rendered color.
     */
    public function testScaleHintTakesPriorityOverColorHint(): void
    {
        self::assertSame(
            ['Specific failure'],
            $this->formatTooltips(
                ['FAILED' => '~ERROR'],
                ['FAILED' => 'Specific failure', '~ERROR' => 'Generic error', '#bb0000' => 'Red']
            )
        );
    }

    /**
     * Hint text remains plain text, including quotes, markup and Unicode.
     */
    public function testTooltipHintsRemainPlainText(): void
    {
        $hint = 'Grün "OK" <b>ready</b>';
        self::assertSame([$hint], $this->formatTooltips(['green'], ['green' => $hint]));
    }

    /**
     * Hints keyed by rendered CSS color also replace semantic preset tooltips.
     */
    public function testCssHintReplacesSemanticTooltip(): void
    {
        self::assertSame(['Information'], $this->formatTooltips(['~INFO'], ['#0a6ed1' => 'Information']));
    }

    /**
     * Empty descriptions leave the value-based fallback intact.
     */
    public function testEmptyHintKeepsValueTooltip(): void
    {
        self::assertSame(['#107e3e (~OK)'], $this->formatTooltips(['~OK'], ['~OK' => '']));
    }

    /**
     * Generated tooltips use color_hints and prefer explicit widget hints.
     */
    public function testTooltipDelegateUsesConfiguredHints(): void
    {
        $type = DataTypeFactory::createFromPrototype(new Workbench(), ColorDataType::class);
        $type->importUxonObject(new UxonObject([
            'color_hints' => ['~INFO' => 'Information', '~OK' => 'Success']
        ]));
        $binding = $this->createStub(WidgetPropertyDataTypeBindingInterface::class);
        $binding->method('getDataType')->willReturn($type);
        $hintScale = $this->createStub(WidgetPropertyScaleInterface::class);
        $hintScale->method('getScaleValues')->willReturn(['~INFO' => 'Widget-specific information']);
        $widget = $this->createStub(InputColorPalette::class);
        $widget->method('getColorBinding')->willReturn($binding);
        $widget->method('getHintScale')->willReturn($hintScale);
        $element = $this->getMockBuilder(UI5InputColorPalette::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getWidget', 'getPaletteColors'])
            ->getMock();
        $element->method('getWidget')->willReturn($widget);
        $element->method('getPaletteColors')->willReturn(['~INFO', '~OK']);

        $method = (new ReflectionClass($element))->getMethod('buildJsColorTooltips');
        $method->setAccessible(true);
        $js = $method->invoke($element, 'oColorPopover');
        self::assertStringContainsString(
            json_encode(['Widget-specific information', 'Success']),
            $js
        );
        self::assertStringContainsString('onAfterRendering', $js);
        self::assertStringContainsString('setAttribute("aria-label", sTooltip)', $js);
        self::assertStringContainsString('sap.ui.core.Element.getElementById(oColorPopover.getId() + "-palette")', $js);
        self::assertStringNotContainsString('sap.ui.getCore()', $js);
    }
}

