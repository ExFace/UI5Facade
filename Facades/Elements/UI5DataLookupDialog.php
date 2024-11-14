<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Interfaces\Widgets\iHaveHeader;
use exface\Core\Interfaces\Widgets\iSupportMultiSelect;
use exface\Core\Widgets\DataColumn;
use exface\Core\Exceptions\Facades\FacadeRuntimeError;
use exface\UI5Facade\Facades\Interfaces\UI5DataElementInterface;

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
                $this->getController()->addOnEventScript($this, self::EVENT_NAME_TOKEN_UPDATE, $this->buildJsOnTokenUpdate('oEvent'));
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
        $widget = $this->getWidget();
        if ($widget->getMultiSelect() !== true){
            return '';
        }
        
        $tableElement = $this->getTableElement();
        $modelName = $tableElement->getModelNameForSelections();

        
        

        $splitterId = $this->getIdOfSplitter();
        return <<<JS
            new sap.m.Panel("{$this->getIdOfContentPanel()}",
                {
                    expandable: true,
                    expandAnimation: false,
                    expanded: true,
                    height: "100%",
                    headerToolbar: [
                        new sap.m.OverflowToolbar({
                            content: [
                                new sap.m.Text({
                                    text: "{$this->translate('WIDGET.DATALOOKUPDIALOG.SELECTED_ITEMS')} ({= \${{$this->getModelNameForSelections()}>/rows}.length})"
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
                                new sap.m.MultiInput('{$this->getIdOfSelectedTokensInput()}', {
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
                                    tokens: {
                                        path: "{$modelName}>/rows",
                                        template: new sap.m.Token({
                                            key: "{{$modelName}>{$this->getWidget()->getDataWidget()->getUidColumn()->getDataColumnName()}}",
                                            text: "{{$modelName}>{$this->getTokenNameColumn()->getDataColumnName()}}"
                                        })
                                    }
                                }).addStyleClass('exf-datalookup-tokenizer'),
                                new sap.m.Button({
                                    icon: "sap-icon://sys-cancel",
                                    type: "Transparent",
                                    enabled: '{= \${{$modelName}>/rows}.length > 0 ? true : false}',
                                    layoutData: [
                                        new sap.m.FlexItemData({
                                            growFactor: 0
                                        })
                                    ],
                                    press: function(oEvent){
                                        var oInput = sap.ui.getCore().byId('{$this->getId()}_selectedTokens');
                                        oInput.fireTokenUpdate({
                                            type: sap.m.Tokenizer.TokenUpdateType.Removed,
                                            removedTokens: oInput.getTokens()
                                        });
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

    protected function getModelNameForSelections() : string
    {
        $table = $this->getWidget()->getDataWidget();
        $tableElement = $this->getFacade()->getElement($table);
        return $tableElement->getModelNameForSelections();
    }
    
    /**
     * 
     * @return string
     */
    protected function getIdOfContentPanel() : string
    {
        return $this->getId() . '_' . 'SelectedItemsPanel';
    }

    protected function getIdOfSelectedTokensInput() : string
    {
        return "{$this->getId()}_selectedTokens";
    }
    
    /**
     * 
     * @return string
     */
    protected function getIdOfSplitter() : string
    {
        return $this->getIdOfContentPanel() . '_' . 'SplitterLayoutData';
    }

    protected function getTableElement() : UI5DataElementInterface
    {
        return $this->getFacade()->getElement($this->getWidget()->getDataWidget());
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
    
    protected function buildJsOnTokenUpdate(string $oEventJs) : string
    {
        $table = $this->getWidget()->getDataWidget();
        $tableElement = $this->getFacade()->getElement($table);
        
        return <<<JS

                var oMultiInput = $oEventJs.getSource();
                var oEventParams = $oEventJs.getParameters();
                var aRemovedTokens = oEventParams['removedTokens'] || [];
                
                aRemovedTokens.forEach(function(oToken){
                    var sKey = oToken.getKey();
                    {$tableElement->buildJsSelectRowByValue($table->getUidColumn(), 'sKey', '', 'rowIdx', true)}
                });
JS;
    }
    
    protected function getTokenNameColumn() : DataColumn
    {
        return $this->tokenNameColumn;
    }
}