<?php
namespace exface\UI5Facade\Facades\Formatters;

use exface\Core\Facades\AbstractAjaxFacade\Formatters\JsBooleanFormatter;
use exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsDataTypeFormatterInterface;

/**
 * 
 * @method JsBooleanFormatter getJsFormatter()
 * 
 * @author Andrej Kabachnik
 *
 */
class UI5BooleanFormatter extends AbstractUI5BindingFormatter
{
    /**
     * @inerhitDoc 
     * @see AbstractUI5BindingFormatter::setJsFormatter()
     */
    protected function setJsFormatter(JsDataTypeFormatterInterface $jsFormatter)
    {
        if($jsFormatter instanceof JsBooleanFormatter) {
            $jsFormatter->setHtmlChecked('sap-icon://accept');
            $jsFormatter->setHtmlUnchecked('');
        }
        
        return parent::setJsFormatter($jsFormatter);
    }
}