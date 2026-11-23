<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\UI5Facade\Facades\Elements\Traits\UI5MarkianTrait;

/**
 * Renders Markdown with Markian's independent, sanitized viewer.
 *
 * @method \exface\Core\Widgets\Markdown getWidget()
 */
class UI5Markdown extends UI5Value
{
    use UI5MarkianTrait;

    /**
     * {@inheritDoc}
     * @see UI5Value::buildJsConstructorForMainControl()
     */
    public function buildJsConstructorForMainControl($oControllerJs = 'oController')
    {
        $this->registerExternalModules($this->getController());
        return <<<JS
new exface.ui5Custom.Markdown({$this->escapeString($this->getId())}, {
    {$this->buildJsProperties()}
    {$this->buildJsPropertyWidth()}
    {$this->buildJsPropertyHeight()}
    value: {$this->buildJsMarkdownValue()},
    viewer: true
}).setEditorConfig({$this->buildJsMarkdownConfig()})
JS;
    }

    /**
     * {@inheritDoc}
     * @see UI5Value::getHeight()
     */
    public function getHeight()
    {
        return $this->getWidget()->getHeight()->isUndefined() ? 'auto' : parent::getHeight();
    }
}
