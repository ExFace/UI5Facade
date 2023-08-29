<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Facades\AbstractAjaxFacade\Elements\JExcelTrait;
use exface\UI5Facade\Facades\Elements\Traits\UI5DataElementTrait;
use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;
use exface\UI5Facade\Facades\Interfaces\UI5DataElementInterface;

class UI5DataSpreadSheet extends UI5AbstractElement implements UI5DataElementInterface
{    
    use JExcelTrait;
    use UI5DataElementTrait {
        UI5DataElementTrait::buildJsDataResetter as buildJsDataResetterViaTrait;
        UI5DataElementTrait::buildJsDataLoaderOnLoaded as buildJsDataLoaderOnLoadedViaTrait;
        JExcelTrait::buildJsDataResetter as buildJsJExcelResetter;
        JExcelTrait::buildJsDataGetter insteadof UI5DataElementTrait;
        JExcelTrait::buildJsValueGetter insteadof UI5DataElementTrait;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsConstructor()
     */
    public function buildJsConstructorForControl($oControllerJs = 'oController') : string
    {
        $this->getFacade()->getElement($this->getWidget()->getConfiguratorWidget())->registerFiltersWithApplyOnChange($this);
        
        $this->registerReferencesAtLinkedElements();
        
        $controller = $this->getController();
        $controller->addOnDefineScript($this->buildJsFixJqueryImportUseStrict());
        
        $controller->addMethod('onFixedFooterSpread', $this, '', $this->buildJsFixedFootersSpreadFunctionBody());
        
        $this->registerExternalModules($controller);
        
        $table = <<<JS
        
                new sap.ui.core.HTML("{$this->getId()}", {
                    content: "<div id=\"{$this->getId()}\"><div id=\"{$this->getId()}_jexcel\" class=\"{$this->buildCssElementClass()} sapUiTable\"></div></div>",
                    afterRendering: function(oEvent) {
                        {$this->buildJsDestroy()};
                        {$this->buildJsJExcelInit()};
                        {$this->buildJsRefresh()};
                        {$this->buildJsFixOverflowVisibility()}
                    }
                })
                
JS;
                            
        return $this->buildJsPanelWrapper($table, $oControllerJs) . ".addStyleClass('sapUiNoContentPadding exf-panel-no-border')";
    }
    
    /**
     * Dropdowns from jExcel are cut off by the border of the containing UI5 control sometimes
     * because that UI5 control has overflow:hidden at some point. The overflow is temporary
     * made visible by this code everytime a dropdown is opened and restored when it closes.
     * 
     * Making the overflow visible after rendering initially did not work as the UI5 controls
     * got scrollbars then. It must be done right before the dropdown appears!
     * 
     * So far the following problematic situations were identified:
     * 
     * - jExcel is inside a responsive grid 
     *      - All DOM parents up to the first grid get overflow:visible anyway - just to be sure.
     *      - If the grid has .sapUiRespGridOverflowHidden, its immediate children get overflow:hidden 
     *      in certain situations, but we just make the overflow visible always. This is done for
     *      all grids up the hierarchy because they may be nested
     * 
     * @return string
     */
    protected function buildJsFixOverflowVisibility() : string
    {
        return <<<JS
                        (function() {
                            var jExcel = {$this->buildJsJqueryElement()}[0].exfWidget.getJExcel();
                            var fnOnEditStart = jExcel.options.oneditionstart;
                            var fnOnEditEnd = jExcel.options.oneditionend;

                            jExcel.options.oneditionstart = function(el, domCell, x, y){
                                if ($(domCell).hasClass('jexcel_dropdown')) {
                                    var bRespGridFound = false;
                                    $(domCell).data('overflowShown', []);
                                    {$this->buildJsJqueryElement()}.parents().each(function(i, domEl) {
                                        var jqEl = $(domEl);
                                        if (jqEl.hasClass('sapUiRespGrid') === true) {
                                            bRespGridFound = true;
                                        }
                                        if (! bRespGridFound) {
                                            jqEl.css('overflow', 'visible');
                                            $(domCell).data('overflowShown').push(jqEl[0]);
                                        }
                                        if (jqEl.hasClass('sapUiRespGridOverflowHidden')) {
                                            jqEl.children().each(function(j, domChild){
                                                $(domChild).css('overflow', 'visible');
                                                $(domCell).data('overflowShown').push(domChild);
                                            });
                                        }
                                    });
                                }

                                if (fnOnEditStart) {
                                    fnOnEditStart(el, domCell, x, y);
                                }
                            };
                            jExcel.options.oneditionend = function(el, domCell, x, y){
                                if ($(domCell).hasClass('jexcel_dropdown')) {
                                    var aEls = $(domCell).data('overflowShown');
                                    if (aEls) {
                                        aEls.forEach(function(domEl) {
                                            $(domEl).css('overflow', '');
                                        });
                                        $(domCell).removeData('overflowShown');
                                    }
                                }

                                if (fnOnEditEnd) {
                                    fnOnEditEnd(el, domCell, x, y);
                                }
                            };
                        })();

JS;
    }
    
    /**
     * @see JExcelTrait::buildJsJqueryElement()
     */
    protected function buildJsJqueryElement() : string
    {
        return "$('#{$this->getId()}_jexcel')";
    }
    
    /**
     *
     * {@inheritdoc}
     * @see UI5DataElementTrait::buildJsDataLoaderOnLoaded()
     */
    protected function buildJsDataLoaderOnLoaded(string $oModelJs = 'oModel') : string
    {
        return $this->buildJsDataLoaderOnLoadedViaTrait($oModelJs) . <<<JS

        {$this->buildJsDataSetter('oModel.getData()')}
        {$this->buildJsFooterRefresh('oModel.getData()', $this->buildJsJqueryElement())}

JS;
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
     * {@inheritdoc}
     * @see UI5DataElementTrait::isEditable()
     */
    protected function isEditable()
    {
        return true;
    }

    /**
     *
     * {@inheritdoc}
     * @see UI5DataElementTrait::buildJsGetRowsSelected()
     */
    protected function buildJsGetRowsSelected(string $oTableJs): string
    {
        return '[]';
    }

    /**
     * 
     * @return string
     */
    protected function buildJsDataResetter() : string
    {
        return $this->buildJsDataResetterViaTrait() . ';' . $this->buildJsJExcelResetter();
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
     * @see UI5DataElementTrait::buildJsEditableChangesChecker()
     * @param string $oTableJs
     * @return string
     */
    public function buildJsEditableChangesChecker(string $oTableJs = null) : string
    {
        return "({$this->buildJsJqueryElement()}.length === 0 ? false : {$this->buildJsJqueryElement()}[0].exfWidget.hasChanges())";
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::registerConditionalProperties()
     */
    public function registerConditionalProperties() : UI5AbstractElement
    {
        parent::registerConditionalProperties();
        $this->registerConditionalPropertiesOfColumns();
        $this->getController()->addOnPrefillDataChangedScript("(function(jqEl){if (jqEl.length) jqEl[0].exfWidget.refreshConditionalProperties()})({$this->buildJsJqueryElement()})");
        return $this;
    }
    
    /**
     *
     * @see UI5DataElementTrait::isWrappedInPanel()
     */
    protected function isWrappedInPanel() : bool
    {
        return true;
    }
    
    /**
     * 
     * @see JExcelTrait::buildJsCheckHidden()
     */
    protected function buildJsCheckHidden(string $jqElement) : string
    {
        return "($jqElement.parents().filter('.sapUiHidden').length > 0)";
    }
}