<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Widgets\DataColumn;
use exface\Core\Interfaces\Widgets\iHaveIcon;
use exface\UI5Facade\Facades\Interfaces\UI5ValueBindingInterface;
use exface\UI5Facade\Facades\Interfaces\UI5CompoundControlInterface;
use exface\Core\Widgets\DataColumnTransposed;
use exface\Core\Widgets\DataTable;
use exface\Core\Interfaces\Widgets\iHaveMultipleBindings;
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
        
        $caption = $this->getCaption();
        $iconJs = '';
        $labelClass = '';
        if ($icon = $col->getIcon()) {
            $iconAlignConfig = $this->getFacade()->getConfig()->getOption("ICON_ALIGNMENT.DATA_COLUMN") ?? 'Center';
            $iconJs = "icon: {$this->escapeString($this->getIconSrc($icon))}, textAlign: sap.ui.core.TextAlign.{$iconAlignConfig},";
            
            // Icons should replace the caption in the colum header
            $caption = '';
            $labelClass = 'exf-icon-only';
            
            // SVG icons need a special CSS class to fix their positioning and color
            $iconSet = $col->getIconSet();
            if ($iconSet === iHaveIcon::ICON_SET_SVG_COLORED) {
                $labelClass .= ' exf-svg-icon exf-svg-colored';
            } else if ($iconSet === iHaveIcon::ICON_SET_SVG) {
                $labelClass .= ' exf-svg-icon';
            }
        }
        $labelClassJs = $labelClass ? ".addStyleClass('$labelClass')" : '';
        
        // The tooltips for columns of the UI table also include the column caption
        // because columns may get quite narrow and in this case there would not be
        // any way to see the entire caption except for using the tooltip.
        return <<<JS

	 new sap.ui.table.Column('{$this->getId()}', {
	    label: new sap.ui.commons.Label({
            text: "{$caption}",
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
	{$this->buildJsSetDataProperties($col)}
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
        $filterInputTooltipJs = $this->escapeString($this->translate('WIDGET.DATATABLE.FILTER_INPUT_TOOLTIP'));
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
                var sFilterInputTooltip = {$filterInputTooltipJs};
                
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

                    // add a tooltip to the built-in filter input to explain additional filter syntax
                    if (sFilterInputTooltip) {
                        try {
                            var oFilterFieldItem = oMenu.getItems().find(function(oItem) {
                                return oItem && typeof oItem.isA === 'function' && oItem.isA('sap.ui.unified.MenuTextFieldItem');
                            });
                            if (oFilterFieldItem && typeof oFilterFieldItem.setTooltip === 'function') {
                                oFilterFieldItem.setTooltip(sFilterInputTooltip);
                            }
                        } catch (e) {
                            console.warn('Could not set custom tooltip on column filter field: ', e);
                        }
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
            // For DisplayTemplate (iHaveMultipleBindings) inside a transposed column each placeholder
            // binding must resolve to 'colDataName/attr' so that:
            //  1. The transposing algorithm can store all attribute values as a sub-object under the
            //     column key (e.g. {key: {placeholderKey1: 10, placeHolderKey2: 20}}).
            //  2. The matrix column-clone code can rewrite the paths from
            //     originalColName/attr -> transposedColName/attr using a simple replaceAll().
            if ($cellWidget instanceof iHaveMultipleBindings && $widget instanceof DataColumnTransposed) {
                $tpl->setValueBindingPrefix($widget->getDataColumnName() . '/');
            } else {
                $tpl->setValueBindingPrefix($modelPrefix);
            }
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
					{$this->buildJsSetDataProperties($col)}
JS;
    }

    /**
     * Generates a JS snippet with a series of `.data()` calls for important additional column properties
     * 
     * @param DataColumn $col
     * @return string
     */
    protected function buildJsSetDataProperties(DataColumn $col) : string
    {
        $captionJs = $this->escapeString($this->getCaption());
        $result = <<<JS

                    .data('_exfDataColumnName', '{$col->getDataColumnName()}')
					.data('_exfHiddenColumn', {$this->escapeBool($col->isHidden())})
                    .data('_exfHiddenIfColumn', {$this->escapeBool($col->getHiddenIf() !== null)})
                    {$this->buildJsHiddenIfEvaluatorData($col)}
					.data('_exfCaption', {$captionJs})
JS;
        
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
                    .data('_exfAbbreviation', {$captionJs})
JS;
        }
        
        return '';
    }

    /**
     * Returns a `.data('_exfHiddenIfEval', function(){...})` snippet that attaches a live evaluator
     * for the column's `hidden_if` condition to the column control - or an empty string if the
     * column has no `hidden_if`.
     * 
     * The attached function returns TRUE when the condition currently resolves to hidden, and FALSE otherwise
     * 
     * @param DataColumn $col
     * @return string
     */
    protected function buildJsHiddenIfEvaluatorData(DataColumn $col) : string
    {
        $hiddenIf = $col->getHiddenIf();
        if ($hiddenIf === null) {
            return '';
        }
        try {
            $ifJs = $this->buildJsConditionalPropertyIf($hiddenIf->getConditionGroup());
        } catch (\Throwable $e) {
            // If the condition cannot be rendered to JS (e.g. unsupported expression), skip it
            // the column will simply be treated as not hidden by condition.
            return '';
        }
        return <<<JS

                    .data('_exfHiddenIfEval', function(){ return ({$ifJs}); })
JS;
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