<?php
namespace exface\UI5Facade\Facades\Elements\Traits;

use exface\Core\DataTypes\MarkdownDataType;
use exface\Core\Facades\AbstractAjaxFacade\Elements\MarkianTrait;
use exface\Core\Widgets\Markdown;
use exface\UI5Facade\Facades\Elements\UI5AbstractElement;
use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;

/**
 * Keeps Markian configuration shared while UI5 owns model bindings and control lifetime.
 */
trait UI5MarkianTrait
{
    use MarkianTrait;

    /**
     * Loads only the selected engine and its optional rendering extensions.
     *
     * {@inheritDoc}
     * @see UI5AbstractElement::registerExternalModules()
     */
    public function registerExternalModules(UI5ControllerInterface $controller) : UI5AbstractElement
    {
        parent::registerExternalModules($controller);
        $viewer = $this->isMarkdownViewer();
        $prefix = $viewer ? 'LIBS.MARKIAN.VIEWER' : 'LIBS.MARKIAN.EDITOR';
        $assets = [
            [$viewer ? 'viewer' : 'editor', $prefix, $viewer ? 'MarkianViewer' : 'Markian'],
            ['bridge', 'LIBS.MARKIAN.BRIDGE', 'MarkianAdapter'],
            ['code', 'LIBS.MARKIAN.CODE', 'MarkianCode']
        ];
        if ($this->getWidget()->hasRenderMermaidDiagrams()) {
            $assets[] = [
                $viewer ? 'viewerMermaid' : 'mermaid',
                $viewer ? 'LIBS.MARKIAN.VIEWER_MERMAID' : 'LIBS.MARKIAN.MERMAID',
                $viewer ? 'MarkianViewerMermaid' : 'MarkianMermaid'
            ];
        }
        foreach ($assets as [$name, $key, $global]) {
            $controller->addExternalModule('libs.exface.markian.' . $name, $this->getFacade()->buildUrlToSource($key . '.JS'), null, $global);
            if ($key !== 'LIBS.MARKIAN.BRIDGE') {
                $controller->addExternalCss($this->getFacade()->buildUrlToSource($key . '.CSS'));
            }
        }
        $controller->addExternalModule('libs.exface.ui5Custom.Markdown', 'vendor/exface/ui5facade/Facades/js/ui5Custom/Markdown');
        return $this;
    }

    /**
     * Leaves binding expressions intact and preserves multiline literal source.
     *
     * @return string
     */
    protected function buildJsMarkdownValue() : string
    {
        if ($this->isValueBoundToModel()) {
            return $this->buildJsValueBinding();
        }
        $value = $this->getWidget()->getValueWidgetLink() ? '' : $this->getWidget()->getValueWithDefaults();
        return '"' . str_replace('<', '\\u003C', $this->escapeJsTextValue($value ?? '')) . '"';
    }

    /**
     * Keeps the configured Markdown height instead of the generic text-control default.
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Value::buildJsPropertyHeight()
     */
    protected function buildJsPropertyHeight()
    {
        return 'height: ' . $this->escapeString($this->getHeight()) . ',';
    }

    /**
     * Both inputs and viewers expose a native source value rather than rendered text.
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Value::buildJsValueBindingPropertyName()
     */
    public function buildJsValueBindingPropertyName() : string
    {
        return 'value';
    }

    /**
     * Passes shared editor settings or rendering-only viewer settings to the UI5 host.
     *
     * @return string
     */
    protected function buildJsMarkdownConfig() : string
    {
        $widget = $this->getWidget();
        if ($this->isMarkdownViewer()) {
            $type = $widget->getValueDataType();
            $optionsJs = '{markdownCssUrls: [' . $this->escapeString($this->getFacade()->buildUrlToSource('LIBS.MARKIAN.MARKDOWN.CSS')) . '],'
                . 'markdownCssClasses: ["markdown-body"], softbreaks: '
                . $this->escapeBool($type instanceof MarkdownDataType ? $type->getEnableNewlines() : true) . '}';
            if ($widget instanceof Markdown) {
                if ($widget->getBaseUrl()) {
                    $optionsJs = 'Object.assign(' . $optionsJs . ', {documentBase: ' . $this->escapeString($widget->getBaseUrl()) . '})';
                }
                if ($widget->getOpenLinksIn() !== Markdown::OPEN_LINKS_IN_SELF) {
                    $targetJs = $widget->getOpenLinksIn() === Markdown::OPEN_LINKS_IN_NEW_TAB ? '"_blank"' : '"window"';
                    $featuresJs = $this->escapeString('width=' . $widget->getOpenLinksInPopupWidth()
                        . ',height=' . $widget->getOpenLinksInPopupHeight() . ',toolbar=no,menubar=no,resizable=yes');
                    $optionsJs = <<<JS
Object.assign({$optionsJs}, {onLinkOpen: function(oContext) {
    if (oContext.destination.startsWith('#')) {
        var sId = decodeURIComponent(oContext.destination.slice(1));
        var domTarget = Array.from(oContext.event.currentTarget.querySelectorAll('[id]')).find(function(domNode) { return domNode.id === sId; });
        if (domTarget) domTarget.scrollIntoView({block: 'nearest'});
        return;
    }
    window.open(oContext.event.target.closest('a').href, {$targetJs}, {$featuresJs});
}})
JS;
                }
            }
            $optionsJs = 'Object.assign(' . $optionsJs . ', {renderers: MarkianAdapter.buildCodeRenderers(document, ' . $this->buildJsMarkdownCodeSettings() . ')})';
        } else {
            $optionsJs = $this->buildJsMarkdownEditorOptions();
        }
        $mermaidJs = $widget->hasRenderMermaidDiagrams()
            ? $this->escapeMarkdownForInlineJavaScript($this->getFacade()->buildUrlToSource('LIBS.PELE.JS'))
            : 'null';
        $imagesJs = $this->isMarkdownViewer() ? 'true' : $this->escapeBool($widget->getAllowImages());
        return '{options: ' . $optionsJs . ', mermaidSource: ' . $mermaidJs . ', allowImages: ' . $imagesJs . '}';
    }

    /**
     * Reads the native control so values remain available before mounting and after rerender.
     *
     * {@inheritDoc}
     * @see UI5AbstractElement::buildJsValueGetter()
     */
    public function buildJsValueGetter()
    {
        return '(function(oControl) { return oControl ? oControl.getValue() : ""; })(sap.ui.getCore().byId(' . $this->escapeString($this->getId()) . '))';
    }

    /**
     * Uses the native value property without firing a synthetic user-change event.
     *
     * {@inheritDoc}
     * @see UI5AbstractElement::buildJsValueSetter()
     */
    public function buildJsValueSetter($value, $disable_formatting = false) : string
    {
        return '(function(oControl, mValue) { if (oControl) oControl.setValue(mValue); })(sap.ui.getCore().byId('
            . $this->escapeString($this->getId()) . '), ' . $value . ');';
    }

    /**
     * Distinguishes an input from a display without requiring display-specific widget APIs.
     *
     * @return bool
     */
    protected function isMarkdownViewer() : bool
    {
        return true;
    }
}
