<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\UI5Facade\Facades\Interfaces\UI5ValueBindingInterface;
use exface\UI5Facade\Facades\Interfaces\UI5CompoundControlInterface;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JqueryLiveReferenceTrait;
use exface\Core\Widgets\Input;
use exface\Core\Interfaces\Widgets\iShowDataColumn;
use exface\Core\Interfaces\Widgets\iHaveValue;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\DataTypes\StringDataType;
use exface\Core\CommonLogic\DataSheets\DataColumn;
use exface\Core\Widgets\Filter;

/**
 * Generates sap.m.Text controls for Value widgets
 * 
 * @method \exface\Core\Widgets\Value getWidget()
 *
 * @author Andrej Kabachnik
 *        
 */
class UI5Value extends UI5AbstractElement implements UI5ValueBindingInterface, UI5CompoundControlInterface
{
    use JqueryLiveReferenceTrait {
        registerLiveReferenceAtLinkedElement as registerLiveReferenceAtLinkedElementViaTrait;
    }
    
    private $valueBindingPath = null;
    
    private $valueBindingPrefix = '/';
    
    private $valueBindingDisabled = false;
    
    private $valueBoundToModel = null;
    
    private $renderCaptionAsLabel = null;
    
    private $labelRendered = false;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsConstructor()
     */
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {
        return $this->buildJsConstructorForMainControl($oControllerJs);
    }
    
    /**
     * Returns the constructor of the text/input control without the label
     * 
     * @return string
     */
    public function buildJsConstructorForMainControl($oControllerJs = 'oController')
    {
        if ($this->getWidget()->getVisibility() === EXF_WIDGET_VISIBILITY_PROMOTED) {
            $this->addElementCssClass('exf-promoted');
        }
        
        return <<<JS

        new sap.m.Text("{$this->getId()}", {
            {$this->buildJsProperties()}
            {$this->buildJsPropertyValue()}
        })
        .addStyleClass("{$this->buildCssElementClass()}")
        {$this->buildJsPseudoEventHandlers()}

JS;
    }
            
    public function buildJsProperties()
    {
        return parent::buildJsProperties() . <<<JS
            {$this->buildJsPropertyTooltip()}
            {$this->buildJsPropertyLayoutData()}
JS;
    }
    
    /**
     * Returns the value property with property name and value followed by a comma.
     * 
     * @return string
     */
    protected function buildJsPropertyValue()
    {
        return <<<JS
            {$this->buildJsValueBindingPropertyName()}: {$this->buildJsValue()},
JS;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsValueSetter()
     */
    public function buildJsValueSetterMethod($valueJs)
    {
        return "setText({$valueJs} || '')";
    }
    
    /**
     * Returns inline javascript code for the value of the value property (without the property name).
     * 
     * Possible results are a quoted JS string, a binding expression or a binding object.
     * 
     * @return string
     */
    public function buildJsValue()
    {
        if (! $this->isValueBoundToModel()) {
            if ($this->getWidget()->hasValue() && $this->getWidget()->getValueExpression()->isReference()) {
                $value = '""';
            } else {
                $value = str_replace("\n", '', $this->getWidget()->getValue());
                $value = '"' . $this->escapeJsTextValue($value) . '"';
            }            
        } else {
            $value = $this->buildJsValueBinding();
        }
        return $value;
    }
    
    /**
     * Wraps the element constructor in a layout with a label.
     * 
     * @param string $element_constructor
     * @return string
     */
    protected function buildJsLabelWrapper($element_constructor)
    {
        return $this->buildJsConstructorForLabel() . $element_constructor;
    }
    
    /**
     * Builds the label for the element.
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Interfaces\UI5CompoundControlInterface::buildJsConstructorForLabel()
     */
    public function buildJsConstructorForLabel() : string
    {
        $widget = $this->getWidget();
        $caption = $this->getCaption();
        if ($this->getRenderCaptionAsLabel() === false) {
            return '';
        }        
        $caption = $this->escapeJsTextValue($caption);
        $labelAppearance = '';
        if ($widget->getHideCaption() === true || $widget->isHidden()) {
            $labelAppearance .= 'visible: false,';
        } elseif ($this->isRequired()) {
            $labelAppearance .= 'required: true,';
        }
        $this->labelRendered = true;
        
        return <<<JS
        new sap.m.Label('{$this->getIdOfLabel()}', {
            text: "{$caption}",
            {$this->buildJsPropertyTooltip()}
            {$labelAppearance}
        }),
        
JS;
    }
    
    /**
     * 
     * @return bool
     */
    protected function isRequired() : bool
    {
        return false;
    }
    
    /**
     * Returns TRUE if the value of the control should be bound to the default model.
     * 
     * This actually depends on meny factors:
     * - you can force value binding programmatically via setValueBoundToModel(). For example,
     * table cell widgets MUST be bound to model, so table columns call this method on their
     * cell widgets.
     * - you can set binding options like setValueBindingPath() which means using a binding
     * implicitly
     * - same happens if the prefill model has a binding for this widget
     * - on the other hand, widgets, that have static values should not have a binding unless
     * any of the above forces it
     * 
     * If none of the above applies, the element is concidered to have a binding unless there
     * is a binding conflict in the model (i.e. other widgets use the same binding name). This
     * is mainly for historical reasons - not sure, if it's still required.
     * 
     * @return boolean
     */
    protected function isValueBoundToModel()
    {
        if ($this->valueBoundToModel !== null) {
            return $this->valueBoundToModel;
        }
        
        $widget = $this->getWidget();
        $model = $this->getView()->getModel();
        
        // If the widget can be bound to a data column, but has no column name really
        if ($widget instanceof iShowDataColumn && ! $widget->isBoundToDataColumn()) {
            return false;
        }
        
        // If there is a model binding, obviously return true
        if ($model->hasBinding($widget, $this->getValueBindingWidgetPropertyName())) {
            return true;
        }
        
        // If the the binding was disabled explicitly, return false
        if ($this->isValueBindingDisabled() === true) {
            return false;
        }
        
        // Otherwise assume model binding unless the widget has an explicit value
        if ($widget->hasValue() === true) {
            $valueExpr = $widget->getValueExpression();
        } elseif ($widget instanceof Input && $widget->hasDefaultValue()) {
            $valueExpr = $widget->getDefaultValueExpression();
        } 
        
        if ($valueExpr && $valueExpr->isStatic() === true) {
            return false;
        }
        
        if ($model->hasBindingConflict($widget, $this->getValueBindingWidgetPropertyName())) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Forces value binding on or off for this control.
     * 
     * Note: `setValueBoundToModel(false)` and `setValueBindingDisabled(true)` have the same
     * effect, but not `setValueBoundToModel(true)` and `setValueBindingDisabled(false)`
     * because `setValueBindingDisabled(false)` does not force binding - it merely reinstantiates
     * the automatic detection algorithm if it was disabled previously.
     * 
     * @param bool $trueOrFalse
     * @return UI5Value
     */
    public function setValueBoundToModel(bool $trueOrFalse) : UI5Value
    {
        $this->valueBoundToModel = $trueOrFalse;
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Interfaces\UI5ValueBindingInterface::buildJsValueBindingOptions()
     */
    public function buildJsValueBindingOptions()
    {
        return '';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Interfaces\UI5ValueBindingInterface::buildJsValueBinding()
     */
    public function buildJsValueBinding($customOptions = '')
    {
        $js = <<<JS
            {
                path: "{$this->getValueBindingPath()}",
                {$this->buildJsValueBindingOptions()}
                {$customOptions}
            }
JS;
                return $js;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Interfaces\UI5ValueBindingInterface::setValueBindingPath()
     */
    public function setValueBindingPath($string)
    {
        $this->valueBindingPath = $string;
        $this->setValueBoundToModel(true);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Interfaces\UI5ValueBindingInterface::getValueBindingPath()
     */
    public function getValueBindingPath() : string
    {
        if ($this->valueBindingPath === null) {
            $widget = $this->getWidget();
            $model = $this->getView()->getModel();
            if ($model->hasBinding($widget, $this->getValueBindingWidgetPropertyName())) {
                return $model->getBindingPath($widget, $this->getValueBindingWidgetPropertyName());
            }
            return $this->getValueBindingPrefix() . $this->getWidget()->getDataColumnName();
        }
        return $this->valueBindingPath;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Interfaces\UI5ValueBindingInterface::getValueBindingPrefix()
     */
    public function getValueBindingPrefix() : string
    {
        return $this->valueBindingPrefix;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Interfaces\UI5ValueBindingInterface::setValueBindingPrefix()
     */
    public function setValueBindingPrefix(string $value) : UI5ValueBindingInterface
    {
        $this->valueBindingPrefix = $value;
        $this->setValueBoundToModel(true);
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsPropertyWidth()
    {
        $dim = $this->getWidget()->getWidth();
        switch (true) {
            case $dim->isFacadeSpecific():
            case $dim->isPercentual():
                $val = $dim->getValue();
                break;
            case $dim->isRelative() && $factor = NumberDataType::cast($dim->getValue()):
                $val = ($this->getWidthRelativeUnit() * $factor) . 'px';
                break;
            default:
                $val = $this->buildCssWidthDefaultValue();
                break;
        }
        if (! is_null($val) && $val !== '') {
            return 'width: "' . $val . '",';
        } else {
            return '';
        }
    }
    
    protected function buildCssWidthDefaultValue() : string
    {
        return '100%';
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsPropertyHeight()
    {
        $dim = $this->getWidget()->getHeight();
        switch (true) {
            case $dim->isFacadeSpecific():
            case $dim->isPercentual():
                $val = $dim->getValue();
                break;
            case $dim->isRelative():
                $val = ($this->getHeightRelativeUnit() * $dim->getValue()) . 'px';
                break;
            default:
                $val = $this->buildCssHeightDefaultValue();
                break;
        }
        if (! is_null($val) && $val !== '') {
            return 'height: "' . $val . '",';
        } else {
            return '';
        }
    }
    
    protected function buildCssHeightDefaultValue()
    {
        return '';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Interfaces\UI5ValueBindingInterface::buildJsValueBindingPropertyName()
     */
    public function buildJsValueBindingPropertyName() : string
    {
        return 'text';
    }

    /**
     * Returns the widget property, that is used for the value binding (i.e. "value" for value-widgets).
     * 
     * NOTE: this is different from buildJsValueBindingPropertyName()! While the latter returns the name
     * of the UI5 control property for the main value, this method returns the name of the widget property,
     * that is used in this binding. I.e. for a simple Value widget (sap.m.Text), the widget property `value`
     * is bound to the control property `text`.
     * 
     * @return string
     */
    protected function getValueBindingWidgetPropertyName() : string
    {
        return 'value';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Interfaces\UI5ValueBindingInterface::isValueBindingDisabled()
     */
    public function isValueBindingDisabled() : bool
    {
        return $this->valueBindingDisabled;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Interfaces\UI5ValueBindingInterface::setValueBindingDisabled()
     * 
     * Note: there is also setValueBoundToModel() with forces binding on or off regardless
     * of any other parameters.
     * 
     * Note: `setValueBoundToModel(false)` and `setValueBindingDisabled(true)` have the same
     * effect, but not `setValueBoundToModel(true)` and `setValueBindingDisabled(false)`
     * because `setValueBindingDisabled(false)` does not force binding - it merely reinstantiates
     * the automatic detection algorithm if it was disabled previously.
     * 
     * @see setValueBoundToModel()
     */
    public function setValueBindingDisabled(bool $value) : UI5ValueBindingInterface
    {
        $this->valueBindingDisabled = $value;
        return $this;
    }
    
    /**
     * {@inheritdoc}
     * @see JqueryLiveReferenceTrait::registerLiveReferenceAtLinkedElement()
     */
    public function registerLiveReferenceAtLinkedElement() 
    {
        $this->registerLiveReferenceAtLinkedElementViaTrait();
        // Also refresh the live reference each time the view is prefilled!
        // But use setTimeout() to make sure all widgets binding-events affected
        // by the prefill really are done!        
        if ($this->getLinkedFacadeElement()) {
            $this->getController()->addOnPrefillDataChangedScript("setTimeout(function(){{$this->buildJsLiveReference()}}, 0);");
        }
        
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::registerConditionalProperties()
     */
    public function registerConditionalProperties() : UI5AbstractElement
    {
        parent::registerConditionalProperties();
        
        if ($this->isUnrendered()) {
            return $this;
        }
        
        // Update this element if its value is a live reference
        // Make sure, this is only done for value widgets - e.g. not for InlineGroup, which
        // also inherits from UI5Value
        if ($this->getWidget() instanceof iHaveValue) {
            $this->registerLiveReferenceAtLinkedElement();
        }
        
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsResetter()
     */
    public function buildJsResetter() : string
    {
        $widget = $this->getWidget();
        $js = '';
        
        if ($widget->getValueWidgetLink() !== null) {
            return '';
        }
        
        if (! $this->isValueBoundToModel()) {
            $staticDefault = $widget->getValueWithDefaults();
            $initialValueJs = json_encode($staticDefault);
            $js = $this->buildJsValueSetter($initialValueJs);
        } else {
            
            $js = <<<JS
            
(function(){
    var oInput = sap.ui.getCore().byId('{$this->getId()}');
    var oViewModel = {$this->getController()->getView()->buildJsViewGetter($this)}.getModel('view');
    var sPath = "{$this->getValueBindingPath()}";
    var mVal = null;
    if (oViewModel && sPath !== '') {
        mVal = oViewModel.getProperty('/_prefill/data' + sPath);
    }
    {$this->buildJsValueSetter('mVal')};
    if (oInput.setValueState !== undefined) {
        oInput.setValueState('None');
    }
    return oInput;
})()

JS;
        }
        
        return $js;
    }
    
    /**
     * 
     * @return bool
     */
    protected function getRenderCaptionAsLabel(bool $default = true) : bool
    {
        return $this->renderCaptionAsLabel ?? $default;
    }
    
    /**
     * 
     * @param bool $value
     * @return UI5Value
     */
    public function setRenderCaptionAsLabel(bool $value) : UI5Value
    {
        $this->renderCaptionAsLabel = $value;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    protected function isLabelRendered() : bool
    {
        return $this->labelRendered;
    }
    
    protected function getIdOfLabel() : string
    {
        return $this->getUseWidgetId() ? $this->getId() . '__label' : '';
    }
    
    protected function buildJsSetHidden(bool $hidden, bool $resetWidget = false, bool $emptyWidget = false, string $elementId = null) : string
    {
        $showHideLabelJs = '';
        if ($this->isLabelRendered() === true || $this->getRenderCaptionAsLabel()) {
            if (! ($this->getWidget()->getHideCaption() === true || $this->getWidget()->isHidden())) {
                $showHideLabelJs = "sap.ui.getCore().byId('{$this->getIdOfLabel()}').setVisible(bVisible);";
            }
        }
        
        $bVisibleJs = ($hidden ? 'false' : 'true');
        $bResetJs = ($resetWidget ? 'true' : 'false');
        $bEmptyJs = ($emptyWidget ? 'true' : 'false');
        $elementId = $elementId ?? $this->getId();
        return <<<JS
(function(bVisible, oCtrl, bReset, bEmpty){
    if (oCtrl.getParent().getMetadata().getName() == 'sap.ui.layout.form.FormElement') {
        if (bVisible === oCtrl.getParent().getVisible()) {
            return;
        }
        oCtrl.getParent().setVisible(bVisible);
    } else {
        if (bVisible === oCtrl.getVisible()) {
            return;
        }
        oCtrl.setVisible(bVisible);
        {$showHideLabelJs}
    }
    oCtrl.$()?.trigger('visibleChange', [{visible: bVisible}]);
    // reset only if element gets hidden, should be reset and should not be emptied
    if (bReset === true && bVisible === false && bEmpty === false) {
        {$this->buildJsResetter()}
    }
    // empty only if element gets hidden and should be emptied
    if (bEmpty === true && bVisible === false) {
        {$this->buildJsEmpty()}
    }
})($bVisibleJs, sap.ui.getCore().byId('{$elementId}'), $bResetJs, $bEmptyJs)
JS;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsValueGetterMethod()
     */
    public function buildJsValueGetterMethod()
    {
        return "getText()";
    }
    
    protected function buildJsLiveReference()
    {
        if (($this->getWidget() instanceof Value) && $this->getWidget()->isInTable() === true) {
            return '';
        }
        
        $output = '';
        if ($linked_element = $this->getLinkedFacadeElement()) {
            // TODO check the logic, maybe invent a new uxon-proberty to control that?
            // actually dont refresh it when the value is bound to the model and the
            // value of the linked element is empty but only if the widget is not a filter!
            // need this to not empty inputs that are bound to the model and to a value
            // especially after a prefill InputComboTables load their columns and
            // if the input is bound to a column that is empty but was actually filled manually
            // the prefill and data loading from additional columns would empty the input
            $bBoundToModelJs = ($this->isValueBoundToModel() ? 'true' : 'false');
            $bIsFilter = (($this->getWidget()->getParent() instanceof Filter) ? 'true' : 'false');
            $link = $this->getWidget()->getValueWidgetLink();
            $col = $link->getTargetColumnId();
            if (! StringDataType::startsWith($col, '~')) {
                $col = DataColumn::sanitizeColumnName($col);
            }
            $output = <<<JS
                var bBoundToModel = {$bBoundToModelJs};
                var bIsFilter = {$bIsFilter};
                console.log('Binding for {$this->getId()}');
                var val = {$linked_element->buildJsValueGetter($col, $link->getTargetRowNumber())}
                if (! (bBoundToModel === true && bIsFilter === false && (val === undefined || val === '' || val === null))) {
                    {$this->buildJsValueSetter('val')}
                }              
JS;
        }
        return $output;
    }
}