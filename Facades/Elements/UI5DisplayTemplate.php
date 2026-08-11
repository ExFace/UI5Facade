<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\DataTypes\StringDataType;
use exface\UI5Facade\Facades\UI5PropertyBinding;

/**
 * Renders a `sap.ui.core.HTML` control with bindings for placeholders in an HTML tempalte.
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
        foreach ($widget->getBindings() as $i => $widgetBinding) {
            $ph = $phs[$i];
            $ui5Binding = new UI5PropertyBinding($this, 'content', $widgetBinding);
            $ui5BindingPath = $ui5Binding->getModelBindingPath();
            $phVals[$ph] = '{' . $ui5BindingPath . '}';
        }

        // replace placeholders, and pass workbench to evaluate formulas 
        $html = StringDataType::replacePlaceholders($html, $phVals, true, false, $this->getWorkbench());

        // if the lighter rendering option 'render_as_formatted_text' is set, use a sap.m.FormattedText control instead of sap.ui.core.HTML
        if ($widget->isInTable()) {
            if ($widget->getRenderAsFormattedText() === true) {
                return $this->buildJsConstructorForFormattedText($html);
            }
        }

        // otherwise use the full sap.ui.core.HTML control
        return $this->buildJsConstructorForHtmlControl($html);
    }

    /**
     * Builds a lightweight sap.m.FormattedText constructor for table cells.
     *
     * @param string $html
     * @return string
     */
    protected function buildJsConstructorForFormattedText(string $html) : string
    {
        // Compact pretty-printed templates to avoid excessive whitespace nodes in table cells.
        $html = preg_replace('/>\s+</', '><', trim($html));
        $html = $this->escapeString($html);

        return <<<JS
        new sap.m.FormattedText({
            htmlText: {$html}
        })
JS;
    }

    /**
     * Builds the full sap.ui.core.HTML constructor.
     *
     * @param string $html
     * @return string
     */
    protected function buildJsConstructorForHtmlControl(string $html) : string
    {
        // Wrap in an outer div: otherwise the html content might duplicate in tables during scrolling 
        // when there is no central control to replace the bindings in 
        $html = $this->escapeString('<div>' . $html . '</div>');

        // removed id for now, to avoid duplicates (?)
        // TODO: should we sanitize here (?) turned it on for now
        return <<<JS
        new sap.ui.core.HTML({
            content: {$html},
            sanitizeContent: true
        })
JS;
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