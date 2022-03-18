<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Facades\AbstractAjaxFacade\Elements\JExcelTrait;
use exface\UI5Facade\Facades\Elements\Traits\UI5DataElementTrait;
use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;

class UI5DataSpreadSheet extends UI5AbstractElement
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
        
        $chart = <<<JS
        
                new sap.ui.core.HTML("{$this->getId()}", {
                    content: "<div id=\"{$this->getId()}_jexcel\" class=\"{$this->buildCssElementClass()} sapUiTable\"></div>",
                    afterRendering: function(oEvent) {
                        {$this->buildJsDestroy()}
                        {$this->buildJsJExcelInit()}
                        {$this->buildJsRefresh()}
                        {$this->buildJsFixOverflowVisibility()}
                    }
                })
                
JS;
                            
        return $this->buildJsPanelWrapper($chart, $oControllerJs) . ".addStyleClass('sapUiNoContentPadding exf-panel-no-border')";
    }
    
    protected function buildJsFixOverflowVisibility() : string
    {
        return <<<JS

                        var aParents = {$this->buildJsJqueryElement()}.parents();
                        for (var i = 0; i < aParents.length; i++) {
                            var jqParent = $(aParents[i]);
                            if (jqParent.hasClass('sapUiRespGrid ') === true) {
                                break;
                            }
                            $(jqParent).css('overflow', 'visible');
                        }

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
        {$this->buildJsFooterRefresh('data', 'jqSelf')}

JS;
    }
    
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
        $controller->addExternalModule('libs.exface.exfTools', $this->getFacade()->buildUrlToSource("LIBS.EXFTOOLS.JS"), null, 'exfTools');
        
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
     * @see UI5DataElementTrait::buildJsGetSelectedRows()
     */
    protected function buildJsGetSelectedRows(string $oTableJs): string
    {
        return '[]';
    }

    protected function buildJsDataResetter() : string
    {
        return $this->buildJsDataResetterViaTrait() . ';' . $this->buildJsJExcelResetter();
    }
    
    protected function buildJsFixAutoColumnWidth() : string
    {
        return '';
    }
    
    public function buildJsBusyIconShow($global = false)
    {
        if ($global) {
            return parent::buildJsBusyIconShow($global);
        } else {
            return 'sap.ui.getCore().byId("' . $this->getId() . '").getParent().setBusyIndicatorDelay(0).setBusy(true);';
        }
    }
    
    public function buildJsBusyIconHide($global = false)
    {
        if ($global) {
            return parent::buildJsBusyIconHide($global);
        } else {
            return 'sap.ui.getCore().byId("' . $this->getId() . '").getParent().setBusy(false);';
        }
    }
}