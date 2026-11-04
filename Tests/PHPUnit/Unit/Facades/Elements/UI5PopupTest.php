<?php
namespace exface\UI5Facade\Tests\PHPUnit\Unit\Facades\Elements;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Widgets\Popup;
use exface\UI5Facade\Facades\Elements\UI5Popup;
use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Covers generated popup headers independently of the metamodel and UI lifecycle.
 */
class UI5PopupTest extends TestCase
{
    /**
     * Keeps a nonempty caption and the default footer visible.
     */
    public function testCaptionKeepsHeaderVisible() : void
    {
        $js = $this->buildConstructor($this->createPopup(['caption' => 'Measures']));

        $this->assertStringContainsString('title: "Measures"', $js);
        $this->assertStringContainsString('showHeader: true', $js);
        $this->assertStringContainsString('footer: new sap.m.Toolbar()', $js);
    }

    /**
     * Hides an empty header for missing, empty and whitespace-only captions.
     */
    public function testEmptyCaptionsHideHeader() : void
    {
        foreach ([[], ['caption' => ''], ['caption' => '   ']] as $config) {
            $js = $this->buildConstructor($this->createPopup($config));

            $this->assertStringContainsString('showHeader: false', $js);
            $this->assertStringContainsString('footer: new sap.m.Toolbar()', $js);
        }
    }

    /**
     * Hides the caption without changing its configured value or footer visibility.
     */
    public function testHideCaptionHidesHeaderIndependentlyOfFooter() : void
    {
        $popup = $this->createPopup([
            'caption' => 'Measures',
            'hide_caption' => true,
            'hide_footer' => true
        ]);
        $js = $this->buildConstructor($popup);

        $this->assertStringContainsString('title: ""', $js);
        $this->assertStringContainsString('showHeader: false', $js);
        $this->assertStringContainsString('footer: null', $js);
        $this->assertSame('Measures', $popup->getCaption());
    }

    /**
     * Preserves the existing object-name suffix for a visible inherited caption.
     */
    public function testVisibleParentCaptionRetainsObjectNameSuffix() : void
    {
        $popup = $this->createPopup(['caption' => 'Details']);
        $parent = $this->createStub(WidgetInterface::class);
        $parent->method('getCaption')->willReturn('Details');
        $popup->setParent($parent);
        $js = $this->buildConstructor($popup);

        $this->assertStringContainsString('title: "Details: Task"', $js);
        $this->assertStringContainsString('showHeader: true', $js);
    }

    /**
     * Does not append an object name when the caption is explicitly hidden.
     */
    public function testEmptyParentCaptionDoesNotReintroduceHiddenTitle() : void
    {
        $popup = $this->createPopup(['caption' => 'Measures', 'hide_caption' => true]);
        $parent = $this->createStub(WidgetInterface::class);
        $parent->method('getCaption')->willReturn('');
        $popup->setParent($parent);
        $js = $this->buildConstructor($popup);

        $this->assertStringContainsString('title: ""', $js);
        $this->assertStringContainsString('showHeader: false', $js);
        $this->assertStringNotContainsString(': Task', $js);
    }

    /**
     * Preserves the original object-name suffix for empty captions unless explicitly hidden.
     */
    public function testEmptyCaptionMatchingParentRetainsObjectNameSuffix() : void
    {
        $popup = $this->createPopup(['caption' => '']);
        $parent = $this->createStub(WidgetInterface::class);
        $parent->method('getCaption')->willReturn('');
        $popup->setParent($parent);
        $js = $this->buildConstructor($popup);

        $this->assertStringContainsString('title: ": Task"', $js);
        $this->assertStringContainsString('showHeader: true', $js);
    }

    /**
     * Treats the numeric-looking caption zero as visible text.
     */
    public function testZeroCaptionKeepsHeaderVisible() : void
    {
        $js = $this->buildConstructor($this->createPopup(['caption' => '0']));

        $this->assertStringContainsString('title: "0"', $js);
        $this->assertStringContainsString('showHeader: true', $js);
    }

    /**
     * Preserves a configured icon and keeps its header visible even when the caption is hidden.
     */
    public function testIconKeepsHeaderVisibleWhenCaptionIsHidden() : void
    {
        $js = $this->buildConstructor($this->createPopup([
            'caption' => 'Measures',
            'hide_caption' => true,
            'icon' => 'info'
        ]));

        $this->assertStringContainsString('showHeader: true', $js);
        $this->assertStringContainsString('title: ""', $js);
        $this->assertStringContainsString('icon: "sap-icon://information"', $js);
    }

    /**
     * Keeps the header visible for an icon without any configured caption.
     */
    public function testIconWithoutCaptionKeepsHeaderVisible() : void
    {
        $js = $this->buildConstructor($this->createPopup(['icon' => 'info']));

        $this->assertStringContainsString('showHeader: true', $js);
        $this->assertStringContainsString('icon: "sap-icon://information"', $js);
    }

    /**
     * Treats an empty icon as unset and hides a header without caption text.
     */
    public function testEmptyIconDoesNotKeepHeaderVisible() : void
    {
        $js = $this->buildConstructor($this->createPopup(['icon' => '']));

        $this->assertStringContainsString('showHeader: false', $js);
        $this->assertStringNotContainsString('icon:', $js);
    }

    /**
     * Imports real popup settings with a real Workbench and local object metadata.
     *
     * Constructor bypass isolates configuration from toolbar initialization and translations.
     *
     * @param array $config
     * @return Popup
     */
    private function createPopup(array $config) : Popup
    {
        $object = $this->createStub(MetaObjectInterface::class);
        $object->method('getName')->willReturn('Task');
        $popup = $this->getMockBuilder(Popup::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getWorkbench', 'getMetaObject'])
            ->getMock();
        $popup->method('getWorkbench')->willReturn(new Workbench());
        $popup->method('getMetaObject')->willReturn($object);
        $popup->importUxonObject(new UxonObject($config));
        return $popup;
    }

    /**
     * Runs the real constructor and caption renderer while isolating unrelated layout generation.
     *
     * @param Popup $popup
     * @return string
     */
    private function buildConstructor(Popup $popup) : string
    {
        $element = $this->getMockBuilder(UI5Popup::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getWidget',
                'getController',
                'getId',
                'getIconSrc',
                'registerSubmitOnEnter',
                'buildJsPropertyContentHeight',
                'buildJsPropertyContentWidth',
                'buildJsLayoutConstructor',
                'buildJsFloatingToolbar',
                'buildJsResetter',
                'buildCssElementClass',
                'buildJsPseudoEventHandlers'
            ])
            ->getMock();
        $element->method('getWidget')->willReturn($popup);
        $element->method('getController')->willReturn($this->createStub(UI5ControllerInterface::class));
        $element->method('getId')->willReturn('popup');
        $element->method('getIconSrc')->willReturn('sap-icon://information');
        $element->method('registerSubmitOnEnter')->willReturn($element);
        $element->method('buildJsPropertyContentHeight')->willReturn('');
        $element->method('buildJsPropertyContentWidth')->willReturn('');
        $element->method('buildJsLayoutConstructor')->willReturn('');
        $element->method('buildJsFloatingToolbar')->willReturn('new sap.m.Toolbar()');
        $element->method('buildJsResetter')->willReturn('');
        $element->method('buildCssElementClass')->willReturn('exf-popup');
        $element->method('buildJsPseudoEventHandlers')->willReturn('');
        return $element->buildJsConstructor();
    }
}