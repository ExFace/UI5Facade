<?php
namespace exface\UI5Facade\Facades\Elements\ServerAdapters;

use exface\UI5Facade\Facades\Elements\UI5AbstractElement;
use exface\UI5Facade\Facades\Interfaces\UI5ServerAdapterInterface;
use exface\UrlDataConnector\DataConnectors\OData2Connector;
use exface\Core\Exceptions\Facades\FacadeLogicError;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\DataTypes\DateDataType;
use exface\Core\Factories\QueryBuilderFactory;
use exface\UrlDataConnector\QueryBuilders\OData2JsonUrlBuilder;
use exface\Core\Factories\ConditionFactory;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Actions\ReadData;
use exface\Core\Actions\ReadPrefill;
use exface\UrlDataConnector\Actions\CallOData2Operation;
use exface\Core\Actions\DeleteObject;
use exface\Core\Interfaces\Widgets\iHaveQuickSearch;
use exface\Core\Actions\ExportXLSX;
use exface\Core\Actions\ExportCSV;
use exface\Core\Actions\UpdateData;
use exface\Core\CommonLogic\QueryBuilder\QueryPart;
use exface\Core\CommonLogic\QueryBuilder\QueryPartAttribute;
use exface\Core\Actions\SaveData;
use exface\Core\Actions\CreateData;
use exface\Core\DataTypes\TimeDataType;
use exface\Core\DataTypes\DateTimeDataType;

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
        $test = '';
        switch (true) {
            case $action instanceof ExportXLSX:
                return "console.log('oParams:', {$oParamsJs});";
            case $action instanceof ExportCSV:
                return "console.log('oParams:', {$oParamsJs});";
            case $action instanceof ReadPrefill:
                return $this->buildJsPrefillLoader($oModelJs, $oParamsJs, $onModelLoadedJs, $onErrorJs, $onOfflineJs);
            case $action instanceof ReadData:
                return $this->buildJsDataLoader($oModelJs, $oParamsJs, $onModelLoadedJs, $onErrorJs, $onOfflineJs);
            case $action instanceof CallOData2Operation:
                return $this->buildJsCallFunctionImport($action, $oModelJs, $oParamsJs, $onModelLoadedJs, $onErrorJs, $onOfflineJs);
            case $action instanceof DeleteObject:
                return $this->buildJsDataDelete($oModelJs, $oParamsJs, $onModelLoadedJs, $onErrorJs, $onOfflineJs);
            case $action instanceof UpdateData:
                return $this->buildJsDataUpdate($action, $oModelJs, $oParamsJs, $onModelLoadedJs, $onErrorJs, $onOfflineJs);
            case $action instanceof CreateData:
                return $this->buildJsDataUpdate($action, $oModelJs, $oParamsJs, $onModelLoadedJs, $onErrorJs, $onOfflineJs);
            case $action instanceof SaveData:
                return $this->buildJsDataUpdate($action, $oModelJs, $oParamsJs, $onModelLoadedJs, $onErrorJs, $onOfflineJs);
            default:
                return <<<JS
console.log('oParams:', '{$action->getName()}');
console.log('oParams:', {$oParamsJs});

JS;
                //throw new FacadeLogicError('TODO');
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
        $dateAttributes = [];
        $timeAttributes = [];
        foreach ($object->getAttributes() as $qpart) {
            if ($qpart->getDataType() instanceof DateDataType) {
                $dateAttributes[] = $qpart->getAlias();
            }
            if ($qpart->getDataType() instanceof TimeDataType) {
                $timeAttributes[] = $qpart->getAlias();
            }
        }
        $dateAttributes = json_encode($dateAttributes);
        $timeAttributes = json_encode($timeAttributes);
        
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
                        if ({$timeAttributes}.indexOf(conditions[i].expression) > -1) {
                            var d = conditions[i].value;
                            var timeParts = d.split(':');
                            if (timeParts[3] === undefined || timeParts[3]=== null || timeParts[3] === "") {
                                timeParts[3] = "00";
                            }
                            for (var j = 0; j < timeParts.length; j++) {
                                timeParts[j] = ('0'+(timeParts[j])).slice(-2);
                            }                            
                            var timeString = "PT" + timeParts[0] + "H" + timeParts[1] + "M" + timeParts[3] + "S";
                            var value = timeString;
                        } else {
                            var value = conditions[i].value;
                        }
                        var filter = new sap.ui.model.Filter({
                            path: conditions[i].expression,
                            operator: oOperator,
                            value1: value
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
                var tempFilter = new sap.ui.model.Filter({filters: oDataReadFiltersSearch, and: true})
                var test = tempFilter instanceof sap.ui.model.Filter;
                oDataReadFiltersArray.push(tempFilter);
            }
            if (oDataReadFiltersQuickSearch.length !== 0) {
                var tempFilter2 = new sap.ui.model.Filter({filters: oDataReadFiltersQuickSearch, and: false})
                var test2 = tempFilter2 instanceof sap.ui.model.Filter;
                oDataReadFiltersArray.push(tempFilter2);
            }
            if (oDataReadFiltersArray.length !== 0) {
                var test3 = oDataReadFiltersArray.every (filter => filter instanceof sap.ui.model.Filter)
                var combinedFilter = new sap.ui.model.Filter({
                    filters: oDataReadFiltersArray,
                    and: true
                })
                oDataReadFilters.push(combinedFilter)
            }

            //Sorters
            var oDataReadSorters = [];
            if ({$oParamsJs}.sort !== undefined && {$oParamsJs}.sort !== "") {
                var sorters = {$oParamsJs}.sort.split(",");
                var directions = {$oParamsJs}.order.split(",");
                for (var i = 0; i < sorters.length; i++) {
                    if (directions[i] === "desc") {
                        var sortObject = new sap.ui.model.Sorter(sorters[i], true);
                    } else {
                        var sortObject = new sap.ui.model.Sorter(sorters[i], false);
                    }
                    oDataReadSorters.push(sortObject);
                }
            }

            oDataModel.read('/{$object->getDataAddress()}', {
                urlParameters: oDataReadParams,
                filters: oDataReadFilters,
                sorters: oDataReadSorters,
                success: function(oData, response) {
                    var resultRows = oData.results;

                    //Date Conversion
                    if ({$dateAttributes}[0] !== undefined) {
                        for (var i = 0; i < resultRows.length; i++) {
                            for (var j = 0; j < {$dateAttributes}.length; j++) {
                                var attr = {$dateAttributes}[j].toString();
                                var d = resultRows[i][attr];
                                if (d !== undefined && d !== "" && d !== null) {
                                    var oDateFormat = sap.ui.core.format.DateFormat.getDateTimeInstance();
                                    var newVal = oDateFormat.format(d);                                   
                                    resultRows[i][attr] = newVal;
                                }
                            }
                        }
                    }
                    //Time Conversion
                    if ({$timeAttributes}[0] !== undefined) {
                        for (var i = 0; i < resultRows.length; i++) {
                            for (var j = 0; j < {$timeAttributes}.length; j++) {
                                var attr = {$timeAttributes}[j].toString();
                                var d = resultRows[i][attr];
                                if (d.ms !== undefined && d.ms !== "" && d.ms !== null) {
                                    var hours = Math.floor(d.ms / (1000 * 60 * 60));
                                    var minutes = Math.floor(d.ms / 60000 - hours * 60);
                                    var seconds = Math.floor(d.ms / 1000 - hours * 60 * 60 - minutes * 60);
                                    var newVal = hours + ":" + minutes + ":" + seconds;
                                    resultRows[i][attr] = newVal;
                                }
                            }
                        }
                    }
                    
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
                error: {$onErrorJs}
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
        
        $onErrorJs = "function() { " . $onErrorJs . "}";
        
        return <<<JS
        
            var oFirstRow = {$oParamsJs}.data.rows[0];
            if (oFirstRow === undefined) {
                console.error('No data to filter the prefill!');
            }
    
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
    
    protected function buildJsDataUpdate(ActionInterface $action, string $oModelJs, string $oParamsJs, string $onModelLoadedJs, string $onErrorJs = '', string $onOfflineJs = '') : string
    {
        // TODO
        $widget = $this->getElement()->getWidget();
        $object = $widget->getMetaObject();
        $uidAttribute = $object->getUidAttributeAlias();
        $attributes = $object->getAttributes();
        $attributesType = (object)array();
        foreach ($attributes as $attr) {
            $key = $attr->getAlias();
            $attributesType->$key= $attr->getDataAddressProperty('odata_type');
        }
        $attributesType = json_encode($attributesType);
        $uidAttributeType = $object->getUidAttribute()->getDataAddressProperty('odata_type');
        if ($action instanceof CreateData) {
            $serverCall = <<<JS
            
            oDataModel.create("/{$object->getDataAddress()}", oData, {
                success: {$onModelLoadedJs},
                error: {$onErrorJs}
            });

JS;
        }
        elseif ($action instanceof UpdateData || $action instanceof SaveData) {
            $serverCall = <<<JS
            
            if ('{$uidAttribute}' in oData) {
                var oDataUid = oData.{$uidAttribute};
                var type = '{$uidAttributeType}';
                switch (type) {
                    case 'Edm.Guid':
                        oDataUid = "guid" + "'" + data['{$uidAttribute}']+ "'";
                        break;
                    case 'Edm.Binary':
                        oDataUid = "binary" + "'" + data['{$uidAttribute}'] + "'";
                        break;
                    default:
                        oDataUid = "'" + oDataUid + "'";
                }
            } else {
                var oDataUid = '';
            }

            oDataModel.update("/{$object->getDataAddress()}(" + oDataUid+ ")", oData, {
                success: {$onModelLoadedJs},
                error: {$onErrorJs}
            });

JS;
        }
        
        return <<<JS
            console.log('Params: ',{$oParamsJs})
            var oDataModel = new sap.ui.model.odata.v2.ODataModel({$this->getODataModelParams($object)});
            //oDataModel.setUseBatch(false);
            var data = {$oParamsJs}.data.rows[0];            
            var oData = {};
            Object.keys(data).forEach(key => {
                if (data[key] != "") {
                    var type = {$attributesType}[key];
                    switch (type) {
                        case 'Edm.DateTimeOffset':
                            var d = new Date(data[key]);
                            var date = d.toISOString();
                            var datestring = date.replace(/\.[0-9]{3}/, '');
                            oData[key] = datestring;
                            break;
                        case 'Edm.DateTime':
                            var d = new Date(data[key]);
                            var date = d.toISOString();
                            var datestring = date.substring(0,19);
                            oData[key] = datestring;
                            break;                        
                        case 'Edm.Time':
                            var d = data[key];
                            var timeParts = d.split(':');
                            if (timeParts[3] === undefined || timeParts[3]=== null || timeParts[3] === "") {
                                timeParts[3] = "00";
                            }
                            for (var i = 0; i < timeParts.length; i++) {
                                timeParts[i] = ('0'+(timeParts[i])).slice(-2);
                            }                            
                            var timeString = "PT" + timeParts[0] + "H" + timeParts[1] + "M" + timeParts[3] + "S";
                            oData[key] = timeString;
                            break;
                        case 'Edm.Decimal':
                            oData[key] = data[key].toString();
                            break; 
                        default:
                            oData[key] = data[key];
                    }
                }
            });
            {$serverCall}

JS;
    }
    
    protected function buildJsDataDelete(string $oModelJs, string $oParamsJs, string $onModelLoadedJs, string $onErrorJs = '', string $onOfflineJs = '') : string
    {
        $widget = $this->getElement()->getWidget();
        $object = $widget->getMetaObject();
        $uidAttribute = $object->getUidAttributeAlias();
        $uidAttributeType = $object->getUidAttribute()->getDataAddressProperty('odata_type');
        
        return <<<JS
            console.log($oParamsJs)
            var oDataModel = new sap.ui.model.odata.v2.ODataModel({$this->getODataModelParams($object)});
            //oDataModel.setUseBatch(false);
            var data = {$oParamsJs}.data.rows[0];
            if ('{$uidAttribute}' in data) {
                var oDataUid = data.{$uidAttribute};
                var type = '{$uidAttributeType}';
                switch (type) {
                    case 'Edm.Guid':
                        oDataUid = "guid" + "'" + oDataUid + "'";
                        break;
                    case 'Edm.Binary':
                        oDataUid = "binary" + "'" + oDataUid + "'";
                        break;
                    case 'Edm.DateTimeOffset':
                        var d = new Date(oDataUid);
                        var date = d.toISOString();
                        var datestring = date.replace(/\.[0-9]{3}/, '');
                        oDataUid = "'" + datestring + "'";
                        break;
                    case 'Edm.DateTime':
                        var d = new Date(oDataUid);
                        var date = d.toISOString();
                        var datestring = date.substring(0,19);
                        oDataUid = "'" + datestring + "'";
                        break;                        
                    case 'Edm.Time':
                        var d = oDataUid;
                        var timeParts = d.split(':');
                        if (timeParts[3] === undefined || timeParts[3]=== null || timeParts[3] === "") {
                            timeParts[3] = "00";
                        }
                        for (var i = 0; i < timeParts.length; i++) {
                            timeParts[i] = ('0'+(timeParts[i])).slice(-2);
                        }                            
                        var timeString = "PT" + timeParts[0] + "H" + timeParts[1] + "M" + timeParts[3] + "S";
                        oDataUid = "'" + timeString + "'";
                        break;
                    case 'Edm.Decimal':
                        oDataUid = "'" + oDataUid.toString() + "'";
                        break;
                    default:
                        oDataUid = "'" + oDataUid + "'";
                }
            } else {
                var oDataUid = '';
            }
            oDataModel.remove("/{$object->getDataAddress()}(" + oDataUid+ ")" , {
                success: {$onModelLoadedJs},
                error: {$onErrorJs}
            });

JS;
        
    }
    
    protected function buildJsCallFunctionImport(ActionInterface $action, string $oModelJs, string $oParamsJs, string $onModelLoadedJs, string $onErrorJs = '', string $onOfflineJs = '') : string
    {
        // TODO
        $widget = $this->getElement()->getWidget();
        $object = $widget->getMetaObject();
        $parameters = $action->getParameters();
        $requiredParams = [];
        $defaultValues = (object)array();
        foreach ($parameters as $param) {
            if ($param->isRequired() === true) {
                $requiredParams[] = $param->getName();
                if ($param->hasDefaultValue()) {
                    $key = $param->getName();
                    $defaultValues->$key= $param->getDefaultValue();
                }
            }
        }
        $requiredParams = json_encode($requiredParams);
        $defaultValues = json_encode($defaultValues);
        
        
        return <<<JS

            console.log('Params: ',$oParamsJs);
            var oDataModel = new sap.ui.model.odata.v2.ODataModel({$this->getODataModelParams($object)});
            var requiredParams = {$requiredParams};
            var defaultValues = {$defaultValues};

            var oDataActionParams = {};
            if ({$oParamsJs}.data.rows.length !== 0) {
                if (requiredParams[0] !== undefined) {
                    for (var i = 0; i < requiredParams.length; i++) {
                        var param = requiredParams[i];
                        if ({$oParamsJs}.data.rows[0][requiredParams[i]] != undefined && {$oParamsJs}.data.rows[0][requiredParams[i]] != "") {
                            oDataActionParams[param] = {$oParamsJs}.data.rows[0][requiredParams[i]];
                        } else if (defaultValues.hasOwnProperty(param)) {
                            oDataActionParams[param] = defaultValues[param];
                        } else {
                            oDataActionParams[param] = "";
                            console.log('WARNING: No value given for required parameter: ', param);
                        }
                    }
                }
            } else {
                console.log('No row selected!');
            }
            oDataModel.callFunction('/{$action->getServiceName()}', {
                urlParameters: oDataActionParams,
                success: {$onModelLoadedJs},
                error: {$onErrorJs}
            });
            
JS;
    }
}