<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\CommonLogic\Constants\Icons;
use exface\UI5Facade\Facades\Elements\Traits\UI5ColorClassesTrait;
use exface\Core\Interfaces\Widgets\iHaveIcon;

/**
 * Generates sap.m.Text controls for Text widgets
 * 
 * @method \exface\Core\Widgets\Icon getWidget()
 *
 * @author Andrej Kabachnik
 *        
 */
class UI5Icon extends UI5Display
{
    use UI5ColorClassesTrait;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Display::buildJsConstructor()
     */
    public function buildJsConstructorForMainControl($oControllerJs = 'oController') : string
    {
        $this->registerExternalModules($this->getController());
        $icon = $this->buildJsConstructorForIcon();
        
        if ($this->getWidget()->hasValueWidget() === true) {
            $valueElement = $this->getFacade()->getElement($this->getWidget()->getValueWidget());
            switch ($this->getWidget()->getIconPosition()) {
                case EXF_ALIGN_RIGHT: $js = <<<JS
                
            new sap.m.HBox({
                justifyContent: "SpaceAround",
                alignItems: "Center",
                items: [
                    {$valueElement->buildJsConstructorForMainControl($oControllerJs)},
                    {$icon}
                    
                ]
            })
            
JS;
                case EXF_ALIGN_CENTER: $js = <<<JS
                
            new sap.m.VBox({
                width: "100%",
                alignItems: "Center",
                items: [
                    {$icon}.addStyleClass("sapUiSmallMargin"),
                    {$valueElement->buildJsConstructorForMainControl($oControllerJs)}
                ]
            })
            
JS;
                default: $js = <<<JS
    
            new sap.m.HBox({
                justifyContent: "SpaceAround",
                alignItems: "Center",
                items: [
                    {$icon},
                    {$valueElement->buildJsConstructorForMainControl($oControllerJs)}
                ]
            })
    
JS;
            }
        } else {
            $js = $icon;
        }
        return $js;
    }
    
    /**
     * 
     * @return string
     */
    public function buildJsConstructorForIcon() : string
    {
        $widget = $this->getWidget();
        
        $iconSrc = $this->buildJsPropertyValue();
        if ($iconSrc === '') {
            return '';
        }
        
        switch (strtolower($widget->getIconSize() ?? '')) {
            case EXF_TEXT_SIZE_BIG: $size = 'size: "36px",'; break;
            case EXF_TEXT_SIZE_SMALL: $size = 'size: "12px",'; break;
            case EXF_TEXT_SIZE_NORMAL: 
            default: $size = '';
        }
        
        return <<<JS

            new sap.ui.core.Icon ({
                {$iconSrc}
                {$size}
                {$this->buildJsPropertyWidth()}
                {$this->buildJsPropertyHeight()}
            })
            .addStyleClass('{$this->buildCssElementClass()}')
            {$this->buildJsPseudoEventHandlers()}

JS;
    }
    
    /**
    * Returns the value property with property name and value followed by a comma.
    *
    * @return string
    */
    protected function buildJsPropertyValue()
    {
        $src = $this->buildJsValue();
        
        if ($src === '') {
            return '';
        }
        return <<<JS
            src: {$src},
JS;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Display::buildJsValueBindingOptions()
     */
    public function buildJsValueBindingOptions()
    {
        $widget = $this->getWidget();
        // SVG icons as data-URLs are not supported here in contrast to sap.m.Button. Instead
        // we apply the same trick as with custom colors: use an injected CSS class set by a
        // value formatter. The CSS class will override the icon.
        if ($widget->hasIconScale()) {
            if (Icons::isIconSetSVG($widget->getIconSet())) {
                $bSvgJs = 'true';
                // Add the widget id to the CSS class in case there are multiple widgets
                // with same values (like 1 and 0) but different icons
                $svgClassPrefix = "exf-svg-" . str_replace($this->cssClassNameRemoveChars, '', $widget->getId()) . "-";
                $iconScaleEncoded = [];
                $iconScaleVals = [];
                foreach ($widget->getIconScale() as $val => $icon) {
                    $iconScaleEncoded[$val] = rawurlencode($icon);
                    $iconScaleVals[$val] = str_replace($this->cssClassNameRemoveChars, '', trim($val));
                }
                $valueJs = $this->buildJsScaleResolver('value', $iconScaleVals, $widget->isIconScaleRangeBased());
                $this->registerColorClasses($iconScaleEncoded, ".sapUiIcon.exf-svg-icon.{$svgClassPrefix}[#value#]::before", 'content: url("data:image/svg+xml,[#color#]")');
            } else {
                $valueJs = $this->buildJsScaleResolver('value', $widget->getIconScale(), $widget->isIconScaleRangeBased());
            }
        } else {
            $valueJs = 'value';
            if (Icons::isIconSetSVG($widget->getIconSet())) {
                $this->registerColorClasses(['icon'], "#{$this->getId()}.exf-svg-icon:before", 'content: url("data:image/svg+xml,' . rawurlencode($this->getWidget()->getIcon()) . '")');
            }
        }
        $bSvgJs = $bSvgJs ?? 'false';
        return parent::buildJsValueBindingOptions() . <<<JS
        
                formatter: function(value){
                    var oCtrl = this;
                    var sIcon = {$valueJs};
                    var bSvg = {$bSvgJs};
                    switch (true) {
                        case sIcon === undefined:
                        case sIcon === null:
                        case sIcon === '':
                            return null;
                        case bSvg:
                            {$this->buildJsColorClassSetter('oCtrl', 'sIcon', 'exf-svg-icon', $svgClassPrefix)};
                            return 'sap-icon://circle-task';
                        case ! sIcon.toString().startsWith('sap-icon://'):
                            return 'sap-icon://font-awesome/' + sIcon;
                    }
                    return sIcon;
                },
JS;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildCssElementClass()
     */
    public function buildCssElementClass()
    {
        $cls = parent::buildCssElementClass();
        $iconSet = $this->getWidget()->getIconSet();
        
        if ($iconSet === iHaveIcon::ICON_SET_SVG_COLORED) {
            $cls .= ' exf-svg-icon exf-svg-colored';
        } else if ($iconSet === iHaveIcon::ICON_SET_SVG) {
            $cls .= ' exf-svg-icon';
        }
        return $cls;
    }
    
    /**
     * Returns inline javascript code for the value of the value property (without the property name).
     *
     * Possible results are a quoted JS string, a binding expression or a binding object.
     *
     * @return string
     */
    public function buildJsValue()
    {
        if ($staticIcon = $this->getWidget()->getIcon()) {
            return '"' . $this->getIconSrc($staticIcon) . '"';
        }
        return parent::buildJsValue();
    }
   
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Value::buildJsLabelWrapper()
     */
    protected function buildJsLabelWrapper($element_constructor)
    {
        if ($this->getCaption() === '') {
            $widget = $this->getWidget();
            if ($widget->hasValueWidget() === true) {
                $widget->setCaption($widget->getValueWidget()->getCaption());
            } else {
                return $element_constructor;
            }
        }
        return parent::buildJsLabelWrapper($element_constructor);
    }
}