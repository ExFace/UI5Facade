<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Widgets\InputComboTable;
use exface\Core\Widgets\DataColumn;
use exface\Core\Exceptions\Widgets\WidgetHasNoUidColumnError;
use exface\Core\Exceptions\Widgets\WidgetLogicError;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\DataTypes\UrlDataType;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Exceptions\Facades\FacadeLogicError;

/**
 * Generates OpenUI5 selects
 *
 * @method InputComboTable getWidget()
 *
 * @author Andrej Kabachnik
 *        
 */
class UI5InputComboTable extends UI5Input
{
    
    protected function init()
    {
        parent::init();
        
        // If the combo does not allow new values, we need to force the UI5 input to
        // check any input via autosuggest _before_ any other action is taken.
        // TODO this only works if there was no value before and needs to be
        // extended to work with changing values too.
        if (! $this->getWidget()->getAllowNewValues()) {
            if ($this->getWidget()->getMultiSelect() === false) {
                $missingKeyCheckJs = "oInput.getSelectedKey() === ''";
            } else {
                $missingKeyCheckJs = "oInput.getTokens().length === 0";
            }
            $onChange = <<<JS

                        var oInput = oEvent.getSource();
                        if (oInput.getValue() !== '' && $missingKeyCheckJs){
                            oInput.fireSuggest({suggestValue: {q: oInput.getValue()}});
                            oEvent.cancelBubble();
                            oEvent.preventDefault();
                            return false;
                        }
                        if (oInput.getValue() === '' && $missingKeyCheckJs){
                            oInput.setValueState(sap.ui.core.ValueState.None);
                        }
JS;
            $this->addOnChangeScript($onChange);
            
            // TODO explicitly prevent propagation of enter-events to stop data widgets
            // from autoreloading if enter was pressed to soon.
            $onEnter = <<<JS
                
                        var oInput = oEvent.srcControl;
                        if (oInput.getValue() !== '' && {$missingKeyCheckJs}){
                            oEvent.stopPropagation();
                            oEvent.preventDefault();
                            return false;
                        }
JS;
                
            $this->addPseudoEventHandler('onsapenter', $onEnter);
        }
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::buildJsConstructorForMainControl()
     */
    public function buildJsConstructorForMainControl($oControllerJs = 'oController')
    {
        $widget = $this->getWidget();
        $controller = $this->getController();
        
        if ($widget->isPreloadDataEnabled()) {
            $cols = '';
            foreach ($widget->getTable()->getColumns() as $col) {
                $cols .= $col->getDataColumnName() . ',';
            }
            $cols = rtrim($cols, ",");
            $controller->addOnDefineScript("exfPreloader.addPreload('{$widget->getTableObject()->getAliasWithNamespace()}', ['{$cols}'], [], '{$widget->getPage()->getId()}', '{$widget->getTable()->getId()}');");
        }
        
        $controller->addMethod('onSuggest', $this, 'oEvent', $this->buildJsDataLoder('oEvent'));
        
        if (! $this->isValueBoundToModel() && $value = $widget->getValueWithDefaults()) {
            // If the widget value is set explicitly, we either set the key only or the 
            // key and the text (= value of the input)
            if ($widget->getValueText() === null || $widget->getValueText() === '') {
                $valueJs = '"' . $this->escapeJsTextValue($value) . '"';
                $value_init_js = <<<JS

        .{$this->buildJsSetSelectedKeyMethod($valueJs, null, true)}.fireChange({value: {$valueJs}})
JS;
            } else {
                $value_init_js = <<<JS

        .{$this->buildJsSetSelectedKeyMethod($this->escapeJsTextValue($value), $widget->getValueText())}
JS;
            }
        } else {
            // If the value is to be taken from a model, we need to check if both - key
            // and value are there. If not, the value needs to be fetched from the server.
            // FIXME for some reason sKey is sometimes empty despite the binding getting a value...
            // This seems to happen in non-maximized dialogs (e.g. editor of a small object).
            $value_init_js = <<<JS

        .attachModelContextChange(function(oEvent) {
            var oInput = oEvent.getSource();
            var sKey = sap.ui.getCore().byId('{$this->getId()}').{$this->buildJsValueGetterMethod()};
            var sVal = oInput.getValue();
            if (sKey !== '' && sVal === '') {
                {$this->buildJsValueSetter('sKey')};
            }
        })
JS;
        }
        
        // See if there are promoted columns. If not, make the first two visible columns 
        // promoted to make sap.m.table look nice on mobiles.
        $promotedCols = [];
        $firstVisibleCols = [];
        foreach ($widget->getTable()->getColumns() as $col) {
            if (! $col->isHidden()) {
                if (empty($firstVisibleCols)) {
                    $firstVisibleCols[] = $col;
                } elseif (count($firstVisibleCols) === 1) {
                    $firstVisibleCols[] = $col;
                }
                
                if ($col->getVisibility() === EXF_WIDGET_VISIBILITY_PROMOTED) {
                    $promotedCols[] = $col;
                    break;
                }
            }
            
        }
        if (empty($promotedCols) && ! empty($firstVisibleCols)) {
            // If the first automatically selected column is right-aligned, it will not
            // look nice, so change the order of the columns. Actually, the condition
            // is right the opposite, because the columns will be added to the beginning
            // of the list one after another, so the first column ends up being last.
            // TODO Make column reordering depend on the screen size. On desktops, having
            // right-aligned column in the middle does not look good, but on mobiles it
            // is very important. Maybe generate two sets of columns and assign one of
            // them depending on jQuery.device.is.phone?
            if (! ($firstVisibleCols[0]->getAlign() !== EXF_ALIGN_DEFAULT || $firstVisibleCols[0]->getAlign() === EXF_ALIGN_LEFT)) {
                $firstVisibleCols = array_reverse($firstVisibleCols);
            }
            foreach ($firstVisibleCols as $col) {
                $widget->getTable()->removeColumn($col);
                $col->setVisibility(EXF_WIDGET_VISIBILITY_PROMOTED);
                $widget->getTable()->addColumn($col, 0);
            }
        }
        
        // Now generate columns and cells from the column widgets
        $columns = '';
        $cells = '';
        foreach ($widget->getTable()->getColumns() as $idx => $col) {
            /* @var $element \exface\UI5Facade\Facades\Elements\UI5DataColumn */
            $element = $this->getFacade()->getElement($col);
            $columns .= ($columns ? ",\n" : '') . $element->buildJsConstructorForMColumn();
            $cells .= ($cells ? ",\n" : '') . $element->buildJsConstructorForCell($this->getModelNameForAutosuggest());
            if ($col->getId() === $widget->getValueColumn()->getId()) {
                $value_idx = $idx;
            }
            if ($col->getId() === $widget->getTextColumn()->getId()) {
                $text_idx = $idx;
            }
        }
        
        if (is_null($value_idx)) {
            throw new WidgetLogicError($widget, 'Value column not found for ' . $this->getWidget()->getWidgetType() . ' with id "' . $this->getWidget()->getId() . '"!');
        }
        if (is_null($text_idx)) {
            throw new WidgetLogicError($widget, 'Text column not found for ' . $this->getWidget()->getWidgetType() . ' with id "' . $this->getWidget()->getId() . '"!');
        }
        
        $control = $widget->getMultiSelect() ? 'sap.m.MultiInput' : 'sap.m.Input';
        
        return <<<JS

	   new {$control}("{$this->getId()}", {
			{$this->buildJsProperties()}
            {$this->buildJsPropertyType()}
			textFormatMode: "ValueKey",
			showSuggestion: true,
            maxSuggestionWidth: "400px",
            startSuggestion: 1,
            showTableSuggestionValueHelp: false,
            filterSuggests: false,
            showValueHelp: true,
            valueHelpRequest: {$this->buildJsPropertyValueHelpRequest()}
			suggest: {$this->buildJsPropertySuggest($oControllerJs)},
            suggestionRows: {
                path: "{$this->getModelNameForAutosuggest()}>/rows",
                template: new sap.m.ColumnListItem({
				   cells: [
				       {$cells}
				   ]
				})
            },
            suggestionItemSelected: {$this->buildJsPropertySuggestionItemSelected($value_idx, $text_idx)}
			suggestionColumns: [
				{$columns}
            ]
        })
        .setModel(new sap.ui.model.json.JSONModel(), "{$this->getModelNameForAutosuggest()}")
        {$value_init_js}
        {$this->buildJsPseudoEventHandlers()}
JS;
    }
	
    /**
     * Returns the function to be called for autosuggest.
     * 
     * This makes an AJAX requests to fetch suggestions. Normally the
     * event parameter "suggestValue" will contain the text typed by
     * the user and will be used as the autosuggest query. 
     * 
     * To make the programmatic value setter work, there is also a 
     * possibility to pass an object instead of text when firing the 
     * suggest event automatically (see buildJsDataSetterMethod()).
     * In this case, the properties of that object will be used as 
     * parameters of the AJAX request directly. This also will "silence"
     * the request and make the control refresh it's value automatically
     * if the expected suggestion rows (matching the filter) will be
     * returned. This way, setting just the value (key) will lead to
     * a silent autosuggest and the selection of the correkt text value.
     * 
     * @return string
     */
    protected function buildJsPropertySuggest(string $oControllerJs)
    {        
        return <<<JS
            function(oEvent) {
                {$this->getController()->buildJsMethodCallFromController('onSuggest', $this, 'oEvent', $oControllerJs)}
    		}
JS;
    }
                
    protected function buildJsPropertySuggestionItemSelected(int $valueColIdx, int $textColIdx) : string
    {
        return <<<JS
            function(oEvent){
                var oItem = oEvent.getParameter("selectedRow");
                if (! oItem) return;
				var aCells = oEvent.getParameter("selectedRow").getCells();
                var oInput = oEvent.getSource();
                oInput.{$this->buildJsSetSelectedKeyMethod("aCells[ {$valueColIdx} ].getText()", "aCells[ {$textColIdx} ].getText()")};
                oInput.setValueState(sap.ui.core.ValueState.None);
			},
JS;
    }
       
    /**
     * Returns the value of the property valueHelpRequest.
     * 
     * @return string
     */
    protected function buildJsPropertyValueHelpRequest() : string
    {
        // Currently, the value-help-button will simply trigger the autosuggest by firing the suggest
        // event with a special callback, that forces the input to show suggestions. This callback
        // is needed because just firing the suggest event will only show the suggestions if the
        // current text differs from the previous suggestion - don't know if this is a feature or
        // a bug. But with an explicit .showItems() it works well.
        return <<<JS
            function(oEvent) {
                var oInput = oEvent.getSource();
                {$this->buildJsBusyIconShow()};
                
                var sVal = oInput.getValue();
                if (sVal === undefined || sVal === '') {
                    sVal = ' ';
                }
                
                oInput.fireSuggest({
                    suggestValue: sVal, 
                    onLoaded: function(){
                        sap.ui.getCore().byId('{$this->getId()}')
                        .showItems(function(oItem){
                            return oItem;
                        })
                    }
                });
            },

JS;
    }
     
    /**
     * 
     * @param string $oEventJs
     * @return string
     */
    protected function buildJsDataLoder(string $oEventJs = 'oEvent') : string
    {
        $widget = $this->getWidget();
        $configuratorElement = $this->getFacade()->getElement($widget->getTable()->getConfiguratorWidget());
        $serverAdapter = $this->getFacade()->getElement($widget->getTable())->getServerAdapter();
        
        return <<<JS

                var oInput = {$oEventJs}.getSource();
                var q = {$oEventJs}.getParameter("suggestValue");
                var fnCallback = {$oEventJs}.getParameter("onLoaded");
                var qParams = {};
                var silent = false;

                if (typeof q == 'object') {
                    qParams = q;
                    silent = true;
                } else {
                    qParams.q = q;
                }
                var params = { 
                    action: "{$widget->getLazyLoadingActionAlias()}",
                    resource: "{$this->getPageId()}",
                    element: "{$widget->getTable()->getId()}",
                    object: "{$widget->getTable()->getMetaObject()->getId()}",
                    length: "{$widget->getMaxSuggestions()}",
				    start: 0,
                    data: {$configuratorElement->buildJsDataGetter($widget->getTable()->getLazyLoadingAction(), true)}
                };
                $.extend(params, qParams);

                var oModel = oInput.getModel('{$this->getModelNameForAutosuggest()}');
                if (silent) {
                    {$this->buildJsBusyIconShow()}
                    var silencer = function(oEvent){
                        if (oEvent.getParameters().success) {
                            var data = this.getProperty('/rows');
                            var curKey = oInput.{$this->buildJsValueGetterMethod()};
                            if (parseInt(this.getProperty("/recordsTotal")) == 1 && (curKey === '' || data[0]['{$widget->getValueColumn()->getDataColumnName()}'] == curKey)) {
                                oInput.{$this->buildJsSetSelectedKeyMethod("data[0]['{$widget->getValueColumn()->getDataColumnName()}']", "data[0]['{$widget->getTextColumn()->getDataColumnName()}']")}
                                oInput.closeSuggestions();
                                oInput.setValueState(sap.ui.core.ValueState.None);
                            } else {
                                oInput.setSelectedKey("");
                                oInput.setValueState(sap.ui.core.ValueState.Error);
                            }
                        }
                        this.detachRequestCompleted(silencer);
                        {$this->buildJsBusyIconHide()}
                    };
                    oModel.attachRequestCompleted(silencer);
                }
                if (fnCallback) {
                    oModel.attachRequestCompleted(function(){
                        fnCallback();
                        oModel.detachRequestCompleted(fnCallback);
                    });
                }

                {$serverAdapter->buildJsServerRequest($widget->getLazyLoadingAction(), 'oModel', 'params', $this->buildJsBusyIconHide(), $this->buildJsBusyIconHide())}

JS;
    }
    
    /**
     * The value and selectedKey properties of input controls do not seem to work before
     * a model is bound, so we set initial value programmatically at the end of the constructor.
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::buildJsPropertyValue()
     */
    protected function buildJsPropertyValue()
    {
        $widget = $this->getWidget();
        $model = $this->getView()->getModel();
        if ($model->hasBinding($widget, 'value_text')) {
            $valueBinding = ' value: "{' . $model->getBindingPath($widget, 'value_text') . '}",';
        }
        if ($this->isValueBoundToModel()) {
            // NOTE: for some reason putting the value binding _BEFORE_ the key binding is important!
            // Otherwise the key is not set sometimes...
            return $valueBinding . 'selectedKey: ' . $this->buildJsValueBinding() . ',';
        }
        return '';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsValueGetterMethod()
     */
    public function buildJsValueGetterMethod()
    {
        if ($this->getWidget()->getMultiSelect() === false) {
            return "getSelectedKey()";
        } else {
            $delim = $this->getWidget()->getMultiSelectTextDelimiter();
            return "getTokens().reduce(function(sList, oToken, iIdx, aTokens){ return sList + (sList !== '' ? '$delim' : '') + oToken.getKey() }, '')";
        }
    }
    
    public function buildJsValueGetter($column = null, $row = null)
    {
        $selectedKeyGetter = parent::buildJsValueGetter();
        if (($column === null || $column == $this->getWidget()->getValueAttributeAlias()) && ($row === null || $row === 0)) {
            return $selectedKeyGetter;
        }
        
        return <<<JS
function(){
    var sSelectedKey = {$selectedKeyGetter};
    if (sSelectedKey === undefined || sSelectedKey === undefined === '' || sSelectedKey === null) {
        return undefined;
    }
    var oInput = sap.ui.getCore().byId('{$this->getId()}');
    var oModel = oInput.getModel('{$this->getModelNameForAutosuggest()}');
    
    var oItem = oModel.getData().rows.find(function(element, index, array){
        return element['{$this->getWidget()->getValueAttributeAlias()}'] == sSelectedKey;
    });

    return oItem['$column'];
}()

JS;
    }
    
    /**
     * Returns a special parameter for the oInput.fireSuggest() method, that
     * cases a silent lookup of the value matching the given key - without actually
     * opening the suggestions.
     * 
     * @return string
     */
    protected function buildJsFireSuggestParamForSilentKeyLookup(string $keyJs) : string
    {
        $filterParam = UrlDataType::urlEncode($this->getFacade()->getUrlFilterPrefix() . $this->getWidget()->getValueColumn()->getAttributeAlias());
        return <<<JS
{
                    suggestValue: {
                        '{$filterParam}': $keyJs
                    }
                }
JS;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsValueSetter()
     */
    public function buildJsValueSetter($valueJs)
    {
        // After setting the key, we need to fetch the corresponding text value, so we use a trick
        // and pass the given value not directly, but wrapped in an object. The suggest-handler
        // above will recognize this and use merge this object with the request parameters, so
        // we can directly tell it to use our input as a value column filter instead of a regular
        // suggest string.
        return "(function(){
            var oInput = sap.ui.getCore().byId('{$this->getId()}');
            var val = {$valueJs};
            if (val == undefined || val === null || val === '') {
                oInput.{$this->buildJsEmptyMethod('val', '""')};
            } else {
                oInput
                .setSelectedKey(val)
                .fireSuggest({$this->buildJsFireSuggestParamForSilentKeyLookup('val')});
            }
            oInput.fireChange({
                value: val
            });
        })()";
    }
    
    /**
     * There is no value setter method for this class, because the logic of the value setter
     * (see above) cannot be easily packed into a single method to be called on the control.
     * 
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::buildJsValueSetterMethod()
     */
    public function buildJsValueSetterMethod($value)
    {
        throw new FacadeLogicError('Cannot use UI5InputComboTable::buildJsValueSetterMethod() - use buildJsValueSetter() instead!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::buildJsValueBindingPropertyName()
     */
    public function buildJsValueBindingPropertyName() : string
    {
        return 'selectedKey';
    }
    
    protected function getModelNameForAutosuggest() : string
    {
        return 'suggest';
    }
    
    protected function buildJsEmptyMethod() : string
    {
        if ($this->getWidget()->getMultiSelect() === false) {
            return "setValue('').setSelectedKey('')";
        } else {
            return "removeAllTokens()";
        }
    }
    
    /**
     * Returns a chained method call to set the key and value for the Input control.
     * 
     * If $lookupKeyValue is set to TRUE, a silenced suggest event will be fired to
     * request the value from the server based on the given $keyJs. This value will
     * will overwrite $valueJs!
     * 
     * In contrast to the value setter this method does not trigger a change event!!!
     * 
     * @param string $keyJs
     * @param string $valueJs
     * @param bool $lookupKeyValue
     * @return string
     */
    protected function buildJsSetSelectedKeyMethod(string $keyJs, string $valueJs = null, bool $lookupKeyValue = false) : string
    {
        if ($this->getWidget()->getMultiSelect() === false) {
            if ($valueJs !== null) {
                $setValue = "setValue($valueJs).";
            } else {
                $setValue = '';
            }
            $js = "{$setValue}setSelectedKey($keyJs)";
        } else {
            $js = "addToken(new sap.m.Token({key: $keyJs, text: $valueJs}))";
        }
        
        if ($lookupKeyValue === true) {
            $js .= ".fireSuggest({$this->buildJsFireSuggestParamForSilentKeyLookup($keyJs)})";
        }
        
        return $js;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::buildJsValidatorCheckDataType()
     */
    protected function buildJsValidatorCheckDataType(string $valueJs, string $onFailJs, DataTypeInterface $type) : string
    {
        $widget = $this->getWidget();
        if ($widget->getMultiSelect() === false) {
            return parent::buildJsValidatorCheckDataType($valueJs, $onFailJs, $type);
        } else {
            $partValidator = parent::buildJsValidatorCheckDataType('part', $onFailJs, $type);
            return <<<JS
if ($valueJs !== undefined) {
    $valueJs.toString().split("{$widget->getMultiSelectValueDelimiter()}").forEach(part => {
        $partValidator
    });
}

JS;
        }
    }
}
?>