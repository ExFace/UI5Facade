<?php
namespace exface\UI5Facade\Facades\Formatters;

use exface\Core\Facades\AbstractAjaxFacade\Formatters\JsDateFormatter;

/**
 * 
 * @method JsDateFormatter getJsFormatter()
 * 
 * @author Andrej Kabachnik
 *
 */
class UI5DateFormatter extends AbstractUI5BindingFormatter
{    
    use UI5MomentFormatterTrait;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Interfaces\UI5BindingFormatterInterface::buildJsBindingProperties()
     */
    public function buildJsBindingProperties()
    {
        $dateFormatEscaped = json_encode($this->getJsFormatter()->getFormat());
        $props = <<<JS
                type: '{$this->getSapDataType()}',
                formatOptions: {
                    dateFormat: {$dateFormatEscaped},
                    emptyText: {$this->getJsFormatter()->getJsEmptyText('""')}
                },

JS;
        return $props;
    }
    
    protected function getSapDataType()
    {
        return 'exface.ui5Custom.dataTypes.MomentDateType';
    }
}