<?php
namespace exface\UI5Facade\Facades\Elements\ServerAdapters;

use exface\UI5Facade\Facades\Elements\UI5AbstractElement;
use exface\UI5Facade\Facades\Interfaces\UI5ServerAdapterInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Actions\ReadData;
use exface\Core\Interfaces\Widgets\iHaveQuickSearch;
use exface\Core\Actions\ReadPrefill;
use exface\Core\Exceptions\Facades\FacadeUnsupportedWidgetPropertyWarning;
use exface\Core\Exceptions\Facades\FacadeRuntimeError;

class PreloadServerAdapter implements UI5ServerAdapterInterface
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
        $fallBackRequest = $this->getFallbackAdapter()->buildJsServerRequest($action, $oModelJs, $oParamsJs, $onModelLoadedJs, $onOfflineJs);
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
        $uidComp = EXF_COMPARATOR_EQUALS;
        return <<<JS

                var uid;
                if ($oParamsJs.data && $oParamsJs.data.rows && $oParamsJs.data.rows[0]) {
                    uid = $oParamsJs.data.rows[0]['{$this->getElement()->getMetaObject()->getUidAttribute()->getDataAddress()}'];
                } else {
                    console.warn('Cannot fetch preload data: no request data rows selected!');
                }

                if (uid === undefined || uid === '') {
                    console.warn('Cannot prefill from preload data: no UID value found in input rows!');
                }

                if ($oParamsJs.data !== undefined) {

                    if ($oParamsJs.data.filters === undefined) {
                        $oParamsJs.data.filters = {};
                    }
    
                    if ($oParamsJs.data.filters.conditions === undefined) {
                        $oParamsJs.data.filters.conditions = [];
                    }     
    
                    $oParamsJs.data.filters.conditions.push({
                        expression: '{$this->getElement()->getMetaObject()->getUidAttribute()->getDataAddress()}',
                        comparator: '{$uidComp}',
                        value: uid,
                        object_alias: '{$this->getElement()->getMetaObject()->getAliasWithNamespace()}'
                    });

                }

                {$this->buildJsDataLoader($oModelJs, $oParamsJs, $onModelLoadedJs, $onOfflineJs, $fallBackRequest, true)}

JS;
    }
    protected function buildJsDataLoader(string $oModelJs, string $oParamsJs, string $onModelLoadedJs, string $onOfflineJs, string $fallBackRequest, bool $useFirstRowOnly = false) : string
    {
        $element = $this->getElement();
        $widget = $element->getWidget();
        $beforeModelUpdate = $useFirstRowOnly ? '' : $element->buildJsDataLoaderOnBeforeModelUpdate($oModelJs);
        
        if ($useFirstRowOnly === true) {
            $newData = "aData[0]";
        } else {
            $newData = "$.extend({}, preload.response, {rows: aData, recordsFiltered: iFiltered})";
        }
        
        return <<<JS
        
                exfPreloader
                .getPreload('{$widget->getMetaObject()->getAliasWithNamespace()}')
                .then(preload => {
                    if (preload !== undefined && preload.response !== undefined && preload.response.rows !== undefined) {
                        var aData = preload.response.rows;
                        if ({$oParamsJs}.data && {$oParamsJs}.data.filters && {$oParamsJs}.data.filters.conditions) {
                            var conditions = {$oParamsJs}.data.filters.conditions;
                            var fnFilter;

                            for (var i in conditions) {
                                var cond = conditions[i];
                                if (cond.value === undefined || cond.value === null || cond.value === '') continue;
                                switch (cond.comparator) {
                                    case '==':
                                        aData = aData.filter(oRow => {
                                            return oRow[cond.expression] == cond.value
                                        });
                                        break;
                                    case '!==':
                                        aData = aData.filter(oRow => {
                                            return oRow[cond.expression] !== cond.value
                                        });
                                        break;
                                    case '!=':
                                        var val = cond.value.toString().toLowerCase();
                                        aData = aData.filter(oRow => {
                                            if (oRow[cond.expression] === undefined) return true;
                                            return ! oRow[cond.expression].toString().toLowerCase().includes(val);
                                        });
                                        break;
                                    case '=':
                                    default:
                                        var val = cond.value.toString().toLowerCase();
                                        aData = aData.filter(oRow => {
                                            if (oRow[cond.expression] === undefined) return false;
                                            return oRow[cond.expression].toString().toLowerCase().includes(val);
                                        });
                                }
                            }

                            if ({$oParamsJs}.q !== undefined && {$oParamsJs}.q !== '') {
                                var sQuery = {$oParamsJs}.q.toString().toLowerCase();
                                {$this->buildJsQuickSearchFilter('sQuery', 'aData')}
                            }
                            
                            var iFiltered = aData.length;
                        }
                        
                        if ({$oParamsJs}.start >= 0 && {$oParamsJs}.length > 0) {
                            aData = aData.slice({$oParamsJs}.start, {$oParamsJs}.start+{$oParamsJs}.length);
                        }
                        
                        {$beforeModelUpdate}
                        {$oModelJs}.setData($newData);
                        {$onModelLoadedJs}
                    } else {
                        console.log('No preloaded data found: falling back to server request');
                        {$fallBackRequest}
                    }
                })
                .then(function(){
                    return $oModelJs;
                });
                
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
                                    if (oRow[cond.expression] === undefined) return false;
                                    return {$filterJs};
                                });

JS;
    }
}