<?php

namespace exface\UI5Facade\Tests\PHPUnit\Unit\Facades\Elements;

use exface\Core\Widgets\Button;
use exface\Core\Widgets\DataLookupDialog;
use exface\Core\Widgets\Toolbar;
use exface\UI5Facade\Facades\Elements\UI5DataLookupDialog;
use exface\UI5Facade\Facades\Elements\UI5DataTable;
use exface\UI5Facade\Facades\Elements\UI5Dialog;
use exface\UI5Facade\Facades\Elements\UI5Toolbar;
use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;
use exface\UI5Facade\Facades\UI5Facade;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Covers lookup tour menus without putting them in Dialog.buttons.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class UI5LookupTourMenuTest extends TestCase
{
    /**
     * Closing a lookup notifies the underlying page even without a visible tour menu.
     */
    public function testClosingLookupResumesPendingTour() : void
    {
        foreach (['', 'new sap.m.MenuButton()'] as $tourJs) {
            $js = $this->buildDialogScript($tourJs);

            self::assertStringContainsString('afterClose: function () {', $js);
            self::assertStringContainsString("window.dispatchEvent(new Event('exf-tour-resume'));", $js);
            self::assertSame(1, substr_count($js, "new Event('exf-tour-resume')"));
        }
    }

    /**
     * Normal buttons stay in Dialog.buttons; only the tour menu is added to the toolbar.
     */
    public function testVisibleTourMenuIsAddedToFooterToolbar() : void
    {
        $js = $this->buildDialogScript('new sap.m.MenuButton(),');

        self::assertStringContainsString('buttons : [ new sap.m.Button() ]', $js);
        self::assertStringNotContainsString('footer:', $js);
        self::assertStringNotContainsString('subHeader:', $js);
        self::assertStringContainsString('var aTourMenus = [new sap.m.MenuButton(),]', $js);
        self::assertStringContainsString('oTourDialog._getToolbar().insertContent(oControl, 1 + 0 + iIndex)', $js);
        self::assertLessThan(strpos($js, 'new sap.m.MenuButton()'), strpos($js, 'afterOpen:'));
    }

    /**
     * The menu follows the left group and precedes the right group, after UI5's spacer.
     */
    public function testTourMenuIsInsertedBetweenButtonGroups() : void
    {
        $cases = [
            'no left buttons' => [['right', 'opposite'], 0],
            'one left button' => [['left', 'right'], 1],
            'mixed groups' => [['right', 'default', 'opposite', 'left', 'center'], 3],
            'only left buttons' => [['left', 'default'], 2]
        ];
        foreach ($cases as $name => [$alignments, $leftButtonCount]) {
            $js = $this->buildDialogScript('new sap.m.MenuButton()', $alignments);

            self::assertStringContainsString(
                "_getToolbar().insertContent(oControl, 1 + {$leftButtonCount} + iIndex)",
                $js,
                $name
            );
            self::assertStringNotContainsString('_getToolbar().addContent(', $js);
            self::assertStringContainsString('buttons : [ new sap.m.Button() ]', $js);
        }
    }

    /**
     * Without a tour menu, normal footer buttons and selection initialization remain.
     */
    public function testHiddenTourKeepsNormalFooterButtons() : void
    {
        $js = $this->buildDialogScript('');

        self::assertStringContainsString('buttons : [ new sap.m.Button() ]', $js);
        self::assertStringContainsString('var aTourMenus = []', $js);
        self::assertStringNotContainsString('footer:', $js);
        self::assertStringNotContainsString('subHeader:', $js);
        self::assertStringNotContainsString('new sap.m.MenuButton(', $js);
        self::assertStringContainsString('initializeSelections(oEvent);', $js);
    }

    /**
     * Reopening destroys only the previous tour menu and preserves the normal buttons.
     */
    public function testReopeningCleansUpOnlyTourMenus() : void
    {
        $js = $this->buildDialogScript('new sap.m.MenuButton()');

        self::assertStringContainsString("oTourDialog.data('exfLookupTourMenus') || []", $js);
        self::assertStringContainsString('oControl.destroy();', $js);
        self::assertStringContainsString("oTourDialog.data('exfLookupTourMenus', aTourMenus)", $js);
        self::assertStringNotContainsString('destroyContent()', $js);
        self::assertLessThan(strpos($js, 'new sap.m.MenuButton()'), strpos($js, 'oControl.destroy();'));
        self::assertStringContainsString('initializeSelections(oEvent);', $js);
    }

    /**
     * Only lookups exclude the tour from Dialog.buttons; other dialogs remain unchanged.
     */
    public function testLookupSeparatesTourMenuWithoutChangingOtherDialogs() : void
    {
        $widget = $this->createMock(DataLookupDialog::class);
        $widget->method('getToolbarMain')->willReturn($this->createMock(Toolbar::class));
        $toolbar = $this->createMock(UI5Toolbar::class);
        $toolbar->method('buildJsConstructorsForLeftButtons')->willReturn('left(),');
        $toolbar->method('buildJsConstructorsForRightButtons')->willReturn('right()');
        $facade = $this->createMock(UI5Facade::class);
        $facade->method('getElement')->willReturn($toolbar);

        foreach ([UI5DataLookupDialog::class => false, UI5Dialog::class => true] as $class => $hasTour) {
            $element = $this->getMockBuilder($class)->disableOriginalConstructor()
                ->onlyMethods(['getWidget', 'getFacade', 'getController', 'buildJsTourGuideDropdown'])
                ->getMock();
            $element->method('getWidget')->willReturn($widget);
            $element->method('getFacade')->willReturn($facade);
            $element->method('getController')->willReturn($this->createMock(UI5ControllerInterface::class));
            $element->expects($hasTour ? self::once() : self::never())
                ->method('buildJsTourGuideDropdown')->willReturn('tour(),');

            $js = (new \ReflectionMethod($class, 'buildJsDialogButtons'))->invoke($element, false);
            self::assertSame($hasTour ? 'left(),tour(),right()' : 'left(),right()', $js);
        }
    }

    /**
     * Exercises the dialog generator with isolated rendering collaborators.
     *
     * @param string $tourJs
     * @param string[] $buttonAlignments
     * @return string
     */
    private function buildDialogScript(string $tourJs, array $buttonAlignments = []) : string
    {
        require_once dirname((new \ReflectionClass(Button::class))->getFileName(), 2)
            . DIRECTORY_SEPARATOR . 'CommonLogic' . DIRECTORY_SEPARATOR . 'Constants.php';

        $widget = $this->createMock(DataLookupDialog::class);
        $buttons = [];
        foreach ($buttonAlignments as $alignment) {
            $button = $this->createMock(Button::class);
            $button->method('getAlign')->willReturn($alignment);
            $buttons[] = $button;
        }
        $toolbar = $this->createMock(Toolbar::class);
        $toolbar->method('getButtons')->willReturn($buttons);
        $widget->method('getToolbarMain')->willReturn($toolbar);
        $controller = $this->createMock(UI5ControllerInterface::class);
        $table = $this->createMock(UI5DataTable::class);
        $element = $this->getMockBuilder(UI5DataLookupDialog::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getWidget', 'getController', 'getTableElement', 'getModelNameForSelections',
                'needsPrefill', 'getId', 'getCaption', 'buildJsDialogButtons',
                'buildJsDialogContent', 'buildJsOnDialogAfterOpen', 'buildCssElementClass',
                'buildCssWidgetClass', 'buildJsPseudoEventHandlers', 'buildJsTourGuideDropdown'
            ])->getMock();
        $element->method('getWidget')->willReturn($widget);
        $element->method('getController')->willReturn($controller);
        $element->method('getTableElement')->willReturn($table);
        $element->method('getModelNameForSelections')->willReturn('selections');
        $element->method('needsPrefill')->willReturn(false);
        $element->method('getId')->willReturn('lookup');
        $element->method('getCaption')->willReturn('Lookup');
        $element->expects(self::once())->method('buildJsDialogButtons')
            ->with(false)->willReturn('new sap.m.Button()');
        $element->method('buildJsTourGuideDropdown')->with($widget, $controller)->willReturn($tourJs);
        $element->method('buildJsDialogContent')->willReturn('new sap.m.Text()');
        $element->method('buildJsOnDialogAfterOpen')->with('oEvent')
            ->willReturn('initializeSelections(oEvent);');
        $element->method('buildCssElementClass')->willReturn('exf-datalookup');
        $element->method('buildCssWidgetClass')->willReturn('');
        $element->method('buildJsPseudoEventHandlers')->willReturn('');

        return (new \ReflectionMethod(UI5DataLookupDialog::class, 'buildJsDialog'))
            ->invoke($element, 'oController');
    }
}
