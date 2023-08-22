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
            $setterJs = $widget->getScriptToSetValue("sap.ui.getCore().byId('{$this->getId()}').getModel().getProperty('{$this->getValueBindingPath()}')");
            $initPropsJs = <<<JS

            var oValueBinding = new sap.ui.model.Binding(sap.ui.getCore().byId('{$this->getId()}').getModel(), '{$this->getValueBindingPath()}', sap.ui.getCore().byId('{$this->getId()}').getModel().getContext('{$this->getValueBindingPath()}'));
            oValueBinding.attachChange(function(oEvent){
                {$setterJs}
            });

JS;
        }
        
        if ($this->getWidget()->isDisabled()) {
            $initPropsJs .= $this->buildJsSetDisabled(true);
        }
        
        $css = StringDataType::replaceLineBreaks($this->getWidget()->getCss(), ' ');
        
        return <<<JS

        new sap.ui.core.HTML("{$this->getId()}", {
            content: "{$this->escapeJsTextValue($widget->getHtml())}",
            afterRendering: function() {
                {$initJs}
                {$initPropsJs}
                if ($('#{$this->getId()}_css').length === 0) {
                    $('head').append($('<style id="{$this->getId()}_css">{$css}</style>'));
                }
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
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsValueSetter()
     */
    public function buildJsValueSetter($value)
    {
        return $this->getWidget()->getScriptToSetValue($value) ?? '';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsValueGetter()
     */
    public function buildJsValueGetter()
    {
        return $this->getWidget()->getScriptToGetValue() ?? '';
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\JqueryInputValidationTrait::buildJsValidator()
     */
    public function buildJsValidator(?string $valJs = null)
    {
        return $this->getWidget()->getScriptToValidateInput() ?? parent::buildJsValidator();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::buildJsSetDisabled()
     */
    public function buildJsSetDisabled(bool $trueOrFalse, bool $resetWidgetOnChange = false) : string
    {
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
}