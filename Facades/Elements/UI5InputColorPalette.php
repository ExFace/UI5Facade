<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Widgets\ColorIndicator;
use exface\Core\CommonLogic\Constants\Colors;
use exface\Core\Widgets\ColorPalette;
use exface\Core\Widgets\InputColorPalette;
use exface\UI5FAcade\Facades\UI5PropertyBinding;

/**
 * Renders a InputColorPalette widget as sap.ui.core.Button with a color select pop-up.
 * 
 * @method InputColorPalette getWidget()
 *        
 * @author Miriam Seitz
 *        
 */
class UI5InputColorPalette extends UI5Input
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
        
        new sap.ui.core.Icon("{$this->getid()}", {
            src: "sap-icon://color-fill",
            color: {$this->buildJsValue()},
            press: function() {                
                var oColorPopover = new sap.m.ColorPalettePopover({    	
                    colors: {$this->buildJsColorValues()},
                    defaultColor: 'transparent',
                    showDefaultColorButton: false,
                    showMoreColorsButton: true,
                    displayMode: 'Simplified',
                    colorSelect: {$this->buildJsColorSelect()},
                    {$this->buildJsProperties()}
                })
                oColorPopover.openBy(this);
            }
    	})
    	
JS;
    }
    
    /**
     * Resolves the widget colors into the necessary CSS colors for the color palette.
     */
    protected function buildJsColorValues() : string
    {
        $widget = $this->getWidget();
        $widgetColorBinding = $widget->getColorBinding();
        $ui5ColorBinding = new UI5PropertyBinding($this, 'state', $widgetColorBinding);
        $values = [];
        if (!$ui5ColorBinding->isBoundToModel()) {
            $values = $this->buildJsColorValueNoColor(); // TODO
        } else {
            $semColsJs = $this->getColorSemanticMap();
            $colorScaleWithSemCols = [];
            foreach ($widget->getColorScale() as $color) {
                if (str_starts_with($color, '~')) {
                    $colorScaleWithSemCols[] = $semColsJs[$color];
                } else {
                    $colorScaleWithSemCols[] = $color;
                }
            }
            $values = $colorScaleWithSemCols;
        }
        return json_encode($values);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsValueGetterMethod()
     */
    public function buildJsValueGetterMethod()
    {
        return <<<JS
        getTooltip()
JS;
    }

    /**
     * @return array|string[]
     * @doc https://www.sap.com/design-system/fiori-design-web/v1-84/foundations/visual/colors/quartz-light-colors?external#semantic-colors
     */
    protected function getColorSemanticMap() : array
    {
        $semCols = [];
        foreach (Colors::getSemanticColors() as $semCol) {
            switch ($semCol) {
                case Colors::SEMANTIC_ERROR: $cssColor = '#bb0000'; break; # sapThemeNegativeText
                case Colors::SEMANTIC_WARNING: $cssColor = '#e9730c'; break; # sapThemeCriticalText
                case Colors::SEMANTIC_OK: $cssColor = '#107e3e'; break; # sapThemePositiveText
                case Colors::SEMANTIC_INFO: $cssColor = '#0a6ed1'; break; # sapThemeInformation
            }
            $semCols[$semCol] = $cssColor;
        }

        return $semCols;
    }

    /**
     * Event on color select that updates the icon color to the selected color.
     *
     * @return string
     */
    private function buildJsColorSelect()
    {
        return <<<JS
		function (oEvent) {
          var sColor = oEvent.getParameter("value");
          var icon = sap.ui.getCore().byId("{$this->getid()}");
          icon.setColor(sColor);
          icon.setHoverColor(sColor);
          icon.setActiveColor(sColor);
          
          // convert function from co-pilot
          function rgbToHex(rgb) {
            var result = rgb.match(/\d+/g);
            if (!result || result.length < 3) return rgb;
            return "#" + result.slice(0, 3).map(function (x) {
              return ("0" + parseInt(x).toString(16)).slice(-2);
            }).join("");
          }

          icon.setTooltip(sColor.startsWith("rgb") ? rgbToHex(sColor) : sColor);
        }
JS;
    }

    /**
     * ColorPalettePopup has less options to modify
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsProperties()
     */
    public function buildJsProperties()
    {
        $options = <<<JS
            {$this->buildJsPropertyTooltip()}
            {$this->buildJsPropertyLayoutData()}
            {$this->buildJsPropertyHeight()}
            {$this->buildJsPropertyDisabled()}
JS;
        return $options;
    }
}