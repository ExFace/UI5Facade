<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Widgets\Tabs;
use exface\Core\Widgets\Tab;
use exface\Core\Widgets\Image;
use exface\Core\Interfaces\Widgets\iHaveValue;
use exface\Core\Interfaces\Actions\iShowWidget;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\Factories\ActionFactory;
use exface\Core\Widgets\Split;

/**
 * Renders a Popup widget as a sap.m.Popover
 * 
 * @method \exface\Core\Widgets\Popup getWidget()
 *        
 * @author Andrej Kabachnik
 *        
 */
class UI5Popup extends UI5Form
{
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Form::buildJsConstructor()
     */   
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {
        $widget = $this->getWidget();
        $controller = $this->getController();
        
        // Submit on enter
        $this->registerSubmitOnEnter($oControllerJs);
        
        
        // Finally, instantiate the dialog
        // Make sure to reset the contents of the popup every time it closes.
        // Otherwise values, that had bee inserted would be there again when
        // the popup is opened the next time - in particular if opened in another 
        // context, where not all widgets of the popup explicitly receive values.
        $icon = $widget->getIcon() ? 'icon: "' . $this->getIconSrc($widget->getIcon()) . '",' : '';
        return <<<JS

        new sap.m.Popover("{$this->getId()}", {
			{$icon}
            {$this->buildJsPropertyContentHeight()}
            {$this->buildJsPropertyContentWidth()}
            title: {$this->escapeString($this->getCaption())},
			content : [ {$this->buildJsLayoutConstructor()} ],
            footer: {$this->buildJsFloatingToolbar()},
            afterClose: function(oEvent) {
                {$this->buildJsResetter()};
            }
		}).addStyleClass('{$this->buildCssElementClass()}')
        {$this->buildJsPseudoEventHandlers()}
JS;
    }

    /**
     * 
     * @return string
     */
    protected function buildJsPropertyContentHeight() : string
    {
        $height = '';
        
        $dim = $this->getWidget()->getHeight();
        switch (true) {
            case $dim->isPercentual():
            case $dim->isFacadeSpecific() && strtolower($dim->getValue()) !== 'auto':
                $height = json_encode($dim->getValue());
                break;
            case $dim->isRelative():
                $height = json_encode(($dim->getValue() * $this->getHeightRelativeUnit()) . 'px');
                break;
            default:
                $height = '"auto"';
        }
        
        return $height ? 'contentHeight: ' . $height . ',' : '';
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsPropertyContentWidth() : string
    {
        $width = '';
        
        $dim = $this->getWidget()->getWidth();
        switch (true) {
            case $dim->isPercentual():
            case $dim->isFacadeSpecific():
                $width = json_encode($dim->getValue());
                break;
            case $dim->isRelative():
                $width = json_encode(($dim->getValue() * $this->getWidthRelativeUnit()) . 'px');
                break;
            default:
                $width = '"auto"'; // This is the size of a P13nDialog used for data configurator
        }
        
        return $width ? 'contentWidth: ' . $width . ',' : '';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::getCaption()
     */
    protected function getCaption() : string
    {
        $caption = parent::getCaption();
        $widget = $this->getWidget();
        $objectName = $widget->getMetaObject()->getName();
        $buttonCaption = $widget->hasParent() ? $widget->getParent()->getCaption() : null;
        
        // Append the object name to the caption unless
        // - The dialog has a custom caption (= not qual to the button caption)
        // - The caption is the same as the object name (would look stupid then)
        return $caption === $objectName || $caption !== $buttonCaption ? $caption : $caption . ': ' . $objectName;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Form::buildCssElementClass()
     */
    public function buildCssElementClass()
    {
        return 'exf-popup' .  ($this->getWidget()->isFilledBySingleWidget() ? ' exf-dialog-filled' : '');
    }
    
    /**
     * Returns the button constructors for the dialog buttons.
     * 
     * @return string
     */
    protected function buildJsDialogButtons(bool $addSpacer = true)
    {
        $toolbarEl = $this->getFacade()->getElement($this->getWidget()->getToolbarMain());
        $js = $toolbarEl->buildJsConstructorsForLeftButtons();
        if ($addSpacer === true) {
            $js .= 'new sap.m.ToolbarSpacer(),';
        }
        $js .= $toolbarEl->buildJsConstructorsForRightButtons();
        return $js;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Container::hasButtonBack()
     */
    public function hasButtonBack() : bool
    {
        return false;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Form::buildJsFloatingToolbar()
     */
    protected function buildJsFloatingToolbar()
    {
        // The Dialog does not need a caption in the toolbar like the Form does
        return $this->getFacade()->getElement($this->getWidget()->getToolbarMain())->buildJsConstructor();
    }
    
    /**
     * 
     * @return string
     */
    public function buildJsCloseDialog() : string
    {
        return "sap.ui.getCore().byId('{$this->getId()}').close();";
    }

    /**
     * The validation error of a popup just highlights the bad inputs and tries to focus the first one.
     * 
     * It does not show an error message dialog because that would automatically close the popup.
     * 
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\JqueryContainerTrait::buildJsValidationError()
     */
    public function buildJsValidationError()
    {
        foreach ($this->getWidgetsToValidate() as $child) {
            $el = $this->getFacade()->getElement($child);
            $validator = $el->buildJsValidator();
            $output .= '
				if(!' . $validator . ') { ' . $el->buildJsValidationError() . "; if (sFirstInvalidId === undefined) sFirstInvalidId = {$this->escapeString($el->getId())};}";
        }
        return <<<JS
(function(){
    var sFirstInvalidId, oFailedCtrl;
    $output;
    if (sFirstInvalidId && (oFailedCtrl = sap.ui.getCore().byId(sFirstInvalidId))) {
        if (oFailedCtrl.focus !== undefined) {
            oFailedCtrl.focus();
        }
    }
})()
JS;
    }
}