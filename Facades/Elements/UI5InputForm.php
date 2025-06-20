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
        buildJsSurveyInit AS buildJsSurveyInitViaTrait;
    }
    
    const CONTROLLER_VAR_SURVEY = 'survey';
    
    protected function init()
    {
        parent::init();
        
        // Make sure to register the controller var as early as possible because it is needed in buildJsValidator(),
        // which is called by the outer Dialog or Form widget
        $this->getController()->addDependentObject(self::CONTROLLER_VAR_SURVEY, $this, 'null');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::buildJsConstructorForMainControl()
     */
    public function buildJsConstructorForMainControl($oControllerJs = 'oController')
    {
        $controller = $this->getController();
        
        $this->registerExternalModules($controller);
        
        // Update the survey every time the value in the UI5 model changes.
        // Also update the UI5 model every time the answer to a survey question changes. Note,
        // that this doesnot seem to trigger a binding change, so there will be no recursion
        $this->addOnChangeScript(<<<JS
            
                    (function(oHtml) {
                        var oCurrentValue = {$this->buildJsValueGetter()};
                        oHtml.getModel().setProperty('{$this->getValueBindingPath()}', oCurrentValue);
                    })(sap.ui.getCore().byId("{$this->getId()}"))
JS);
        
        return <<<JS

        new sap.ui.core.HTML("{$this->getId()}", {
            content: "<div id=\"{$this->getIdOfSurveyDiv()}\"></div>",
            afterRendering: function() {
                var oHtml = sap.ui.getCore().byId('{$this->getId()}');
                // Re-render the form
                var fnRefresh = function(){
                    {$this->buildJsValueSetter("oHtml.getModel().getProperty('{$this->getValueBindingPath()}')")};
                };
                // Some changes in the UI5 constrols make the form get heigth=0 for some reason. This method here
                // will check, if it disappeared and refresh the form if so. This will re-render it and it will
                // become visible again
                var fnMakeVisible = function() {
                	var jqSurvey = $('#{$this->getIdOfSurveyDiv()}');
                    if (jqSurvey.length === 0 || jqSurvey.height() === 0) {
                        fnRefresh();
                    }
                };
                var oValueBinding = new sap.ui.model.Binding(oHtml.getModel(), '{$this->getValueBindingPath()}', sap.ui.getCore().byId('{$this->getId()}').getModel().getContext('{$this->getValueBindingPath()}'));
                
                // Init Survey.js
                {$this->buildJsSurveySetup()}

				// Make sure, the form is refreshed with every model change
                oValueBinding.attachChange(function(oEvent){
                    fnRefresh();
                });
                
                // Re-render survey when something happens to the parent - e.g. if the tab is hidden/shown.
                // Survey actually disappeared even if another tab was added before the survey tab!
                sap.ui.core.ResizeHandler.register(oHtml.getParent(), fnMakeVisible);
                
                // Finally ensure the form is visible by default
                fnMakeVisible();
            }
        })

JS;
    }
    
    /**
     * 
     * @see SurveyJsTrait::buildJsSurveyVar()
     */
    protected function buildJsSurveyVar() : string
    {
        return $this->getController()->buildJsDependentObjectGetter(self::CONTROLLER_VAR_SURVEY, $this);
    }
    
    /**
     * 
     * @see SurveyJsTrait::buildJsSurveyModelGetter()
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
     * @param string $valueJs
     * @return string
     */
    protected function buildJsSurveyModelSetter(string $valueJs) : string
    {
        $widget = $this->getWidget();
        $model = $this->getView()->getModel();
        if ($model->hasBinding($widget, 'form_config')) {
            $modelPath = $model->getBindingPath($widget, 'form_config');
        } else {
            $modelPath = $this->getValueBindingPrefix() . $this->getWidget()->getFormConfigDataColumnName();
        }
        return <<<JS
(function(oModel) {
            var oValue = {$this->buildJsValueGetter()};
            sap.ui.getCore().byId('{$this->getId()}').getModel().setProperty('{$modelPath}', oModel);
            {$this->buildJsValueSetter('oValue')};
        })({$valueJs})
JS;
    }
    
    /**
     * 
     * @see SurveyJsTrait::buildJsSurveyInit()
     */
    protected function buildJsSurveyInit(string $oSurveyJs = 'oSurvey') : string
    {
        // Make sure the left-aligned titles are the same width as those of UI5 controls
        return $this->buildJsSurveyInitViaTrait($oSurveyJs) . <<<JS
    
    $oSurveyJs.onUpdateQuestionCssClasses.add(function(_, options) {
        const classes = options.cssClasses;
        if (classes.headerLeft === 'title-left') {
            classes.titleLeftRoot += ' sapUiRespGrid sapUiRespGridHSpace0 sapUiRespGridVSpace0 sapUiFormResGridCont sapUiRespGridOverflowHidden sapUiRespGridMedia-Std-LargeDesktop';
            /* TODO replace class sapUiRespGridMedia-Std-LargeDesktop to match current device in UI5 */
            classes.headerLeft += ' sapUiRespGridSpanXL5 sapUiRespGridSpanL4 sapUiRespGridSpanM4 sapUiRespGridSpanS12';
        }
    });

    // Move the dropdown popups to the end of the body to avoid z-index issues
    $oSurveyJs.onAfterRenderSurvey.add(function(_, options) {
        setTimeout(function() {

            const userAgent = window.navigator.userAgent;
            const isMobileAgent = /(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i.test(userAgent);
            if (!((isMobileAgent || navigator.platform === "MacIntel" && navigator.maxTouchPoints > 0) || navigator.platform === "iPad")) { return; }

            const popupElements = document.querySelectorAll('.sv-popup.sv-dropdown-popup');
            let customContainer = document.querySelector('.sv-popup-custom-container');
            if (!customContainer) {
                customContainer = document.createElement('div');
                customContainer.className = 'sv-popup-custom-container';
                document.body.appendChild(customContainer);
            }
            popupElements.forEach(element => {
                customContainer.appendChild(element);
            });
        }, 100);
    });

JS;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\JqueryInputValidationTrait::buildJsValidator()
     */
    public function buildJsValidator(?string $valJs = null) : string
    {
        // Always validate the form if it can be found in the dialog - even if the widget is not required explicitly. Otherwise required
        // fields inside the form will not produce validation errors if the InputForm is not explicitly
        // marked as required
        //
        return <<<JS
(function(){
    var surveyJsVar = {$this->buildJsSurveyVar()};
    if (surveyJsVar !== null && surveyJsVar !== undefined) {   
        return {$this->buildJsSurveyVar()}.validate();
    }
    return true;
}())
JS;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::buildJsValidationError()
     */
    public function buildJsValidationError()
    {
        // No need to do anything here - the .validate() method of Survey.js already shows the errors
        return '';
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