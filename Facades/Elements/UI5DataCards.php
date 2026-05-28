<?php
namespace exface\UI5Facade\Facades\Elements;

class UI5DataCards extends UI5DataTable
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5DataTable::buildJsConstructorForTable()
     */
    protected function buildJsConstructorForControl($oControllerJs = 'oController') : string
    {
        $mode = $this->getWidget()->getMultiSelect() ? 'sap.m.ListMode.MultiSelect' : 'sap.m.ListMode.SingleSelectMaster';
        return <<<JS

        new sap.m.VBox({
            items: [
                new sap.f.GridList("{$this->getId()}", {
                    mode: {$mode},
                    noDataText: "{$this->escapeJsTextValue($this->getWidget()->getEmptyText())}",
            		itemPress: {$this->getController()->buildJsEventHandler($this, self::EVENT_NAME_CHANGE, true)},
                    headerToolbar: [
                        {$this->buildJsToolbar()}
            		],
            		items: {
            			path: '/rows',
                        {$this->buildJsBindingOptionsForGrouping()}
                        template: new sap.m.CustomListItem({
                            type: "Active",
                            content: [
                                {$this->buildJsConstructorForCard()}
                            ]
                        }),
            		}
                })
                .setModel(new sap.ui.model.json.JSONModel())
                {$this->buildJsClickHandlers('oController')}
                {$this->buildJsPseudoEventHandlers()}
                ,
                {$this->buildJsConstructorForMTableFooter()}
            ]
        }) 

JS;
    }
                
    protected function buildJsConstructorForCard() : string
    {
        return <<<JS

                                new sap.m.VBox({
                                    layoutData: new sap.m.FlexItemData({
                                        growFactor: 1,
                                        shrinkFactor: 0
                                    }),
                                    items: [
                                        {$this->buildJsCellsForMTable()}
                                    ]
                                }).addStyleClass("sapUiSmallMargin")

JS;
    }
           
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5DataTable::buildJsCellsForMTable()
     */
    protected function buildJsCellsForMTable()
    {
        $cells = '';
        foreach ($this->getWidget()->getColumns() as $column) {
            $class = '';
            // TODO add support for optional cells (hiding them here will not allow to make
            // them visible per JS!)
            switch ($column->getVisibility()) {
                case EXF_WIDGET_VISIBILITY_PROMOTED:
                    $class .= ' exf-promoted';
                    break;
                case EXF_WIDGET_VISIBILITY_HIDDEN:
                    $column->getCellWidget()->setHidden(true);
            }
            // Force left align even for dates and numbers - they don't look good with right alignment
            // in cards!
            if (! $column->isAlignSet()) {
                $column->setAlign(EXF_ALIGN_DEFAULT);
            }
            $cells .= ($cells ? ", " : '') . $this->getFacade()->getElement($column)->buildJsConstructorForCell(null, false) . ($class !== '' ? '.addStyleClass("' . $class . '")' : '');
        }
        
        return $cells;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5DataTable::buildJsClickIsTargetRowCheck()
     */
    protected function buildJsClickIsTargetRowCheck(string $oTargetDomJs = 'oTargetDom') : string
    {
        return "{$oTargetDomJs} !== undefined && $({$oTargetDomJs}).parents('ul.sapFGridListDefault').length > 0";
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5DataTable::isMList()
     */
    public function isMList() : bool
    {
        return true;
    }
        
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5DataTable::isMTable()
     */
    public function isMTable() : bool
    {
        return false;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5DataTable::isUiTable()
     */
    public function isUiTable() : bool
    {
        return false;
    }
}