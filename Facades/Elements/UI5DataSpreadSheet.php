<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\UI5Facade\Facades\Elements\Traits\UI5DataElementTrait;
use exface\UI5Facade\Facades\Interfaces\UI5DataElementInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\UI5Facade\Facades\Elements\Traits\UI5JExcelTrait;

class UI5DataSpreadSheet extends UI5AbstractElement implements UI5DataElementInterface
{    
    use UI5JExcelTrait;
    use UI5DataElementTrait {
        UI5DataElementTrait::buildJsDataResetter as buildJsDataResetterViaTrait;
        UI5DataElementTrait::buildJsDataLoaderOnLoaded as buildJsDataLoaderOnLoadedViaTrait;
        UI5JExcelTrait::buildJsDataResetter as buildJsJExcelResetter;
        UI5JExcelTrait::buildJsDataGetter as buildJsJExcelDataGetter;
        UI5JExcelTrait::buildJsValueGetter insteadof UI5DataElementTrait;
        UI5JExcelTrait::registerExternalModules insteadof UI5DataElementTrait;
        UI5JExcelTrait::buildJsChangesGetter insteadof UI5DataElementTrait;
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
        $this->registerControllerMethods($controller);
        $this->registerExternalModules($controller);
        
        $table = <<<JS
        
                new sap.ui.core.HTML("{$this->getId()}", {
                    content: "<div id=\"{$this->getId()}\"><div id=\"{$this->getId()}_jexcel\" class=\"{$this->buildCssElementClass()} exf-spreadsheet-container sapUiTable\"></div></div>",
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
     * {@inheritDoc}
     * @see JExcelTrait::buildJsDataGetter()
     */
    public function buildJsDataGetter(ActionInterface $action = null)
    {
        return <<<JS
        
        (function(){
            var bDataPending = {$this->buildJsIsDataPending()};
            if (bDataPending) {
                return {};
            }
            
            return ({$this->buildJsJExcelDataGetter($action)});
        }())
JS;
    }
}