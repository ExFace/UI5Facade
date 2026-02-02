<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Widgets\DataColumn;
use exface\UI5Facade\Facades\Interfaces\UI5ValueBindingInterface;
use exface\UI5Facade\Facades\Interfaces\UI5CompoundControlInterface;
use exface\Core\Widgets\DataTable;
use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Widgets\DataColumnResponsive;
use exface\Core\Interfaces\Widgets\iCanWrapText;
use exface\Core\Facades\AbstractAjaxFacade\Formatters\JsEnumFormatter;
use exface\Core\Widgets\Text;

/**
 *
 * @method \exface\Core\Widgets\DataColumn getWidget()
 *        
 * @author Andrej Kabachnik
 *        
 */
class UI5DataColumn extends UI5AbstractElement
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsConstructor()
     */
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {
        $parentElement = $this->getFacade()->getElement($this->getWidget()->getDataWidget());
        if (($parentElement instanceof UI5DataTable) && $parentElement->isMTable()) {
            return $this->buildJsConstructorForMColumn();
        }
        return $this->buildJsConstructorForUiColumn();
    }

    /**
     * Returns the constructor for a sap.ui.table.Column for this DataColumn widget
     * 
     * @return string
     */
    public function buildJsConstructorForUiColumn()
    {
        $col = $this->getWidget();
        $table = $col->getDataWidget();
        
        $grouped = '';
        if (($table instanceof DataTable) && $table->hasRowGroups()) {
            if ($col === $table->getRowGrouper()->getGroupByColumn()) {
                $grouped = 'grouped: true,';
            }
        }
        
        $width = $col->getWidth();
        $widthMax = $col->getWidthMax();
        $widthMin = $col->getWidthMin();
        $widthJson = json_encode([
            'auto' => $col->getNowrap() && ($width->isUndefined() || strtolower($width->getValue()) === 'auto'),
            'fixed' => $this->buildCssWidth($width),
            'min' => $this->buildCssWidth($widthMin),
            'max' => $this->buildCssWidth($widthMax)
        ]);
        $labelWrappingJs = $col->getNowrap() ? 'wrapping: false,' : 'wrapping: true,';
        
        $formatter = $this->getFacade()->getDataTypeFormatter($col->getDataType());
        if ($col->isBoundToAttribute() && $formatter instanceof JsEnumFormatter) {
            $formatParserJs = $formatter->buildJsFormatParser('mVal', true, $col->getAttribute()->getValueListDelimiter());
        } else {
            $formatParserJs = $formatter->buildJsFormatParser('mVal');
        }
        
        $iconJs = '';
        $labelClassJs = '';
        if ($icon = $col->getIcon()) {
            $iconJs = "icon: '{$this->getIconSrc($icon)}',";
            if ($col->getIconSet() === 'svg') {
                $labelClassJs .= '.addStyleClass("exf-svg-icon exf-svg-colored")';
            }
        }
        $expression = $this->buildJsAddDataExpression($col);
        
        // The tooltips for columns of the UI table also include the column caption
        // because columns may get quite narrow and in this case there would not be
        // any way to see the entire caption except for using the tooltip.
        return <<<JS

	 new sap.ui.table.Column('{$this->getId()}', {
	    label: new sap.ui.commons.Label({
            text: "{$this->getCaption()}",
            {$this->buildJsPropertyTooltip(true)}
            {$iconJs}
            {$labelWrappingJs}
        }){$labelClassJs},
        autoResizable: true,
        template: {$this->buildJsConstructorForCell()},
	    {$this->buildJsPropertyShowSortMenuEntry()}
        {$this->buildJsPropertyShowFilterMenuEntry()}
	    {$this->buildJsPropertyVisibile()}
	    {$this->buildJsPropertyWidth()}
        {$this->buildJsPropertyWidthMin()}
        {$this->buildJsAddFilterResetBtn()}
        {$grouped}
	})
	{$expression}
	.data('_exfDataColumnName', '{$col->getDataColumnName()}')
	.data('_exfWidth', {$widthJson})
    .data('_exfFilterParser', function(mVal){ return {$formatParserJs} })
JS;
    }

    /**
     * Adds an additional reset filter button to the column menu (via on columnMenuOpen) if the column is filterable.
     * 
     * @return string
     */
    private function buildJsAddFilterResetBtn()
    {
        $col = $this->getWidget();
        $isFilterable = $col->isFilterable() === true;
        $dataTable = $this->getFacade()->getElement($this->getWidget()->getDataWidget());
        $configurator = $this->getFacade()->getElement($dataTable->getWidget()->getConfiguratorWidget());

        // only add reset button for filterable columns
        if ($isFilterable){
            return <<<JS
            columnMenuOpen: function(oEvent) {

            // get column, menu and id from event params
            let sResetBtnId = oEvent.getParameter('id') + "_resetBtn";
            let oColumn = sap.ui.getCore().byId(oEvent.getParameter('id'));
            let oMenu = oEvent.getParameter('menu');

            if (!oMenu) {
                return;
            }

            // columnMenuOpen fires before menu is there, so timeout prevents lifecycle issues here
            setTimeout(function() {
                
                // since adding menu items to the default column menu was not encouraged in documentation, wrap in try/catch
                // see https://ui5.sap.com/1.136.9/#/api/sap.ui.table.ColumnMenu
                try {
                    // check if the button already exists, otherwsie add it
                    let bButtonExists = oMenu.getItems().some(function(oItem) {
                        return oItem.getId() === sResetBtnId;
                    });

                    if (!bButtonExists) {
                        oMenu.addItem(
                            new sap.ui.unified.MenuItem({
                                id: sResetBtnId,
                                icon: "sap-icon://clear-filter",
                                text: {$this->escapeString($this->translate('WIDGET.DATATABLE.FILTER_CLEAR'))},
                                select: function(oEvent) {
                                    
                                    let oSearchPanel = sap.ui.getCore().byId('{$configurator->getIdOfSearchPanel()}');
                                    if (oSearchPanel && oColumn) {
                                        let aFilterItems = oSearchPanel.getFilterItems();
                                        let aMatchingFilters = aFilterItems.filter(oFilterItem => oFilterItem.getColumnKey() === oColumn.getFilterProperty());

                                        // remove all matching filter items
                                        aMatchingFilters.forEach(oMatchingFilter => {
                                            oSearchPanel.removeFilterItem(oMatchingFilter);
                                        });

                                        // reset filter value (input field in column menu)
                                        oColumn.setFilterValue(null);
                                    }
                                    // reload data
                                    {$dataTable->getController()->buildJsMethodCallFromController('onLoadData', $dataTable, '')}
                                }
                            })
                        );
                    }
                }
                catch (e) {
                    console.error(e);
                }
            }, 0);  
        },
JS;
        }
        else{
            // if not filterable, add/do nothing
            return '';
        }
    }

    /**
     * {@inheritDoc}
     * @see exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::getWidthRelativeUnit()
     */
    public function getWidthRelativeUnit()
    {
        return $this->getFacade()->getConfig()->getOption('WIDGET.DATACOLUMN.WIDTH_RELATIVE_UNIT');
    }

    /**
     * {@inheritDoc}
     * @see exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildCssWidthDefaultValue()
     */
    protected function buildCssWidthDefaultValue() : string
    {
        return '';
    }
    
    /**
     * Returns constructor properties showFilterMenuEntry and filterProperty
     * 
     * @return string
     */
    protected function buildJsPropertyShowFilterMenuEntry() : string
    {
        $col = $this->getWidget();
        $filterableJs = $col->isFilterable() === true ? 'true' : 'false';
        return "showFilterMenuEntry: $filterableJs,
        filterProperty: '{$col->getAttributeAlias()}',";
    }
    
    /**
     * Returns constructor properties showSortMenuEntry and sortProperty.
     * 
     * @return string
     */
    protected function buildJsPropertyShowSortMenuEntry() : string
    {
        $col = $this->getWidget();
        $sortable = $col->isSortable() === true ? 'true' : 'false';
        
        return "showSortMenuEntry: $sortable,
        sortProperty: '{$col->getAttributeAlias()}',";
    }
	
    /**
     * Returns the javascript constructor for a cell control to be used in cell template aggregations.
     * 
     * @return string
     */
    public function buildJsConstructorForCell(string $modelName = null, bool $hideCaptions = true)
    {
        $widget = $this->getWidget();
        $cellWidget = $widget->getCellWidget();
        $tpl = $this->getFacade()->getElement($cellWidget);
        // Disable using widget id as control id because this is a template for multiple controls
        $tpl->setUseWidgetId(false);
        // Force element to use model binding if the widget "knows" it's column
        if ($cellWidget->getDataColumnName() !== '' && $cellWidget->getDataColumnName() !== null) {
            $tpl->setValueBoundToModel(true);
        }
        if ($cellWidget instanceof iCanWrapText) {
            $cellWidget->setNowrap($widget->getNowrap());
        }
        
        $modelPrefix = $modelName ? $modelName . '>' : '';
        if ($tpl instanceof UI5Display) {
            if (($widget->getDataWidget() instanceof DataTable) && $widget->getNowrap() === false) {
                $tpl->setWrapping(true);
                if ($cellWidget instanceof Text) {
                    $maxLines = $cellWidget->getMultiLineMaxLines();
                } else {
                    $maxLines = null;
                }
                $tpl->setPropertyMaxLines($maxLines ?? $this->getWrapLinesMax());
            }
            $tpl->setValueBindingPrefix($modelPrefix);
            $tpl->setAlignment($this->buildJsAlignment());
        } elseif ($tpl instanceof UI5ValueBindingInterface) {
            $tpl->setValueBindingPrefix($modelPrefix);
        }
        if (($tpl instanceof UI5CompoundControlInterface) && ($hideCaptions === true || $widget->getHideCaption() === true || $cellWidget->getHideCaption() === true)) {
            return $tpl->buildJsConstructorForMainControl();
        } else {
            return $tpl->buildJsConstructor();
        }
    }
		
    /**
     * Returns the constructor for a sap.m.Column for this DataColumn widget.
     * 
     * @return string
     */
    public function buildJsConstructorForMColumn()
    {
        $col = $this->getWidget();
        $alignment = 'hAlign: ' . $this->buildJsAlignment() . ',';
        
        switch (true) {
            case $col->getHideCaption():
            case $col->getCellWidget()->getHideCaption():
            case ($col instanceof DataColumnResponsive) && $col->getHideCaptionOnSmartphone():
                $popinDisplay = 'sap.m.PopinDisplay.WithoutHeader';
                break;
            default:
                $popinDisplay = 'sap.m.PopinDisplay.Inline';
        }
        
        return <<<JS
        
                    new sap.m.Column('{$this->getId()}', {
						popinDisplay: {$popinDisplay},
						demandPopin: true,
						{$this->buildJsPropertyMinScreenWidth()}
						{$this->buildJsPropertyWidth()}
						header: [
                            new sap.m.Label({
                                text: {$this->escapeString($this->getCaption())},
                                {$this->buildJsPropertyTooltip()}
                            })
                        ],
                        {$alignment}
                        {$this->buildJsPropertyVisibile()}
					})
					.data('_exfAttributeAlias', '{$col->getAttributeAlias()}')
					.data('_exfDataColumnName', '{$col->getDataColumnName()}')
					{$this->buildJsAddDataExpression($col)}
JS;
    }
    
    protected function buildJsAddDataExpression(DataColumn $col) : string
    {
        $caption = $this->escapeString($this->getCaption());
        $result = ".data('_exfCaption', {$caption})";
        
        if ($col->getAttributeAlias() !== null) {
            $abbreviation = $col->getAttribute()->getAbbreviation() ?? $this->getCaption();
            $abbreviation = $this->escapeString($abbreviation);
            
            return $result . <<<JS

.data('_exfAttributeAlias', {$this->escapeString($col->getAttributeAlias())})
.data('_exfAbbreviation', {$abbreviation})
JS;
        } elseif ($col->getCalculationExpression() !== null) {
            return $result . <<<JS

.data('_exfCalculation', {$this->escapeString($col->getCalculationExpression()->__toString())})
.data('_exfAbbreviation', {$caption})
JS;
        }
        
        return '';
    }
                        
    protected function buildJsPropertyVisibile()
    {
        $dataWidget = $this->getWidget()->getDataWidget();
        
        // Hide the column used for row grouping if its a sap.m.Table.
        // The sap.ui.table.Table will hide the column automatically!
        if ($dataWidget instanceof DataTable && $dataWidget->hasRowGroups()) {
            if ($this->getWidget() === $dataWidget->getRowGrouper()->getGroupByColumn()) {
                return 'visible: false,';
            }
        }
        
        switch ($this->getWidget()->getVisibility()) {
            case EXF_WIDGET_VISIBILITY_OPTIONAL:
            case EXF_WIDGET_VISIBILITY_HIDDEN:
                return 'visible: false,';
        }
        return '';
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsPropertyMinScreenWidth()
    {
        switch ($this->getWidget()->getVisibility()) {
            case EXF_WIDGET_VISIBILITY_PROMOTED:
                $val = '';
                break;
            case EXF_WIDGET_VISIBILITY_NORMAL:
            default:
                $val = 'Tablet';
        }
        
        if ($val) {
            return 'minScreenWidth: "' . $val . '",';
        } else {
            return '';
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsPropertyTooltip()
     */
    protected function buildJsPropertyTooltip(bool $includeCaption = false)
    {
        return 'tooltip: "' . $this->escapeJsTextValue($this->buildTextTooltip($includeCaption)) . '",';
    }
    
    /**
     * 
     * @param bool $includeCaption
     * @return string
     */
    protected function buildTextTooltip(bool $includeCaption = false) : string
    {
        if ($includeCaption) {
            $caption = $this->getWidget()->getCaption();
            $hint = $this->getWidget()->getHint();
            if ($caption && ! StringDataType::startsWith($hint, $caption)) {
                return $caption . ($hint ? ': ' . $hint : '');
            }
        }
        return $this->getWidget()->getHint() ?? '';
    }
    
    /**
     * Builds alignment options like 'hAlign: "Begin",' etc. - allways ending with a comma.
     * 
     * @param string $propertyName
     * @return string
     */
    protected function buildJsAlignment()
    {
        switch ($this->getWidget()->getAlign()) {
            case EXF_ALIGN_RIGHT:
            case EXF_ALIGN_OPPOSITE: $alignment = 'sap.ui.core.TextAlign.End'; break;
            case EXF_ALIGN_CENTER: $alignment = 'sap.ui.core.TextAlign.Center'; break;
            case EXF_ALIGN_LEFT:
            case EXF_ALIGN_DEFAULT:
            default: $alignment = 'sap.ui.core.TextAlign.Begin'; break;
        }
        
        return $alignment;
    }
    
    protected function buildJsPropertyWidth()
    {        
        if ($val = $this->buildCssWidth()) {
            return 'width: "' . $val . '",';
        }   
        
        return '';
    }
    
    protected function buildJsPropertyWidthMin()
    {
        $dim = $this->getWidget()->getWidthMin();
        
        switch (true) {
            case $dim->isFacadeSpecific() && StringDataType::endsWith($dim->getValue(), 'px'):
                return 'minWidth: ' . StringDataType::substringBefore($dim->getValue(), 'px') . ',';
            case $dim->isRelative():
                return 'minWidth: ' . ($dim->getValue() * $this->getWidthRelativeUnit()) . ',';
        }
        
        return '';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::registerExternalModules()
     */
    public function registerExternalModules(UI5ControllerInterface $controller) : UI5AbstractElement
    {
        $this->getFacade()->getElement($this->getWidget()->getCellWidget())->registerExternalModules($controller);
        return $this;
    }
    
    /**
     * 
     * @return int
     */
    protected function getWrapLinesMax() : int
    {
        return $this->getFacade()->getConfig()->getOption('WIDGET.DATATABLE.MAX_TEXT_LINES_PER_CELL');
    }
}