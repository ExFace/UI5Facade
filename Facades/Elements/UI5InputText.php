<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\DataTypes\StringDataType;
use exface\Core\Widgets\InputText;

/**
 * Generates OpenUI5 inputs
 *
 * @author Andrej Kabachnik
 *        
 */
class UI5InputText extends UI5Input
{
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Text::buildJsConstructorForMainControl()
     */
    public function buildJsConstructorForMainControl($oControllerJs = 'oController')
    {
        return <<<JS
        new sap.m.TextArea("{$this->getId()}", {
            {$this->buildJsProperties()}
            {$this->buildJsPropertyMaxLength()}
        })
JS;
    }
    
    /**
     * Returns the `maxLength` and `showExceededText` properties (with tailing comma)
     * if the value data type defines a maximum character length - or an empty string
     * otherwise.
     * 
     * This shows a remaining character counter in the input field, and can be disabled by setting the `show_character_limit` property to FALSE.
     * 
     * @return string
     */
    protected function buildJsPropertyMaxLength() : string
    {
        $widget = $this->getWidget();
        // Only apply the character counter to plain InputText widgets, not to subclasses
        if (! $widget instanceof InputText || get_class($widget) !== InputText::class) {
            return '';
        }
        if (! $widget->getShowCharacterLimit()) {
            return '';
        }
        $dataType = $widget->getValueDataType();
        if (! $dataType instanceof StringDataType) {
            return '';
        }
        $maxLength = $dataType->getLengthMax();
        if ($maxLength === null || $maxLength <= 0) {
            return '';
        }
        $maxLength = (int) $maxLength;
        return <<<JS

            maxLength: {$maxLength},
            showExceededText: true,
JS;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::getHeight()
     */
    public function getHeight()
    {
        if ($this->getWidget()->getHeight()->isUndefined()) {
            return (2 * $this->getHeightRelativeUnit()) . 'px';
        }
        return parent::getHeight();
    }
}
?>