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
        $widget = $this->getWidget();

        // TODO: teach the icon color to use defaultColor if the color binding has no value yet to show in a new dialog the default color to begin with (for now use default color button to get it after click)
        return <<<JS
        
        new sap.ui.core.Icon("{$this->getid()}", {
            src: "sap-icon://color-fill",
            color: {$this->buildJsValue()},
            activeColor: {$this->buildJsValue()},
            hoverColor: {$this->buildJsValue()},
            press: function() {                
                var oColorPopover = new sap.m.ColorPalettePopover({    	
                    colors: {$this->buildJsColorValues()},
                    {$this->buildJSPaletteOptions($widget)},
                    colorSelect: {$this->buildJsColorSelect()},
                    {$this->buildJsProperties()}
                })
                oColorPopover.openBy(this);
            }
    	}).addStyleClass('exf-colorPalette')
    	
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
        if ($ui5ColorBinding->isBoundToModel() === false) {
            $values = [$this->getWidget()->getDefaultColor()];
        } else {
            $values = $this->translateSemanticColors($widget->getColorPresets());
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
        getColor()
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
          {$this->buildJsValidatorCheckRequired('sColor', '')}
          
          // convert function from co-pilot
          function rgbToHex(rgb) {
            var result = rgb.match(/\d+/g);
            if (!result || result.length < 3) return rgb;
            return "#" + result.slice(0, 3).map(function (x) {
              return ("0" + parseInt(x).toString(16)).slice(-2);
            }).join("");
          }

          // we only alow CSS and HEX colors for the input value
          sColor = sColor.startsWith("rgb") ? rgbToHex(sColor) : sColor;          
          var icon = sap.ui.getCore().byId("{$this->getid()}");
          icon.setColor(sColor);
          icon.setHoverColor(sColor);
          icon.setActiveColor(sColor);
          icon.setTooltip(sColor);
          
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

    private function buildJSPaletteOptions(InputColorPalette $widget)
    {
        $options = [
            'defaultColor' => $this->translateSemanticColors([$widget->getDefaultColor()]),
            'showDefaultColorButton' => $widget->getShowDefaultColorButton(),
            'showMoreColorsButton' => $widget->getShowMoreColorsButton(),
            'displayMode' => $widget->getDisplayMode(),
        ];

        $jsOptions = json_encode($options, JSON_PRETTY_PRINT);
        $jsOptions = str_replace(array( '{', '}' ), '', $jsOptions);
        return <<<JS
        {$jsOptions}
JS;
    }

    /**
     * @param array $colorPresets
     * @return array|string
     */
    public function translateSemanticColors(array $colorPresets): array|string
    {
        $semColsJs = $this->getColorSemanticMap();
        $colorPresetsWithSemCols = [];
        foreach ($colorPresets as $color) {
            if (str_starts_with($color, '~')) {
                $colorPresetsWithSemCols[] = $semColsJs[$color];
            } else {
                $colorPresetsWithSemCols[] = $color;
            }
        }

        if (count($colorPresetsWithSemCols) == 1) {
            return $colorPresetsWithSemCols[0];
        }

        return $colorPresetsWithSemCols;
    }

    protected function buildJsValidatorCheckRequired(string $valueJs, string $onFailJs): string
    {
        if (($this->getWidget()->isRequired() === true || $this->getWidget()->getRequiredIf())) {
            return <<<JS
            var oCtrl = sap.ui.getCore().byId('{$this->getId()}');
            if ({$valueJs} === undefined || {$valueJs} === null || {$valueJs} === '') { 
                oCtrl.addStyleClass('exf-colorPalette-Error');
            } else {
                oCtrl.removeStyleClass('exf-colorPalette-Error');
            }
JS;
        }
        return '';
    }
}