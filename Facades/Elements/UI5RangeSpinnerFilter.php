<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Facades\AbstractAjaxFacade\Elements\JsRangeSpinnerFilterTrait;
use exface\Core\Widgets\Button;

/**
 * Creates and renders an InlineGroup with to and from filters and +/- buttons.
 * 
 * @method \exface\Core\Widgets\RangeSpinner Filter getWidget();
 * 
 * @author Andrej Kabachnik
 *
 */
class UI5RangeSpinnerFilter extends UI5RangeFilter
{
    use JsRangeSpinnerFilterTrait;
    
    protected function buildCssWidthOfStepButton() : string
    {
        return '2rem';
    }
    
    protected function buildCssWidthOfRangeSeparator() : string
    {
        return '1rem';
    }
}