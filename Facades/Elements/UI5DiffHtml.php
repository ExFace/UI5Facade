<?php

namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Widgets\DiffText;
use exface\Core\Widgets\Value;

class UI5DiffHtml extends UI5Value
{
    public function buildJsConstructorForMainControl($oControllerJs = 'oController')
    {
        $widget = $this->getWidget();
        $this->registerExternalModules($this->getController());

        $layout = $widget->getVersionToRender();
        $id = "{$this->getId()}";
        $bindingPathOld = $this->getValueBindingPath(Value::VALUE_ALIAS);
        $bindingPathNew = $this->getValueBindingPath(DiffText::VALUE_TO_COMPARE_ALIAS);
        $setterJs = $this->buildJsValueSetter();
        $setterJs = str_replace("setText", "setContent", $setterJs);
        $widget->setHideCaption(true);
        $initPropsJs = <<<JS

            var oValueBinding = new sap.ui.model.Binding(
                sap.ui.getCore().byId('{$id}').getModel(), 
                '{$bindingPathOld}', sap.ui.getCore().byId('{$id}').getModel().getContext('{$bindingPathOld}'));
            oValueBinding.attachChange(function(oEvent){
                let htmlOld = sap.ui.getCore().byId('{$id}').getModel().getProperty('{$bindingPathOld}');
                let htmlNew = sap.ui.getCore().byId('{$id}').getModel().getProperty('{$bindingPathNew}');
                let htmlDiff = htmldiff(htmlOld, htmlNew);
                {$setterJs}
                console.log("SET");
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

    public function buildJsValueSetter($variableName = "htmlDiff") : string
    {
        $setterJs = parent::buildJsValueSetter($variableName);

        $js = <<<JS
            console.log("HI");
            if({$variableName} !== undefined && {$variableName} !== null) {
                // Apply styling.
                {$variableName} = "".concat(
                    // Injecting CSS into the iFrame. TODO: Find a prettier way.
                    '<style> .difftext-container {border: 1px solid #c3d9e0;} .difftext-diff del {text-decoration: line-through; color: white; background-color: red;} .difftext-diff ins {text-decoration: none; color: white; background-color: green;} </style>',
                    '<div id="{$variableName}_shell" class="difftext-diff">', 
                    {$variableName},
                    '</div>');
                // Escape HTML.            
                {$variableName} = {$variableName}
                  .replace(/&/g, "&amp;")
                  .replace(/</g, "&lt;")
                  .replace(/>/g, "&gt;")
                  .replace(/"/g, "&quot;")
                  .replace(/'/g, "&#039;");
                // Enclose in iFrame.
                {$variableName} = "".concat('<iframe ' +
                    'id="IFRAME"  ' +
                    'sandbox="allow-same-origin" ' + // Required for iFrame resizing. TODO: Is this a security risk?
                    'title="HTML RENDERER" ' +
                    'onload="this.style.height=(this.contentWindow.document.body.scrollHeight+20)+\'px\';"' + // Resizing iFrame to fit entire document. 
                    '{$this->buildCssElementStyle()} ' +
                    'srcdoc="', {$variableName}, '"></iframe>');
            }
            {$setterJs}
JS;
        return $js;
    }

    public function buildCssElementStyle() : string
    {
        return <<< HTML
style="width:80%; height:800px; padding:.5%; border:5px solid lightgrey;"
HTML;
    }

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
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::registerExternalModules()
     */
    public function registerExternalModules(\exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface $controller) : UI5AbstractElement
    {
        $controller->addExternalModule('libs.exface.custom.htmlDiff', $this->getFacade()->buildUrlToSource('LIBS.HTMLDIFF.JS'));
        return $this;
    }
}