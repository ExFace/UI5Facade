<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\CommonLogic\Constants\Colors;
use exface\Core\DataTypes\ColorDataType;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;
use exface\Core\Widgets\InputColorPalette;
use exface\UI5Facade\Facades\UI5PropertyBinding;

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
    private string $colorModelPath = 'color';
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsConstructor()
     */
    public function buildJsConstructorForMainControl($oControllerJs = 'oController')
    {
        $this->registerExternalModules($this->getController());
        $widget = $this->getWidget();
        
        // if the widget is not bound to a model we need our own model binding, so we can use it so synchronize the values of the input and the icon colors/tooltip.
        $modelBindingJs = $this->isValueBoundToModel() ? "" : <<<JS
                .setModel(new sap.ui.model.json.JSONModel({
                        {$this->colorModelPath}: null
                    }))
JS;

        // TODO: teach the icon color to use defaultColor if the color binding has no value yet to show in a new dialog the default color to begin with (for now use default color button to get it after click) [only do this if model is already bound, otherwise a filter will filter the default color when loading the page!]
        /*
         * We use HBox as the UI5 flex element to be able to combine an Input field that allows text as well as a button with the color palette for color selection
         * [__________][▦]
         * 
         * There is one binding for color within all elements that need to react to it. It is bound to Input value and all Icon colors and it's tooltip.
         * The value of the binding is changed either directly when the input value changes by user input or on the colorSelect.
         */
        return <<<JS
        new sap.m.HBox({
            items: [ 
                new sap.m.Input("{$this->getId()}", {
                    value: {$this->buildJsValue()},
                    {$this->buildJsProperties()}
                    {$this->buildJsPropertyType()}
                    {$this->buildJsPropertyChange()}
                    {$this->buildJsPropertyRequired()}
                    layoutData: new sap.m.FlexItemData({
                        growFactor: 1
                    })
                }),
                new sap.m.Button("{$this->getId()}_Button", {
                    icon: "sap-icon://color-fill",
                    press: function() {                
                            var oColorPopover = new sap.m.ColorPalettePopover({    	
                                colors: {$this->buildJsColorValues()},
                                {$this->buildJSPaletteOptions($widget)},
                                colorSelect: {$this->buildJsColorSelect()},
                                {$this->buildJsProperties()}
                            })
                            {$this->buildJsColorTooltips('oColorPopover')}
                            oColorPopover.openBy(this);
                        },
                    {$this->buildJsProperties()}
                    layoutData: new sap.m.FlexItemData({
                        alignSelf: "Center"
                    })
                }).addEventDelegate({ onAfterRendering: function () {
                    var icon = sap.ui.core.Element.getElementById("{$this->getId()}_Button-img");
                    icon.bindProperty("color", "{$this->getValueBindingPath()}");
                    icon.bindProperty("activeColor", "{$this->getValueBindingPath()}");
                    icon.bindProperty("hoverColor", "{$this->getValueBindingPath()}");
                    icon.bindProperty("tooltip", "{$this->getValueBindingPath()}");
                }})
            ],
            {$this->buildJsPropertyWidth()}
            {$this->buildJsPropertyHeight()}
        }){$modelBindingJs}

JS;
    }

    /**
     * @return string
     * @see UI5Value::buildJsValue()
     */
    public function buildJsValue()
    {
        // use internal binding if there is no binding on the model itself
        if ($this->isValueBoundToModel() === false) {
            return '"{/' . $this->colorModelPath . '}"';
        }
        return parent::buildJsValue(); 
    }

    /**
     * @return string
     * @see UI5Value::getValueBindingPath()
     */
    public function getValueBindingPath(): string
    {
        // use internal binding if there is no binding on the model itself
        if ($this->isValueBoundToModel() === false) {
            return '/' . $this->colorModelPath;
        }
        return parent::getValueBindingPath(); 
    }

    /**
     * Resolves the widget colors into the necessary CSS colors for the color palette.
     */
    protected function buildJsColorValues() : string
    {
        // UI5 requires an array even when the palette contains just one color.
        return json_encode((array) $this->translateSemanticColors($this->getPaletteColors()));
    }

    /**
     * Returns the original presets in the order used by the rendered palette.
     *
     * @return string[]
     */
    protected function getPaletteColors() : array
    {
        $widget = $this->getWidget();
        $widgetColorBinding = $widget->getColorBinding();
        $ui5ColorBinding = new UI5PropertyBinding($this, 'state', $widgetColorBinding);
        if ($ui5ColorBinding->isBoundToModel() === false) {
            return [$widget->getDefaultColor()];
        }
        return $widget->getColorPresets();
    }

    /**
     * Uses configured hints as tooltips, falling back to the color value and scale key.
     *
     * @param string[] $colors
     * @param string[] $hints
     * @return string[]
     */
    protected function formatColorTooltips(array $colors, array $hints) : array
    {
        $cssColors = (array) $this->translateSemanticColors($colors);
        $isScale = $colors !== array_values($colors);
        $tooltips = [];
        foreach ($colors as $key => $color) {
            $cssColor = $cssColors[count($tooltips)];
            $value = $isScale ? (string) $key : $color;
            $hint = $hints[$value] ?? $hints[$color] ?? $hints[$cssColor] ?? null;
            $tooltips[] = $hint !== null && $hint !== ''
                ? $hint : $cssColor . ($value !== $cssColor ? ' (' . $value . ')' : '');
        }
        return $tooltips;
    }

    /**
     * Replaces UI5's generic "Color n: Custom" labels after every palette rendering.
     *
     * @param string $popoverJs
     * @return string
     */
    protected function buildJsColorTooltips(string $popoverJs) : string
    {
        $widget = $this->getWidget();
        $dataType = $widget->getColorBinding()->getDataType();
        $hints = [];
        if ($dataType instanceof ColorDataType) {
            $hints = $dataType->getColorHints();
        } elseif ($dataType instanceof EnumDataTypeInterface) {
            $hints = $dataType->getValueHints();
        }
        $hints = array_replace($hints, $widget->getHintScale()->getScaleValues());
        $tooltipsJs = json_encode($this->formatColorTooltips($this->getPaletteColors(), $hints));

        return <<<JS
                            // ColorPalettePopover has no public per-swatch tooltip API.
                            // Its internal palette uses the wrapper ID with the "-palette" suffix.
                            var oPalette = sap.ui.core.Element.getElementById({$popoverJs}.getId() + "-palette");
                            if (oPalette) {
                                var aColorTooltips = {$tooltipsJs};
                                oPalette.addEventDelegate({onAfterRendering: function () {
                                    var oDom = oPalette.getDomRef();
                                    if (!oDom) {
                                        return;
                                    }
                                    var aColors = oPalette.getColors();
                                    // UI5 renders preset swatches first, then recent colors.
                                    oDom.querySelectorAll('[data-sap-ui-color]').forEach(function (oSwatch, iIndex) {
                                        var sColor = oSwatch.getAttribute("data-sap-ui-color");
                                        if (!sColor) {
                                            return;
                                        }
                                        var iPreset = iIndex < aColors.length ? iIndex : aColors.indexOf(sColor);
                                        // A recent color cannot identify a preset when multiple values share its color.
                                        if (iIndex >= aColors.length && iPreset !== aColors.lastIndexOf(sColor)) {
                                            iPreset = -1;
                                        }
                                        var sTooltip = aColorTooltips[iPreset] || sColor;
                                        oSwatch.setAttribute("title", sTooltip);
                                        oSwatch.setAttribute("aria-label", sTooltip);
                                    });
                                }});
                            }
JS;
    }

    /**
     * Returns the equivalent hex code for each semantic color.
     * 
     * @return array|string[]
     * @doc https://www.sap.com/design-system/fiori-design-web/v1-84/foundations/visual/colors/quartz-light-colors?external#semantic-colors
     */
    protected function getColorSemanticMap() : array
    {
        return [
            Colors::SEMANTIC_ERROR => '#bb0000', // sapThemeNegativeText
            Colors::SEMANTIC_WARNING => '#e9730c', // sapThemeCriticalText
            Colors::SEMANTIC_OK => '#107e3e', // sapThemePositiveText
            Colors::SEMANTIC_INFO => '#0a6ed1' // sapThemeInformation
        ];
    }

    /**
     * Event on color select that updates the icon color to the selected color and thus the binding value.
     *
     * @return string
     */
    private function buildJsColorSelect()
    {
        return <<<JS
		function (oEvent) {
          var sColor = oEvent.getParameter("value");
          
          // a convert function from co-pilot to change rgb colors into hex
          function rgbToHex(rgb) {
            var result = rgb.match(/\d+/g);
            if (!result || result.length < 3) return rgb;
            return "#" + result.slice(0, 3).map(function (x) {
              return ("0" + parseInt(x).toString(16)).slice(-2);
            }).join("");
          }

          // only alow CSS and HEX colors for the input value
          sColor = sColor.startsWith("rgb") ? rgbToHex(sColor) : sColor;          
          var icon = sap.ui.core.Element.getElementById("{$this->getId()}_Button-img");
          icon.setColor(sColor);
        }
JS;
    }

    /**
     * Returns a JS for ColorPalettePopup properties.
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsProperties()
     */
    public function buildJsProperties() : string
    {
        return <<<JS
            {$this->buildJsPropertyTooltip()}
            {$this->buildJsPropertyLayoutData()}
            {$this->buildJsPropertyHeight()}
            {$this->buildJsPropertyDisabled()}
JS;
    }

    /**
     * Returns a JS with custom Palette properties.
     * 
     * @param InputColorPalette $widget
     * @return string
     */
    private function buildJSPaletteOptions(InputColorPalette $widget) : string
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
     * Returns the associated html colors for each semantic color within the array.
     * Invalid entries are reported instead of silently replacing a requested color.
     * 
     * @param array $colorPresets
     * @return array|string
     * @throws \UnexpectedValueException
     */
    public function translateSemanticColors(array $colorPresets): array|string
    {
        $semColsJs = $this->getColorSemanticMap();
        $colorPresetsWithSemCols = [];
        foreach ($colorPresets as $key => $color) {
            if (! is_string($color) || trim($color) === '') {
                throw new \UnexpectedValueException('Invalid color preset at key ' . var_export($key, true) . ': ' . var_export($color, true));
            }
            if (str_starts_with($color, '~')) {
                if (! isset($semColsJs[$color])) {
                    throw new \UnexpectedValueException('Unknown semantic color preset: ' . $color);
                }
                $color = $semColsJs[$color];
            }
            $colorPresetsWithSemCols[] = $color;
        }

        if (count($colorPresetsWithSemCols) == 1) {
            return $colorPresetsWithSemCols[0];
        }

        return $colorPresetsWithSemCols;
    }
}