<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\UI5Facade\Facades\Interfaces\UI5ValueBindingInterface;
use exface\UI5Facade\Facades\Interfaces\UI5CompoundControlInterface;
use exface\Core\Widgets\DataTable;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;
use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;

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
        
        return <<<JS

	 new sap.ui.table.Column('{$this->getId()}', {
	    label: new sap.ui.commons.Label({
            text: "{$this->getCaption()}"
        }),
        autoResizable: true,
        template: {$this->buildJsConstructorForCell()},
	    {$this->buildJsPropertyShowSortMenuEntry()}
        {$this->buildJsPropertyShowFilterMenuEntry()}
        {$this->buildJsPropertyTooltip()}
	    {$this->buildJsPropertyVisibile()}
	    {$this->buildJsPropertyWidth()}
        {$grouped}
	})
	.data('_exfAttributeAlias', '{$col->getAttributeAlias()}')
	.data('_exfDataColumnName', '{$col->getDataColumnName()}')
JS;
    }
    
    /**
     * Returns constructor properties showFilterMenuEntry and filterProperty
     * 
     * @return string
     */
    protected function buildJsPropertyShowFilterMenuEntry() : string
    {
        $col = $this->getWidget();
        $filterable = $col->isFilterable() === true ? 'true' : 'false';
        if ($col->getCellWidget()->getValueDataType() instanceof EnumDataTypeInterface) {
            $filterable = 'false';
        }
        
        return "showFilterMenuEntry: $filterable,
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
        
        $modelPrefix = $modelName ? $modelName . '>' : '';
        if ($tpl instanceof UI5Display) {
            if (($widget->getDataWidget() instanceof DataTable) && $widget->getDataWidget()->getNowrap() === false) {
                $tpl->setWrapping(true);
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
        $popinDisplay = $col->getHideCaption() || $col->getCellWidget()->getHideCaption() ? 'sap.m.PopinDisplay.WithoutHeader' : 'sap.m.PopinDisplay.Inline';
        
        return <<<JS
        
                    new sap.m.Column('{$this->getId()}', {
						popinDisplay: {$popinDisplay},
						demandPopin: true,
						{$this->buildJsPropertyMinScreenWidth()}
						{$this->buildJsPropertyTooltip()}
						{$this->buildJsPropertyWidth()}
						header: [
                            new sap.m.Label({
                                text: "{$this->getCaption()}"
                            })
                        ],
                        {$alignment}
                        {$this->buildJsPropertyVisibile()}
					})
					.data('_exfAttributeAlias', '{$col->getAttributeAlias()}')
					.data('_exfDataColumnName', '{$col->getDataColumnName()}')
					
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
    protected function buildJsPropertyTooltip()
    {
        return 'tooltip: "' . $this->escapeJsTextValue($this->buildTextTooltip()) . '",';
    }
    
    protected function buildTextTooltip()
    {
        return $this->getWidget()->getCaption() . ($this->getWidget()->getCaption() ? ': ' : '') . $this->getWidget()->getHint();
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
            case EXF_ALIGN_OPPOSITE:
                $alignment = '"End"';
                break;
            case EXF_ALIGN_CENTER:
                $alignment = '"Center"';
                break;
            case EXF_ALIGN_LEFT:
            case EXF_ALIGN_DEFAULT:
            default:
                $alignment = '"Begin"';
                
        }
        
        return $alignment;
    }
    
    protected function buildJsPropertyWidth()
    {
        $dim = $this->getWidget()->getWidth();
        
        if ($dim->isFacadeSpecific()) {
            return 'width: "' . $dim->getValue() . '",';
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
}
?>