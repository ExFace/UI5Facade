<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Facades\AbstractAjaxFacade\Elements\SurveyJsTrait;

/**
 * Creates a Survey-JS instance for an InputForm widget
 * 
 * @method \exface\Core\Widgets\InputForm getWidget()
 * 
 * @author Andrej Kabachnik
 *
 */
class UI5InputForm extends UI5Input
{
    use SurveyJsTrait {
        buildJsSurveyInitOptions AS buildJsSurveyInitOptionsViaTrait;
    }
    
    const CONTROLLER_VAR_SURVEY = 'survey';
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::buildJsConstructorForMainControl()
     */
    public function buildJsConstructorForMainControl($oControllerJs = 'oController')
    {
        $controller = $this->getController();
        $controller->addDependentObject(self::CONTROLLER_VAR_SURVEY, $this, 'null');
        
        $this->registerExternalModules($controller);
        
        return <<<JS

        new sap.ui.core.HTML("{$this->getId()}", {
            content: "<div id=\"{$this->getIdOfSurveyDiv()}\"></div>",
            afterRendering: function() {
                {$this->buildJsSurveySetup()}

                var oValueBinding = new sap.ui.model.Binding(sap.ui.getCore().byId('{$this->getId()}').getModel(), '{$this->getValueBindingPath()}', sap.ui.getCore().byId('{$this->getId()}').getModel().getContext('{$this->getValueBindingPath()}'));
                oValueBinding.attachChange(function(oEvent){
                    {$this->buildJsValueSetter("sap.ui.getCore().byId('{$this->getId()}').getModel().getProperty('{$this->getValueBindingPath()}')")};
                });
            }
        })

JS;
    }
    
    protected function buildJsSurveyVar() : string
    {
        return $this->getController()->buildJsDependentObjectGetter(self::CONTROLLER_VAR_SURVEY, $this);
    }
    
    /**
     * 
     * @see SurveyJsTrait::buildJsSurveyConfigGetter()
     */
    protected function buildJsSurveyModelGetter() : string
    {
        $widget = $this->getWidget();
        $model = $this->getView()->getModel();
        if ($model->hasBinding($widget, 'form_config')) {
            $modelPath = $model->getBindingPath($widget, 'form_config');
        } else {
            $modelPath = $this->getValueBindingPrefix() . $this->getWidget()->getFormConfigDataColumnName();
        }
        return "sap.ui.getCore().byId('{$this->getId()}').getModel().getProperty('{$modelPath}')";
    }
    
    /**
     * 
     * @see SurveyJsTrait::buildJsSurveyInitOptions()
     */
    protected function buildJsSurveyInitOptions(string $oSurveyJs = 'oSurvey') : string
    {
        return $this->buildJsSurveyInitOptionsViaTrait($oSurveyJs) . <<<JS
    
    $oSurveyJs.onUpdateQuestionCssClasses.add(function(_, options) {
        const classes = options.cssClasses;
        if (classes.headerLeft === 'title-left') {
            classes.titleLeftRoot += ' sapUiRespGrid sapUiRespGridHSpace0 sapUiRespGridVSpace0 sapUiFormResGridCont sapUiRespGridOverflowHidden sapUiRespGridMedia-Std-LargeDesktop';
            /* TODO replace class sapUiRespGridMedia-Std-LargeDesktop to match current device in UI5 */
            classes.headerLeft += ' sapUiRespGridSpanXL5 sapUiRespGridSpanL4 sapUiRespGridSpanM4 sapUiRespGridSpanS12';
        }
    });

JS;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\JqueryInputValidationTrait::buildJsValidator()
     */
    public function buildJsValidator(?string $valJs = null)
    {
        // TODO
        return "true";
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::registerExternalModules()
     */
    public function registerExternalModules(UI5ControllerInterface $controller) : UI5AbstractElement
    {
        foreach ($this->getJsIncludes() as $src) {
            $name = StringDataType::substringAfter($src, '/', $src, false, true);
            $name = str_replace('-', '_', $name);
            
            $name = 'libs.exface.survey.' . $name;
            $controller->addExternalModule($name, $src);
        }
        
        foreach ($this->getCssIncludes() as $src) {
            $controller->addExternalCss($src);
        }
        
        return $this;
    }
    
    /**
     *
     * @return string[]
     */
    protected function getJsIncludes() : array
    {
        $htmlTagsArray = $this->buildHtmlHeadTagsForSurvey();
        $tags = implode('', $htmlTagsArray);
        $jsTags = [];
        preg_match_all('#<script[^>]*src="([^"]*)"[^>]*></script>#is', $tags, $jsTags);
        return $jsTags[1];
    }
    
    /**
     *
     * @return string[]
     */
    protected function getCssIncludes() : array
    {
        $htmlTagsArray = $this->buildHtmlHeadTagsForSurvey();
        $tags = implode('', $htmlTagsArray);
        $jsTags = [];
        preg_match_all('#<link[^>]*href="([^"]*)"[^>]*/?>#is', $tags, $jsTags);
        return $jsTags[1];
    }
}