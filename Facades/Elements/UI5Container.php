<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Facades\AbstractAjaxFacade\Elements\JqueryContainerTrait;
use exface\Core\Widgets\Input;

/**
 * Renders a sap.m.Panel with no margins or paddings for a simple Container widget.
 * 
 * @method \exface\Core\Widgets\Container getWidget()
 * 
 * @author Andrej Kabachnik
 *
 */
class UI5Container extends UI5AbstractElement
{
    const CONTROLLER_METHOD_GET_DATA = 'getData';
    
    use JqueryContainerTrait;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsConstructor()
     */
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {
        if ($this->getWidget()->isDisabled()) {
            $this->getController()->addOnShowViewScript($this->buildJsSetDisabled(true), false);
        }
        
        $js = $this->buildJsPanelWrapper($this->buildJsChildrenConstructors());
        
        if ($this->hasPageWrapper() === true) {
            return $this->buildJsPageWrapper($js) . $this->buildJsAddCssWidgetClasses();
        } else {
            $js .= $this->buildJsAddCssWidgetClasses();
        }
        
        return $js;
    }
    
    /**
     * Wraps any JS content in an sap.m.Panel with no margins/padding.
     * 
     * @param string $contentJs
     * @return string
     */
    protected function buildJsPanelWrapper(string $contentJs) : string
    {
        $caption = $this->getCaption();
        if ($caption && $this->hasPageWrapper() === false) {
            $heading = "headerText: '{$caption}',";
        }
        return <<<JS

        new sap.m.Panel("{$this->getId()}", {
            {$heading}
            {$this->buildJsPropertyHeight()}
            {$this->buildJsPropertyWidth()}
            content: [
                {$contentJs}
            ]
        }).addStyleClass("sapUiNoMargin sapUiNoContentPadding {$this->buildCssElementClass()}")

JS;
    }
    
    /**
     * Returns `height: "xxx",` if required by the container control
     * 
     * @return string
     */
    protected function buildJsPropertyHeight() : string
    {
        if ($this->getWidget()->hasParent() === false) {
            return 'height: "100%",';
        }
        return '';
    }
    
    /**
     * Returns `width: "xxx",` if required by the container control
     * 
     * @return string
     */
    protected function buildJsPropertyWidth()
    {
        if ($this->getWidget()->hasParent() === false) {
            return '';
        }
        
        $dim = $this->getWidget()->getWidth();
        switch (true) {
            case $dim->isFacadeSpecific():
                $val = strtolower($dim->getValue());
                // If we have a large px-value the container will not be responsive anymore,
                // so we set a max-width on the main controls DOM element instead of specifying
                // a width in the UI5 control itself. 
                if (substr($val, -2) === 'px' && substr($val, 0, -2) > 200) {
                    $this->getController()->addOnShowViewScript("sap.ui.getCore().byId('{$this->getId()}').$().css('max-width', '{$val}');", false);
                    $val = null;
                }
                break;
            case $dim->isPercentual():
                $val = $dim->getValue();
                break;
            default:
            // TODO add support for relative units
            $val = $this->buildCssWidthDefaultValue();
        }
        if (! is_null($val) && $val !== '') {
            return 'width: "' . $val . '",';
        } else {
            return '';
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildCssWidthDefaultValue()
     */
    protected function buildCssWidthDefaultValue() : string
    {
        return '';
    }
                
    /**
     * Returns TRUE if this widget requires a page wrapper.
     * 
     * @return bool
     */
    protected function hasPageWrapper() : bool
    {
        return $this->getWidget()->hasParent() === false && $this->getView()->isWebAppRoot() === false;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::hasButtonBack()
     */
    public function hasButtonBack() : bool
    {
        return $this->hasPageWrapper();
    }
    
    /**
     * Wraps the given content in a sap.m.Page with back-button and a title.
     *
     * @param string $contentJs
     * @param string $footerConstructor
     * @param string $headerContentJs
     *
     * @return string
     */
    protected function buildJsPageWrapper(string $contentJs, string $footerConstructor = '', string $headerContentJs = '') : string
    {
        $this->getController()->addOnShowViewScript($this->buildJsFocusFirstInput());
        
        $showNavButton = $this->getView()->isWebAppRoot() ? 'false' : 'true';
        
        $caption = $this->getCaption();
        if ($caption === '' && $this->getWidget()->hasParent() === false) {
            $caption = $this->getWidget()->getPage()->getName();
        }
        
        return <<<JS
        
        new sap.m.Page({
            title: "{$caption}",
            showNavButton: {$showNavButton},
            navButtonPress: [oController.navBack, oController],
            content: [
                {$contentJs}
            ],
            footer: [
                {$footerConstructor}
            ],
            headerContent: [
                {$headerContentJs}
            ]
        })
        
JS;
    }
    
    /**
     * 
     * @return string
     */
    public function buildJsChildrenConstructors() : string
    {
        $js = '';
        $widget = $this->getWidget();
        // DO NOT use foreach() here because while we generate the code for child widgets, the
        // container might receive new widgets. Using a for() here ensures, dynamically added
        // widgets are also iterated over. Dynamically added widgets can happen for example,
        // when a Button has `hidden_if_input_invalid` or `hidden_if_access_denied` and the
        // conditions for these require data, that is not yet present in the container. The
        // Button widgets will add more widgets to make sure, it has all data for its conditional
        // properties.
        for ($i = 0; $i < $widget->countWidgets(); $i++) {
            $child = $widget->getWidget($i);
            $js .= ($js ? ",\n" : '') . $this->getFacade()->getElement($child)->buildJsConstructor();
        }
        
        return $js;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\JqueryContainerTrait::buildJsValidationError()
     */
    public function buildJsValidationError()
    {        
        $output = $this->buildJsShowMessageError($this->escapeString($this->translate('WIDGET.FORM.MESSAGE_VALIDATION_FAILED'))) . ';';
        foreach ($this->getWidgetsToValidate() as $child) {
            $el = $this->getFacade()->getElement($child);
            $validator = $el->buildJsValidator();
            $output .= '
				if(!' . $validator . ') { ' . $el->buildJsValidationError() . '; }';
        }
        return $output;
    }
    
    /**
     * Returns the JS code to give focus on the first input widget within the container
     * 
     * @return string
     */
    public function buildJsFocusFirstInput() : string
    {
        foreach ($this->getWidget()->getInputWidgets() as $input) {
            if ($input->isHidden() || $input->isDisabled()) {
                continue;
            }
            if (! $input->hasFunction(Input::FUNCTION_FOCUS)) {
                continue;
            }
            return $this->getFacade()->getElement($input)->buildJsCallFunction(Input::FUNCTION_FOCUS);
        }
        
        return '';
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Container::buildJsChangesGetter()
     */
    public function buildJsChangesGetter(bool $onlyVisible = false) : string
    {
        $checks = [];
        foreach ($this->getWidget()->getWidgets() as $w) {
            $el = $this->getFacade()->getElement($w);
            $check = $el->buildJsChangesGetter();
            if ($check !== '' && $check !== '[]') {
                $checks[] = $check;
            }
        }
        if (empty($checks)) {
            return '[]';
        }
        
        $js = "([]).concat(\n" . implode(",\n", $checks) . "\n)";
        if ($onlyVisible === true) {
            $js .= <<<JS
.filter(function(oChange) {
                    var oCtrl;
                    if (! oChange.elementId) return true;
                    oCtrl = sap.ui.getCore().byId(oChange.elementId);
                    if (oCtrl && oCtrl.getVisible !== undefined) {
                        return oCtrl.getVisible();
                    }
                    return true;
                })
JS;
        }
        
        return $js;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::registerConditionalProperties()
     */
    public function registerConditionalProperties() : UI5AbstractElement
    {
        parent::registerConditionalProperties();
        
        // If the entire container is disable, call the disabler of all inner elements here
        if ($this->getWidget()->isDisabled()) {
            $this->getController()->addOnShowViewScript($this->buildJsSetDisabled(true), false);
        }
        
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    protected function isFillingContainer() : bool
    {
        $widget = $this->getWidget();
        return $widget->hasParent() && $widget->getParent()->countWidgetsVisible() === 1;
    }
}