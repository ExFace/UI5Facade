<?php
namespace exface\UI5Facade\Facades\Elements\Traits;

use exface\Core\Facades\AbstractAjaxFacade\Elements\JExcelTrait;
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
                            var jqScroller = jqExcel.parents('.sapMPanelContent').first();
                            var fnOnEditStart = jExcel.options.oneditionstart;
                            var fnOnEditEnd = jExcel.options.oneditionend;
                            var bIsDialog = false;
                            
                            jExcel.options.oneditionstart = function(el, domCell, x, y){
                                var jqCell = $(domCell);
                                // The dropdown is not instantiated yet! There is just the cell
                                if (jqCell.hasClass('jexcel_dropdown')) {
                                    setTimeout(function(){
                                        // Now the dropdown is here
                                        var jqDC = jqCell.find('.jdropdown-container');
                                        var oPosCellInit = jqCell.offset();
                                        var oPosDCInit = jqDC.offset();
                                        if (oPosCellInit === undefined || oPosDCInit === undefined) {
                                            return;
                                        }

                                        // If the height of the scroll element is larger than viewport, try and find a DialogSection instead
                                        // it seems that somethimes, the sapMPanelContent we usually use as a scroll element isnt the correct element,
                                        // so we look for a parent element (dialogue section) instead.
                                        if (jqScroller.innerHeight() > window.innerHeight){
                                            var jqScrollerDlg = jqExcel.parents('.sapMDialogSection').first();
                                            if (jqScrollerDlg.length !== 0){
                                                jqScroller = jqScrollerDlg;
                                                bIsDialog = true;
                                            }
                                        }

                                        let bInScrollElement = jqExcel.parents('.sapMDialogScroll').first().length > 0;

                                        // Class .sapMDialog also has overflow: hidden, which cuts off the dropdown when it exceeds the dialogue
                                        // Similarly, if the spreadsheet is in a dialogue and wrapped in a scroll element, we also need to flip the 
                                        // dropdown upwards if it exceeds the scroll container of the dialogue
                                        // 
                                        // so we check if the spreadsheet is inside a scrollable dialogue, or if it exceeds the viewport: 
                                        //          whether the bottom of the dialogue/bounding box (absolute top pos of scroll element + height of scroll element)
                                        //          is smaller than the bottom of the currently opened dropdown (top of current cell + height of cell + height of dropdown container)
                                        // -> if so, we move the dropdown upwards (set the top of the dropdown to the top of the current cell - height of dropdown container)
                                        if ((bInScrollElement || bIsDialog === true) && (jqScroller.offset().top + jqScroller.height() <  (oPosCellInit.top-jqCell.height()) + jqDC.innerHeight())) {
                                            jqDC.offset({
                                                top: oPosCellInit.top - jqDC.innerHeight(),
                                                left: oPosCellInit.left
                                            });

                                            // update init pos for scrolling update
                                            oPosDCInit = jqDC.offset();
                                        }

                                        var fnFixPosition = function() {
                                            var oPosCellCur = jqCell.offset();
                                            var iViewTop = jqScroller.offset().top;
                                            var iViewHeight = jqScroller.innerHeight();
                                            var iScrollTop = oPosCellCur.top - oPosCellInit.top;
                                            var iScrollLeft = oPosCellCur.left - oPosCellInit.left;
                                            var bVisible = (oPosCellCur.top > iViewTop && oPosCellCur.top < iViewTop + iViewHeight);
                                            
                                            // only show dropdown if in viewport, otherwise close it
                                            if (bVisible) {
                                                jqDC.show();
                                                jqDC.offset({
                                                    top: oPosDCInit.top + iScrollTop,
                                                    left: oPosDCInit.left + iScrollLeft
                                                });

                                                // fixes layout issue with upward dropdown
                                                jqDC.css('bottom', 'unset'); 
                                            } else {
                                                jqDC.hide();
                                            }
                                        };
                                        jqDC.css('position', 'fixed');
                                        fnFixPosition();
                                        jqScroller.on('scroll.{$this->getId()}', fnFixPosition);
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
    
    // TODO override UI5DataElementTrait::buildJsIsCellRequired()
}