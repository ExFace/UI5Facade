<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\Widgets\DataLookupDialog;
use exface\Core\Widgets\DataTable;
use exface\Core\Widgets\DataTableResponsive;
use exface\Core\Interfaces\Widgets\iHaveHeader;
use exface\Core\Interfaces\Widgets\iSupportMultiSelect;
use exface\Core\Factories\ActionFactory;
use exface\Core\Actions\UpdateData;

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
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::init()
     */
    protected function init()
    {
        parent::init();
        $table = $this->getWidget()->getDataWidget();
        $table->setHideCaption(true);
        if ($table instanceof iHaveHeader) {
            $this->getWidget()->getDataWidget()->setHideHeader(false);
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
                {$this->buildJsPrefillLoader('oView')}
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
            title: "{$this->getCaption()}",
			buttons : [ {$this->buildJsDialogButtons()} ],
			content : [ {$this->buildJsDialogContent()} ],
            {$prefill}
		});
JS;
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
        $widget = $this->getWidget();
        $visibleChildren = $widget->getWidgets(function(WidgetInterface $widget){
            return $widget->isHidden() === false;
        });
            if (count($visibleChildren) === 1 && $visibleChildren[0] instanceof iFillEntireContainer) {
                $childrenJs = $this->buildJsChildrenConstructors(false);
            } else {
                $childrenJs = $this->buildJsLayoutForm($this->buildJsChildrenConstructors(true));
            }
        return $childrenJs;
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
        
        $splitterId = $this->getDialogContentPanelSplitterLayoutId();
        
        return <<<JS
            new sap.m.Panel( "{$this->getDialogContentPanelId()}",
                {
                    expandable: true,
                    expanded: true,
                    height: "100%",
                    headerToolbar: [
                        {$this->buildJsSelectedItemsPanelHeaderToolbar()}
                    ],
                    content: [
                        new sap.m.HBox({
                            width: "100%",
                            alignItems: "Center",
                            fitContainer: true,
                            items: [
                                {$this->buildJsSelectedItemsPanelTokenizer()},
                                {$this->buildJsSelectedItemsPanelDeleteButtons()}
                            ]
                        })
                    ],
                    layoutData: [
                        new sap.ui.layout.SplitterLayoutData("{$splitterId}",
                            {
                                size: "5rem",
                                resizable: false
                            })
                    ]
                }).attachExpand(function(){
                                    // resize on expanding / collapsing to allow the table to utilize as much space as possible
                                    if (this.getExpanded() == true){
                                        sap.ui.getCore().byId('{$splitterId}').setSize("5rem");
                                    } else {
                                        sap.ui.getCore().byId('{$splitterId}').setSize("2.1rem");
                                    }
                                })
JS;
    }
    
    /**
     * This function returns the JS-code for the `sap.m.OverflowToolbar` for the
     * `headerToolbar` aggrgation used in the 'Selected Items' panel.
     * It contains a Text, which displays the number of itmes, currently selelected.
     * 
     * @return string
     */
    protected function buildJsSelectedItemsPanelHeaderToolbar() : string
    {
        return <<<JS
                        new sap.m.OverflowToolbar({
                            content: [
                                new sap.m.Text("{$this->getDialogContentPanelItemCounterId()}",
                                {
                                    text: "Selected Items"
                                })
                            ]
                        })
JS;
    }
    
    /**
     * This function returns the JS-code for the `sap.m.Tokenizer`, used in the 'SelectedItems' panel.
     * 
     * @return string
     */
    protected function buildJsSelectedItemsPanelTokenizer() : string
    {
        return <<<JS
                                new sap.m.Tokenizer("{$this->getDialogContentPanelTokenizerId()}",
                                    {
                                    width: "100%",
                                    layoutData: [
                                        new sap.m.FlexItemData({
                                            styleClass: "dataLookupDialogSelectedElementsHBoxFlexItem"
                                        })
                                    ]
                                }).addStyleClass('dataLookupDialogSelectedElementsTokenizer')
JS;
    }
    
    /**
     * This function returns the JS-code for the `sap.m.Button`, used in the 'SelectedItems' panel.
     * Upon clicking it, it clears the selection from the DataTable, therefore deleting all tokens
     * from the SelectedItems tokenizer.
     * 
     * @return string
     */
    protected function buildJsSelectedItemsPanelDeleteButtons() : string
    {
        return <<<JS
                                new sap.m.Button("{$this->getDialogContentPanelTokenizerClearButtonId()}",
                                {
                                    icon: "sap-icon://sys-cancel",
                                    type: "Transparent",
                                    enabled: false,
                                    press: function(){
                                        var oTokenizer = sap.ui.getCore().byId("{$this->getDialogContentPanelTokenizerId()}");
                                        var aTokens = oTokenizer.getTokens();
                                        if (aTokens.length != 0){
                                            // remove all tokens by clearing the selection in the table, the tokens are assigned to
                                            var oTable = sap.ui.getCore().byId(aTokens[0].data().tableId);
                                            oTable.removeSelections().fireSelectionChange();
                                        }
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
        return $this->getWidget()->getID() . '_' . 'SelectedItemsPanel';
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
    public function buildJsChildrenConstructors(bool $useFormLayout = true) : string
    {
        $js = '';
        $firstVisibleWidget = null;
        foreach ($this->getWidget()->getWidgets() as $widget) {
            
            // if the widget is the DataTable, and it uses Multiselect attatch the handlers for the SelectedITems panel
            if ($widget instanceof iSupportMultiSelect && $this->getWidget()->getMultiSelect() === true){
                $this->getFacade()->getElement($widget)->addOnChangeScript($this->buildJsSelectionChangeHandler());
            }
            
            if ($widget->isHidden() === false) {
                // Larger widgets need a Title before them to make SimpleForm generate a new FormContainer
                if ($firstVisibleWidget !== null && $useFormLayout === true && (($widget instanceof iFillEntireContainer) || $widget->getWidth()->isMax())) {
                    $js .= ($js ? ",\n" : '') . $this->buildJsFormRowDelimiter();
                }
                $firstVisibleWidget = $widget;
            }
            $tableElement = $this->getFacade()->getElement($widget);
            $tableElement->setDynamicPageHeaderCollapsed(true);
            $tableElement->setDynamicPageShowToolbar(true);
            $js .= ($js ? ",\n" : '') . $tableElement->buildJsConstructor();
        }
        
        return $js;
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
        
        if ($table->getMetaObject()->hasLabelAttribute() === true){
            if ($labelCol = $table->getColumnByAttributeAlias($table->getMetaObject()->getLabelAttributeAlias())) {
                $labelColName = $labelCol->getDataColumnName();
            } else {
                $labelColName = $table->getMetaObject()->getLabelAttributeAlias();
            }
        } else {
            $labelColName = $idAttributeAlias;
        }
        
        $dataGetterJs = $tableElement->buildJsDataGetter(ActionFactory::createFromString($this->getWorkbench(), UpdateData::class));
        
        return <<<JS
            var oTokenizer =  sap.ui.getCore().byId("{$this->getDialogContentPanelTokenizerId()}");
            if (! oTokenizer) {
                return;
            }

			oTokenizer.destroyTokens();

            var aSelection = {$dataGetterJs};
            var aRows =  aSelection.rows;

            var sItemCounterText = 'Selected Items';
            if (aRows.length != 0){
                sItemCounterText += ' (' + aRows.length + ')';
            }
            sap.ui.getCore().byId("{$this->getDialogContentPanelItemCounterId()}").setText(sItemCounterText);

            // disable the remove-selection button when no selection is made
            var oTokenizerClearButton = sap.ui.getCore().byId("{$this->getDialogContentPanelTokenizerClearButtonId()}");
            if (aRows.length == 0){
                oTokenizerClearButton.setEnabled(false);                
                return;
            }
            
            oTokenizerClearButton.setEnabled(true);

            //get selected items from table
            var aSelectedIds = {$tableElement->buildJsValueGetter($idAttributeAlias)};
            var aSelectedLables = {$tableElement->buildJsValueGetter("{$labelColName}")};
            aRows.forEach(function(oRow){
      
                 oTokenizer.addToken(
    				 new sap.m.Token({
                         customData: [
                            {
                                Type: "sap.ui.core.CustomData",
                                key: "tableId",
                                value: oEvent.getSource().getId()
                            }
                         ],
    				 	 key: oRow.{$idAttributeAlias},
    				 	 text: oRow.{$labelColName},        				 	 
                         delete: function(oEvent){
                              var sKey = this.getKey();
                              {$tableElement->buildJsSelectRowByValue($table->getUidColumn(), 'sKey', '', 'rowIdx', true)}
                              sap.ui.getCore().byId(this.data().tableId).fireSelectionChange();
                        }
    				 })
    			);
            });
JS;
    }
}