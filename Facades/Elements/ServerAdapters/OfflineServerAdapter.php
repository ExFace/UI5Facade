<?php
namespace exface\UI5Facade\Facades\Elements\ServerAdapters;

use exface\UI5Facade\Facades\Elements\UI5AbstractElement;
use exface\UI5Facade\Facades\Interfaces\UI5ServerAdapterInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Actions\ReadData;
use exface\Core\Interfaces\Widgets\iHaveQuickSearch;
use exface\Core\Actions\ReadPrefill;
use exface\Core\Exceptions\Facades\FacadeUnsupportedWidgetPropertyWarning;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\CommonLogic\DataSheets\DataColumn;

class OfflineServerAdapter implements UI5ServerAdapterInterface
{
    private $element = null;
    
    private $fallbackAdapter = null;
    
    public function __construct(UI5AbstractElement $element, UI5ServerAdapterInterface $fallBackAdapter)
    {
        $this->element = $element;
        $this->fallbackAdapter = $fallBackAdapter;
    }
    
    public function getElement() : UI5AbstractElement
    {
        return $this->element;
    }
    
    protected function getFallbackAdapter() : UI5ServerAdapterInterface
    {
        return $this->fallbackAdapter;
    }
    
    public function buildJsServerRequest(ActionInterface $action, string $oModelJs, string $oParamsJs, string $onModelLoadedJs, string $onErrorJs = '', string $onOfflineJs = '') : string
    {
        $fallBackRequest = $this->getFallbackAdapter()->buildJsServerRequest($action, $oModelJs, $oParamsJs, $onModelLoadedJs, $onErrorJs, $onOfflineJs);
        switch (true) {
            case $action instanceof ReadPrefill:
                return $this->buildJsPrefillLoader($oModelJs, $oParamsJs, $onModelLoadedJs, $onOfflineJs, $fallBackRequest);
            case $action instanceof ReadData:
                return $this->buildJsDataLoader($oModelJs, $oParamsJs, $onModelLoadedJs, $onOfflineJs, $fallBackRequest);
        }
        
        return $fallBackRequest;
    }
    
    protected function buildJsPrefillLoader(string $oModelJs, string $oParamsJs, string $onModelLoadedJs, string $onOfflineJs, string $fallBackRequest) : string
    {
        $equals = EXF_COMPARATOR_EQUALS;
        $obj = $this->getElement()->getMetaObject();
        $uidColNameJs = $obj->hasUidAttribute() ? "'" . DataColumn::sanitizeColumnName($obj->getUidAttributeAlias()) . "'" : 'null';
        // TODO this does not load any prefill if there is no input data. What about prefills for formula values or
        // context values? Probably need some separate "prefill" row in the offline data of an object if there
        // is such kind of prefill needed for the object.
        $checkIfPrefillRequiredJs = <<<JS

                if (!exfLauncher.isOnline()) {
                    var uid;
                    var uidCol = $uidColNameJs;
                    var bStopPrefill = false;
                    if ($oParamsJs.data && $oParamsJs.data.rows && $oParamsJs.data.rows[0]) {
                        if (uidCol) {
                            uid = $oParamsJs.data.rows[0][uidCol]; 
        
                            if (uid === undefined || uid === '') {
                                console.warn('Cannot prefill from preload data: no UID value found in input rows!');
                            }
            
                            if ($oParamsJs.data.filters === undefined) {
                                $oParamsJs.data.filters = {};
                            }
            
                            if ($oParamsJs.data.filters.conditions === undefined) {
                                $oParamsJs.data.filters.conditions = [];
                            }     
            
                            $oParamsJs.data.filters.conditions.push({
                                expression: uidCol,
                                comparator: '{$equals}',
                                value: uid,
                                object_alias: '{$obj->getAliasWithNamespace()}'
                            });
                        } else {
                            $oModelJs.setData($oParamsJs.data.rows[0]);
                            bStopPrefill = true;
                        }
                    } else {
                        bStopPrefill = true;
                    }
    
                    if(bStopPrefill === true) {
                        {$onModelLoadedJs}
                        return Promise.resolve($oModelJs);
                    }
                }

JS;
             
        return $checkIfPrefillRequiredJs . $this->buildJsDataLoader($oModelJs, $oParamsJs, $onModelLoadedJs, $onOfflineJs, $fallBackRequest, true);
    }
    
    protected function buildJsDataLoader(string $oModelJs, string $oParamsJs, string $onModelLoadedJs, string $onOfflineJs, string $fallBackRequest, bool $useFirstRowOnly = false) : string
    {
        $element = $this->getElement();
        $widget = $element->getWidget();
        
        $useFirstRowJs = $useFirstRowOnly ? 'true' : 'false';
        
        return <<<JS
        
            (function(){
                var fnFallback = function(){
                    {$fallBackRequest};
                };

                if (!exfLauncher.isOnline()) {
                    return fnFallback();
                };

                return exfPWA
                .data.get('{$widget->getMetaObject()->getAliasWithNamespace()}')
                .then(oDataSet => {
                    var bGetFirstRowOnly = $useFirstRowJs;
                    var aData = [];
                    var sUrlFilterPrefix = '{$this->getElement()->getFacade()->getUrlFilterPrefix()}';
                    var oFilters = {operator: 'AND', conditions: [], ignore_empty_values: true};
                    var aSorters = [];
                    var iFiltered = null;
                    var aRowsAddedOffline = exfPWA.data.getRowsAddedOffline(oDataSet);

                    if (oDataSet === undefined || ! Array.isArray(oDataSet.rows)) {
                        console.log('No ofline data found for {$widget->getMetaObject()->getAliasWithNamespace()}: falling back to server request');
                        return fnFallback();
                    }
                    console.log('offline data loaded');
                    aData = oDataSet.rows;

                    // TODO add offline data here always once filtering and sorting
                    // reliably works offline. Currently sorting over date/time goes wrong
                    if (bGetFirstRowOnly === true && aRowsAddedOffline.length > 0) {
                        aData = aRowsAddedOffline.concat(aData);
                    }

                    for (var k in {$oParamsJs}) {
                        if (k.startsWith(sUrlFilterPrefix)) {
                            oFilters.conditions.push({
                                expression: k.substring(sUrlFilterPrefix.length),
                                comparator: '=',
                                value: {$oParamsJs}[k]
                            });
                        }
                    }
                    if ({$oParamsJs}.data && {$oParamsJs}.data.filters) {
                        if (oFilters.conditions.length === 0) {
                            oFilters = {$oParamsJs}.data.filters;
                        } else {
                            if ({$oParamsJs}.data.filters.operator === 'AND') {
                                {$oParamsJs}.data.filters.conditions.push(...oFilters.conditions);
                                oFilters = {$oParamsJs}.data.filters;
                            } else {
                                oFilters.nested_groups = [{$oParamsJs}.data.filters];
                            }
                        }
                    } 
                    aData = exfTools.data.filterRows(aData, oFilters);

                    if ({$oParamsJs}.q !== undefined && {$oParamsJs}.q !== '') {
                        var sQuery = {$oParamsJs}.q.toString().toLowerCase();
                        {$this->buildJsQuickSearchFilter('sQuery', 'aData')}
                    }

                    if ({$oParamsJs}.sort !== undefined && {$oParamsJs}.order !== undefined) {
                        {$oParamsJs}.sort.split(',').forEach(function(sSort, iPos) {
                            aSorters.push({
                                columnName: sSort,
                                direction: ({$oParamsJs}.order.split(',')[iPos] || 'asc')
                            });
                        });
                        aData = exfTools.data.sortRows(aData, aSorters);
                    }

                    if (bGetFirstRowOnly === false && aRowsAddedOffline.length > 0) {
                        aData = aRowsAddedOffline.concat(aData);
                    }

                    iFiltered = aData.length;
                    
                    if ({$oParamsJs}.start >= 0 && {$oParamsJs}.length > 0) {
                        aData = aData.slice({$oParamsJs}.start, {$oParamsJs}.start+{$oParamsJs}.length);
                    }

                    if (bGetFirstRowOnly) {
                        {$oModelJs}.setData(aData = aData[0]);
                    } else {
                        {$oModelJs}.setData({
                            oId: '{$widget->getMetaObject()->getId()}', 
                            rows: aData, 
                            recordsFiltered: iFiltered,
                            recordsTotal: iFiltered
                        });
                    }
                    
                    setTimeout(function(){
                        {$onModelLoadedJs}
                    }, 100);
                })
                .then(function(){
                    return Promise.resolve($oModelJs);
                });

            })()
                
JS;
    }
    
    /**
     * Returns an inline JS-snippet to test if a given JS row object matches the quick search string.
     *  
     * @param string $sQueryJs
     * @param string $oRowJs
     * @return string
     */
    protected function buildJsQuickSearchFilter(string $sQueryJs = 'sQuery', string $aDataJs = 'aData') : string
    {
        $widget = $this->getElement()->getWidget();
        
        if (! $widget instanceof iHaveQuickSearch) {
            return '';
        }
        
        $filters = [];
        $quickSearchCondGroup = $widget->getQuickSearchConditionGroup();
        if ($quickSearchCondGroup->countNestedGroups(false) > 0) {
            throw new FacadeUnsupportedWidgetPropertyWarning('Quick search with custom condition_group not supported in preloaded offline data!');
        }
        foreach ($quickSearchCondGroup->getConditions() as $condition) {
            if ($condition->getExpression()->isMetaAttribute()) {
                $filters[] = "((oRow['{$condition->getExpression()->toString()}'] || '').toString().toLowerCase().indexOf({$sQueryJs}) !== -1)";
                if ($condition->getExpression()->getAttribute()->isLabelForObject()) {
                    $labelAlias = MetaAttributeInterface::OBJECT_LABEL_ALIAS;
                    $filters[] = "((oRow['{$labelAlias}'] || '').toString().toLowerCase().indexOf({$sQueryJs}) !== -1)";
                }
            } else {
                throw new FacadeUnsupportedWidgetPropertyWarning('Quick search filters not based on simple attribute_alias not supported in preloaded offline data!');
            }
        }
        
        if (! empty($filters)) {
            $filterJs = implode(' || ', $filters);
        } else {
            return ''; 
        }
        
        return <<<JS

                            
                                {$aDataJs} = {$aDataJs}.filter(oRow => {
                                    if (oRow === undefined) {
                                        return false;
                                    }
                                    return {$filterJs};
                                });

JS;
    }
}