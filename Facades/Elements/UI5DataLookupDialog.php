<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Interfaces\Widgets\iHaveHeader;
use exface\Core\Interfaces\Widgets\iSupportMultiSelect;
use exface\Core\Factories\ActionFactory;
use exface\Core\Actions\UpdateData;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Widgets\Data;
use exface\Core\Widgets\DataColumn;
use exface\Core\Exceptions\Facades\FacadeRuntimeError;

/**
 * The `DataLookupDialog` is a `ValueHelpDialog` which may be used to search for values from `DataTables`.
 * On opening a `DataLookupDialog` a new `Dialog` is being rendered, containing a `DataTable` to select
 * one (or multiple) items from. It's apperance and functionallity is based on UI5's ValueHelpDialog.
 * 
 * It's features include:
 *  - a basic searchbar, extended search and filters
 *  - a panel at the bottom of the dialog, displaying the current selection of items in a tokenized form
 *  
 * 
 * @method DataLookupDialog getWidget()
 * @author tmc
 *
 */
class UI5DataLookupDialog extends UI5Dialog 
{
    const EVENT_NAME_TOKEN_UPDATE = 'tokenUpdate';
    
    private $tokenNameColumn = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::init()
     */
    protected function init()
    {
        parent::init();
        $dialog = $this->getWidget();
        if ($dialog->getHideHeader() === null) {
            $dialog->setHideHeader(true);
        }
        $table = $this->getWidget()->getDataWidget();
        $table->setHideCaption(true);
        
        if ($table instanceof iHaveHeader) {
            $this->getWidget()->getDataWidget()->setHideHeader(false);
        }
        
        // Make sure, a label column exists, so the label can be used in the selection-chips
        if ($table->getMetaObject()->hasLabelAttribute()) {
            $labelColExists = false;
            foreach ($table->getColumns() as $col) {
                if ($col->isBoundToAttribute() && $col->getAttribute()->isLabelForObject()) {
                    $this->tokenNameColumn = $col;
                    $labelColExists = true;
                    break;
                }
            }
            if ($labelColExists === false) {
                $labelAttr = $table->getMetaObject()->getLabelAttribute();
                if (! $table->hasAggregations() || $table->hasAggregationOverAttribute($labelAttr)) {
                    $this->tokenNameColumn = $table->createColumnFromAttribute($labelAttr);
                    $table->addColumn($this->tokenNameColumn);
                    //TODO data for added Label Column might be not loaded by the datasheet because column not part of the widget
                    //Shouldn't we use the attribute defined as label in the Widget calling the Lookup Dialog?
                } else {
                    $this->tokenNameColumn = $table->getColumns()[0];
                }
            }
        } elseif ($table->hasColumns()) {
            $this->tokenNameColumn = $table->getColumns()[0];
        } else {
            throw new FacadeRuntimeError('Cannot render lookup dialog "' . $this->getWidget()->getId() . '" - no columns found!');
        }
        
        return;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Dialog::buildJsDialog()
     */
    protected function buildJsDialog()
    {
        $widget = $this->getWidget();
        $icon = $widget->getIcon() ? 'icon: "' . $this->getIconSrc($widget->getIcon()) . '",' : '';
                    
            // If the dialog requires a prefill, we need to load the data once the dialog is opened.
            if ($this->needsPrefill()) {
                $prefill = <<<JS
                
            beforeOpen: function(oEvent) {
                var oDialog = oEvent.getSource();
                var oView = {$this->getController()->getView()->buildJsViewGetter($this)};
                {$this->getController()->buildJsMethodCallFromController(UI5Dialog::CONTROLLER_METHOD_PREFILL, $this, 'oView')}
            },
            
JS;
            } else {
                $prefill = '';
            }
            
            // Finally, instantiate the dialog
            return <<<JS
            
        new sap.m.Dialog("{$this->getId()}", {
			{$icon}
            contentHeight: "80%",
            contentWidth: "70%",
            stretch: jQuery.device.is.phone,
            title: {$this->escapeString($this->getCaption())},
			buttons : [ {$this->buildJsDialogButtons(false)} ],
			content : [ {$this->buildJsDialogContent()} ],
            afterOpen: function () {
                const oInputCombo = sap.ui.getCore().byId("{$this->getFacade()->getElement($this->getWidget()->getParent()->getParent())->getId()}");
                const oMultiInput = sap.ui.getCore().byId("{$this->getDialogContentPanelTokenizerId()}");
                const tokens = oInputCombo.getTokens();
                if (tokens) {
                    oMultiInput.setTokens(oInputCombo.getTokens());
                    oMultiInput.fireTokenUpdate({
                        type: sap.m.Tokenizer.TokenUpdateType.Added,
                        addedTokens: oInputCombo.getTokens()
                    });
                }
            },
            {$prefill}
		}).addStyleClass('{$this->buildCssElementClass()}')
JS;
    }
    
    public function buildCssElementClass()
    {
        return 'exf-datalookup';
    }
    
    /**
     * This Function generates the JS code for the dialog's content aggregation.
     * 
     * It consists of an `sap.m.Splitter` element, which splits the content itself into an
     * area where the DataTable is located in, and a Panel which is used for showing the currently 
     * selected items. The second is only being initalized, if the DataTable is using multiselect.
     * @return string
     */
    protected function buildJsDialogContent() : string
    {
        return <<<JS

                new sap.ui.layout.Splitter({
                    orientation: "Vertical",
                    height: "100%",
                    contentAreas: [
                        {$this->buildJsDialogContentChildren()},
                        {$this->buildJsDialogSelectedItemsPanel()}
                    ]
                })
JS;
    }
    
    /**
     * This function returns the JS code of the dialog's children Widgets, which are to be rendered.
     * Typically, those just consist of a DataTable.
     * 
     * @return string
     */
    protected function buildJsDialogContentChildren() : string
    {
        $this->attachEventHandlersToElements();
        $childrenJs = $this->buildJsLayoutConstructor();
        return $childrenJs;
    }
    
    protected function attachEventHandlersToElements() : UI5DataLookupDialog
    {
        foreach ($this->getWidget()->getWidgets() as $widget) {
            
            // if the widget is the DataTable, and it uses Multiselect attatch the handlers for the SelectedITems panel
            if ($widget instanceof iSupportMultiSelect && $this->getWidget()->getMultiSelect() === true){
                $this->getFacade()->getElement($widget)->addOnChangeScript($this->buildJsSelectionChangeHandler());
                $this->getController()->addOnEventScript($this, self::EVENT_NAME_TOKEN_UPDATE, $this->buildJsTokenChangeHandler('oEvent'));
            }
            $tableElement = $this->getFacade()->getElement($widget);
            $dialog = $this->getWidget();
            $hideHeader = true;
            if ($dialog->getHideHeader() === false) {
                $hideHeader =  false;
            }
            $tableElement->setDynamicPageHeaderCollapsed($hideHeader);
            $tableElement->setDynamicPageShowToolbar(true);
        }
        return $this;
    }
    
    /**
     * This fucntion generates the JS-code for the 'Selected Items' panel.
     * This expandable panel uses a `sap.m.Tokenizer` for displaying the current selection of items.
     * Therefore this panel only is generated when the table this dialog referrs to is using multiselect.
     * 
     * There is almost no program logic in this part of the code, the tokenizer is working by handlers
     * in the `DataTable`-element of the dialog.
     * 
     * @return string
     */
    protected function buildJsDialogSelectedItemsPanel() : string
    {
        if ($this->getWidget()->getMultiSelect() !== true){
            return '';
        }
        
        $table = $this->getWidget()->getDataWidget();
        $tableElement = $this->getFacade()->getElement($table);
        
        $splitterId = $this->getDialogContentPanelSplitterLayoutId();
        
        return <<<JS
            new sap.m.Panel( "{$this->getDialogContentPanelId()}",
                {
                    expandable: true,
                    expandAnimation: false,
                    expanded: true,
                    height: "100%",
                    headerToolbar: [
                        new sap.m.OverflowToolbar({
                            content: [
                                new sap.m.Text("{$this->getDialogContentPanelItemCounterId()}",
                                {
                                    text: "{$this->translate('WIDGET.DATALOOKUPDIALOG.SELECTED_ITEMS')}"
                                })
                            ]
                        })
                    ],
                    content: [
                        new sap.m.HBox({
                            width: "100%",
                            alignItems: "Center",
                            fitContainer: true,
                            items: [
                                new sap.m.MultiInput("{$this->getDialogContentPanelTokenizerId()}",
                                    {
                                    width: "100%",
                                    showValueHelp: true,
                                    valueHelpOnly: true,
                                    showSuggestion: false,
                                    showTableSuggestionValueHelp: false,
                                    layoutData: [
                                        new sap.m.FlexItemData({
                                            growFactor: 1
                                        })
                                    ],
                                    tokenUpdate: {$this->getController()->buildJsEventHandler($this, self::EVENT_NAME_TOKEN_UPDATE, true)},
                                }).addStyleClass('exf-datalookup-tokenizer'),
                                new sap.m.Button("{$this->getDialogContentPanelTokenizerClearButtonId()}",
                                {
                                    icon: "sap-icon://sys-cancel",
                                    type: "Transparent",
                                    enabled: false,
                                    layoutData: [
                                        new sap.m.FlexItemData({
                                            growFactor: 0
                                        })
                                    ],
                                    press: function(){
                                        sap.ui.getCore().byId("{$this->getDialogContentPanelTokenizerId()}").removeAllTokens();
                                        sap.ui.getCore().byId("{$tableElement->getId()}").removeSelections().fireSelectionChange();
                                    }
                                })
                            ]
                        })
                    ],
                    layoutData: [
                        new sap.ui.layout.SplitterLayoutData("{$splitterId}", {
                            //size: "5rem",
                            size: "6.5rem",
                            resizable: false
                        })
                    ]
                }).attachExpand(function(){
                    // resize on expanding / collapsing to allow the table to utilize as much space as possible
                    if (this.getExpanded() == true){
                        //sap.ui.getCore().byId('{$splitterId}').setSize("5rem");
                        sap.ui.getCore().byId('{$splitterId}').setSize("6.5rem");
                    } else {
                        //sap.ui.getCore().byId('{$splitterId}').setSize("2.1rem");
                        sap.ui.getCore().byId('{$splitterId}').setSize("3.0rem");
                    }
                })
JS;
    }
    
    /**
     * 
     * @return string
     */
    protected function getDialogContentPanelId() : string
    {
        return $this->getId() . '_' . 'SelectedItemsPanel';
    }
    
    /**
     * 
     * @return string
     */
    protected function getDialogContentPanelSplitterLayoutId() : string
    {
        return $this->getDialogContentPanelId() . '_' . 'SplitterLayoutData';
    }
    
    /**
     * 
     * @return string
     */
    protected function getDialogContentPanelTokenizerId() : string
    {
        return $this->getDialogContentPanelId() . '_' . 'Tokenizer';
    }
    
    /**
     * 
     * @return string
     */
    protected function getDialogContentPanelTokenizerClearButtonId() : string
    {
        return $this->getDialogContentPanelId() . '_' . 'TokenizerClearButton';
    }
    
    protected function getDialogContentPanelItemCounterId() : string
    {
        return $this->getDialogContentPanelId() . '_' . 'ItemCounter';
    }
    
    /**
     * This function generates the JS-code for the children of the Dialog. It is setting up the
     * properties for the table elements too. 
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Container::buildJsChildrenConstructors()
     */
    public function buildJsChildrenConstructors(array $widgets = null) : string
    {
        $js = '';
        foreach ($this->getWidget()->getWidgets() as $widget) {
            
            // if the widget is the DataTable, and it uses Multiselect attatch the handlers for the SelectedITems panel
            if ($widget instanceof iSupportMultiSelect && $this->getWidget()->getMultiSelect() === true){
                $this->getFacade()->getElement($widget)->addOnRefreshScript($this->buildJsTableRefreshHandler());
                $this->getFacade()->getElement($widget)->addOnChangeScript($this->buildJsSelectionChangeHandler());
                $this->getController()->addOnEventScript($this, self::EVENT_NAME_TOKEN_UPDATE, $this->buildJsTokenChangeHandler('oEvent'));
            }
            $tableElement = $this->getFacade()->getElement($widget);
            $dialog = $this->getWidget();
            $hideHeader = true;
            if ($dialog->getHideHeader() === false) {
                $hideHeader =  false;
            }
            $tableElement->setDynamicPageHeaderCollapsed($hideHeader);
            $tableElement->setDynamicPageShowToolbar(true);
            $js .= ($js ? ",\n" : '') . $tableElement->buildJsConstructor();
        }        
        return $js;
    }
    
    protected function buildJsTokenChangeHandler(string $oEventJs) : string
    {
        $table = $this->getWidget()->getDataWidget();
        $tableElement = $this->getFacade()->getElement($table);
        
        return <<<JS
                var oMultiInput = $oEventJs.getSource();
                var oEventParams = $oEventJs.getParameters();
                var aRemovedTokens = oEventParams['removedTokens'] || [];
                var aAddedTokens = oEventParams['addedTokens'] || [];
                var iItemCounter = oMultiInput.getTokens().length + aAddedTokens.length - aRemovedTokens.length;
                var sItemCounterText = '{$this->translate('WIDGET.DATALOOKUPDIALOG.SELECTED_ITEMS')} (' + iItemCounter + ')';
                
                aRemovedTokens.forEach(function(oToken){
                    var sKey = oToken.getKey();
                    {$tableElement->buildJsSelectRowByValue($table->getUidColumn(), 'sKey', '', 'rowIdx', true)}
                });

                sap.ui.getCore().byId("{$this->getDialogContentPanelItemCounterId()}").setText(sItemCounterText);
    
                // disable the remove-selection button when no selection is made
                var oMultiInputClearButton = sap.ui.getCore().byId("{$this->getDialogContentPanelTokenizerClearButtonId()}");
                if (iItemCounter == 0){
                    oMultiInputClearButton.setEnabled(false);
                } else {
                    oMultiInputClearButton.setEnabled(true);
                }

JS;
    }


    protected function buildJsTableRefreshHandler(): string
    {
        $table = $this->getWidget()->getDataWidget();
        $tableElementId = $this->getFacade()->getElement($table)->getId();

        return <<<JS
        const sId = "{$this->getWidget()->getDataWidget()->getUidColumn()->getDataColumnName()}";
        var oMultiInput =  sap.ui.getCore().byId("{$this->getDialogContentPanelTokenizerId()}");

        const oTable = sap.ui.getCore().byId("{$tableElementId}");
        const items = oTable.getItems();
        const tokens = oMultiInput.getTokens();
        
        items.forEach(item => {
            const bExistInTokens = tokens.some(token => token.getKey() === item.getBindingContext().getObject()[sId]);
            oTable.setSelectedItem(item, bExistInTokens);
        });

        const newSelectedObjetcs = [];

        (oTable._selectedObjects || []).forEach(object => {
            if (tokens.some(token => token.getKey() === object[sId])) {
                newSelectedObjetcs.push(object);
            }
        });

        oTable._selectedObjects = newSelectedObjetcs;

JS;

    }
    
    /**
     * This function generates the JS-code for the handler of the event onChange on the LookupDialog's `DataTable`.
     * It is responsible for most of the logic for the tokenizer in the 'SelectedItems' panel.
     * 
     * Creating the tokens works as follows:
     * First the important values (label and ID) of the currently selected items are getting extracted from the table.
     * Then for every row of selected elements, a token is created, it's key being the UID value of a row,
     * and the value yeilding the label value of the row. If there is no label attribute is set for the current object,
     * it will just use the UID-Attribute.
     * In addition to this, on creation of the tokens, another value is stored in their `CustomData`, this being the 
     * ID of the table. This value is used to determinate the table, on which the deletion of the selection is to be fired on,
     * when a Token is deleted or when the 'delete-all' button in the 'Selected Items' panel is pressed.
     * 
     * @return string
     */
    protected function buildJsSelectionChangeHandler() : string
    {
        $table = $this->getWidget()->getDataWidget();
        $tableElement = $this->getFacade()->getElement($table);
        
        $idAttributeAlias = $table->getMetaObject()->getUidAttributeAlias();
        $labelColName = $this->getTokenNameColumn()->getDataColumnName();
        
        $dataGetterJs = $tableElement->buildJsDataGetter(ActionFactory::createFromString($this->getWorkbench(), UpdateData::class));
        
        return <<<JS

            var oMultiInput =  sap.ui.getCore().byId("{$this->getDialogContentPanelTokenizerId()}");
            if (! oMultiInput) {
                return;
            }
            
            var aNewTokens = [];

            var aSelection = {$dataGetterJs};
            var aAllRows = sap.ui.getCore().byId("{$tableElement->getId()}").getModel().getData().rows;
            var aRows =  aSelection.rows;

            // Create tokens for every selected row
            var aSelectedIds = {$tableElement->buildJsValueGetter($idAttributeAlias)};
            var aSelectedLables = {$tableElement->buildJsValueGetter("{$labelColName}")};

            aRows.forEach(function(oRow){
                var oToken = new sap.m.Token({
                    key: oRow.{$idAttributeAlias},
                    text: oRow.{$labelColName}
                });
                aNewTokens.push(oToken);
            });
             
            // keep not existant token in new tokens list
            const oldTokens = oMultiInput.getTokens();
            oldTokens.forEach(token => {
                const bExistInCurrentPage = aAllRows.some(row => row["{$idAttributeAlias}"] === token.getKey());
                const bExistInTokenList= aNewTokens.some(newToken => newToken.getKey() === token.getKey());
                if (!bExistInCurrentPage && !bExistInTokenList) {
                    aNewTokens.push(token);
                }
            });


            oMultiInput.removeAllTokens();

            // Fire tokenUpdaet (_before_ actually adding tokens because that's how it seems
            // to work when doing it manually)
            oMultiInput.fireTokenUpdate({
                type: sap.m.Tokenizer.TokenUpdateType.Added,
                addedTokens: aNewTokens
            });
            
            // add the tokens
            aNewTokens.forEach(function(oToken) {oMultiInput.addToken(oToken);});
JS;
    }
    
    protected function getTokenNameColumn() : DataColumn
    {
        return $this->tokenNameColumn;
    }
}