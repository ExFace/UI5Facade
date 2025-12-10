<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\DataTypes\StringDataType;

/**
 * Creates a sap.ui.core.HTML for InputCustom widgets
 * 
 * @method \exface\Core\Widgets\InputCustom getWidget()
 * 
 * @author Andrej Kabachnik
 *
 */
class UI5InputCustom extends UI5Input
{
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::buildJsConstructorForMainControl()
     */
    public function buildJsConstructorForMainControl($oControllerJs = 'oController')
    {
        $widget = $this->getWidget();
        $controller = $this->getController();
        
        foreach ($this->getWidget()->getScriptVariables() as $varName => $initVal) {
            $controller->addDependentObject($varName, $this, $initVal);
            $controllerVar = $controller->buildJsDependentObjectGetter($varName, $this);
            $this->getWidget()->setScriptVariablePlaceholder($varName, $controllerVar);
        }
        
        $this->registerExternalModules($this->getController());
        
        $initJs = $widget->getScriptToInit() ?? '';
        $initPropsJs = '';
        if (! $this->isValueBoundToModel() && ($value = $widget->getValueWithDefaults()) !== null) {
            $initPropsJs = ($widget->getScriptToSetValue(json_encode($value)) ?? '');
        } else {
            $initPropsJs = <<<JS

            var oValueBinding = new sap.ui.model.Binding(sap.ui.getCore().byId('{$this->getId()}').getModel(), '{$this->getValueBindingPath()}', sap.ui.getCore().byId('{$this->getId()}').getModel().getContext('{$this->getValueBindingPath()}'));
            oValueBinding.attachChange(function(oEvent){
                var mVal = sap.ui.getCore().byId('{$this->getId()}').getModel().getProperty('{$this->getValueBindingPath()}');
                // Do not update if the model does not have this property
                if (mVal === undefined) {
                    return;
                }
                {$widget->getScriptToSetValue("mVal")}
            });

JS;
        }
        
        if ($this->getWidget()->isDisabled()) {
            $initPropsJs .= $this->buildJsSetDisabled(true);
        }
        
        if (null !== $css = $this->getWidget()->getCss()) {
            $css = StringDataType::replaceLineBreaks($css, ' ');
            $appendCssJs = "$('head').append($('<style id=\"{$this->getId()}_css\">{$css}</style>'));";
        }
        
        return <<<JS

        new sap.ui.core.HTML("{$this->getId()}", {
            content: "{$this->escapeJsTextValue($widget->getHtml())}",
            afterRendering: function() {
                
                {$initJs}
                {$initPropsJs}
                if ($('#{$this->getId()}_css').length === 0) {
                    $appendCssJs
                }
                sap.ui.core.ResizeHandler.register(sap.ui.getCore().byId('{$this->getId()}').getParent(), function(){
                    {$widget->getScriptToResize()}
                });
            }
        })

JS;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::registerExternalModules()
     */
    public function registerExternalModules(UI5ControllerInterface $controller) : UI5AbstractElement
    {
        foreach ($this->getWidget()->getIncludeJs() as $nr => $url) {
            $controller->addExternalModule('libs.exface.custom.' . $this->buildJsFunctionPrefix() . $nr, $url);
        }
        foreach ($this->getWidget()->getIncludeCss() as $url) {
            $controller->addExternalCss($url);
        }
        foreach ($this->getWidget()->getIncludeJsModules() as $i => $url) {
            $id = md5($url) . '_' . ($i + 1);
            $html = '<script id="' . $id . '" type="module" src="' . $url . '"></script>';
            $controller->addOnInitScript(<<<JS

        if ($('#{$id}').length === 0) {
            $('head').append('{$html}');
        }
JS);
        }
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsValueSetter()
     */
    public function buildJsValueSetter($value)
    {
        return $this->getWidget()->getScriptToSetValue($value) ?? $this->buildJsFallbackForEmptyScript('script_to_set_value');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsValueGetter()
     */
    public function buildJsValueGetter()
    {
        return $this->getWidget()->getScriptToGetValue() ?? $this->buildJsFallbackForEmptyScript('script_to_get_value');
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\JqueryInputValidationTrait::buildJsValidator()
     */
    public function buildJsValidator(?string $valJs = null) : string
    {
        return $this->getWidget()->getScriptToValidateInput() ?? parent::buildJsValidator($valJs);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::buildJsSetDisabled()
     */
    public function buildJsSetDisabled(bool $trueOrFalse) : string
    {
        // TODO call on-true/false widget functions here. But currently they cannot be defined for InputCustom...
        if ($trueOrFalse === true) {
            return $this->getWidget()->getScriptToDisable() ?? parent::buildJsSetDisabled($trueOrFalse);
        } else {
            return $this->getWidget()->getScriptToEnable() ?? parent::buildJsSetDisabled($trueOrFalse);
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsDataGetter()
     */
    public function buildJsDataGetter(ActionInterface $action = null)
    {
        return $this->getWidget()->getScriptToGetData($action) ?? parent::buildJsDataGetter($action);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsDataSetter()
     */
    public function buildJsDataSetter(string $jsData) : string
    {
        return $this->getWidget()->getScriptToSetData($jsData) ?? parent::buildJsDataSetter($jsData);
    }
    
    /**
     *
     * @param string $widgetProperty
     * @param string $returnValueJs
     * @return string
     */
    protected function buildJsFallbackForEmptyScript(string $widgetProperty, string $returnValueJs = "''") : string
    {
        return "(function(){console.warn('Property {$widgetProperty} not set for widget InputCustom. Falling back to empty string'); return {$returnValueJs};})()";
    }
}