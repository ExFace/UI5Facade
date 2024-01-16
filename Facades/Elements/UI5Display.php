<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\UI5Facade\Facades\Interfaces\UI5BindingFormatterInterface;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\CommonLogic\Constants\Colors;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JsValueScaleTrait;
use exface\Core\Interfaces\Widgets\iHaveColorScale;
use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;
use exface\Core\DataTypes\TextDataType;
use exface\Core\Interfaces\Widgets\iCanWrapText;

/**
 * Generates sap.m.Text controls for Display widgets.
 * 
 * @method \exface\Core\Widgets\Display getWidget()
 *
 * @author Andrej Kabachnik
 *        
 */
class UI5Display extends UI5Value
{
    use JsValueScaleTrait;
    
    const ICON_YES_TABLE = "'sap-icon://accept'";
    const ICON_NO_TABLE = "null";
    const ICON_YES_FORM = "'sap-icon://message-success'";
    const ICON_NO_FORM = "'sap-icon://border'";
    
    private $alignmentProperty = null;
    
    private $onChangeHandlerRegistered = false;
    
    private $wrap = null;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsConstructor()
     */
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {
        return $this->buildJsLabelWrapper($this->buildJsConstructorForMainControl($oControllerJs));
    }
    
    /**
     * 
     * @return bool
     */
    protected function isIcon() : bool
    {
        return $this->getWidget()->getValueDataType() instanceof BooleanDataType;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Value::buildJsConstructorForMainControl()
     */
    public function buildJsConstructorForMainControl($oControllerJs = 'oController')
    {
        // Register stuff here, that is needed for in-table rendering where buildJsConstructor()
        // is not called
        $this->registerExternalModules($this->getController());
        
        $widget = $this->getWidget();        
        $visible = '';
        if ($this->isIcon()) {
            if ($this->getWidget()->isInTable() === true) {
                $icon_yes = self::ICON_YES_TABLE;
                $icon_no = self::ICON_NO_TABLE;
                $icon_width = '"100%"';
            } else {
                $icon_yes = self::ICON_YES_FORM;
                $icon_no = self::ICON_NO_FORM;
                $icon_width = "'16px'";
                if ($widget->isHidden() === true) {
                    $visible = 'visible: false,';
                }
            }
            $js = <<<JS

        new sap.ui.core.Icon("{$this->getId()}", {
            width: {$icon_width},
            {$this->buildJsPropertyTooltip()}
            {$visible}
            src: {$this->buildJsValueBinding("formatter: function(value) {
                    if (value === '1' || value === 'true' || value === 1 || value === true) return $icon_yes;
                    else return $icon_no;
                }")}
        })
        .addStyleClass('sapMText')
        {$this->buildJsPseudoEventHandlers()}

JS;
        } elseif($widget instanceof iHaveColorScale && $widget->hasColorScale()) {
            $objStatus = new UI5ObjectStatus($widget, $this->getFacade());
            $objStatus->setTitle('');
            $objStatus->setValueBindingPrefix($this->getValueBindingPrefix());
            $js = $objStatus->buildJsConstructorForMainControl($oControllerJs);
        } else {
            $js = parent::buildJsConstructorForMainControl();
        }

        // TODO #binding store values in real model
        if(! $this->isValueBoundToModel()) {
            $value = $this->escapeJsTextValue($this->getWidget()->getValue());
            $js .= <<<JS

            .setModel(function(){
                var oModel = new sap.ui.model.json.JSONModel();
                oModel.setProperty("/{$this->getWidget()->getDataColumnName()}", "{$value}");
                return oModel;
            }())
JS;
        }
        
        return $js;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Value::buildJsValueBindingOptions()
     */
    public function buildJsValueBindingOptions()
    {
        return $this->getValueBindingFormatter()->buildJsBindingProperties();
    }
    
    /**
     * 
     * @return UI5BindingFormatterInterface
     */
    protected function getValueBindingFormatter()
    {
        return $this->getFacade()->getDataTypeFormatterForUI5Bindings($this->getWidget()->getValueDataType());
    }
    
    /**
     * Sets the alignment for the content within the display: `"Begin"`, `"End"`, `"Center"`, `"Left"` or `"Right"`.
     * 
     * Accepts a JS snippet as argument: e.g. `"Begin"` or `sap.ui.core.TextAlign.Center`.
     * 
     * @param $propertyValueJs
     * @return UI5Display
     */
    public function setAlignment(string $propertyValueJs) : UI5Display
    {
        $this->alignmentProperty = $propertyValueJs;
        return $this;
    }

    /**
     * 
     * @return string
     */
    protected function buildJsPropertyAlignment() : string
    {
        return $this->alignmentProperty ? 'textAlign: ' . $this->alignmentProperty . ',' : '';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsProperties()
     */
    public function buildJsProperties()
    {
        return parent::buildJsProperties() . <<<JS
            {$this->buildJsPropertyWidth()}
            {$this->buildJsPropertyHeight()}
            {$this->buildJsPropertyAlignment()}
            {$this->buildJsPropertyWrapping()}
JS;
    }
            
    /**
     * Returns "wrapping: false/true," with tailing comma.
     * 
     * @return string
     */
    protected function buildJsPropertyWrapping()
    {
        return 'wrapping: ' . ($this->getWrapping() === true ? 'true' : 'false'). ',';
    }
    
    /**
     * {@inheritDoc}
     * 
     * If the display is used as cell widget in a DataColumn, the tooltip will
     * contain the value instead of a description, because ui5 tables tend to
     * cut off long values on smaller screens. On the other hande, the description 
     * is already there in the column header.
     * 
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsPropertyTooltip()
     */
    protected function buildJsPropertyTooltip()
    {
        if ($this->getWidget()->isInTable() === true) {
            if ($this->isValueBoundToModel()) {
                $value = $this->buildJsValueBinding('formatter: function(value){return (value === null || value === undefined) ? value : value.toString();},');
            } else {
                $value = $this->buildJsValue();
            }
            
            return 'tooltip: ' . $value .',';
        }
        
        return parent::buildJsPropertyTooltip();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Value::buildJsValueSetterMethod()
     */
    public function buildJsValueSetterMethod($value)
    {
        if ($this->isIcon()) {
            if ($this->getWidget()->isInTable() === true) {
                $icon_yes = self::ICON_YES_TABLE;
                $icon_no = self::ICON_NO_TABLE;
            } else {
                $icon_yes = self::ICON_YES_FORM;
                $icon_no = self::ICON_NO_FORM;
            }
            return "setSrc((function(value) {
                    if (value === '1' || value === 'true' || value === 1 || value === true) return $icon_yes;
                    else return $icon_no;
                })($value))";
        }
        return "setText({$value})";
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsValueSetter()
     */
    public function buildJsValueSetter($valueJs)
    {
        // If we are not bound to the model, we need to do the value formatting by hand here
        // TODO Actually, this is probably not a very good idea as this implies, that the
        // value getter needs to do the opposite conversion. Maybe it would be smarter to
        // always use the value binding and set that via value setter. This would cause
        // quite some refactoring though...
        // #value-binding
        if (! $this->isValueBoundToModel()) {
            $valueJs = $this->getFacade()->getDataTypeFormatter($this->getWidget()->getValueDataType())->buildJsFormatter($valueJs);
        }
        return parent::buildJsValueSetter($valueJs);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsValueGetterMethod()
     */
    public function buildJsValueGetterMethod()
    {
        return "getText()";
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsValueGetter()
     */
    public function buildJsValueGetter()
    {
        if ($this->isIcon()) {
            if ($this->getWidget()->isInTable() === true) {
                $icon_yes = self::ICON_YES_TABLE;
            } else {
                $icon_yes = self::ICON_YES_FORM;
            }
            return <<<JS
                (function(oIcon){
                    return (oIcon.getSrc() === $icon_yes);
                })(sap.ui.getCore().byId('{$this->getId()}'))
JS;
        }
        // always return the normalized value, therefor we have to parse it
        $rawValueGetter = parent::buildJsValueGetter();
        return $this->getFacade()->getDataTypeFormatter($this->getWidget()->getValueDataType())->buildJsFormatParser($rawValueGetter);
    }
        
    /**
     * 
     * @return string[]
     */
    protected function getColorSemanticMap() : array
    {
        $semCols = [];
        foreach (Colors::getSemanticColors() as $semCol) {
            switch ($semCol) {
                case Colors::SEMANTIC_ERROR: $ui5Color = 'Error'; break;
                case Colors::SEMANTIC_WARNING: $ui5Color = 'Warning'; break;
                case Colors::SEMANTIC_OK: $ui5Color = 'Success'; break;
                case Colors::SEMANTIC_INFO: $ui5Color = 'Information'; break;
            }
            $semCols[$semCol] = $ui5Color;
        }
        return $semCols;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::addOnChangeScript()
     */
    public function addOnChangeScript($script)
    {
        if ($this->isValueBoundToModel() && $this->onChangeHandlerRegistered === false) {
            $this->addOnBindingChangeScript($this->buildJsValueBindingPropertyName(), $this->getController()->buildJsEventHandler($this, self::EVENT_NAME_CHANGE, false));
            $this->onChangeHandlerRegistered = true;
        }
        return parent::addOnChangeScript($script);
    }
    
    protected function buildJsColorValue() : string
    {
        $widget = $this->getWidget();
        if (! ($widget instanceof iHaveColorScale && $widget->hasColorScale() !== false)) {
            return '';
        }
        
        if (! $this->isValueBoundToModel()) {
            $value = ''; // TODO
        } else {
            $semColsJs = json_encode($this->getColorSemanticMap());
            $bindingOptions = <<<JS
                formatter: function(value){
                    var sColor = {$this->buildJsScaleResolver('value', $widget->getColorScale(), $widget->isColorScaleRangeBased())};
                    var sValueColor;
                    var oCtrl = this;
                    if (sColor.startsWith('~')) {
                        var oColorScale = {$semColsJs};
                        {$this->buildJsColorCssSetter('oCtrl', 'null')}
                        return oColorScale[sColor];
                    } else if (sColor) {
                        {$this->buildJsColorCssSetter('oCtrl', 'sColor')}
                    }
                    return {$this->buildJsColorValueNoColor()};
                }
                
JS;
            $value = $this->buildJsValueBinding($bindingOptions);
        }
        return $value;
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsColorValueNoColor() : string
    {
        return 'sap.ui.core.ValueState.None';
    }
    
    /**
     * 
     * @param string $oControlJs
     * @param string $sColorJs
     * @return string
     */
    protected function buildJsColorCssSetter(string $oControlJs, string $sColorJs) : string
    {
        return "if ($sColorJs === null) { $oControlJs.$().css('color', null);} else {setTimeout(function(){ $oControlJs.$().css('color', $sColorJs); }, 0)}";
    }
    
    /**
     *
     * @return bool
     */
    protected function getWrapping() : bool
    {
        if ($this->wrap === null) {
            $widget = $this->getWidget();
            if ($widget->isInTable() && $widget->getParent() instanceof iCanWrapText) {
                return ! $widget->getParent()->getNowrap();
            }
            return ($this->getWidget()->getValueDataType() instanceof TextDataType);
        }
        return $this->wrap;
    }
    
    /**
     * 
     * @param bool $value
     * @return UI5Display
     */
    public function setWrapping(bool $value) : UI5Display
    {
        $this->wrap = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::registerExternalModules()
     */
    public function registerExternalModules(UI5ControllerInterface $controller) : UI5AbstractElement
    {
        $this->getValueBindingFormatter()->registerExternalModules($controller);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Value::buildJsValueBindingPropertyName()
     */
    public function buildJsValueBindingPropertyName() : string
    {
        return $this->isIcon() ? 'src' : 'text';
    }
}