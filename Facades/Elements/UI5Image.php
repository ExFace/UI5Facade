<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Widgets\Image;

/**
 * Generates sap.m.Image
 * 
 * @method Image getWidget()
 *
 * @author Andrej Kabachnik
 *        
 */
class UI5Image extends UI5Display
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsConstructor()
     */
    public function buildJsConstructorForMainControl($oControllerJs = 'oController')
    {      
        $this->registerExternalModules($this->getController());
        $alignment = $this->getWidget()->getAlign();
        $classes = '';
        switch ($alignment) {
            case EXF_ALIGN_DEFAULT: 
                break;
            case EXF_ALIGN_CENTER:
                $classes .= ' pull-center';
                break;
            case EXF_ALIGN_RIGHT:
                $classes .= ' pull-right';
                break;
        }
        $addStyleClass = $classes !== '' ? '.addStyleClass("' . $classes . '")' : '';
        return <<<JS

        new sap.m.Image("{$this->getid()}", {
    		densityAware: false,
            src: {$this->buildJsValue()},
            {$this->buildJsProperties()}
    	}){$addStyleClass}

JS;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Value::buildJsValue()
     */
    public function buildJsValue()
    {
        // Make sure NOT to yield live ref formulas as values as this would cause an attempt to load
        // a non-existant URI.
        if (! $this->isValueBoundToModel() && $this->getWidget()->hasValue() && $this->getWidget()->getValueExpression()->isReference()) {
            return '""';
        }
        return parent::buildJsValue();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Display::buildJsValueBindingOptions()
     */
    public function buildJsValueBindingOptions()
    {
        $base = $this->getWidget()->getBaseUrl();
        if ($this->getWidget()->getUseProxy()) {
            $proxyFormatter = <<<JS

            var proxyUrl = "{$this->getWidget()->buildProxyUrl('xxurixx')}";
            sUrl = proxyUrl.replace("xxurixx", url);

JS;
        }
        
            return <<<JS

        formatter: function(value) {
            var sBase = encodeURI('{$base}');
            var sUrl = value;
            if (sUrl) {
                sUrl = sBase + encodeURI(sUrl);
            }
            {$proxyFormatter}

            return (sUrl || '');
        },

JS;
        
            
        return parent::buildJsValueBindingOptions();
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
        return 'src';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Display::buildJsPropertyAlignment()
     */
    protected function buildJsPropertyAlignment() : string
    {
        return '';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Display::buildJsPropertyWrapping()
     */
    protected function buildJsPropertyWrapping()
    {
        return '';
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsValueSetter()
     */
    public function buildJsValueSetterMethod($valueJs)
    {
        if ($base = $this->getWidget()->getBaseUrl()) {
            $valueJs = "({$valueJs} ? '{$base}' : '') + ({$valueJs} || '')";
        }
        return "setSrc({$valueJs})";
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Value::buildJsPropertyWidth()
     */
    protected function buildJsPropertyWidth()
    {
        if ($this->getWidget()->getWidth()->isUndefined() && ! $this->getWidget()->getHeight()->isUndefined()) {
            return 'width: "auto",';
        }
        return parent::buildJsPropertyWidth();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Value::buildJsPropertyHeight()
     */
    protected function buildJsPropertyHeight()
    {
        if ($this->getWidget()->getHeight()->isUndefined() && ! $this->getWidget()->getWidth()->isUndefined()) {
            return 'height: "auto",';
        }
        return parent::buildJsPropertyHeight();
    }
}