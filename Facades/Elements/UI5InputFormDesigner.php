<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Facades\AbstractAjaxFacade\Elements\SurveyJsTrait;
use exface\Core\Exceptions\Facades\FacadeLogicError;
use exface\Core\DataTypes\FilePathDataType;

/**
 * Creates a Survery-JS instance for an InputForm widget
 * 
 * @method \exface\Core\Widgets\InputForm getWidget()
 * 
 * @author Andrej Kabachnik
 *
 */
class UI5InputFormDesigner extends UI5InputForm
{
    const CONTROLLER_VAR_CREATOR = 'creator';
    
    protected function init()
    {
        parent::init();
        
        $creatorPath = FilePathDataType::normalize($this->getFacade()->getConfig()->getOption('LIBS.SURVEY.CREATOR_JS'), DIRECTORY_SEPARATOR);
        if (! file_exists($this->getWorkbench()->filemanager()->getPathToVendorFolder() . DIRECTORY_SEPARATOR . $creatorPath)) {
            throw new FacadeLogicError('Cannot initialize InputFormDesigner widget: Survey JS Creator is not installed!', '7S55K77');
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::buildJsConstructorForMainControl()
     */
    public function buildJsConstructorForMainControl($oControllerJs = 'oController')
    {
        $controller = $this->getController();
        $controller->addDependentObject(self::CONTROLLER_VAR_CREATOR, $this, 'null');
        
        $this->registerExternalModules($controller);
        
        return <<<JS

        new sap.ui.core.HTML("{$this->getId()}", {
            content: "<div id=\"{$this->getIdOfCreatorDiv()}\"></div>",
            afterRendering: function() {
                var oCreator;
                {$this->buildJsCreatorSetup()}
                    
                // Show Designer, Test Survey, JSON Editor and additionally Logic tabs
                var oOptions = {};
                {$this->buildJsCreatorOptions('oOptions')}
                //create the SurveyJS Creator and render it in div with id equals to "creatorElement"
                oCreator = new SurveyCreator.SurveyCreator("{$this->getIdOfCreatorDiv()}", oOptions);

                {$this->buildJsCreatorInit('oCreator')}

                {$this->buildJsCreatorVar()} = oCreator;

                var oValueBinding = new sap.ui.model.Binding(sap.ui.getCore().byId('{$this->getId()}').getModel(), '{$this->getValueBindingPath()}', sap.ui.getCore().byId('{$this->getId()}').getModel().getContext('{$this->getValueBindingPath()}'));
                oValueBinding.attachChange(function(oEvent){
                    var sVal = sap.ui.getCore().byId('{$this->getId()}').getModel().getProperty('{$this->getValueBindingPath()}');
                    // Do not update if the model does not have this property
                    if (sVal === undefined) {
                        return;
                    }
                    {$this->buildJsValueSetter("sVal")};
                });
            }
        })

JS;
    }
    
    protected function buildJsCreatorVar() : string
    {
        return $this->getController()->buildJsDependentObjectGetter(self::CONTROLLER_VAR_CREATOR, $this);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5InputForm::buildJsSurveyConfigGetter()
     */
    protected function buildJsSurveyConfigGetter() : string
    {
        return $this->buildJsValueGetter();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5InputForm::buildJsValidator()
     */
    public function buildJsValidator(?string $valJs = null) : string
    {
        // TODO
        return "true";
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsValueSetter()
     */
    public function buildJsValueSetter($value)
    {
        return $this->buildJsCreatorValueSetter($value);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsValueGetter()
     */
    public function buildJsValueGetter()
    {
        return $this->buildJsCreatorValueGetter();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::buildJsSetDisabled()
     */
    public function buildJsSetDisabled(bool $trueOrFalse) : string
    {
        // TODO
        return '';
    }
}