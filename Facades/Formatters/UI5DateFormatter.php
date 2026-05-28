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
        // UI5-Upgrade - using custom data types with string declarations is no longer supported/throws warnings
        // so here we need to use the proper constructor now
        $props = <<<JS
                type: new {$this->getSapDataType()}({
                    dateFormat: {$dateFormatEscaped},
                    emptyText: {$this->getJsFormatter()->getJsEmptyText('""')}
                }),

JS;
        return $props;
    }
    
    protected function getSapDataType()
    {
        return 'exface.ui5Custom.dataTypes.MomentDateType';
    }
}