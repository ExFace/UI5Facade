<?php
namespace exface\UI5Facade\Facades\Formatters;

use exface\UI5Facade\Facades\Interfaces\UI5BindingFormatterInterface;
use exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsDataTypeFormatterInterface;
use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;

abstract class AbstractUI5BindingFormatter implements UI5BindingFormatterInterface
{
    private $jsFormatter = null;
    
    public function __construct(JsDataTypeFormatterInterface $jsFormatter)
    {
        $this->setJsFormatter($jsFormatter);
    }
    
    /**
     * 
     * @param JsDataTypeFormatterInterface $jsFormatter
     * @return \exface\UI5Facade\Facades\Formatters\UI5DateFormatter
     */
    protected function setJsFormatter(JsDataTypeFormatterInterface $jsFormatter)
    {
        $this->jsFormatter = $jsFormatter;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Interfaces\UI5BindingFormatterInterface::getJsFormatter()
     */
    public function getJsFormatter()
    {
        return $this->jsFormatter;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Formatters\JsDateFormatter::getDataType()
     */
    public function getDataType()
    {
        return $this->getJsFormatter()->getDataType();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Interfaces\UI5BindingFormatterInterface::registerExternalModules()
     */
    public function registerExternalModules(UI5ControllerInterface $controller) : UI5BindingFormatterInterface
    {
        return $this;
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Interfaces\UI5BindingFormatterInterface::buildJsBindingProperties()
     */
    public function buildJsBindingProperties()
    {
        return <<<JS
                formatter: function(value) {
                    return {$this->getJsFormatter()->buildJsFormatter('value')}
                },
JS;
    }
}