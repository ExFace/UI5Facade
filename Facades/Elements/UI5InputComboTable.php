<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Exceptions\Widgets\WidgetLogicError;
use exface\Core\DataTypes\UrlDataType;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Exceptions\Facades\FacadeLogicError;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;

/**
 * Generates sap.m.Input with tabular autosuggest and value help.
 * 
 * ## Technical details
 * 
 * ### SelectedKey vs. value
 * 
 * From the official docs: https://openui5.hana.ondemand.com/api/sap.m.Input#overview
 * 
 * The control has the following behavior regarding the selectedKey and value properties:
 * 
 * - On initial loading, if the control has a selectedKey set which corresponds to a matching item, and a set value, the value will be updated to the matching item's text.
 * - If a selectedKey is set and the user types an input which corresponds to an item's text, the selectedKey will be updated with the matching item's key.
 * - If a selectedKey is set and the user types an input which does not correspond to any item's text, the selectedKey will be set to an empty string ("")
 * - If a selectedKey is set and the user selects an item, the selectedKey will be updated to match the selected item's key.
 * - If a selectedKey is bound and the user types before the data is loaded, the user's input will be overwritten by the binding update.
 *
 * @method \exface\Core\Widgets\InputComboTable getWidget()
 *
 * @author Andrej Kabachnik
 *        
 */
class UI5InputComboTable extends UI5Input
{
    const DROPDOWN_WIDTH_MIN = 400;
    
    const DROPDOWN_WIDTH_MAX = 700;
    
    const DROPDOWN_WIDTH_PER_COLUMN = 160;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Value::init()
     */
    protected function init()
    {
        parent::init();
        $widget= $this->getWidget();
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

                        var oInput = oEvent !== undefined ? oEvent.getSource() : sap.ui.getCore().byId('{$this->getId()}');
                        var sText = oInput.getValue();
                        if (sText !== '' && $missingKeyCheckJs){
                            oInput.fireSuggest({suggestValue: {q: sText}});
                            oEvent.cancelBubble();
                            oEvent.preventDefault();
                            return false;
                        }
                        if (sText === '' && $missingKeyCheckJs){
                            oInput.setValueState(sap.ui.core.ValueState.None);
                            oInput._invalidKey = false;
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
        
        // If the combo has filters bound to the value of another widget, check if the current
        // value still fits the filter every time the value of the other widget changes. Do so
        // by calling the silen key lookup. If the current value can still be found with the
        // new filter, nothing changes. If the new filter excludes the current value, the suggest
        // will return an empty result, which will invalidate the field, and the user will need
        // to select another value.
        // The setTimeout() had something to do with async prefills. No sure, if it is still needed
        // as the logic had change a couple of times.
        if ($widget->getTable()->hasFilters()) {
            foreach ($widget->getTable()->getFilters() as $fltr) {
                if ($link = $fltr->getValueWidgetLink()) {
                    $linked_element = $this->getFacade()->getElement($link->getTargetWidget());
                    $linked_element->addOnChangeScript(<<<JS

                setTimeout(function(){
                    var oInput = sap.ui.getCore().byId('{$this->getId()}');
                    var mKey = oInput.getSelectedKey();
                    if (mKey !== undefined && mKey !== null && mKey !== '') {
                        oInput.fireSuggest({$this->buildJsFireSuggestParamForSilentKeyLookup('mKey')});
                    }
                },0);
JS);
                }
            }
        }
        
        // lookup if only a single suggestion is found if the view is shown or the prefill changes
        // TODO idea: also do that if widgets bound to that combo as filters change values (similar as above)
        if ($widget->getAutoSearchSingleSuggestion()) {
            $jsSearchSingleSuggestion = <<<JS
            setTimeout(function(){
                    var oInput = sap.ui.getCore().byId('{$this->getId()}');
                    var mKey = oInput.getSelectedKey();
                    if (mKey == undefined || mKey == null || mKey == '') {
                        oInput.fireSuggest({$this->buildJsFireSuggestParamForSilentKeyLookup('""')});
                    }
            },0);
            
JS;
            
            $this->getController()->addOnShowViewScript($jsSearchSingleSuggestion, false);
            //TODO should we also look for a single suggestion on prefill change?
            //$this->getController()->addOnPrefillDataChangedScript($jsSearchSingleSuggestion);
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
        
        $controller->addMethod('onSuggest', $this, 'oEvent', $this->buildJsDataLoader('oEvent'));
        
        // If there are links to this combo, that point to additional column, we need a lazy load right
        // at the start to make sure all columns are loaded. Otherwise no columns accespt value/text
        // will have empty values.
        $allColumnsRequiredJs = 'false';
        foreach ($widget->getValueLinksToThisWidget() as $link) {
            if ($link->getTargetColumnId() !== $widget->getValueColumnId() && $link->getTargetColumnId() !== $widget->getTextColumnId()) {
                $allColumnsRequiredJs = 'true';
                break;
            }
        }
        
        switch (true) {
            // If the widget value is set explicitly, we either set the key only or the 
            // key and the text (= value of the input)
            case ! $this->isValueBoundToModel() && $value = $widget->getValueWithDefaults():
                if ($widget->getValueText() === null || $widget->getValueText() === '') {
                    $valueJs = '"' . $this->escapeJsTextValue($value) . '"';
                    $valueInitJs = <<<JS

                .{$this->buildJsSetSelectedKeyMethod($valueJs, null, true)}.fireChange({value: {$valueJs}})
JS;
                } else {
                    $valueInitJs = <<<JS

                .{$this->buildJsSetSelectedKeyMethod($this->escapeJsTextValue($value), $widget->getValueText())}
JS;
                }
                break;

            // If the value is to be taken from a model, we need to check if both - key
            // and value are there. If not, the value needs to be fetched from the server.
            // Same goes for the case when some additional columns are required by widget
            // links - the values for these columns need to be fetched to.
            // NOTE: in sap.m.MultiInput there are no tokens yet, so we tell the getter
            // method not to rely on them explicitly!!!
            case $widget->getValueAttribute() !== $widget->getTextAttribute():
                $missingValueJs = <<<JS

                    var sKey = oInput.{$this->buildJsValueGetterMethod(false)};
                    var sVal = oInput.getValue();
                    var bNeedAllCols = {$allColumnsRequiredJs};
                    if (sKey !== '' && (sVal === '' || bNeedAllCols)) {
                        oInput.fireSuggest({$this->buildJsFireSuggestParamForSilentKeyLookup('sKey')});
                    } else {
                        oInput.setValueState(sap.ui.core.ValueState.None);
                        oInput._invalidKey = false;
                    }
JS;
                // Do the missing-text-check every time the model of the sap.m.Input changes
                $valueInitJs = <<<JS

                .attachModelContextChange(function(oEvent) {
                    var oInput = oEvent.getSource();
                    $missingValueJs
                })
JS;
                // Also do the check with every prefill (the model-change-trigger for some reason does not
                // work on non-maximized dialogs, but this check does)
                $this->getController()->addOnPrefillDataChangedScript(<<<JS

                    setTimeout(function(){
                        var oInput = sap.ui.getCore().byId('{$this->getId()}');
                        if (oInput === undefined) {
                            return;
                        }
                        {$missingValueJs} 
                    }, 0);
JS);
                
                // Finally, if the value is bound to model, but the text is not, all the above logic will only
                // work once, because after that one time, there will be a text (value) and it won't change
                // with the model. To avoid this, the following code will empty the value of the input every
                // time the selectedKey changes to empty. This happens at least before every prefill.
                // The logic is different for Input and MultiInput: while Input can just be emptied if 
                // the current selectedKey is empty, the MultiInput selectedKey is also empty if there are
                // only tokens and no non-token text. For the MultiSelect we need to double check if the
                // value in the model is really empty then.
                // NOTE: without setTimeout() the oInput is sometimes not initialized yet when init() of the
                // view is called in dialogs. In particular, this happens if the InputComboTable is a filter
                // in a table, that is the only direct child of a dialog.
                // NOTE: Event with setTimeout() it does not always work because hidden controls may even no
                // be rendered at init(). In this case, the binding is attached to the view model. This should
                // work for most cases too!
                if ($this->isValueBoundToModel() && ! $this->getView()->getModel()->hasBinding($widget, 'value_text')) {
                    $emptyValueWithKeyJs = <<<JS

                    setTimeout(function(){
                        var oInput = sap.ui.getCore().byId('{$this->getId()}');
                        var oModel, oKeyBinding;
                        if (oInput) {
                            oModel = oInput.getModel();
                        } else if (oController) {
                            oModel = oController.getView().getModel();
                        } else {
                            return;
                        }
                        oKeyBinding = new sap.ui.model.Binding(oModel, '{$this->getValueBindingPath()}', oModel.getContext('{$this->getValueBindingPath()}'));
                        oKeyBinding.attachChange(function(oEvent){
                            var sBindingPath, mModelVal;
                            if (! oInput) {
                                return;
                            }
                            if (oInput.getSelectedKey() === '') {
                                if (oInput.getTokens !== undefined) {
                                    sBindingPath = oInput.getBinding('selectedKey').sPath;
                                    mModelVal = oEvent.getSource().getModel().getProperty(sBindingPath);
                                    if (mModelVal === undefined || mModelVal === undefined) {
                                        oInput.destroyTokens();
                                        oInput.setValue('');
                                    }
                                } else {
                                    oInput.setValue('');
                                }
                            }
                        });
                    }, 0);
JS;
                    $this->getController()->addOnInitScript($emptyValueWithKeyJs);
                }
                break;

            // If value and text are the same attribut, there are still issues to fix:
            // - When the inner value (selectedKey) changes programmatically (e.g. with a prefill), the tokes are
            // not updated for some reason - need to do it manually. Previously we updated them manually
            // onPrefillDataChange, but this was not enough - the values still disappeared if the InputComboTable
            // was in a big dialog and that dialog had other buttons, that would cause a refresh on-change.
            // - TODO sometimes the input remains empty even in single-select widgets. For example, if an object
            // has no LABEL and the UID should be used as value and text at the same time. Still need investigation
            // here!
            case $widget->getValueAttribute() === $widget->getTextAttribute() && $widget->getMultiSelect() === true:
                $this->getController()->addOnInitScript(<<<JS

                    (function(oInput){
                        var oKeyBinding = oInput.getBinding('selectedKey');
                        if (! oKeyBinding) {
                            return;
                        }
                        oKeyBinding.attachChange(function(oEvent){
                            var sKeys = oEvent.getSource().getValue();
                            oInput.destroyTokens();
                            if (sKeys !== undefined && sKeys !== null && sKeys !== '') {
                                sKeys.toString().split(',').forEach(function(sVal){
                                    oInput.addToken(new sap.m.Token({key: sVal, text: sVal}));
                                })
                            }
                        });
                    })(sap.ui.getCore().byId('{$this->getId()}'));
JS);
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
        $valueColName = $widget->getValueColumn()->getDataColumnName();
        $textColName = $widget->getTextColumn()->getDataColumnName();
        foreach ($widget->getTable()->getColumns() as $idx => $col) {
            /* @var $element \exface\UI5Facade\Facades\Elements\UI5DataColumn */
            $element = $this->getFacade()->getElement($col);
            $columns .= ($columns ? ",\n" : '') . $element->buildJsConstructorForMColumn();
            $cells .= ($cells ? ",\n" : '') . $element->buildJsConstructorForCell($this->getModelNameForAutosuggest());
            if ($col->getDataColumnName() === $valueColName) {
                $value_idx = $idx;
            }
            if ($col->getDataColumnName() === $textColName) {
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
        $vhpOptions = "showValueHelp: true, valueHelpRequest: {$this->buildJsPropertyValueHelpRequest()}";
        
        // Add some tweaks for sap.m.MultiInput
        $tokenUpdateJs = '';
        if ($widget->getMultiSelect()) {
            //Removing a token does not fire a change event, which means linked widgets do not react to it,
            //therefor so we do it manually in a timeout function.
            $tokenUpdateJs = <<<JS

            .attachTokenUpdate(function(oEvent){
                if (oEvent.getParameters().type !== 'removed') {
                    return;
                }
                        setTimeout(function(){
                            var oInput = sap.ui.getCore().byId('{$this->getId()}'); 
                            var sVal = {$this->buildJsValueGetter()};
                            oInput.fireChange({value: sVal});
                        },0);
            })
JS;
            // Also make sure tokens are destroyed when the bound model value is emptied. This does not happen automatically!
            // In particular, this means, when a dialog is closed and reopened for another instance, the previous value remains
            // in place until the new prefill finished loading. This looks stupid as all the other inputs are emptied right away.
            // The setTimeout() is required to ensure, all bindings are already instantiated 
            $this->getController()->addOnInitScript(<<<JS

            setTimeout(function(){
                var oInput = sap.ui.getCore().byId('{$this->getId()}');
                if (! oInput) {
                    return;
                }
                var oBinding = oInput.getBinding('selectedKey');
                if (! oBinding) {
                    return;
                }
                oBinding.attachChange(function(oEvent){
                    var sVal = oBinding.getValue();
                    if (sVal === undefined || sVal === null || sVal === '') {
                        {$this->buildJsEmpty()};
                    }
                });
            }, 0);
            
JS);
        }
        
        // Render a function to initialize the control. Need to wrap the initialization in
        // a function because the extra value-init stuff gets executed to early if a non-model
        // value is used. In that case, a lookup needs to be done to load all additional data
        // like the value text, other columns, etc. That lookup is triggered by firing a change
        // event. Thus, if a lookup is needed, the change event is fired right upon initialization
        // of this control and while other controls may not have been initialized yet. This causes
        // JS errors if other controls listen to changes of this one. Thus, we use setTimeout() to
        // make sure the value-init logic gets only executed at the end of the current call stack
        // - when all other controls were already initialized.
        // FIXME firing setValue() to trigger initial lookup seems to produce too many server requests.
        // Probably because parsing the result triggers another change event? Need further testing.
        // Test setup: InputComboTable with a static value (key) and at least one other InputComboTable 
        // with a widget link to a column of this InputComboTable other than the key. Ideally, the
        // dependent combo should only load once. It appears to load twice though.
        // IDEA perhaps anohter way to initialize the value would be addOnShowView() on the controller
        return <<<JS
    (function() {
	   var oCombo = new {$control}("{$this->getId()}", {
			{$this->buildJsProperties()}
            {$this->buildJsPropertyType()}
			textFormatMode: "ValueKey",
			showSuggestion: true,
            maxSuggestionWidth: "{$this->buildCssDropdownWidth()}",
            startSuggestion: function(){
                return sap.ui.Device.system.phone ? 0 : 1;
            }(),
            showTableSuggestionValueHelp: true,
            filterSuggests: false,
            suggest: {$this->getController()->buildJsMethodCallFromView('onSuggest', $this, $oControllerJs)},
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
            ],
            {$vhpOptions}
        })
        .setModel(new sap.ui.model.json.JSONModel(), "{$this->getModelNameForAutosuggest()}")
        {$this->buildJsPseudoEventHandlers()};

        setTimeout(function(){
            oCombo
            {$valueInitJs}
            {$tokenUpdateJs}
        }, 0);

        return oCombo;
    })()
JS;
    }
             
    /**
     * 
     * @param int $valueColIdx
     * @param int $textColIdx
     * @return string
     */
    protected function buildJsPropertySuggestionItemSelected(int $valueColIdx, int $textColIdx) : string
    {
        // Remember to trigger the change event here as it is not triggered automatically.
        // The `buildJsSetSelectedKeyMethod()` does not trigger change either!
        // For MultiInput make sure not to add duplicate tokens as this might cause unexpected
        // behavior and is not usefull anyway.
        // The if (aCells.length) is a special treatment for autoselect_single_suggestion - if the single
        // suggestion is selected automatically (see buildJsDataLoader()), aCells is not set yet, so
        // we need to fetch the first row of the suggestion table - in this case we know, that there
        // is only a single row!
        $valueDataType = $this->getWidget()->getValueColumn()->getDataType();
        return <<<JS
            function(oEvent){
                var oItem = oEvent.getParameter("selectedRow");
                if (! oItem) return;
				var aCells = oEvent.getParameter("selectedRow").getCells();
                var oInput = oEvent.getSource();
                var fnValueParser = function(mVal) {
                    return {$this->getFacade()->getDataTypeFormatter($valueDataType)->buildJsFormatParser('mVal')};
                }
                if (oInput.getTokens !== undefined) {
                    if (oInput.getTokens().filter(function(oToken){
                            return oToken.getKey() === aCells[ {$valueColIdx} ].getText();
                        }).length > 0) {
                        return;
                    }
                }
                if (aCells.length === 0) {
                    var oSuggestTable = sap.ui.getCore().byId('{$this->getId()}-popup-table');
                    aCells = oSuggestTable.getItems()[0].getCells();
                }
                oInput.{$this->buildJsSetSelectedKeyMethod("fnValueParser(aCells[ {$valueColIdx} ].getText())", "aCells[ {$textColIdx} ].getText()")};
                oInput.setValueState(sap.ui.core.ValueState.None);
                oInput._invalidKey = false;
                oInput.fireChange({value: aCells[ {$valueColIdx} ].getText()});
			},
JS;
    }
       
    /**
     * Returns the value of the property valueHelpRequest.
     * 
     * @return string
     */
    protected function buildJsPropertyValueHelpRequest($oControllerJs = 'oController') : string
    {
        $btn = $this->getWidget()->getLookupButton();
        /* @var $btnEl \exface\UI5Facade\Facades\Elements\UI5Button */
        $btnEl = $this->getFacade()->getElement($btn);
        
        return <<<JS

            function(oEvent) {
                if (sap.ui.getCore().byId('{$btnEl->getId()}') === undefined) {
                    var oLookupButton = {$btnEl->buildJsConstructor()};
                    {$this->getController()->getView()->buildJsViewGetter($this)}.addDependent(oLookupButton);
                }
                {$btnEl->buildJsClickEventHandlerCall()}
            },

JS;
        
        return $btnEl->buildJsClickViewEventHandlerCall() . ',';
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
     * @param string $oEventJs
     * @return string
     */
    protected function buildJsDataLoader(string $oEventJs = 'oEvent') : string
    {
        $widget = $this->getWidget();
        $configuratorElement = $this->getFacade()->getElement($widget->getTable()->getConfiguratorWidget());
        $serverAdapter = $this->getFacade()->getElement($widget->getTable())->getServerAdapter();
        $delim = json_encode($widget->getMultiSelectValueDelimiter());
        $allowNewValues = $widget->getAllowNewValues() ? 'true' : 'false';
        $autoSelectSingleJs = $widget->getAutoselectSingleSuggestion() ? 'true' : 'false';
        
        // NOTE: in sap.m.MultiInput there are no tokens yet, so we tell the getter
        // method not to rely on the explicitly!!!
        $onSuggestLoadedJs = <<<JS
                            
                var bAutoSelectSingle = {$autoSelectSingleJs};
                var data = oModel.getProperty('/rows');
                var curKey = oInput.{$this->buildJsValueGetterMethod(false)};
                var curText = oInput.getValue();
                var curKeys = curKey.split({$delim});
                var iRowsCnt = parseInt(oModel.getProperty("/recordsTotal"));
                var aFoundKeys = [];
                var bNewKeysAllowed = {$allowNewValues};
                var aNewKeys = [];
                var sMultiValDelim = {$this->escapeString($widget->getMultipleValuesDelimiter())};

                if (bSilent) {
                    if (iRowsCnt === 1 && (curKey === '' || data[0]['{$widget->getValueColumn()->getDataColumnName()}'] == curKey)) {
                        if (oInput.destroyTokens !== undefined) {
                            oInput.destroyTokens();
                        }
                        oInput.{$this->buildJsSetSelectedKeyMethod("data[0]['{$widget->getValueColumn()->getDataColumnName()}']", "data[0]['{$widget->getTextColumn()->getDataColumnName()}']")}
                        oInput.closeSuggestions();
                        oInput.setValueState(sap.ui.core.ValueState.None);
                        oInput._invalidKey = false;
                    } else if (iRowsCnt > 0 && iRowsCnt === curKeys.length && oInput.addToken !== undefined) {
                        oInput.destroyTokens();
                        curKeys.forEach(function(sKey) {
                            sKey = sKey.trim();
                            data.forEach(function(oRow) {
                                if (oRow['{$widget->getValueColumn()->getDataColumnName()}'] == sKey) {
                                    oInput.addToken(new sap.m.Token({key: sKey, text: oRow['{$widget->getTextColumn()->getDataColumnName()}']}));
                                    aFoundKeys.push(sKey);
                                }
                            });
                        });
                        oInput.closeSuggestions();
                        if (aFoundKeys.length === curKeys.length) {
                            oInput.setValueState(sap.ui.core.ValueState.None);
                            oInput._invalidKey = false;
                        } else {
                            aNewKeys = curKeys.filter(function(x) {return !aFoundKeys.includes(x)});
                            if (bNewKeysAllowed && aNewKeys.length > 0) {
                                aNewKeys.forEach(function(sVal) {
                                    oInput.{$this->buildJsSetSelectedKeyMethod('sVal', 'sVal', false)};
                                });
                                oInput.setValueState(sap.ui.core.ValueState.None);
                                oInput._invalidKey = false;
                            } else {
                                oInput
                                .setValueStateText("'" + curKey + "' {$this->translate('WIDGET.INPUTCOMPBOTABLE.ERROR_KEYS_VALUES_MISMATCH')}")
                                .setValueState(sap.ui.core.ValueState.Error);
                                oInput._invalidKey = true;
                            }
                        }
                        oInput.fireChange({value: curKey});
                    } else {
                        switch (true) {
                            case bNewKeysAllowed === true:
                                oInput.setValueState(sap.ui.core.ValueState.None);
                                oInput._invalidKey = false;
                                oInput.{$this->buildJsSetSelectedKeyMethod('curKey', 'curKey', false)};
                                break;
                            case curKey === '' && (! curText || curText.trim() === ''):
                                oInput
                                    .{$this->buildJsEmptyMethod()}
                                    .setValueState(sap.ui.core.ValueState.None);
                                oInput._invalidKey = false;
                                break;
                            // If it is not a MultiInput, but the value is a delimited list, do not use it!
                            case oInput.getTokens === undefined && curKey != null && (curKey + '').includes(sMultiValDelim):
                                oInput
                                    .{$this->buildJsEmptyMethod()}
                                    .setValueState(sap.ui.core.ValueState.None);
                                oInput._invalidKey = false;
                                break;
                            default:
                                oInput
                                    .setValueStateText("'" + (curKey + " " + curText).trim() + "' {$this->translate('WIDGET.INPUTCOMPBOTABLE.ERROR_INVALID_KEY')}")
                                    .setValueState(sap.ui.core.ValueState.Error);
                                oInput._invalidKey = true;
                                break;
                        }
                    }
                }
                {$this->buildJsBusyIconHide()}

                if (oSuggestTable) {
                    oSuggestTable.setBusy(false);
                }

                if (bAutoSelectSingle && iRowsCnt === 1 && (curKey === '' || data[0]['{$widget->getValueColumn()->getDataColumnName()}'] == curKey)) {
                    // For MultiInput make sure to remove eventual duplicate tokens before
                    // Adding the current value
                    if (oInput.getTokens !== undefined) {
                        oInput
                            .getTokens()
                            .filter(function(oToken){
                                return oToken.getKey() === data[0]['{$widget->getValueColumn()->getDataColumnName()}'];
                            })
                            .forEach(function(oToken){
                                oInput.removeToken(oToken);
                            });
                    }

                    oInput.{$this->buildJsSetSelectedKeyMethod("data[0]['{$widget->getValueColumn()->getDataColumnName()}']", "data[0]['{$widget->getTextColumn()->getDataColumnName()}']")}
                    
                    // In MultiSelect curText is the string being searched for, so empty it
                    // otherwise it will remain sitting next to the freshly added token
                    if (oInput.getTokens !== undefined && (curText || '').toString().length > 0) {
                        oInput.setValue();
                    }

                    oInput.setValueState(sap.ui.core.ValueState.None)
                    oInput._invalidKey = false;
                    setTimeout(function(){
                        oInput.closeSuggestions();
                        oInput.fireChange({value: curKey});
                    }, 1);
                }

                // Remove pure-whitespace values, loading the value again before doing so.
                // Otherwise they will remain while still inivisble
                // eventually causing input values consisting of whitespaces.
                curText = oInput.getValue();
                if (curText && curText.trim() === '') {
                    setTimeout(function(){
                        oInput.setValue('');
                    }, 0);
                }
                
                // Force showing suggestion popup because if not done explicitly, it did not show up when typing
                // if the control was the first one in the form and received focus automatically. In that case,
                // the user needed to switch focus set it again before the autosuggest would start showing.
                if (! bSilent) {
                    oInput._openSuggestionPopup();
                }
                
JS;
        
        return <<<JS

                var oInput = {$oEventJs}.getSource();
                var oSuggestTable = sap.ui.getCore().byId('{$this->getId()}-popup-table');
                var q = {$oEventJs}.getParameter("suggestValue");
                var fnCallback = {$oEventJs}.getParameter("onLoaded");
                var qParams = {};
                var bSilent = false;

                if (typeof q == 'object') {
                    qParams = q;
                    bSilent = true;
                } else {
                    qParams.q = q;
                }
                
                // Just space (or multiple) means trigger autosuggest without filtering!
                if (qParams.q && qParams.q.trim() === '') {
                    qParams.q = '';
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
                
                if (fnCallback) {
                    oModel.attachRequestCompleted(function(){
                        fnCallback();
                        oModel.detachRequestCompleted(fnCallback);
                    });
                }

                if (bSilent) {
                    {$this->buildJsBusyIconShow()}
                }

                if (oSuggestTable) {
                    oSuggestTable.setBusyIndicatorDelay(0).setBusy(true);
                }
                
                {$serverAdapter->buildJsServerRequest($widget->getLazyLoadingAction(), 'oModel', 'params', $onSuggestLoadedJs, $this->buildJsBusyIconHide())}

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
     * Returns the JS method to get the current value.
     * 
     * The additional parameter $useTokensIfMultiSelect controls, how sap.m.MultiInput is handled.
     * For some reason it's methods getTokens() and getSelectedKey() are not in sync. So if the
     * tokens are not initialized yet, getSelectedKey() must be used - that's the one that is
     * bound to the model actually.
     * 
     * @param bool $useTokensIfMultiSelect
     * @return string
     * 
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsValueGetterMethod()
     */
    public function buildJsValueGetterMethod(bool $useTokensIfMultiSelect = true)
    {
        if ($this->getWidget()->getMultiSelect() === false || $useTokensIfMultiSelect === false) {
            // TODO alway using `getSelectedKey()` for now as
            // using `getValue()` can lead to not valid values not causing validator to fail when
            // value attribute is the same as text attribute
            /*if ($this->getWidget()->getValueAttribute() === $this->getWidget()->getTextAttribute()) {
                return "getValue()";
            } else {            
                return "getSelectedKey()";
            }*/
            return "getSelectedKey()";
        } else {
            $delim = $this->getWidget()->getMultiSelectTextDelimiter();
            return "getTokens().reduce(function(sList, oToken, iIdx, aTokens){ return sList + (sList !== '' ? '$delim' : '') + oToken.getKey() }, '')";
        }
    }
    
    public function buildJsValueGetter($column = null, $row = null)
    {
        $allowNewValuesJs = $this->getWidget()->getAllowNewValues() ? 'true' : 'false';
        $valueColName = $this->getWidget()->getValueColumn()->getDataColumnName();
        $delim = $this->getWidget()->getMultiSelectTextDelimiter();
        return <<<JS
function(sColName){
    var oInput = sap.ui.getCore().byId('{$this->getId()}');
    var sSelectedKey = oInput.{$this->buildJsValueGetterMethod()};
    var bAllowNewValues = $allowNewValuesJs;
    var oModel, oItem, aRows, oItems, aSelectedKeys;
    if (oInput === undefined) {
        return null;
    }
    
    
    if (sSelectedKey === undefined || sSelectedKey === '' || sSelectedKey === null) {
        if (bAllowNewValues && oInput.getValue()) {
            return oInput.getValue();
        }
        return null;
    }
    
    
    if (sColName === '' || sColName === '$valueColName') {
        return sSelectedKey;
    }
    
    oModel = oInput.getModel('{$this->getModelNameForAutosuggest()}');
    aRows = oModel.getData().rows || [];
    oItems = [];
    aSelectedKeys = sSelectedKey.split('{$delim}');
    
    aSelectedKeys.forEach(function(sKey) {
        aRows.forEach(function(oRow) {
            // don't compare type strict as the selected key is a string and the
            // value attribute in the rows might be an integer
            if (oRow['{$this->getWidget()->getValueAttributeAlias()}'] == sKey) {
                oItems.push(oRow);
            }
        })
    })
    
    return oItems.length === 0 ? undefined : oItems.reduce(function(sList, oItem){
        var val = oItem[sColName];
        if (val === null || val === undefined) {
            return sList;
        }
        return sList + (sList !== '' ? '$delim' : '') + oItem[sColName];
    }, '');
}('$column')

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
        if ($this->getWidget()->getMultipleValuesAllowed()) {
            $keyJs = "'[' + {$keyJs}";
        }
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
        $bUnrenderedJs = $this->isUnrendered() ? 'true' : 'false';
        return "(function(val){
            var oInput = sap.ui.getCore().byId('{$this->getId()}');
            var bUnrendered = $bUnrenderedJs;
            if (bUnrendered === true && ! oInput) {
                return;
            }
            if (val === undefined || val === null || val === '') {
                oInput.{$this->buildJsEmptyMethod('val', '""')};
                oInput.fireChange({value: val});
            } else {
                if (oInput.destroyTokens !== undefined) {
                    oInput.destroyTokens();
                }
                oInput
                .setSelectedKey(val)
                .fireSuggest({$this->buildJsFireSuggestParamForSilentKeyLookup('val')});
            }
            
            return oInput;
        })($valueJs)";
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

    /**
     * {@inheritDoc}
     * @see \exface\UI5FAcade\Facades\Elements\UI5Input::buildJsEmpty()
     */
    public function buildJsEmpty() : string
    {
        return "sap.ui.getCore().byId('{$this->getId()}').{$this->buildJsEmptyMethod()}";
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsEmptyMethod() : string
    {
        if ($this->getWidget()->getMultiSelect() === false) {
            return "setValue('').setSelectedKey('')";
        } else {
            return "setValue('').setSelectedKey('').destroyTokens()";
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
            if ($valueJs === '' || $valueJs === null) {
                $valueJs = 'null';
            }
            $js = "setSelectedKey($keyJs).addToken(new sap.m.Token({key: $keyJs, text: $valueJs}))";
        }
        
        if ($lookupKeyValue === true) {
            $js .= ".fireSuggest({$this->buildJsFireSuggestParamForSilentKeyLookup($keyJs)})";
        }
        
        return $js;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::buildJsValidatorConstraints()
     */
    protected function buildJsValidatorConstraints(string $valueJs, string $onFailJs, DataTypeInterface $type) : string
    {
        $widget = $this->getWidget();
        $validJs = '';
        $delim = $this->getWidget()->getMultiSelectTextDelimiter();
        if ($widget->getAllowNewValues() === false) {
            // check if the vale state is `ERROR` and an actual invalid key is selected, else it could be possible to
            // safe values that are not actually valid
            // we have to actually do a check if the selected value(s) are valid as they might have change via the
            // value helper (lookup) dialog
            $validJs .=<<<JS
var oInput = sap.ui.getCore().byId('{$this->getId()}');
var oModel, aRows, aSelectedKeys;

if(oInput !== undefined && oInput.getValueState() == sap.ui.core.ValueState.Error && oInput._invalidKey === true
    && $valueJs !== undefined && $valueJs !== null && $valueJs !== '') {
    oModel = oInput.getModel('{$this->getModelNameForAutosuggest()}');
    aRows = oModel.getData().rows || [];
    
    //if the current autosuggest model has no rows at all the value can't be valid
    if (aRows.length === 0 ) {
            {$onFailJs}
    }
    aSelectedKeys = $valueJs.split('{$delim}');    
    aSelectedKeys.forEach(function(sKey) {
        aRows.forEach(function(oRow) {
            // don't compare type strict as the selected key is a string and the
            // value attribute in the rows might be an integer
            if (oRow['{$this->getWidget()->getValueAttributeAlias()}'] !== sKey) {
                {$onFailJs}
            }
        })
    })
    oInput._invalidKey === false;
}
JS;
        }
        if ($widget->getMultiSelect() === false) {
            $constraintJS = parent::buildJsValidatorConstraints($valueJs, $onFailJs, $type);
            return $constraintJS . $validJs;
        } else {
            $partValidator = parent::buildJsValidatorConstraints('part', $onFailJs, $type);
            return <<<JS
if ($valueJs !== undefined && $valueJs !== null) {
    $valueJs.toString().split("{$widget->getMultiSelectValueDelimiter()}").forEach(function(part){
        $partValidator
    });
}
{$validJs}

JS;
        }
    }
    
    /**
     * Returns a JS snippet, that can set data given in the same structure as the data getter would produce.
     *
     * This is basically the opposite of buildJsDataGetter(). The input must be valid JS code representing
     * or returning a JS data sheet.
     *
     * For example, this code will extract data from a table and put it into a container:
     * $container->buildJsDataSetter($table->buildJsDataGetter())
     *
     * @param string $jsData
     * @return string
     */
    public function buildJsDataSetter(string $jsData) : string
    {
        $widget = $this->getWidget();
        
        $parentSetter = parent::buildJsDataSetter($jsData);
        $colName = $this->getWidget()->getValueAttributeAlias();
        $delim = $widget->getMultipleValuesDelimiter();
        
        // Make sure to populate the suggest-model in case the data is based on the table-object
        // This is important, so that value- and data-getters can access additional columns
        return <<<JS

(function(oData) {
    var oInput = sap.ui.getCore().byId("{$this->getId()}");
    var mVal;
    var aVals = [];
    if (oData !== undefined && Array.isArray(oData.rows) && (oData.oId == "{$this->getWidget()->getTable()->getMetaObject()->getId()}" || oData.oId === "{$this->getWidget()->getTable()->getMetaObject()->getAliasWithNamespace()}")) {
        if (oData.rows.length === 0) {
            {$this->buildJsValueSetter("''")};
        } else {
            oInput.getModel('{$this->getModelNameForAutosuggest()}').setData(oData);
            if (oData.rows[0]['{$widget->getTextColumn()->getDataColumnName()}'] != undefined){
                oInput.{$this->buildJsEmptyMethod()};
                oData.rows.forEach(function(oRow){
                    oInput.{$this->buildJsSetSelectedKeyMethod("oRow['{$colName}']", "oRow['{$widget->getTextColumn()->getDataColumnName()}']")};
                    aVals.push(oRow['{$colName}']);
                });
                mVal = aVals.join('{$delim}');                
                oInput.fireChange({mValue: mVal});              
            } else {
                if (oData.rows.length === 1) {
                   mVal = oData.rows[0]['{$colName}'];
                } else if (oData.rows.length > 1) {
                    aVals = [];
                    oData.rows.forEach(function(oRow) {
                        aVals.push(oRow['{$colName}']);
                    });
                    mVal = aVals.join('{$delim}');
                }
                {$this->buildJsValueSetter("mVal")}
            }
        } 
    } else {
        $parentSetter;
    }
})({$jsData})

JS;
    }
    
    public function buildJsDataGetter(ActionInterface $action = null)
    {
        $widget = $this->getWidget();
        $dataObj = $this->getMetaObjectForDataGetter($action);
        
        // If the object of the action is the same as that of the widget, treat
        // it as a regular input.
        if ($action === null || $this->getMetaObject()->is($dataObj) || $action->getInputMapper($this->getMetaObject()) !== null) {
            return parent::buildJsDataGetter($action);
        }
        
        // If it's another object, we need to decide, whether to place the data in a 
        // subsheet.
        if ($dataObj->is($widget->getTableObject())) {
            // FIXME not sure what to do if the action is based on the object of the table.
            // This should be really important in lookup dialogs, but for now we just fall
            // back to the generic input logic.
            return parent::buildJsDataGetter($action);
        } elseif ($relPath = $widget->findRelationPathFromObject($dataObj)) {
            $relAlias = $relPath->toString();
        }
        
        if ($relAlias === null || $relAlias === '') {
            throw new WidgetConfigurationError($widget, 'Cannot use data from widget "' . $widget->getId() . '" with action on object "' . $dataObj->getAliasWithNamespace() . '": no relation can be found from widget object to action object', '7CYA39T');
        }
        
        if ($widget->getMultiSelect() === false) { 
            $rows = "[{ {$widget->getDataColumnName()}: {$this->buildJsValueGetter()} }]";
        } else {
            $delim = str_replace("'", "\\'", $this->getWidget()->getMultiSelectTextDelimiter());
            $rows = <<<JS
                            function(){
                                var aVals = ({$this->buildJsValueGetter()} || '').split('{$delim}');
                                var aRows = [];
                                aVals.forEach(function(sVal) {
                                    if (sVal !== undefined && sVal !== null && sVal !== '') {
                                        aRows.push({
                                            {$widget->getDataColumnName()}: sVal
                                        });
                                    }
                                })
                                return aRows;
                            }()

JS;
        }
        
        return <<<JS
        
            {
                oId: '{$dataObj->getId()}',
                rows: [
                    {
                        '{$relAlias}': {
                            oId: '{$widget->getMetaObject()->getId()}',
                            rows: {$rows}
                        }
                    }
                ]
            }
            
JS;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::registerExternalModules()
     */
    public function registerExternalModules(UI5ControllerInterface $controller) : UI5AbstractElement
    {
        $f = $this->getFacade();
        
        foreach ($this->getWidget()->getTable()->getColumns() as $col) {
            $f->getElement($col)->registerExternalModules($controller);
        }
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    protected function buildCssDropdownWidth() : string
    {
        $visibleCols = 0;
        foreach ($this->getWidget()->getTable()->getColumns() as $col) {
            if ($col->isHidden() === false) {
                $visibleCols++;
            }
        }
        return min(
            max(
                self::DROPDOWN_WIDTH_MIN, 
                $visibleCols * self::DROPDOWN_WIDTH_PER_COLUMN
            ), 
            self::DROPDOWN_WIDTH_MAX
        ) . 'px';
    }

    /**
     * 
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::buildJsResetter()
     */
    public function buildJsResetter() : string
    {
        $prepareJs = '';
        if ($this->getWidget()->getMultiSelect() === true) {
            $prepareJs = "sap.ui.getCore().byId('{$this->getId()}').destroyTokens(); ";
        }
        return $prepareJs . parent::buildJsResetter();
    }
}