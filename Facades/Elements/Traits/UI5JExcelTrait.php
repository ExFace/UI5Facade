<?php
namespace exface\UI5Facade\Facades\Elements\Traits;

use exface\Core\Facades\AbstractAjaxFacade\Elements\JExcelTrait;
use exface\Core\Interfaces\WidgetInterface;
use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;
use exface\UI5Facade\Facades\Elements\UI5AbstractElement;

/**
 * This trait helps to integrate JExcel/JSpreadsheet with UI5
 * 
 * @author Andrej Kabachnik
 *
 * @method UI5Facade getFacade()
 */
trait UI5JExcelTrait {
    
    use JExcelTrait;
    
    /**
     * @see JExcelTrait::buildJsJqueryElement()
     */
    protected function buildJsJqueryElement() : string
    {
        return "$('#{$this->getId()}_jexcel')";
    }
    
    /**
     *
     * @return string
     */
    protected function buildJsFixedFootersSpread() : string
    {
        return $this->getController()->buildJsMethodCallFromController('onFixedFooterSpread', $this, '');
    }
    
    /**
     *
     * @return array
     */
    protected function getJsIncludes() : array
    {
        $htmlTagsArray = $this->buildHtmlHeadTagsForJExcel();
        $tags = implode('', $htmlTagsArray);
        $jsTags = [];
        preg_match_all('#<script[^>]*src="([^"]*)"[^>]*></script>#is', $tags, $jsTags);
        return $jsTags[1];
    }
    
    protected function registerControllerMethods(UI5ControllerInterface $controller) : UI5AbstractElement
    {
        $controller->addOnDefineScript($this->buildJsFixJqueryImportUseStrict());
        
        $controller->addMethod('onFixedFooterSpread', $this, '', $this->buildJsFixedFootersSpreadFunctionBody());
        
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::registerExternalModules()
     */
    public function registerExternalModules(UI5ControllerInterface $controller) : UI5AbstractElement
    {
        $controller->addExternalModule('exface.openui5.jexcel', $this->getFacade()->buildUrlToSource("LIBS.JEXCEL.JS"), null, 'jexcel');
        $controller->addExternalCss($this->getFacade()->buildUrlToSource('LIBS.JEXCEL.CSS'));
        $controller->addExternalModule('exface.openui5.jsuites', $this->getFacade()->buildUrlToSource("LIBS.JEXCEL.JS_JSUITES"), null, 'jsuites');
        $controller->addExternalCss($this->getFacade()->buildUrlToSource('LIBS.JEXCEL.CSS_JSUITES'));
        
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsBusyIconShow()
     */
    public function buildJsBusyIconShow($global = false)
    {
        if ($global) {
            return parent::buildJsBusyIconShow($global);
        } else {
            return 'sap.ui.getCore().byId("' . $this->getId() . '").getParent().setBusyIndicatorDelay(0).setBusy(true);';
        }
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsBusyIconHide()
     */
    public function buildJsBusyIconHide($global = false)
    {
        if ($global) {
            return parent::buildJsBusyIconHide($global);
        } else {
            return 'sap.ui.getCore().byId("' . $this->getId() . '").getParent().setBusy(false);';
        }
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsChangesGetter()
     */
    public function buildJsChangesGetter(bool $onlyVisible = false) : string
    {
        return "({$this->buildJsJqueryElement()}[0] && {$this->buildJsJqueryElement()}[0].exfWidget.hasChanges() ? [{elementId: '{$this->getId()}', caption: {$this->escapeString($this->getCaption())}}] : [])";
    }
    
    /**
     *
     * @see JExcelTrait::buildJsCheckHidden()
     */
    protected function buildJsCheckHidden(string $jqElement) : string
    {
        return "($jqElement.parents().filter('.sapUiHidden').length > 0)";
    }

    /**
     * Dropdowns from jExcel are cut off by the border of the containing UI5 control sometimes
     * because that UI5 control has overflow:hidden at some point. This code fixes this.
     *
     * Every time a dropdown is opened, the corresponding menu gets the css property `position:fixed`.
     * This nails down the current position relative to the viewport. Thus, the menu is not bound by
     * the encoling DOM elements anymore and is displayed above them.
     *
     * However, if the spreadsheet is scrollable, the menu does not scroll with it. This is done
     * explicitly by recalculating the menus offset on scroll events. Also if the table is scrolled
     * far enough for the cell to disappear, the menu is hidden too!
     *
     * The idea was taken from https://medium.com/@thomas.ryu/css-overriding-the-parents-overflow-hidden-90c75a0e7296
     *
     * @return string
     */
    protected function buildJsFixOverflowVisibility() : string
    {
        return <<<JS
                        (function() {
                            var jExcel = {$this->buildJsJqueryElement()}[0].exfWidget.getJExcel();
                            var jqExcel = {$this->buildJsJqueryElement()};
                            var fnOnEditStart = jExcel.options.oneditionstart;
                            var fnOnEditEnd = jExcel.options.oneditionend;
                            var jqScroller = null; // we need to keep track of the scroll element

                            // Finds the scroll element wrapping the spreadsheet once so the repositioning
                            // logic below always has something to attach its scroll listener to
                            var fnEnsureScroller = function() {
                                if (jqScroller !== null) {
                                    return;
                                }
                                // UI5-Upgrade: the old scroll element (sapMPanelContent) didnt seem to work anymore in some pages, not sure why.
                                // so we take the new scroll delegate element instead in those cases
                                jqScroller = jqExcel.parents('.sapUiScrollDelegate').first(); 
                                if (jqScroller.length === 0){
                                    jqScroller = jqExcel.parents('.sapMPanelContent').first();
                                }
                                // Fall back to the window if no known scroll wrapper was found (e.g. inside a
                                // wizard step) - an empty jQuery set here would silently break the fix below
                                if (jqScroller.length === 0) {
                                    jqScroller = $(window);
                                }
                            };

                            // Escapes a jSuites dropdown (.jdropdown-container) from the overflow:hidden of its
                            // containing UI5 control by switching it to position:fixed and keeping it synced with
                            // scrolling/cell movement. Used both for cell editor dropdowns and column filter
                            // dropdowns, since both are the very same jSuites dropdown widget under the hood.
                            // Returns a cleanup function to be called once the dropdown is closed again.
                            var fnFixDropdownPosition = function(jqCell, jqDC) {
                                var domDC = jqDC[0];

                                // Find the nearest CSS-transformed ancestor (if any). Such ancestors break
                                // position:fixed (making it relative to that ancestor instead of the viewport),
                                // so we must use its boundaries to position the dropdown. This is not limited to
                                // dialogs - wizard steps and other containers can have the same effect.
                                var domFixedContainer = null;
                                var parentEl = domDC.parentElement;
                                while (parentEl && parentEl !== document.documentElement) {
                                    var cs = window.getComputedStyle(parentEl);
                                    if (cs.transform !== 'none' || cs.perspective !== 'none' || (cs.filter && cs.filter !== 'none' && cs.filter !== 'blur(0px)')) {
                                        domFixedContainer = parentEl;
                                        break;
                                    }
                                    parentEl = parentEl.parentElement;
                                }

                                // If inside a dialog, prefer its scroll container to track the scroll position
                                var jqScrollerDlg = jqExcel.parents('.sapMDialogSection').first();
                                if (jqScrollerDlg.length !== 0) {
                                    jqScroller = jqScrollerDlg;
                                }

                                // capture initial document-relative positions of cell and dropdown container (before position:fixed)
                                var oPosCellInit = jqCell.offset();
                                var oPosDCInit = jqDC.offset();

                                // Determine if the dropdown needs to flip upwards
                                // Class .sapMDialog also has overflow: hidden, which cuts off the dropdown when it exceeds the dialogue
                                // Similarly, if the spreadsheet is in a dialogue and wrapped in a scroll element, we also need to flip the 
                                // dropdown upwards if it exceeds the scroll container of the dialogue
                                // so we check if the spreadsheet is inside a scrollable dialogue, or if it exceeds the viewport: 
                                var iBottomBoundary = domFixedContainer
                                    ? domFixedContainer.getBoundingClientRect().bottom
                                    : window.innerHeight;
                                var bFlippedUp = iBottomBoundary < jqCell[0].getBoundingClientRect().bottom + jqDC.outerHeight();

                                var fnFixPosition = function() {
                                    var bScrollerIsWindow = (jqScroller[0] === window);
                                    var oPosCellCur = jqCell.offset();
                                    var iViewTop = bScrollerIsWindow ? $(window).scrollTop() : jqScroller.offset().top;
                                    var iViewHeight = bScrollerIsWindow ? window.innerHeight : jqScroller.innerHeight();
                                    var iScrollTop = oPosCellCur.top - oPosCellInit.top;
                                    var iScrollLeft = oPosCellCur.left - oPosCellInit.left;
                                    var bVisible = (oPosCellCur.top > iViewTop && oPosCellCur.top < iViewTop + iViewHeight);
                                    
                                    // only show dropdown if in viewport, otherwise close it
                                    if (bVisible) {
                                        jqDC.show();
                                        if (bFlippedUp) {
                                            // if its flipped up, we cannot use the jQuery offset function because we need to set the bottom property (which offset() doesnt have)
                                            // so, if the flip the dropdown up, the bottom must be anchored to the top of the cell
                                            // otherwise (if we use the top property like previously), if we type in the field, the dropdown opeions get shorter and the dropdown seems disconnected
                                            var rect = jqCell[0].getBoundingClientRect();
                                            var fcRect = domFixedContainer ? domFixedContainer.getBoundingClientRect() : {bottom: window.innerHeight, left: 0};
                                            domDC.style.top = '';
                                            domDC.style.bottom = (fcRect.bottom - rect.top) + 1 + 'px';
                                            domDC.style.left = (rect.left - fcRect.left) + 'px';
                                        } else {
                                            domDC.style.bottom = '';
                                            jqDC.offset({
                                                top: oPosDCInit.top + iScrollTop,
                                                left: oPosDCInit.left + iScrollLeft
                                            });
                                        }
                                    } else {
                                        jqDC.hide();
                                    }
                                };
                                jqDC.css('position', 'fixed');
                                fnFixPosition();
                                jqScroller.on('scroll.{$this->getId()}', fnFixPosition);

                                return function() {
                                    jqScroller.off('scroll.{$this->getId()}', fnFixPosition);
                                };
                            };
                            
                            jExcel.options.oneditionstart = function(el, domCell, x, y){
                                var jqCell = $(domCell);
                                fnEnsureScroller();

                                // The dropdown is not instantiated yet! There is just the cell
                                if (jqCell.hasClass('jexcel_dropdown')) {
                                    setTimeout(function(){
                                        // Now the dropdown is here (if not, return)
                                        var jqDC = jqCell.find('.jdropdown-container');
                                        if (jqDC.length === 0) return;
                                        fnFixDropdownPosition(jqCell, jqDC);
                                    }, 0);
                                }
                                
                                if (fnOnEditStart) {
                                    fnOnEditStart(el, domCell, x, y);
                                }
                            };

                            jExcel.options.oneditionend = function(el, domCell, x, y){
                                // remove scroll listener on edition end
                                if ($(domCell).hasClass('jexcel_dropdown')) {
                                    jqScroller.off('scroll.{$this->getId()}');
                                }

                                if (fnOnEditEnd) {
                                    fnOnEditEnd(el, domCell, x, y);
                                }
                            };

                            // Column header filter dropdowns are opened via jexcel.openFilter(), which - unlike
                            // cell editors - never fires oneditionstart/oneditionend. We cannot hook this via a
                            // click handler either: jexcel's own onload rebinds a plain (non-namespaced) 'click'
                            // handler on '.jexcel_column_filter' after this code runs, and jQuery's .off('click', sel)
                            // silently removes namespaced handlers on the same selector too. So the dropdown being
                            // opened is detected via mutation observer instead. The jSuites dropdown toggles its own
                            // "jdropdown-focus" class when it closes, which we use to clean up the scroll listener.
                            var aFixedFilterDropdowns = [];
                            var oFilterDropdownObserver = new MutationObserver(function(aMutations) {
                                aMutations.forEach(function(oMutation) {
                                    // addedNodes is a NodeList, which has no .forEach in IE11
                                    Array.prototype.forEach.call(oMutation.addedNodes, function(domNode) {
                                        if (domNode.nodeType !== 1) {
                                            return;
                                        }
                                        var jqDC = $(domNode).is('.jdropdown-container') ? $(domNode) : $(domNode).find('.jdropdown-container');
                                        if (jqDC.length === 0 || aFixedFilterDropdowns.indexOf(jqDC[0]) !== -1) {
                                            return;
                                        }
                                        var jqCell = jqDC.closest('.jexcel_column_filter');
                                        var jqDropdown = jqCell.find('.jdropdown').first();
                                        if (jqCell.length === 0 || jqDropdown.length === 0) {
                                            return;
                                        }
                                        aFixedFilterDropdowns.push(jqDC[0]);
                                        fnEnsureScroller();
                                        var fnCleanup = fnFixDropdownPosition(jqCell, jqDC);
                                        var oCloseObserver = new MutationObserver(function() {
                                            if (!jqDropdown.hasClass('jdropdown-focus')) {
                                                fnCleanup();
                                                oCloseObserver.disconnect();
                                                aFixedFilterDropdowns.splice(aFixedFilterDropdowns.indexOf(jqDC[0]), 1);
                                            }
                                        });
                                        oCloseObserver.observe(jqDropdown[0], {attributes: true, attributeFilter: ['class']});
                                    });
                                });
                            });
                            oFilterDropdownObserver.observe(jqExcel[0], {childList: true, subtree: true});
                        })();
                        
JS;
    }

    /**
     * @see JexcelTrait::buildJsCountRows()
     */
    protected function buildJsCountRows() : string
    {
        return "(sap.ui.getCore().byId('{$this->getId()}').getModel().getData().rows || []).length";
    }
    
    /**
     * Returns inline JS code resolving to TRUE if the given cell or column widget is editable and required and FALSE otherwise
     * 
     * This is basically the same as UI5Input::buildJsRequiredGetter(), but works for table columns. The regular
     * JS required checker needs a real instantiated JS facade element, which does not work in tables - here the
     * input element is just a template for the column and neither has an id nor a real instance. It gets even more
     * complicated with required_if linking other columns of the same table - in this case, we even need the specific
     * row number to determine if the cell is required or not.
     * 
     * Thus, UI5Input::buildJsRequiredGetter() and derivatives will not use their regular logic for in-table widgets,
     * but forward to this method here. 
     * 
     * This method should be overridden by specific implementations of data widgets like UI5DataSpreadSheet and
     * similar.
     * 
     * Copied from UI5DataElement::buildJsIsCellRequired() to support required_if in DataImporters
     * 
     * @param WidgetInterface $cell
     * @return string
     */
    public function buildJsIsCellRequired(WidgetInterface $cell) : string
    {
        if ($cell instanceof DataColumn) {
            $cell = $cell->getCellWidget();
        }
        if ($cell instanceof iCanBeRequired) {
            return $cell->isRequired() ? 'true' : 'false';
        }
        return 'false';
    }
}