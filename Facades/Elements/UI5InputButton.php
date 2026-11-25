<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Factories\WidgetLinkFactory;
use exface\Core\Interfaces\Widgets\WidgetLinkCoordinateInterface;
use exface\Core\Exceptions\Widgets\WidgetCoordinateUnresolvableError;

use exface\Core\Widgets\Input;

/**
 * Generates OpenUI5 inputs
 *
 * @author Andrej Kabachnik
 * 
 * @method \exface\Core\Widgets\InputButton getWidget()
 *        
 */
class UI5InputButton extends UI5Input
{    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Value::buildJsConstructorForMainControl()
     */
    public function buildJsConstructorForMainControl($oControllerJs = 'oController')
    {
        $widget = $this->getWidget();
        $btnElement = $this->getFacade()->getElement($this->getWidget()->getButton());
        $updateOwnValueJs = '';
        if ($widget->willUpdateValueWithActionResult()) {
            $updateOwnValueJs = <<<JS

    var mNewVal = ((response.rows || [])[0] || {})['{$widget->getDataColumnName()}'];
    if (mNewVal !== undefined && mNewVal !== '' && mNewVal !== null) {
        {$this->buildJsValueSetter('mNewVal')}
    }
JS;
        } 
        $saveDataToModelJs = <<<JS

var oInput = sap.ui.getCore().byId("{$this->getId()}");
if (typeof response !== 'undefined') {
    oInput.getModel('action_result').setData(response);
    {$updateOwnValueJs}
}
oInput.fireChange();

JS;
        $btnElement->addOnSuccessScript($saveDataToModelJs);
        // Press the button on enter in the input
        $this->getController()->addOnInitScript("sap.ui.getCore().byId('{$this->getId()}').onsapenter = (function(oEvent){{$btnElement->buildJsClickEventHandlerCall()}});");
        // Press the button initially
        if ($widget->getButtonPressOnStart() === true) {
            $this->getController()->addOnPrefillDataChangedScript("sap.ui.getCore().byId('{$btnElement->getId()}').firePress();");
        }
        // Empty input if action fails
        if ($widget->getEmptyAfterActionFails() === true) {
            $btnElement->addOnErrorScript($this->buildJsCallFunction(Input::FUNCTION_EMPTY));
        }
        
        return <<<JS

        new sap.m.HBox({
            items: [
                new sap.m.Input("{$this->getId()}", {
                    {$this->buildJsProperties()}
                    {$this->buildJsPropertyType()}
                    {$this->buildJsPropertyChange()}
                    {$this->buildJsPropertyRequired()}
                    {$this->buildJsPropertyValue()}
                    {$this->buildJsPropertyDisabled()}
                    {$this->buildJsPropertyHeight()}
                    layoutData: new sap.m.FlexItemData({
                        growFactor: 1
                    }),
                }).setModel(new sap.ui.model.json.JSONModel({}), 'action_result')
                {$this->buildJsPseudoEventHandlers()},
                {$btnElement->buildJsConstructor()}
            ],
            {$this->buildJsPropertyWidth()}
            {$this->buildJsPropertyHeight()}
        })

JS;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::buildJsPseudoEventHandlers()
     */
    protected function buildJsPseudoEventHandlers()
    {
        // Select complete text in the input when it is focused
        if ($this->getWidget()->getValueSelectedOnFocus()) {
            $this->addPseudoEventHandler('onfocusin', $this->buildJsValueSelectedOnFocus());
        }
        return parent::buildJsPseudoEventHandlers();
    }
    
    /**
     * Javascript to select the complete value of the input when it gets focused
     * 
     * @return string
     */
    protected function buildJsValueSelectedOnFocus() : string
    {
        return <<<JS
        
                (function(){
                    var iLength = sap.ui.getCore().byId('{$this->getId()}').getValue().length
                    sap.ui.getCore().byId('{$this->getId()}').selectText(0, iLength);
                })();                
JS;
    }
    
    /**
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::validateValueGetterCoordinate()
     */
    protected function validateValueGetterCoordinate(WidgetLinkCoordinateInterface $coord) : void
    {
        if ($coord->hasAggregator() || ($coord->getRowCoordinate() !== null && !$coord->isTargetingRowNumber())) {
            throw new WidgetCoordinateUnresolvableError($this->getWidget(), $coord, 'Only numbered rows without aggregation are supported for UI5 input button coordinates!');
        }
        if ($coord->isTargetingRowNumber()
            && ($coord->getColumnCoordinate() === null || $coord->getColumnCoordinate() === $this->getWidget()->getDataColumnName())) {
            parent::validateValueGetterCoordinate($coord);
        }
    }

    /**
     * {@inheritDoc}
     * @see UI5AbstractElement::buildJsValueGetter()
     */
    public function buildJsValueGetter(string|WidgetLinkCoordinateInterface|null $coord = null) : string
    {
        $coord = WidgetLinkFactory::createCoordinateFromAnything($coord);
        $this->validateValueGetterCoordinate($coord);
        $dataColumnName = $coord->getColumnCoordinate();
        $widget = $this->getWidget();
        
        if ($dataColumnName === null || $dataColumnName === '' || $dataColumnName === $widget->getDataColumnName()) {
            return parent::buildJsValueGetter($coord);
        }
        
        $iRowJs = $coord->getRowCoordinate() ?? 0;
        $columnJs = $this->escapeString($dataColumnName);
        
        return <<<JS

function(sColName, iRowIdx){
    var aData = sap.ui.getCore().byId('{$this->getId()}').getModel('action_result').getData().data;
    if (aData !== undefined && aData[iRowIdx] !== undefined) {
        return aData[iRowIdx][sColName];
    } else {
        return '';
    }
}({$columnJs}, $iRowJs)
JS;
    }
}