<?php
namespace exface\UI5Facade\Facades\Elements\Traits;

use exface\Core\DataTypes\StringDataType;

/**
 * This trait helps add CSS classes for color scales. 
 * 
 * The only way to give most UI5 controls a custom color seems to be giving it a
 * CSS class. 
 * 
 * To use this trait add custom CSS classes to the page via `registerColorClasses()` 
 * and use them by calling `buildJsColorClassSetter()`. See UI5ObjectStatus and
 * UI5ProgressBar for examples.
 * 
 * @author Andrej Kabachnik
 * 
 * @method iHaveContextualHelp getWidget()
 *
 */
trait UI5ColorClassesTrait {
    
    /**
     * Characters to be removed from values and colors if they are to be used in CSS selectors
     * 
     * @var string[]
     */
    private $cssClassNameRemoveChars = ['#', '.', '+'];
    
    /**
     * Makes the controller run a script to add custom CSS styles every time the view is shown.
     *
     * @param array  $colorScale
     * @param string $cssSelectorToColor
     * @param string $cssColorProperties
     * @param bool   $skipSemanticColors
     * @return void
     */
    protected function registerColorClasses(
        array $colorScale, 
        string $cssSelectorToColor = '.exf-custom-color.exf-color-[#color#]', 
        string $cssColorProperties = 'background-color: [#color#]', 
        bool $skipSemanticColors = true
    ) : void
    {
        $css = '';
        foreach ($colorScale as $value => $color) {
            if (substr($color, 0, 1) === '~') {
                if ($skipSemanticColors === true) {
                    continue;
                } else {
                    $color = $this->getFacade()->getSemanticColors()[$color];
                }
            }
            
            $css .= $this->colorToCss($color, $value, $cssSelectorToColor, $cssColorProperties);
        }
        
        $this->registerCustomCss($css, '_color_css');
    }

    /**
     * Converts a color into a CSS class to display said color.
     * 
     * @param string $color
     * @param string $value
     * @param string $selector
     * @param string $properties
     * @return string
     */
    protected function colorToCss(string $color, string $value, string $selector, string $properties) : string
    {
        return $this->buildCssClasses(
            ['color' => $color, 'value' => $value],
            [ $selector => $properties ]
        );
    }

    /**
     * Builds CSS classes with the data provided.
     * 
     * Example:
     * 
     * $cssTemplate = $this->buildCssClasses(
     *       ['content' => '[#content#]', 'id' => '[#id#]'], 
     *       [ 
     *           '.exf-icon-' . '[#id#]' . '.exf-svg-icon:before' => 'content: url("data:image/svg+xml, ' . '[#content#]' . '")'
     *       ],
     *       true
     *   );
     * 
     * Returns:
     * '.exf-icon-[#id#].exf-svg-icon:before { content: url("data:image/svg+xml, [#content#]") }'
     * 
     * 
     *
     * @param array $placeholderValues values to be replaced with actual values at runtime
     * @param array $cssWithPlaceholders css template with inserted placeholders
     * @param bool $keepPlaceholders whether placeholders should be replaced or kep as is
     * @return string
     */
    protected function buildCssClasses(
        array $placeholderValues,
        array $cssWithPlaceholders = [
            '.exf-custom-color.exf-color-[#color#]' => 'background-color: [#color#]'
        ],
        bool $keepPlaceholders = false
        ) : string
    {
        $phsClassName = array_map(
            function ($value) {
                return str_replace($this->cssClassNameRemoveChars, '', trim($value));
            },
            $placeholderValues
        );
        
        $class = '';
        
        foreach ($cssWithPlaceholders as $selector => $properties) {
            if ($keepPlaceholders === false){
                $selector = StringDataType::replacePlaceholders($selector, $phsClassName);
                $properties = StringDataType::replacePlaceholders($properties, $placeholderValues);
            }
            $class .= "{$selector} { {$properties} }";
        }

        return $class;
    }
    
    /**
     * Applies the CSS class corresponding to given color via Control.addStyleClass()
     * 
     * Note, that if the control is used inside a sap.ui.table.Table, the DOM
     * element might not be there yet, when the color setter is called. In this case,
     * an onAfterRendering-handler is registered to add the CSS class and removed right
     * after this. The trouble with sap.ui.table.Table is that it instantiates its
     * cells at some obscure moment and reuses them when scrolling, so we need to
     * be prepared for different situations here.
     * 
     * @param string $oControlJs
     * @param string $sColorJs
     * @param string $cssCustomCssClass
     * @param string $cssColorClassPrefix
     * @return string
     */
    protected function buildJsColorClassSetter(string $oControlJs, string $sColorJs, string $cssCustomCssClass = 'exf-custom-color', $cssColorClassPrefix = 'exf-color-') : string
    {
        $cssReplaceJSON = json_encode($this->cssClassNameRemoveChars);
        $cssInjector = $this->getWidget()->hasColorScale() ? '' : $this->buildJsColorClassInjector() . ';';
        
        return <<<JS
        
        (function(oCtrl, sColor){
            var fnStyler = function(){
                var aCssSelectorRemoveChars = $cssReplaceJSON;
                var sColorClassSuffix = '';
                var sColorClassPrefix = '{$cssColorClassPrefix}';
                var sCustomCssClass = '{$cssCustomCssClass}';
                (oCtrl.$().attr('class') || '').split(/\s+/).forEach(function(sClass) {
                    if (sClass.startsWith(sColorClassPrefix)) {
                        oCtrl.removeStyleClass(sClass);
                    }
                });
                if (sColor === null || sColor === '') {
                    oCtrl.removeStyleClass(sCustomCssClass);
                } else {
                    sColorClassSuffix = sColor.toString();
                    aCssSelectorRemoveChars.forEach(function(sChar) {
                        sColorClassSuffix = sColorClassSuffix.replace(sChar, '');
                    });
                    
                    {$cssInjector}
                    
                    oCtrl.addStyleClass(sCustomCssClass + ' ' + sColorClassPrefix + sColorClassSuffix);
                }
            };
            var oDelegate = {
                onAfterRendering: function() {
                    fnStyler();
                    oCtrl.removeEventDelegate(oDelegate);
                }
            };
            fnStyler();
            if (oCtrl.$().length === 0) {
                oCtrl.addEventDelegate(oDelegate);
            }
        })($oControlJs, $sColorJs);
JS;
    }

    /**
     * Builds an inline JS-Snippet that injects CSS color classes into the document header.
     * 
     * For color example, see UI5ObjectStatus::buildJsColorClassInjector()
     * For Icon Example, see UI5Icon::buildJsColorClassInjector() 
     * 
     * @param string $colorJs
     * @param string $colorSuffixJs
     * @return string
     */
    protected function buildJsColorClassInjector(string $colorJs = 'sColor', string $colorSuffixJs = 'sColorClassSuffix') : string
    {
        return '';
    }
}