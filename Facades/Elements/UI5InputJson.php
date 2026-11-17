<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Facades\AbstractAjaxFacade\Elements\JsonEditorTrait;
use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;

/**
 * Hosts the shared JSON editor in a lifecycle-aware, model-bound UI5 control.
 *
 * @method \exface\Core\Widgets\InputJson getWidget()
 */
class UI5InputJson extends UI5Input
{
    use JsonEditorTrait;

    /**
     * {@inheritDoc}
     * @see UI5Input::buildJsConstructorForMainControl()
     */
    public function buildJsConstructorForMainControl($oControllerJs = 'oController')
    {
        $this->registerExternalModules($this->getController());
        return <<<JS
new exface.ui5Custom.JsonEditor("{$this->getId()}", {
    {$this->buildJsProperties()}
}).setEditorConfig({$this->buildJsJsonEditorConfig()})
JS;
    }

    /**
     * {@inheritDoc}
     * @see UI5Input::getHeight()
     */
    public function getHeight()
    {
        if ($this->getWidget()->getHeight()->isUndefined()) {
            return $this->buildCssHeightDefaultValue();
        }
        return parent::getHeight();
    }

    /**
     * Keeps the library's host separate from the UI5 control root.
     */
    protected function getJsonEditorId() : string
    {
        return $this->getId() . '-editor';
    }

    /**
     * The UI5 control supplies the callback and updates its bound value property.
     */
    protected function buildJsJsonEditorOnChange() : string
    {
        return 'null';
    }

    /**
     * {@inheritDoc}
     * @see UI5AbstractElement::buildJsValueGetter()
     */
    public function buildJsValueGetter() : string
    {
        return parent::buildJsValueGetter();
    }

    /**
     * {@inheritDoc}
     * @see UI5AbstractElement::buildJsValueSetter()
     */
    public function buildJsValueSetter($value) : string
    {
        return parent::buildJsValueSetter($value);
    }

    /**
     * {@inheritDoc}
     * @see UI5Input::buildJsSetDisabled()
     */
    public function buildJsSetDisabled(bool $trueOrFalse) : string
    {
        return parent::buildJsSetDisabled($trueOrFalse);
    }

    /**
     * {@inheritDoc}
     * @see UI5Input::buildJsSetRequired()
     */
    protected function buildJsSetRequired(bool $required) : string
    {
        return parent::buildJsSetRequired($required);
    }

    /**
     * {@inheritDoc}
     * @see UI5Input::buildJsRequiredGetter()
     */
    protected function buildJsRequiredGetter() : string
    {
        return parent::buildJsRequiredGetter();
    }

    /**
     * {@inheritDoc}
     * @see UI5AbstractElement::registerExternalModules()
     */
    public function registerExternalModules(UI5ControllerInterface $controller) : UI5AbstractElement
    {
        parent::registerExternalModules($controller);
        $names = ['jsoneditor', 'picoModal', 'exfJsonEditor'];
        foreach ($this->getJsonEditorJsIncludes() as $index => $url) {
            $controller->addExternalModule('libs.exface.json.' . $names[$index], $url, null, $index === 0 ? 'JSONEditor' : $names[$index]);
        }
        foreach ($this->getJsonEditorCssIncludes() as $url) {
            $controller->addExternalCss($url);
        }
        $controller->addExternalModule('libs.exface.ui5Custom.JsonEditor', 'vendor/exface/ui5facade/Facades/js/ui5Custom/JsonEditor');
        return $this;
    }
}
