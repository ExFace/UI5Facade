<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\UI5Facade\Facades\Elements\Traits\UI5HelpButtonTrait;
use exface\UI5Facade\Facades\Elements\Traits\UI5JExcelTrait;

class UI5DataImporter extends UI5AbstractElement
{    
    use UI5JExcelTrait;
    use UI5HelpButtonTrait;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsConstructor()
     */
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {
        if ($this->getWidget()->hasPreview() === true) {
            $this->getFacade()->getElement($this->getWidget()->getPreviewButton())->addOnSuccessScript($this->buildJsDataSetter('response'));
        }
        
        $controller = $this->getController();
        $controller->addOnPrefillDataChangedScript($this->buildJsResetter());
        $this->registerControllerMethods($controller);
        $this->registerExternalModules($controller);
        
        $table = <<<JS
        
                new sap.ui.core.HTML("{$this->getId()}", {
                    content: "<div id=\"{$this->getId()}_jexcel\" class=\"{$this->buildCssElementClass()} sapUiTable\"></div>",
                    afterRendering: function(oEvent) {
                        {$this->buildJsDestroy()}
                        {$this->buildJsJExcelInit()}
                        {$this->buildJsFixOverflowVisibility()}
                    }
                })
                
JS;
                            
        return $this->buildJsPanelWrapper($table, $oControllerJs) . ".addStyleClass('sapUiNoContentPadding exf-panel-no-border')";
    }
    
    /**
     * 
     * @param string $contentConstructorsJs
     * @param string $oControllerJs
     * @param string $toolbar
     * @return string
     */
    protected function buildJsPanelWrapper(string $contentConstructorsJs, string $oControllerJs = 'oController', string $toolbar = null)  : string
    {
        $toolbar = $toolbar ?? $this->buildJsToolbar($oControllerJs);
        $hDim = $this->getWidget()->getHeight();
        if (! $hDim->isUndefined()) {
            $height = "height: '{$this->getHeight()}',";
        } 
        return <<<JS
        new sap.m.Panel({
            $height
            headerToolbar: [
                {$toolbar}.addStyleClass("sapMTBHeader-CTX")
            ],
            content: [
                {$contentConstructorsJs}
            ]
        })
        
JS;
    }
    
    
    
    /**
     * 
     * @param string $oControllerJsVar
     * @param string $leftExtras
     * @param string $rightExtras
     * @return string
     */
    protected function buildJsToolbar($oControllerJsVar = 'oController', string $leftExtras = null, string $rightExtras = null)
    {
        $widget = $this->getWidget();
        
        $visible = $widget->getHideCaption() === true || ($this->getCaption() === '' && $widget->hasButtons() === false) ? 'false' : 'true';
        $heading = $widget->getHideCaption() === true ? '' : 'new sap.m.Label({text: ' . json_encode($this->getCaption()) . '}),';
        
        $buttons = '';
        foreach ($widget->getToolbarMain()->getButtonGroups() as $btn_group) {
            $buttons .= ($buttons && $btn_group->getVisibility() > EXF_WIDGET_VISIBILITY_OPTIONAL ? ",\n new sap.m.ToolbarSeparator()" : '');
            foreach ($btn_group->getButtons() as $btn) {
                $buttons .= $this->getFacade()->getElement($btn)->buildJsConstructor() . ",\n";
            }
        }
        
        return <<<JS
        
			new sap.m.OverflowToolbar({
                design: "Transparent",
                visible: {$visible},
				content: [
					{$heading}
                    {$leftExtras}
			        new sap.m.ToolbarSpacer(),
                    {$buttons}
                    {$this->buildJsHelpButtonConstructor()}
				]
			})
			
JS;
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
        $this->getController()->addOnPrefillDataChangedScript("{$this->buildJsJqueryElement()}[0].exfWidget.refreshConditionalProperties()");
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsResetter()
     */
    public function buildJsResetter() : string
    {
        return $this->buildJsDataResetter();
    }
}