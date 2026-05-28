<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;
use exface\Core\DataTypes\BinaryDataType;

/**
 * Generates custom PDFViewer using the popular PDF.js library
 * 
 * @method \exface\Core\Widgets\PDFViewer getWidget()
 *
 * @author Andrej Kabachnik
 *        
 */
class UI5PDFViewer extends UI5Value
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

        new exface.ui5Custom.PdfViewer("{$this->getid()}", {
            pdfError: function(oEvent) {
                {$this->buildJsShowMessageError('oEvent.getParameters().message')};
            },
            {$this->buildJsPropertyValue()}
            {$this->buildJsPropertyDownloadEnabled()}
            {$this->buildJsPropertyWidth()}
            {$this->buildJsPropertyHeight()}
            {$this->buildJsProperties()}
    	})

JS;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Value::buildJsPropertyValue()
     */
    protected function buildJsPropertyValue()
    {
        $widget = $this->getWidget();
        $valueType = $widget->getValueType();
        switch ($valueType) {
            case BinaryDataType::ENCODING_HEX:
            case BinaryDataType::ENCODING_BASE64:
            case BinaryDataType::ENCODING_BINARY:
                $pdfSourceType = 'pdfSourceType: "' . ucfirst($valueType) . '",';
                break;
            default:
                $pdfSourceType = '';
        }
        
        if ($widget->isFilenameBoundToAttribute()) {
            $filenameAttr = $widget->getFilenameAttribute();
            $pdfName = <<<JS
            pdfName: {
                path: "{$this->getFilenameBindingPath()}",
                {$this->getFacade()->getDataTypeFormatterForUI5Bindings($filenameAttr->getDataType())->buildJsBindingProperties()}
            },

JS;
        } else {
            $caption = parent::getCaption();
            if ($caption) {
                $pdfName = 'pdfName: "' . $this->escapeJsTextValue($caption) . '",';
            } else {
                $pdfName = '';
            }
        }
        
        return <<<JS

            pdfSource: {$this->buildJsValue()},
            {$pdfName}
            {$pdfSourceType}
JS;
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsPropertyDownloadEnabled() : string
    {
        if ($this->getWidget()->getDownloadEnabled() === false) {
            return 'downloadEnabled: false,';
        }
        return '';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::registerExternalModules()
     */
    public function registerExternalModules(UI5ControllerInterface $controller) : UI5AbstractElement
    {
        $controller->addExternalModule('libs.exface.ui5Custom.PdfViewer', 'vendor/exface/ui5facade/Facades/js/ui5Custom/PDFViewer');
        $controller->addExternalModule('libs.exface.ui5Custom.libs.pdf', 'vendor/exface/ui5facade/Facades/js/ui5Custom/libs/pdf');
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Display::buildJsConstructor()
     */
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {
        return $this->buildJsLabelWrapper($this->buildJsConstructorForMainControl($oControllerJs));
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
        return 'pdfSource';
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Interfaces\UI5ValueBindingInterface::getValueBindingPath()
     */
    public function getFilenameBindingPath() : string
    {
        if ($this->textBindingPath === null) {
            $widget = $this->getWidget();
            $model = $this->getView()->getModel();
            if ($model->hasBinding($widget, 'text')) {
                return $model->getBindingPath($widget, 'text');
            }
            return $this->getValueBindingPrefix() . $this->getWidget()->getFilenameDataColumnName();
        }
        return $this->textBindingPath;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Value::getRenderCaptionAsLabel()
     */
    protected function getRenderCaptionAsLabel(bool $default = false) : bool
    {
        return $this->renderCaptionAsLabel ?? $default;
    }
}