<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\UI5Facade\Facades\Elements\Traits\UI5MarkianTrait;

/**
 * Hosts Markian in a native UI5 control with input validation and two-way bindings.
 *
 * @method \exface\Core\Widgets\InputMarkdown getWidget()
 */
class UI5InputMarkdown extends UI5Input
{
    use UI5MarkianTrait;

    /**
     * {@inheritDoc}
     * @see UI5Input::buildJsConstructorForMainControl()
     */
    public function buildJsConstructorForMainControl($oControllerJs = 'oController')
    {
        $this->registerExternalModules($this->getController());
        $warning = $this->getWidget()->getStencils()
            ? "console.warn('Markian stencil controls are not available yet; existing document source is retained.');"
            : '';
        return <<<JS
(function() {
    {$warning}
    return new exface.ui5Custom.Markdown({$this->escapeString($this->getId())}, {
        {$this->buildJsProperties()}
    }).setEditorConfig({$this->buildJsMarkdownConfig()});
})()
JS;
    }

    /**
     * {@inheritDoc}
     * @see UI5Input::buildJsPropertyValue()
     */
    protected function buildJsPropertyValue()
    {
        return 'value: ' . $this->buildJsMarkdownValue() . ',';
    }

    /**
     * {@inheritDoc}
     * @see UI5Input::getHeight()
     */
    public function getHeight()
    {
        if ($this->getWidget()->getHeight()->isUndefined()) {
            return (2 * $this->getHeightRelativeUnit()) . 'px';
        }
        return parent::getHeight();
    }

    /**
     * {@inheritDoc}
     * @see UI5MarkianTrait::isMarkdownViewer()
     */
    protected function isMarkdownViewer() : bool
    {
        return false;
    }
}
