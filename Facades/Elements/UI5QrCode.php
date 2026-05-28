<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Widgets\Html;
use exface\Core\Widgets\Image;
use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;

/**
 * Generates com.penninkhof.controls.QRCode for a QrCode widget
 * 
 * @method QrCode getWidget()
 *
 * @author Andrej Kabachnik
 *        
 */
class UI5QrCode extends UI5Display
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsConstructor()
     */
    public function buildJsConstructorForMainControl($oControllerJs = 'oController')
    {        
        $this->registerExternalModules($this->getController());
        return <<<JS

        new com.penninkhof.controls.QRCode("{$this->getid()}", {
            code: {$this->buildJsValue()},
            {$this->buildJsPropertyHeight()}
            {$this->buildJsPropertyWidth()}
    	})

JS;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::registerExternalModules()
     */
    public function registerExternalModules(UI5ControllerInterface $controller) : UI5AbstractElement
    {
        $controller->addExternalModule('com.penninkhof.controls.QRCode', $this->getFacade()->buildUrlToSource('LIBS.QRCODE.JS') . 'QRCode');
        $controller->addExternalModule('com.penninkhof.controls.3rdparty.qrcode', $this->getFacade()->buildUrlToSource('LIBS.QRCODE.JS') . '3rdparty/qrcode');
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Value::buildCssHeightDefaultValue()
     */
    protected function buildCssHeightDefaultValue()
    {
        return '';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Value::buildCssWidthDefaultValue()
     */
    protected function buildCssWidthDefaultValue() : string
    {
        return '';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Value::buildJsValueBindingPropertyName()
     */
    public function buildJsValueBindingPropertyName() : string
    {
        return 'code';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Display::buildJsValueSetterMethod()
     */
    public function buildJsValueSetterMethod($valueJs)
    {
        return "setCode({$valueJs})";
    }

    /**
     * {@inheritDoc}
     * @see UI5Display::getShowValueInTooltip()
     */
    protected function getShowValueInTooltip() : bool
    {
        return false;
    }
}