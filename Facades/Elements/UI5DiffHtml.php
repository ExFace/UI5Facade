<?php

namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Widgets\DiffHtml;
use exface\Core\Widgets\DiffText;
use exface\Core\Widgets\Value;

/**
 * UI5 implementation of the corresponding widget.
 *
 * @author Andrej Kabachnik, Georg Bieger
 */
class UI5DiffHtml extends UI5Value
{
    // Defining constants to avoid typos.
    const VAR_OLD = 'sHtmlOld';
    const VAR_NEW = 'sHtmlNew';
    const VAR_DIF = 'sHtmlDiff';

    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::registerExternalModules()
     */
    public function registerExternalModules(\exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface $controller) : UI5AbstractElement
    {
        $controller->addExternalModule('libs.exface.custom.htmlDiff', $this->getFacade()->buildUrlToSource('LIBS.HTMLDIFF.JS'), 'htmldiff');
        $controller->addExternalCss('vendor/exface/ui5facade/Facades/js/HtmlDiff/HtmlDiff.css');
        return $this;
    }

    /**
     * {@inheritDoc}
     * @return string
     */
    public function buildJsConstructorForMainControl($oControllerJs = 'oController') : string
    {
        $this->registerExternalModules($this->getController());

        // Assigning constants to local variables to enable string interpolation.
        $varOld = self::VAR_OLD;
        $varNew = self::VAR_NEW;
        $varDif = self::VAR_DIF;

        $widget = $this->getWidget();
        $widget->setHideCaption(true);

        $id = "{$this->getId()}";
        $bindingPathOld = $this->getValueBindingPath(Value::VALUE_ALIAS);
        $bindingPathNew = $this->getValueBindingPath(DiffText::VALUE_TO_COMPARE_ALIAS);

        $initPropsJs = <<<JS

            var oCtrl = sap.ui.getCore().byId('{$id}');
            var oModel = oCtrl.getModel();
            var oValueBinding = new sap.ui.model.Binding(
                oModel, 
                '{$bindingPathOld}', 
                oModel.getContext('{$bindingPathOld}')
            );
            oValueBinding.attachChange(function(oEvent){
                let {$varOld} = sap.ui.getCore().byId('{$id}').getModel().getProperty('{$bindingPathOld}');
                let {$varNew} = sap.ui.getCore().byId('{$id}').getModel().getProperty('{$bindingPathNew}');
                let {$varDif} = htmldiff({$varOld}, {$varNew});
                {$this->buildJsRefreshDiff($this->getVarName($widget->getRenderedVersion("left")), $this->getVarName($widget->getRenderedVersion("right")), $widget)}
            });

JS;

        return <<<JS
        new sap.ui.core.HTML("{$id}", {
            preferDOM: false,
            afterRendering: function() {
                {$initPropsJs}
            }
        })
JS;
    }

    /**
     * Generates a title card depending on the corresponding layout.
     *
     * @param string $color
     * @return string
     */
    public function buildHtmlTitle(string $color, string $title) : string
    {
        return  '<div style="text-align:center; background-color:'.$color.'; margin-bottom: 30px; padding:.2rem; color:white;">'.
                '    <h1>'.$title.'</h1>'.
                '</div>';
    }

    /**
     * Generates a JS snippet that updates the displayed documents whenever a change event triggers.
     *
     * @param string $leftValName
     * @param string $rightValName
     * @return string
     */
    public function buildJsRefreshDiff(string $leftValName, string $rightValName, DiffHtml $widget) : string
    {
        $titleLeft = $this->buildHtmlTitle($widget->getTitleColor("left"), $widget->getTitle("left"));
        $titleRight = $this->buildHtmlTitle($widget->getTitleColor("right"), $widget->getTitle("right"));
        return <<<JS

            var fnConstructor = function(mVal, sId, sTitle){
                if(mVal === undefined || mVal === null) {
                    return '';
                }
                // Apply styling.
                var sHtml = "".concat(
                    // Injecting CSS into the iFrame. TODO: Find a prettier way.
                    '<style> .difftext-container {border: 1px solid #c3d9e0;} .difftext-diff del {text-decoration: line-through; color: white; background-color: red;} .difftext-diff ins {text-decoration: none; color: white; background-color: green;} </style>',
                    sTitle,
                    '<div id="' + sId + '_shell" class="difftext-diff">', 
                    mVal,
                    '</div>'
                );
                // Escape HTML.  
                sHtml = exfTools.string.htmlEscape(sHtml, true);
                // Enclose in iFrame.
                return  '   <iframe ' +
                        '       id="IFRAME"  ' +
                        '       sandbox="allow-same-origin" ' + // Required for iFrame resizing. TODO: Is this a security risk?
                        '       title="HTML RENDERER" ' +
                        '       onload="this.style.height=(this.contentWindow.document.body.scrollHeight+20)+\'px\';"' + // Resizing iFrame to fit entire document.
                        '       class ="exf-diffHtml-iFrame" ' +
                        '       srcdoc="' + sHtml + '"' +
                        '   ></iframe>'
            };
            var sDiv = '<table style="width: calc(100% - 14px)"><tr>';
            sDiv += '<td width="50%" style="vertical-align: top;">' + fnConstructor({$leftValName}, '{$this->getId()}_left', '{$titleLeft}') + '</td>';
            sDiv += '<td width="50%" style="vertical-align: top;">' + fnConstructor({$rightValName}, '{$this->getId()}_right', '{$titleRight}') + '</td>';
            sDiv += '</table>';

            sap.ui.getCore().byId('{$this->getId()}').setContent(sDiv);
JS;
    }
    /**
     * Generates the binding path for the given property name.
     *
     * @param string $propertyName
     * @return string
     */
    public function getValueBindingPath(string $propertyName = "value") : string
    {
        $widget = $this->getWidget();
        $model = $this->getView()->getModel();
        if ($model->hasBinding($widget, $propertyName)) {
            return $model->getBindingPath($widget, $propertyName);
        }
        return $this->getValueBindingPrefix() . $this->getWidget()->getDataColumnName();
    }

    /**
     * Extracts the corresponding variable name and converts it to the local nomenclature.
     *
     * @param string $version
     * @return string
     */
    private function getVarName(string $version) : string
    {
        return match ($version) {
            "old" => self::VAR_OLD,
            "new" => self::VAR_NEW,
            default => self::VAR_DIF,
        };
    }
}