<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Exceptions\Facades\FacadeUnsupportedWidgetPropertyWarning;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Widgets\Parts\ConditionalProperty;

/**
 * Generates a sap.m.SegmetedButton for InputSelectButtons widget
 *
 * @method \exface\Core\Widgets\InputSelectButtons getWidget()
 * 
 * @author Andrej Kabachnik
 *        
 */
class UI5InputSelectButtons extends UI5InputSelect
{
    /**
     * Collects the data columns of the surrounding table referenced by a condition being built (NULL = not collecting)
     * 
     * @var string[]|null
     */
    private ?array $rowColumnsCollector = null;

    /**
     * Generates the disabled_if of options as JS when the widget is not in a table.
     * 
     * In a table, the condition is evaluated per row via a binding of each item - see `buildJsPropertyEnabled()`.
     * 
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::registerConditionalProperties()
     */
    public function registerConditionalProperties() : UI5AbstractElement
    {
        parent::registerConditionalProperties();
        
        $widget = $this->getWidget();
        if ($this->isUnrendered() || $widget->isInTable()) {
            return $this;
        }
        
        foreach ($widget->getOptionsDisabledIf() as $optionKey => $condProp) {
            $disableJs = $this->buildJsSetOptionEnabled((string) $optionKey, false);
            $enableJs = $this->buildJsSetOptionEnabled((string) $optionKey, true);
            $this->registerConditionalPropertyUpdaterOnLinkedElements($condProp, $disableJs, $enableJs);
            $js = $this->buildJsConditionalProperty($condProp, $disableJs, $enableJs, true);
            $this->getController()
                ->addOnInitScript($js)
                ->addOnPrefillDataChangedScript($js);
        }
        
        return $this;
    }

    /**
     * Enables or disables a single option without touching its selection state.
     * 
     * A selected option that gets disabled stays selected - UI5 renders it as selected and disabled.
     * 
     * @param string $optionKey
     * @param bool $enabled
     * @return string
     */
    protected function buildJsSetOptionEnabled(string $optionKey, bool $enabled) : string
    {
        $keyJs = json_encode($optionKey);
        $enabledJs = $enabled ? 'true' : 'false';
        return "(sap.ui.getCore().byId('{$this->getId()}')?.getItems() || []).forEach(function(oItem){ if (oItem.getKey() === {$keyJs}) { oItem.setEnabled({$enabledJs}); } })";
    }

    /**
     * Generates the `enabled` property of an option item, evaluating its disabled_if for every table row.
     * 
     * The property is bound to all columns of the same table used in the condition, so UI5 re-evaluates it
     * whenever the row is (re-)rendered or one of these values changes. This works on the cloned cell
     * controls without any DOM lookups or row selection.
     * 
     * @param string $optionKey
     * @return string
     */
    protected function buildJsPropertyEnabled(string $optionKey) : string
    {
        $widget = $this->getWidget();
        $condProp = $widget->getOptionDisabledIf($optionKey);
        if ($condProp === null || $widget->isInTable() === false) {
            return '';
        }
        
        return 'enabled: ' . $this->buildJsRowConditionBinding($condProp, true) . ',';
    }

    /**
     * Binds the visibility of the control to the widget's own hidden_if - evaluated per table row.
     * 
     * Outside of tables the inherited logic of `registerConditionalProperties()` takes care of hidden_if.
     * 
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsPropertyVisibile()
     */
    protected function buildJsPropertyVisibile()
    {
        if ($this->isHiddenIfEvaluatedPerRow() && $this->isVisible()) {
            $condProp = $this->getWidget()->getHiddenIf();
            return 'visible: ' . $this->buildJsRowConditionBinding($condProp, true) . ',';
        }
        return parent::buildJsPropertyVisibile();
    }

    /**
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::isHiddenIfEvaluatedPerRow()
     */
    protected function isHiddenIfEvaluatedPerRow() : bool
    {
        $widget = $this->getWidget();
        return $widget->isInTable() && $widget->getHiddenIf() !== null;
    }

    /**
     * Generates a UI5 binding, which evaluates a conditional property for every table row.
     * 
     * The binding uses all columns of the same table used in the condition as parts, so UI5 re-evaluates it
     * whenever the row is (re-)rendered or one of these values changes. This works on the cloned cell
     * controls without any DOM lookups or row selection.
     * 
     * @param ConditionalProperty $condProp
     * @param bool $negate TRUE if the bound property is the opposite of the condition (e.g. `enabled` for `disabled_if`)
     * @return string
     */
    protected function buildJsRowConditionBinding(ConditionalProperty $condProp, bool $negate) : string
    {
        $widget = $this->getWidget();
        $this->rowColumnsCollector = [];
        try {
            $conditionJs = $this->buildJsConditionalPropertyIf($condProp->getConditionGroup());
            $columns = array_values($this->rowColumnsCollector);
        } finally {
            $this->rowColumnsCollector = null;
        }
        
        // A binding needs at least one part, so conditions without row references are re-evaluated with the row
        if (empty($columns)) {
            $columns = [$widget->getDataColumn()->getDataWidget()->getUidColumn()->getDataColumnName()];
        }
        $partsJs = implode(', ', array_map(function($col){ return '{path: ' . json_encode($col) . '}'; }, $columns));
        $columnsJs = json_encode($columns);
        $resultJs = $negate ? "!({$conditionJs})" : "!!({$conditionJs})";
        
        return <<<JS
{
                        parts: [{$partsJs}],
                        formatter: function() {
                            var aVals = arguments;
                            var oRow = {};
                            {$columnsJs}.forEach(function(sCol, i){ oRow[sCol] = aVals[i]; });
                            return {$resultJs};
                        }
                    }
JS;
    }


    /**
     * Uses the values of the current row for references to columns of the surrounding table.
     * 
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\JsConditionalPropertyTrait::buildJsConditionalPropertyValue()
     */
    protected function buildJsConditionalPropertyValue(ExpressionInterface $expr, ConditionalProperty $conditionalProperty) : string
    {
        $dataWidget = $this->rowColumnsCollector !== null ? $this->getWidget()->getDataColumn()?->getDataWidget() : null;
        if ($dataWidget !== null && $expr->isReference()) {
            $link = $expr->getWidgetLink($conditionalProperty->getWidget());
            $coord = $link->getTargetCoordinate();
            if ($link->getTargetWidget() === $dataWidget
                && $coord !== null
                && $coord->getColumnCoordinate() !== null
                && $coord->getRowCoordinate() === null
                && ! $coord->hasAggregator()
            ) {
                $col = $coord->findColumn($dataWidget);
                if ($col !== null) {
                    $name = $col->getDataColumnName();
                    $this->rowColumnsCollector[$name] = $name;
                    return 'oRow[' . json_encode($name) . ']';
                }
            }
        }
        return parent::buildJsConditionalPropertyValue($expr, $conditionalProperty);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5InputSelect::buildJsConstructorForMainControl()
     */
    public function buildJsConstructorForMainControl($oControllerJs = 'oController')
    {
        if ($this->getWidget()->getMultiSelect()) {
            throw new FacadeUnsupportedWidgetPropertyWarning('Widget InputSelectButtons currently does not support multi_select in UI5!');
        }
        return <<<JS
        (function(){
            var oSegmetedBtn = new sap.m.SegmentedButton("{$this->getId()}", {
    			{$this->buildJsProperties()}
            });
            oSegmetedBtn.setValueState = function(state) {
                if (state == 'Error') {
                    $('#{$this->getId()}').find(".sapMSegBBtnInner").each(function(index, el) {el.classList.add('segmentedButtonsError')})
                } else {
                    $('#{$this->getId()}').find(".sapMSegBBtnInner").each(function(index, el) {el.classList.remove('segmentedButtonsError')})
                }
            };
            oSegmetedBtn.setValueStateText = function(text) {
                return;
            };
            {$this->buildJsOnOptionSelectedSetup('oSegmetedBtn', $oControllerJs)}
            return oSegmetedBtn;
        })()
        {$this->buildJsPseudoEventHandlers()}
JS;
    }

    /**
     * Wires the hidden buttons defined via `on_option_selected` to their options.
     * 
     * A `press` handler on every segment triggers the matching button (if any), honoring the
     * per-option `lock` behavior:
     * 
     * - `never`: the button fires on every press of the option.
     * - `until_selection_change`: the button fires once and is locked until a different option is pressed.
     * 
     * ## How the button gets its input data
     * 
     * A `Button` normally collects its own input data from its "input widget" - the widget it
     * belongs to. For a cell button that input widget is the surrounding data widget (table),
     * so by default the action would run on the table's *selected* row(s), not on the row the
     * user actually toggled. That is why one might intuitively expect to select (and later
     * deselect) the row before triggering the button.
     * 
     * We avoid touching the table selection entirely. Instead we read the toggled row directly
     * from the segmented button's UI5 binding context (`getBindingContext().getObject()` yields
     * the row object of that very row) and inject it into the button's click function as
     * pre-built `requestData`. When `buildJsClickFunction()` receives `requestData`, it skips
     * its own input-widget collection and uses exactly what we pass. Hence: no row selection,
     * no side effects on the table - the action simply operates on the row that was toggled.
     * 
     * This depends on where the widget lives:
     * 
     * - **Cell inside a data widget row** (e.g. a DataTable): input data = the toggled row
     * (see above). The button is constructed once on the *view* (not the per-row cell
     * template), so it keeps a stable, resolvable control id - the action looks up its own
     * view/controller via that id - and it is not cloned per row.
     * - **Anywhere else** (e.g. a dialog): no row context exists, so the button keeps its
     * default behavior and derives input data from the surrounding container. It is registered
     * as a hidden dependent and triggered via its controller handler.
     * 
     * ## Why the config is a shared closure but the lock state lives on the control
     * 
     * In a table the segmented button is a *template* that UI5 clones once per row. Cloning
     * copies managed aggregations and event-handler registrations, but NOT ad-hoc JS instance
     * properties. So the option config (`oOptionCfg`) and buttons are kept in a closure shared
     * by all clones (they are stateless - the row is passed in as an argument), while the lock
     * state (`_exfLockedKey`) is stored lazily on each control instance at event time so every
     * row locks independently.
     * 
     * @param string $oControlJs
     * @param string $oControllerJs
     * @return string
     */
    protected function buildJsOnOptionSelectedSetup(string $oControlJs, string $oControllerJs) : string
    {
        $widget = $this->getWidget();
        $buttons = $widget->getOnOptionSelected();
        if (empty($buttons)) {
            return '';
        }

        $isCell = $widget->isInTable();
        $objIdJs = json_encode($widget->getMetaObject()->getId());
        $lockUntilChangeJs = json_encode($widget::LOCK_UNTIL_SELECTION_CHANGE);

        $dependentsJs = '';
        $configJs = '';
        $i = 0;
        foreach ($buttons as $optionKey => $button) {
            /** @var \exface\UI5Facade\Facades\Elements\UI5Button $btnEl */
            $btnEl = $this->getFacade()->getElement($button);
            $keyJs = json_encode((string) $optionKey);
            $lockJs = json_encode($widget->getOnOptionSelectedLock((string) $optionKey));

            $btnVar = 'oExfOptBtn' . $i;
            if ($isCell) {
                // Row context: construct the button once on the view so it keeps a stable,
                // resolvable control id (the action looks up its view/controller by that id)
                // and is not cloned per row.
                $dependentsJs .= <<<JS

            var {$btnVar} = {$btnEl->buildJsConstructor($oControllerJs)};
            {$oControllerJs}.getView().addDependent({$btnVar});
JS;
                // Inject the toggled row as the action's input data. The row is NOT read here -
                // it arrives as the `oRow` parameter when fnOnOptionSelected() calls trigger(oRow)
                // below. Passing `requestData` makes buildJsClickFunction() skip its default
                // input-widget collection, so the action runs on this exact row without touching
                // the table selection. `oId` is the row object's meta object id (cell object = row).
                $requestDataJs = "{ oId: {$objIdJs}, rows: (oRow !== undefined && oRow !== null ? [oRow] : []) }";
                $triggerJs = "function(oRow) { {$btnEl->buildJsClickFunction(null, $requestDataJs)}; }";
            } else {
                // Container context: register the button as a hidden dependent and trigger it via
                // its controller handler, which collects input data from the input widget itself.
                // There is no row context here, so `oRow` is ignored.
                $dependentsJs .= <<<JS

            var {$btnVar} = {$btnEl->buildJsConstructor($oControllerJs)};
            {$oControlJs}.addDependent({$btnVar});
JS;
                $triggerJs = "function(oRow) { {$btnEl->buildJsClickEventHandlerCall($oControllerJs)} }";
            }

            $configJs .= "\n            oOptionCfg[{$keyJs}] = { lock: {$lockJs}, trigger: {$triggerJs} };";
            $i++;
        }

        return <<<JS

            (function(){
                // Shared config for all cloned rows: keyed by option, each entry has the lock
                // behavior and a stateless `trigger(oRow)` that fires the matching hidden button.
                var oOptionCfg = {};
                {$dependentsJs}
                {$configJs}
                // Called on every segment press. `oCtrl` is the pressed segmented button control
                // (the per-row clone in a table, or the single control in a dialog).
                var fnOnOptionSelected = function(oCtrl, sKey) {
                    var oCfg = oOptionCfg[sKey];
                    if (oCfg === undefined) {
                        return;
                    }
                    // Lock state is kept per control instance so each table row locks on its own.
                    if (oCtrl._exfLockedKey === undefined) {
                        oCtrl._exfLockedKey = null;
                    }
                    // Toggling a different option releases the previous lock.
                    if (oCtrl._exfLockedKey !== null && oCtrl._exfLockedKey !== sKey) {
                        oCtrl._exfLockedKey = null;
                    }
                    // Same option still locked (until_selection_change): do nothing.
                    if (oCtrl._exfLockedKey === sKey) {
                        return;
                    }
                    // Read the row this control is bound to. In a table each clone has its own
                    // binding context, so getObject() yields exactly the toggled row; in a dialog
                    // there is usually no binding context and oRow stays undefined (ignored).
                    var oRow, oBindingCtx = (oCtrl.getBindingContext ? oCtrl.getBindingContext() : null);
                    if (oBindingCtx) {
                        oRow = oBindingCtx.getObject();
                    }
                    // Hand the row to the trigger function as its `oRow` argument (the trigger
                    // built above): in cell mode it is injected as requestData.rows, in dialog mode ignored.
                    oCfg.trigger(oRow);
                    if (oCfg.lock === {$lockUntilChangeJs}) {
                        oCtrl._exfLockedKey = sKey;
                    }
                };
                // Attach to every segment. Using the event source's parent (not the closure var)
                // resolves to the correct control - the per-row clone in a table.
                {$oControlJs}.getItems().forEach(function(oItem){
                    oItem.attachPress(function(oEvent){
                        var oSrc = oEvent.getSource();
                        fnOnOptionSelected(oSrc.getParent(), oSrc.getKey());
                    });
                });
            })();
JS;
    }


			
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5InputSelect::buildJsPropertyItems()
     */
    protected function buildJsPropertyItems() : string
    {
        $items = '';
        foreach ($this->getWidget()->getSelectableOptions() as $key => $value) {
            $items .= <<<JS
                new sap.m.SegmentedButtonItem({
                    key: "{$key}",{$this->buildJsPropertyEnabled((string) $key)}
                    text: "{$value}"
                }),
JS;
        }
        
        return <<<JS
            items: [
                {$items}
            ],
JS;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5InputSelect::buildJsPropertyChange()
     */
    protected function buildJsPropertyChange()
    {
        return 'selectionChange: ' . $this->getController()->buildJsEventHandler($this, self::EVENT_NAME_CHANGE, true) . ',';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::buildJsPropertyRequired()
     */
    protected function buildJsPropertyRequired()
    {
        return '';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::buildJsPropertyEditable()
     */
    protected function buildJsPropertyEditable()
    {
        return '';
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::buildJsSetRequired()
     */
    protected function buildJsSetRequired(bool $required) : string
    {
        $val = $required ? 'true' : 'false';
        if ($this->isLabelRendered() === true || $this->getRenderCaptionAsLabel()) {
            if (! ($this->getWidget()->getHideCaption() === true || $this->getWidget()->isHidden())) {
                $requireLabelJs = "sap.ui.getCore().byId('{$this->getIdOfLabel()}')?.setRequired($val);";
            }
        }
        return <<<JS
        
var oElem = sap.ui.getCore().byId('{$this->getId()}');
if (oElem !== undefined && oElem !== null) {
    sap.ui.getCore().byId('{$this->getId()}')._exfRequired = {$val};
}
$requireLabelJs

JS;
    }
    
    protected function buildJsRequiredGetter() : string
    {
        $val = $this->getWidget()->isRequired() ? 'true' : 'false';
        return "sap.ui.getCore().byId('{$this->getId()}')?._exfRequired || {$val}";
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5InputSelect::buildJsValueSetterMethod()
     */
    public function buildJsValueSetter($value)
    {
        return <<<JS

            (function(mVal) {
                var oCtrl = sap.ui.getCore().byId('{$this->getId()}');
                oCtrl.setSelectedKey({$value});
                oCtrl.fireSelectionChange({item: oCtrl.getSelectedItem()});
            })($value);
JS;
    }
}