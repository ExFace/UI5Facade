<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;
use exface\UI5Facade\Facades\Elements\Traits\UI5DataElementTrait;
use exface\Core\Facades\AbstractAjaxFacade\Elements\PerspectiveTrait;

/**
 * Renders PivotTable widgets with Perspective.
 *
 * @author Andrej Kabachnik
 */
class UI5PerspectivePivotTable extends UI5AbstractElement
{
    use PerspectiveTrait;
    use UI5DataElementTrait {
        buildJsDataLoaderOnLoaded as buildJsDataLoaderOnLoadedViaTrait;
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\Traits\UI5DataElementTrait::buildJsConstructorForControl()
     */
    protected function buildJsConstructorForControl($oControllerJs = 'oController') : string
    {
        $language = $this->escapeString($this->getPerspectiveLocale(), false);
        $pivotTable = <<<JS

		new sap.ui.core.HTML("{$this->getId()}", {
                    content: "<perspective-viewer id=\"{$this->getId()}\" class=\"exf-perspective-viewer\" lang=\"{$language}\" style=\"width:100%; height:100%; min-height:100px;\"></perspective-viewer>",
                })
JS;

        return $this->buildJsPanelWrapper($pivotTable, $oControllerJs) . ".addStyleClass('sapUiNoContentPadding')";
    }

    /**
     *
     * @see UI5DataElementTrait::isWrappedInPanel()
     */
    protected function isWrappedInPanel() : bool
    {
        return true;
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\Traits\UI5DataElementTrait::registerExternalModules()
     */
    public function registerExternalModules(UI5ControllerInterface $controller): UI5AbstractElement
    {
        $facade = $this->getFacade();
        $controller->addExternalModule('libs.exface.perspective.loader', $facade->buildUrlToSource('LIBS.PERSPECTIVE.LOADER.JS'));
        $controller->addExternalCss($facade->buildUrlToSource('LIBS.PERSPECTIVE.THEME.CSS'));
        $controller->addExternalCss($facade->buildUrlToSource('LIBS.PERSPECTIVE.FACADE.CSS'));
        $localeStylesheet = $this->buildUrlToPerspectiveLocaleStylesheet();
        if ($localeStylesheet !== null) {
            $controller->addExternalCss($localeStylesheet);
        }
        return $this;
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\Traits\UI5DataElementTrait::buildJsDataLoaderOnLoaded()
     */
    protected function buildJsDataLoaderOnLoaded(string $dataJs): string
    {
        $columnNames = [];
        $columns = $this->getWidget()->getColumns();
        foreach ($columns as $column) {
            $columnNames[$column->getDataColumnName()] = $column->getCaption();
        }

        $labelJs = $this->escapeString($columnNames);

        return $this->buildJsDataLoaderOnLoadedViaTrait($dataJs) . <<<JS
        (function(){
            const newDataArray = [];
			const labels = {$labelJs};
            const rows = {$dataJs}.getData().rows || [];
            rows.forEach(function(row){
                const newRow = {};
                for (let key in labels) {
                    newRow[labels[key]] = row[key];
                }
                newDataArray.push(newRow);
            });
            {$this->buildJsPerspectiveRender('newDataArray')}
        })();
JS;
    }

    /**
     *
     * @return string
     */
    protected function buildJsFullscreenContainerGetter() : string
    {
        return "$('#{$this->getId()}').parent().parent()";
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