<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\DataTypes\DateDataType;
use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\DataTypes\TimestampDataType;
use exface\Core\Widgets\InputDateRange;

/**
 * Bridges the UI5 picker selection object and the single `from..to` value
 * used by exface filters and model bindings.
 *
 * @method InputDateRange getWidget()
 */
class UI5InputDateRange extends UI5Input
{
    public function buildJsConstructorForMainControl($oControllerJs = 'oController')
    {
        $widget = $this->getWidget();
        $controller = $this->getController();
        $controller->addExternalModule('exface.ui5.InputDateRange', 'vendor/exface/ui5facade/Facades/js/InputDateRange.js');
        $format = $this->escapeString($widget->getFormat());
        $presets = [];
        foreach ($widget->getRanges() as $range) {
            $presets[] = [
                'caption' => $range->getCaption(),
                'hint' => $range->getHint(),
                'group' => $range->getGroup(),
                'range' => $range->getRange(),
                'from' => $range->getRangeFrom(),
                'to' => $range->getRangeTo()
            ];
        }
        $presetsJs = json_encode($presets, JSON_THROW_ON_ERROR);
        $mondayJs = $this->escapeBool($widget->getFirstDayOfWeek() === DateDataType::WEEKDAY_MONDAY);

        $allowDefaultOptionsJs = $this->escapeBool($widget->getAllowDefaultOptions());
        $allowCustomRangeJs = $this->escapeBool($widget->getAllowCustomRange());

        // Use the attribute type here, since the widget's value type describes
        // the range string, not whether its endpoints need a time component.
        $attributeType = $widget->getAttribute()?->getDataType();
        $dateTimeJs = $this->escapeBool($attributeType instanceof DateTimeDataType || $attributeType instanceof TimestampDataType);
        $changeJs = $controller->buildJsEventHandler($this, self::EVENT_NAME_CHANGE, true);
        $valueProperty = $this->buildJsPropertyValue();

        return <<<JS
(function(DateRange) {
    var oControl = new DateRange({$this->escapeString($this->getId())}, {
        {$this->buildJsPropertyVisibile()}
        {$this->buildJsPropertyTooltip()}
        {$this->buildJsPropertyLayoutData()}
        {$this->buildJsPropertyWidth()}
        {$this->buildJsPropertyRequired()}
        {$this->buildJsPropertyDisabled()}
        editable: {$this->escapeBool(! $widget->isReadonly() && ! $widget->isDisplayOnly())},
        rangeFormat: {$format},
        dateTimeRange: {$dateTimeJs},
        hideCalendarOption: !{$allowDefaultOptionsJs},
        standardOptions: DateRange.getStandardOptionKeys({$allowDefaultOptionsJs}, {$allowCustomRangeJs}),
        customOptions: DateRange.createPresetOptions({$presetsJs}, {$format}, {$mondayJs}),
        showClearIcon: true,
        {$valueProperty}
        change: function(oEvent) {
            // Invalid text should not trigger apply-on-change filters.
            if (!oEvent.getParameter("valid")) {
                return;
            }
            var aHandler = {$changeJs};
            aHandler[0].call(aHandler[1], oEvent);
        }
    });
    return oControl;
})(sap.ui.require("exface/ui5/InputDateRange"))
{$this->buildJsPseudoEventHandlers()}
JS;
    }

    protected function buildJsPropertyValue()
    {
        // Reuse standard input binding/default handling, but keep the scalar
        // value separate from DynamicDateRange's structured `value` property.
        return preg_replace('/^value:/', 'rangeValue:', parent::buildJsPropertyValue());
    }

    public function buildJsValueBindingPropertyName() : string
    {
        return 'rangeValue';
    }

    public function buildJsValueGetterMethod()
    {
        return 'getExfValue()';
    }

    public function buildJsValueSetterMethod($valueJs)
    {
        return "setExfValue({$valueJs})";
    }

    public function buildJsValidator(?string $valJs = null) : string
    {
        // The scalar getter returns an empty string on parse errors. Check the
        // UI5 selection as well so invalid text cannot pass as an optional blank.
        return "(sap.ui.getCore().byId({$this->escapeString($this->getId())})?.getValue()?.operator !== 'PARSEERROR' && "
            . parent::buildJsValidator($valJs) . ')';
    }
}
