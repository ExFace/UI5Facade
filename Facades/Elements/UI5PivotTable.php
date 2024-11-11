<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;
use exface\UI5Facade\Facades\Elements\Traits\UI5DataElementTrait;
use exface\Core\Facades\AbstractAjaxFacade\Elements\PivotTableTrait;

class UI5PivotTable extends UI5AbstractElement
{

    use PivotTableTrait;
	use UI5DataElementTrait;

	/**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\Traits\UI5DataElementTrait::buildJsConstructorForControl()
     */
    protected function buildJsConstructorForControl($oControllerJs = 'oController') : string
    {
        $pivotTable =  <<<JS

		new sap.ui.core.HTML("{$this->getId()}", {
                    content: "<div id=\"{$this->getId()}\" class='exf-pivottable-wrapper' style=\"height:100%; min-height: 100px;\"></div>",
                })
JS;

		return $this->buildJsPanelWrapper($pivotTable, $oControllerJs);

	}

	/**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\Traits\UI5DataElementTrait::registerExternalModules()
     */
	public function registerExternalModules(UI5ControllerInterface $controller): UI5AbstractElement
    {
    	$f = $this->getFacade();
		$controller->addExternalModule('libs.pivottable.core', $f->buildUrlToSource('LIBS.PIVOTTABLE.CORE.JS'));
		$controller->addExternalModule('libs.pivottable.subtotal', $f->buildUrlToSource('LIBS.PIVOTTABLE.SUBTOTAL.JS'));
		$controller->addExternalModule('libs.pivottable.ui', $f->buildUrlToSource('LIBS.PIVOTTABLE.UI.JS'));
		$controller->addExternalModule('libs.pivottable.libs.plotly', $f->buildUrlToSource('LIBS.PIVOTTABLE.LIBS.PLOTLY'));
		$controller->addExternalModule('libs.pivottable.renderers.export', $f->buildUrlToSource('LIBS.PIVOTTABLE.RENDERERS.EXPORT'));
		$controller->addExternalModule('libs.pivottable.renderers.charts', $f->buildUrlToSource('LIBS.PIVOTTABLE.RENDERERS.CHARTS'));
		$controller->addExternalCss($f->buildUrlToSource('LIBS.PIVOTTABLE.UI5.TEMPLATE.CSS'));
		$controller->addExternalCss($f->buildUrlToSource('LIBS.PIVOTTABLE.CORE.CSS'));
		$controller->addExternalCss($f->buildUrlToSource('LIBS.PIVOTTABLE.SUBTOTAL.CSS'));
		$controller->addExternalCss($f->buildUrlToSource('LIBS.PIVOTTABLE.UI.CSS'));
		$controller->addExternalCss($f->buildUrlToSource('LIBS.PIVOTTABLE.UI.THEME'));
		return $this;
    }
    


	/**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\Traits\UI5DataElementTrait::buildJsDataLoaderOnLoaded()
     */
	protected function buildJsDataLoaderOnLoaded(string $dataJs): string
    {
		$columnNames = array();
		$columns = $this->getWidget()->getColumns();
		foreach ($columns as $column) {
			$columnNames[$column->getDataColumnName()] = $column->getCaption();
		}

		$labelJs = json_encode($columnNames);

		return <<<JS
        (function(jQuery){
            var $ = jQuery;        
            let data = {$dataJs}.rows;
            const newDataArray = [];
			const labels = {$labelJs};
            $dataJs.oData.rows.forEach(function(row){
                const newRow = {};
                for (let key in labels) {
                    newRow[labels[key]] = row[key];
                }
                newDataArray.push(newRow);
            });
            {$this->buildJsPivotRender('newDataArray')}
        })(jQuery);
JS;
	}


	/**
     * 
     * @see UI5DataElementTrait::buildJsGetRowsSelected()
     */
    protected function buildJsGetRowsSelected(string $oTableJs) : string
    {
        return 
<<<JS
		[];
JS;
	}

	/**
     * 
     * @see UI5DataElementTrait::isEditable()
     */
	public function isEditable() : bool
	{
		return true;
	}
}