<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\UI5Facade\Facades\Elements\Traits\UI5DataElementTrait;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JsValueScaleTrait;
use exface\Core\Exceptions\Facades\FacadeRuntimeError;

/**
 * 
 * @method \exface\Core\Widgets\DataTree getWidget()
 * 
 * @author Andrej Kabachnik
 *
 */
class UI5DataTree extends UI5DataTable
{    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5DataTable::buildJsConstructorForControl()
     */
    protected function buildJsConstructorForControl($oControllerJs = 'oController') : string
    {
        return $this->buildJsPanelWrapper($this->buildJsConstructorForTreeTable($oControllerJs), $oControllerJs, null, false);
    }

    /**
     * {@inheritDoc}
     * @see UI5DataElementTrait::buildJsToolbarContent())
     */
    protected function buildJsToolbarContent($oControllerJsVar = 'oController', string $leftExtras = null, string $rightExtras = null) : string
    {
        if ($leftExtras === null && $this->hasPaginator()) {
            $leftExtras = $this->getPaginatorElement()->buildJsConstructor($oControllerJsVar);
        }
        return parent::buildJsToolbarContent($oControllerJsVar, $leftExtras, $rightExtras);
    }
    
    /**
     * 
     * @param string $oControllerJs
     * @return string
     */
    public function buildJsConstructorForTreeTable($oControllerJs = 'oController') : string
    {
        $controller = $this->getController();
        $this->initConfiguratorControl($controller, $oControllerJs);
        $widget = $this->getWidget();
        
        $this->getController()->addOnEventScript($this, self::EVENT_NAME_FIRST_VISIBLE_ROW_CHANGED, $this->buildJsOnCloseScript('oEvent'));
        
        $selection_mode = $widget->getMultiSelect() ? 'sap.ui.table.SelectionMode.MultiToggle' : 'sap.ui.table.SelectionMode.Single';
        $selection_behavior = $widget->getMultiSelect() ? 'sap.ui.table.SelectionBehavior.Row' : 'sap.ui.table.SelectionBehavior.RowOnly';
        
        if ($widget->getTreeExpandedLevels() !== null) {
            $numberOfExpandedLevelsJs = "numberOfExpandedLevels: {$widget->getTreeExpandedLevels()},";
        } else {
            $numberOfExpandedLevelsJs = "";
        }
        
        return <<<JS

        new sap.ui.table.TreeTable('{$this->getId()}', {
            {$this->buildJsProperties()}
            {$this->buildJsPropertyColumnHeaderHeight()}
            {$this->buildJsPropertyMinAutoRowCount()}
            selectionMode: {$selection_mode},
	        selectionBehavior: {$selection_behavior},
    		rowSelectionChange: {$controller->buildJsEventHandler($this, self::EVENT_NAME_CHANGE, true)},
            firstVisibleRowChanged: {$controller->buildJsEventHandler($this, self::EVENT_NAME_FIRST_VISIBLE_ROW_CHANGED, true)},
    		filter: {$controller->buildJsMethodCallFromView('onLoadData', $this)},
    		sort: {$controller->buildJsMethodCallFromView('onLoadData', $this)},
            columns: [
    			{$this->buildJsColumnsForUiTable()}
    		],
            rows: {
                path:'/rows', 
                parameters: {
                    arrayNames: [
                        '_children'
                    ],
                    {$numberOfExpandedLevelsJs}
                }
            },
            noData: [
                new sap.m.FlexBox({
                    height: "100%",
                    width: "100%",
                    justifyContent: "Center",
                    alignItems: "Center",
                    items: [
                        new sap.m.Text("{$this->getIdOfNoDataOverlay()}", {text: {$this->escapeString($this->getWidget()->getEmptyText())} })
                    ]
                })
            ],
            toggleOpenState: function(oEvent) {
                {$this->buildJsOnOpenScript('oEvent')}
            },
        })
        {$this->buildJsClickHandlers('oController')}
        {$this->buildJsPseudoEventHandlers()}
JS;
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsPropertyColumnHeaderHeight() : string
    {
        return '';
    }
    
    /**
     * 
     * @param string $oEventJs
     * @return string
     */
    protected function buildJsOnOpenScript(string $oEventJs) : string
    {
        return '';
    }
    
    /**
     * 
     * @param string $oEventJs
     * @return string
     */
    protected function buildJsOnCloseScript(string $oEventJs) : string
    {
        return '';
    }
    
    /**
     * 
     * @param string $oModelJs
     * @return string
     */
    protected function buildJsDataLoaderOnLoaded(string $oModelJs = 'oModel') : string
    {    
        if (! $this->getWidget()->getTreeParentRelationAlias()) {
            $treeModeJs = "sap.ui.getCore().byId('{$this->getId()}').setUseFlatMode(true);";
        } else {
            $treeModeJs = '';
        }
        return parent::buildJsDataLoaderOnLoaded($oModelJs) . <<<JS

                var oDataTree = {$this->buildJsTransformToTree($oModelJs . '.getData()')};
                {$oModelJs}.setData(oDataTree);
                {$treeModeJs}

JS;
    }
    
    /**
     * 
     * @param string $oControlJs
     * @return string
     */
    protected function buildJsGetRowsAll(string $oControlJs) : string
    {
        // NOTE: oTable.getModel().getData() returns only the top level rows, but .getJSON() yields
        // all. This is why the JSON parsing became neccessary
        return "({$this->buildJsTransformFromTree("JSON.parse({$oControlJs}.getModel().getJSON()")}).rows || [])";
    }
    
    /**
     * 
     * @param string $oDataJs
     * @return string
     */
    protected function buildJsTransformFromTree(string $oDataJs) : string
    {
        return <<<JS
        
                (function(oDataTree) {
                    var oDataFlat = $.extend({}, oDataTree);
                    var fnFlatten = function(aRows) {
                        var aFlat = [];
                        aRows.forEach(function(oRow) {
                            aFlat.push(oRow);
                            if (Array.isArray(oRow._children) && oRow._children.length > 0) {
                                aFlat.push(...fnFlatten(oRow._children));
                            }
                            delete oRow._children;
                        });
                        return aFlat;
                    };
                    oDataFlat.rows = fnFlatten(oDataTree.rows || []);                  
                    return oDataFlat;
                })($oDataJs)
                
JS;
    }
    
    /**
     * 
     * @param string $oDataJs
     * @return string
     */
    protected function buildJsTransformToTree(string $oDataJs) : string
    {
        $cleanupJs = '';
        $widget = $this->getWidget();
        
        // remove rows without children in oDataTree.rows if $folderFlagAlias is set to 1
        if (null !== $folderFlagAlias = $widget->getTreeFolderFlagAttributeAlias()) {
            $cleanupJs = <<<JS

                    for (let i = oDataTree.rows.length - 1; i >= 0; i--) {
                        (function(oItem, iIndex, aArr) {
                            if (oItem['{$folderFlagAlias}'] === 1 && oItem['_children'].length === 0) {
                                aArr.splice(iIndex, 1);
                            }
                         })(oDataTree.rows[i], i, oDataTree.rows);
                    }  
JS;
        }
        
        if (! $widget->hasUidColumn()) {
            throw new FacadeRuntimeError('Cannot render DataTree in UI5 if data has no UID column!');
        }
        
        return <<<JS

                (function(oDataFlat) {
                    var oDataTree = $.extend({}, oDataFlat);
                    var sParentCol = '{$widget->getTreeParentRelationAlias()}';
                    var sUidCol = '{$widget->getUidColumn()->getDataColumnName()}';

                    function list_to_tree(list) {
                      var map = {}, node, roots = [], i;
                      
                      for (i = 0; i < list.length; i += 1) {
                        map[list[i][sUidCol]] = i; // initialize the map
                        list[i]._children = []; // initialize the children
                      }
                      
                      for (i = 0; i < list.length; i += 1) {
                        node = list[i];
                        // Check, if parent node exists and place the node in its _children array
                        // Nodes with non-existent parents will be the roots
                        if (node[sParentCol] !== '' && node[sParentCol] !== null && map[node[sParentCol]] !== undefined) {
                            list[map[node[sParentCol]]]._children.push(node);
                        } else {
                            roots.push(node);
                        }
                      }
                      return roots;
                    }
                    if (sParentCol !== '') {
                        oDataTree.rows = list_to_tree(oDataFlat.rows);
                    } else {
                        for (var i = 0; i < oDataTree.rows.length; i++) {
                            oDataTree.rows[i]._children = [];
                        }
                    }

                    $cleanupJs
                    
                    return oDataTree;
                })($oDataJs)

JS;
    }
    
    /**
     * {@inheritdoc}
     * @see UI5DataElementTrait::isEditable()
     */
    public function isEditable()
    {
        return $this->getWidget()->isEditable();
    }
    
    /**
     * {@inheritdoc}
     * @see UI5DataElementTrait::hasPaginator()
     */
    protected function hasPaginator() : bool
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
     * TODO For some reason, transforming model data to tree did not work when there was a dirty-column
     * 
     * @return bool
     */
    protected function hasDirtyColumn() : bool
    {
        return false;
    }
}