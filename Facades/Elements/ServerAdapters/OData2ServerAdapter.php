<?php
namespace exface\UI5Facade\Facades\Elements\ServerAdapters;

use exface\UI5Facade\Facades\Elements\UI5AbstractElement;
use exface\UI5Facade\Facades\Interfaces\UI5ServerAdapterInterface;
use exface\UrlDataConnector\DataConnectors\OData2Connector;
use exface\Core\Exceptions\Facades\FacadeLogicError;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Factories\QueryBuilderFactory;
use exface\UrlDataConnector\QueryBuilders\OData2JsonUrlBuilder;
use exface\Core\Factories\ConditionFactory;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Actions\ReadData;
use exface\Core\Actions\ReadPrefill;
use exface\UrlDataConnector\Actions\CallOData2Operation;
use exface\Core\Actions\DeleteObject;
use exface\Core\Interfaces\Widgets\iHaveQuickSearch;

/**
 * 
 * @author rml
 * 
 * Known issues:
 * 
 * - Local filtering will not yield expected results if pagination is enabled. However,
 * this seems to be also true for th OData2JsonUrlBuilder. Perhaps, it would be better
 * to disable pagination as soon as at least one filter is detected, that cannot be applied
 * remotely.
 * 
 * - $inlinecount=allpages is allways used, not only if it is explicitly enabled in the
 * data adress property `odata_$inlinecount` (this property has no effect here!). This is
 * due to the fact, that the "read+1" pagination would significantly increase the complexity
 * of the adapter logic.
 * 
 * - for now QuickSearch must only include filters that are filtered locally as there seems to be an issue
 * with the way the URL parameters are build using "substringof()". Either the oData Service needs to support
 * the substring() function or there is another problem, the issue needs some more digging into
 *
 */
class OData2ServerAdapter implements UI5ServerAdapterInterface
{
    private $element = null;
    
    public function __construct(UI5AbstractElement $element)
    {
        $this->element = $element;    
    }
    
    public function getElement() : UI5AbstractElement
    {
        return $this->element;
    }
    
    public function buildJsServerRequest(ActionInterface $action, string $oModelJs, string $oParamsJs, string $onModelLoadedJs, string $onErrorJs = '', string $onOfflineJs = '') : string
    {
        switch (true) {
            case $action instanceof ReadPrefill:
                return $this->buildJsPrefillLoader($oModelJs, $oParamsJs, $onModelLoadedJs, $onErrorJs, $onOfflineJs);
            case $action instanceof ReadData:
                return $this->buildJsDataLoader($oModelJs, $oParamsJs, $onModelLoadedJs, $onErrorJs, $onOfflineJs);
            case $action instanceof CallOData2Operation:
                return $this->buildJsCallFunctionImport($oModelJs, $oParamsJs, $onModelLoadedJs, $onErrorJs, $onOfflineJs);
            case $action instanceof DeleteObject:
                // todo 
            default:
                throw new FacadeLogicError('TODO');
        }
        
        return '';
    }
    
    protected function buildJsDataLoader(string $oModelJs, string $oParamsJs, string $onModelLoadedJs, string $onErrorJs = '', string $onOfflineJs = '') : string
    {
        $widget = $this->getElement()->getWidget();
        $object = $widget->getMetaObject();
        
        $localFilters = json_encode($this->getAttributeAliasesForLocalFilters($object));
        $quickSearchFilters = [];
        if ($widget instanceof iHaveQuickSearch) {
            
            foreach ($widget->getAttributesForQuickSearch() as $attr) {
                $quickSearchFilters[] = $attr->getAlias();
            }
            if (count($quickSearchFilters) !== 0) {
                $quickSearchFilters = json_encode($quickSearchFilters);
            }          
        }
        
        return <<<JS

                console.log('oParams:', {$oParamsJs});
                
                var oDataModel = new sap.ui.model.odata.v2.ODataModel({$this->getODataModelParams($object)});  
                var oDataReadParams = {};
                var oDataReadFiltersSearch = [];
                var oDataReadFiltersQuickSearch = [];
                var oDataReadFilters = [];
                var oDataReadFiltersArray = [];
                var oQuickSearchFilters = {$quickSearchFilters};
                var oLocalFilters = {$localFilters};
                console.log('QuickSearch: ', oQuickSearchFilters[0])
                
                // Pagination
                if ({$oParamsJs}.hasOwnProperty('length') === true) {
                    oDataReadParams.\$top = {$oParamsJs}.length;
                    if ({$oParamsJs}.hasOwnProperty('start') === true) {
                        oDataReadParams.\$skip = {$oParamsJs}.start;      
                    }
                    oDataReadParams.\$inlinecount = 'allpages';
                }

                // Filters
                if ({$oParamsJs}.data && {$oParamsJs}.data.filters && {$oParamsJs}.data.filters.conditions) {
                    var conditions = {$oParamsJs}.data.filters.conditions               
                    for (var i = 0; i < conditions.length; i++) {
                        switch (conditions[i].comparator) {
                            case '=':
                                var oOperator = "Contains";
                                break;
                            case '!=':
                                var oOperator = "NotContains";
                                break;
                            case '==':
                                var oOperator = "EQ";
                                break;                            
                            case '!==':
                                var oOperator = "NE";
                                break;
                            case '<':
                                var oOperator = "LT";
                                break;
                            case '<=':
                                var oOperator = "LE";
                                break;
                            case '>':
                                var oOperator = "GT";
                                break;
                            case '>=':
                                var oOperator ="GE";
                                break;
                            default:
                                var oOperator = "EQ";
                        }
                        if (conditions[i].value !== "") {
                            var filter = new sap.ui.model.Filter({
                                path: conditions[i].expression,
                                operator: oOperator,
                                value1: conditions[i].value
                            });
                            oDataReadFiltersSearch.push(filter);                            
                        }
                        if ({$oParamsJs}.q !== undefined && {$oParamsJs}.q !== "" ) {
                            if (oQuickSearchFilters[0] !== undefined) {
                                if (oQuickSearchFilters.includes(conditions[i].expression) && !oLocalFilters.includes(conditions[i].expression)) {
                                    var filterQuickSearchItem = new sap.ui.model.Filter({
                                        path: conditions[i].expression,
                                        operator: "Contains",
                                        value1: {$oParamsJs}.q
                                    });
                                    oDataReadFiltersQuickSearch.push(filterQuickSearchItem);
                                }
                            } 
                        }                        
                    }
                }
                
                if (oDataReadFiltersSearch.length !== 0) {
                    var tempFilter = new sap.ui.model.Filter({filters: oDataReadFiltersSearch, and: false})
                    var test = tempFilter instanceof sap.ui.model.Filter;
                    console.log('Test1: ', test);
                    oDataReadFiltersArray.push(tempFilter);
                }
                if (oDataReadFiltersQuickSearch.length !== 0) {
                    var tempFilter2 = new sap.ui.model.Filter({filters: oDataReadFiltersQuickSearch, and: false})
                    var test2 = tempFilter2 instanceof sap.ui.model.Filter;
                    console.log('Test2: ', test2);
                    oDataReadFiltersArray.push(tempFilter2);
                }
                if (oDataReadFiltersArray.length !== 0) {
                    var test3 = oDataReadFiltersArray.every (filter => filter instanceof sap.ui.model.Filter)
                    console.log('Test3: ', test3);
                    var combinedFilter = new sap.ui.model.Filter({
                        filters: oDataReadFiltersArray,
                        and: true
                    })
                    oDataReadFilters.push(combinedFilter)
                    //console.log('Test3: ', oDataReadFilters);
                }

                console.log({$localFilters});
                console.log(oDataReadFilters);               
                console.log("oDataParams: ", oDataReadParams);
                oDataModel.read('/{$object->getDataAddress()}', {
                    urlParameters: oDataReadParams,
                    filters: oDataReadFilters,
                    success: function(oData, response) {
                        console.log(oData);
                        var resultRows = oData.results;
                        
                        //Local Filtering
                        if ({$oParamsJs}.data && {$oParamsJs}.data.filters && {$oParamsJs}.data.filters.conditions) {                            
                            if (oLocalFilters.length !== 0) {
                                var conditions = {$oParamsJs}.data.filters.conditions;
                                
                                //QuickSearchFilter Local
                                if ({$oParamsJs}.q !== undefined && {$oParamsJs}.q !== "" && oQuickSearchFilters[0] !== undefined) {
                                    var quickSearchVal = {$oParamsJs}.q.toString().toLowerCase();
                                    resultRows = resultRows.filter(row => {
                                            var filtered = false;
                                            for (var i = 0; i < oQuickSearchFilters.length; i++) {
                                                if (oLocalFilters.includes(oQuickSearchFilters[i]) && row[oQuickSearchFilters[i]].toString().toLowerCase().includes(quickSearchVal)) {
                                                    filtered = true;
                                                }
                                                if (!oLocalFilters.includes(oQuickSearchFilters[i])) {
                                                    filtered = true;
                                                }
                                            }
                                            return filtered;
                                    });
                                }
                                
                                for (var i = 0; i < oLocalFilters.length; i++) {
                                    var filterAttr = oLocalFilters[i];
                                    var cond = {};
                                    for (var j = 0; j < conditions.length; j++) {
                                        if (conditions[j].expression === filterAttr) {
                                            cond = conditions[j];
                                        }
                                    }
                                    if (cond.value === undefined || cond.value === null || cond.value === '') {
                                        continue;
                                    }
                                    switch (cond.comparator) {
                                        case '==':
                                            resultRows = resultRows.filter(row => {
                                                return row[cond.expression] == cond.value
                                            });
                                            break;
                                        case '!==':
                                            resultRows = resultRows.filter(row => {
                                                return row[cond.expression] !== cond.value
                                            });
                                            break;
                                        case '!=':
                                            var val = cond.value.toString().toLowerCase();
                                            resultRows = resultRows.filter(row => {
                                                if (row[cond.expression] === undefined) return true;
                                                return ! row[cond.expression].toString().toLowerCase().includes(val);
                                            });
                                            break;
                                        case '=':
                                        default:
                                            var val = cond.value.toString().toLowerCase();
                                            resultRows = resultRows.filter(row => {
                                                if (row[cond.expression] === undefined) return false;
                                                return row[cond.expression].toString().toLowerCase().includes(val);
                                            });
                                    }                                    
                                }
                            }
                        }
                        var oRowData = {
                            rows: resultRows
                        };

                        // Pagination
                        if (oData.__count !== undefined) {
                            oRowData.recordsFiltered = oData.__count;
                        }
                        
                        {$oModelJs}.setData(oRowData);
                        {$onModelLoadedJs}
                        {$this->getElement()->buildJsBusyIconHide()}
                    },
                    error: function(oError) {
                        console.error(oError);
                    }
                });
                
JS;
    }

    protected function buildJsPrefillLoader(string $oModelJs, string $oParamsJs, string $onModelLoadedJs, string $onErrorJs = '', string $onOfflineJs = '') : string
    {
        $object = $this->getElement()->getMetaObject();
        if ($object->hasUidAttribute() === false) {
            throw new FacadeLogicError('TODO');
        } else {
            $uidAttr = $object->getUidAttribute();
        }
        
        $takeFirstRowOnly = <<<JS

        if (Object.keys({$oModelJs}.getData()).length !== 0) {
            {$oModelJs}.setData({});
        }
        if (Array.isArray(oRowData.rows) && oRowData.rows.length === 1) {
            {$oModelJs}.setData(oRowData.rows[0]);
        }

JS;
        $onModelLoadedJs = $takeFirstRowOnly . $onModelLoadedJs;
        
        return <<<JS
        
        var oFirstRow = {$oParamsJs}.data.rows[0];
        if (oFirstRow === undefined) {
            console.error('No data to filter the prefill!');
        }

        // TODO filter
        {$oParamsJs}.data.filters = {
            conditions: [
                {
                    comparator: "==",
                    expression: "{$uidAttr->getAlias()}",
                    object_alias: "{$object->getAliasWithNamespace()}",
                    value: oFirstRow["{$object->getUidAttribute()->getAlias()}"]
                }
            ]
        };
        {$this->buildJsDataLoader($oModelJs, $oParamsJs, $onModelLoadedJs, $onErrorJs, $onOfflineJs)}
JS;
    }
    
    protected function needsLocalFiltering(MetaAttributeInterface $attr) : bool
    {
        return BooleanDataType::cast($attr->getDataAddressProperty('filter_locally')) ?? false;
    }
           
    protected function getODataModelParams(MetaObjectInterface $object) : string
    {
        $connection = $object->getDataConnection();
        
        if (! $connection instanceof OData2Connector) {
            throw new FacadeLogicError('Cannot use direct OData 2 connections with object "' . $object->getName() . '" (' . $object->getAliasWithNamespace() . ')!');
        }
        
        $params = [];
        $params['serviceUrl'] = rtrim($connection->getUrl(), "/") . '/';
        if ($connection->getUser()) {
            $params['user'] = $connection->getUser();
            $params['password'] = $connection->getPassword();
            $params['withCredentials'] = true;
            //$params['headers'] = ['Authorization' => 'Basic TU9WX0RFVjpzY2h1ZXJlcjVh'];
        }
        if ($fixedParams = $connection->getFixedUrlParams()) {     
            $fixedParamsArr = [];
            parse_str($fixedParams, $fixedParamsArr);
            $params['serviceUrlParams'] = array_merge($params['serviceUrlParams'] ?? [], $fixedParamsArr);
            $params['metadataUrlParams'] = array_merge($params['metadataUrlParams'] ?? [], $fixedParamsArr);
        }
        
        return json_encode($params);
    }
    
    protected function getAttributeAliasesForLocalFilters(MetaObjectInterface $object) : array
    {
        $localFilterAliases = [];
        $dummyQueryBuilder = QueryBuilderFactory::createForObject($object);
        if (! $dummyQueryBuilder instanceof OData2JsonUrlBuilder) {
            throw new FacadeLogicError('TODO');
        }
        foreach ($object->getAttributes()->getAll() as $attr) {
            $filterCondition = ConditionFactory::createFromExpressionString($object, $attr->getAlias(), '');
            $filterQpart = $dummyQueryBuilder->addFilterCondition($filterCondition);
            if ($filterQpart->getApplyAfterReading()) {
                $localFilterAliases[] = $attr->getAlias();
            }
        }
        return $localFilterAliases;
    }
    
    protected function buildJsDataCreate(string $oModelJs, string $oParamsJs, string $onModelLoadedJs, string $onErrorJs = '', string $onOfflineJs = '') : string
    {
        // TODO
        return '';
    }
    
    protected function buildJsCallFunctionImport(string $oModelJs, string $oParamsJs, string $onModelLoadedJs, string $onErrorJs = '', string $onOfflineJs = '') : string
    {
        // TODO
        return '';
    }
}