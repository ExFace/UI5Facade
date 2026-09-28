<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\DataTypes\StringDataType;
use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;
use exface\UI5Facade\Facades\UI5PropertyBinding;

/**
 * Renders an `exface.ui5Custom.DisplayTemplate` control with bindings for placeholders in an HTML tempalte.
 * 
 * The custom control parses the template markup once and lets UI5 patch only the changed texts into
 * the DOM afterwards. This is significantly faster than `sap.ui.core.HTML` inside of data tables,
 * where every scroll tick would otherwise rebuild the entire markup of every visible cell.
 * 
 * @method \exface\Core\Widgets\DisplayTemplate getWidget()
 *        
 * @author Andrej Kabachnik
 *        
 */
class UI5DisplayTemplate extends UI5Display
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
        $html = $widget->getTemplate();

        // Use the original placeholder texts as keys (not getBindingExpression()->__toString()), because
        // after a server-side prefill setValue() is called on the binding, which changes getBindingExpression()
        // to return the prefilled value instead of the attribute alias, causing replacePlaceholders() to fail.
        // (otherwise use outside of tables/in dialogues didnt work)
        $phs = StringDataType::findPlaceholders($html);
        $phVals = [];
        $partsJs = [];
        // Each placeholder becomes a numbered slot in the markup and a part of the values binding at the
        // same position - that index is what tells the control which value belongs to which slot.
        foreach ($widget->getBindings() as $i => $widgetBinding) {
            $ph = $phs[$i];
            $ui5Binding = new UI5PropertyBinding($this, 'values', $widgetBinding);
            $partsJs[] = $this->buildJsBindingPart($ui5Binding->getModelBindingPath());
            $phVals[$ph] = '[[exf-slot-' . $i . ']]';
        }

        // replace placeholders, and pass workbench to evaluate formulas 
        $html = StringDataType::replacePlaceholders($html, $phVals, true, false, $this->getWorkbench());

        // Binding all placeholders as a single composite binding means one property update per row
        // instead of one per placeholder. The formatter just collects the parts into an array.
        if (empty($partsJs)) {
            $settingsJs = '';
        } else {
            $parts = implode(', ', $partsJs);
            $settingsJs = <<<JS

            values: {
                parts: [{$parts}],
                formatter: function() {
                    return Array.prototype.slice.call(arguments);
                }
            }

JS;
        }

        // The template is applied via setTemplate() and not as a constructor property because UI5
        // would try to interpret curly braces inside the markup as binding syntax.
        return <<<JS
        new exface.ui5Custom.DisplayTemplate({{$settingsJs}})
        .setTemplate({$this->escapeString($html)})
JS;
    }

    /**
     * Builds a single `parts` entry of the composite `values` binding from a UI5 binding path
     * 
     * Paths of named models look like `modelName>path`, but parts of a composite binding need the
     * model and the path as separate options.
     * 
     * @param string $path
     * @return string
     */
    protected function buildJsBindingPart(string $path) : string
    {
        $pos = strpos($path, '>');
        if ($pos !== false) {
            $model = substr($path, 0, $pos);
            $path = substr($path, $pos + 1);
            return '{path: ' . $this->escapeString($path) . ', model: ' . $this->escapeString($model) . '}';
        }
        return '{path: ' . $this->escapeString($path) . '}';
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::registerExternalModules()
     */
    public function registerExternalModules(UI5ControllerInterface $controller) : UI5AbstractElement
    {
        parent::registerExternalModules($controller);
        $controller->addExternalModule('libs.exface.ui5Custom.DisplayTemplate', 'vendor/exface/ui5facade/Facades/js/ui5Custom/DisplayTemplate');
        return $this;
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
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsValueGetterMethod()
     */
    public function buildJsValueGetterMethod()
    {
        // What is the value of the template? Maybe a delimited list of placeholders?
        return "";
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Display::buildJsValueSetter()
     */
    public function buildJsValueSetter($valueJs)
    {
        // Similarly to value getter, it is not quite clear, what this does.
        return '';
    }
}