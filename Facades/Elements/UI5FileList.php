<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\UI5Facade\Facades\Elements\Traits\UI5DataElementTrait;
;use exface\Core\Facades\AbstractAjaxFacade\Elements\JqueryDataTableTrait;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JsUploaderTrait;
use exface\Core\DataTypes\WidgetVisibilityDataType;
use exface\Core\Exceptions\Facades\FacadeRuntimeError;
use exface\Core\Widgets\Parts\Uploader;
use exface\Core\CommonLogic\DataSheets\DataColumn;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Actions\iModifyData;
use exface\Core\Exceptions\Widgets\WidgetLogicError;
use exface\Core\Interfaces\Actions\iCallOtherActions;
use exface\UI5Facade\Facades\Interfaces\UI5DataElementInterface;
use exface\Core\Widgets\DataButton;

/**
 * Generates sap.m.upload.UploadSet for a FileList widget.
 * 
 * TODO call `$controller->buildJsEventHandler($this, self::EVENT_NAME_CHANGE)` when a list item is 
 * selected. Also call `$this->getController()->buildJsEventHandler($this, 'select', false)` there somewhere.
 * 
 * FIXME CSS for renaming narrow list items:
 * - .sapMUCTextContainer {width: 200px}
 * - .sapMUSProgressBox {display: none}
 * - .sapMUCButtonContainer button {display: block}
 * 
 * @method \exface\Core\Widgets\FileList getWidget()
 * 
 * @author Andrej Kabachnik
 *
 */
class UI5FileList extends UI5AbstractElement implements UI5DataElementInterface
{
    const EVENT_NAME_AFTER_ITEM_ADDED = 'afterItemAdded';
    const EVENT_NAME_BEFORE_ITEM_REMOVED = 'beforeItemRemoved';
    
    use UI5DataElementTrait, JqueryDataTableTrait {
        buildJsDataLoaderOnLoaded as buildJsDataLoaderOnLoadedViaTrait;
        UI5DataElementTrait::buildJsResetter insteadof JqueryDataTableTrait;
        UI5DataElementTrait::buildJsDataResetter as buildJsDataResetterViaTrait;
        UI5DataElementTrait::buildJsDataGetter as buildJsDataGetterViaTrait;
    }
    
    use JqueryDataTableTrait;
    
    use JsUploaderTrait;
    
    /**
     * 
     * @see JsUploaderTrait::getUploader()
     */
    protected function getUploader() : Uploader
    {
        return $this->getWidget()->getUploader();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsConstructor()
     */
    public function buildJsConstructorForControl($oControllerJs = 'oController') : string
    {
        $widget = $this->getWidget();
        
        $controller = $this->getController();
        
        $controller->addOnInitScript($this->buildJsCustomizeUploaderButton());
        $controller->addOnInitScript("sap.ui.getCore().byId('{$this->getId()}').getList().setMode(sap.m.ListMode.SingleSelectMaster);");
        
        $toobarJs = $this->buildJsToolbar($oControllerJs);
        $controller->addOnInitScript("sap.ui.getCore().byId('{$this->getId()}').getList()" . $this->buildJsClickHandlers($oControllerJs));
        
        // Need to render button handlers AFTER all the other buttons in $toolbarJs
        // to make sure the buttons are rendered in the same order as their widgets
        // are created in FileList::getChildren(). Otherwise the buttons will have
        // different ids depending on whether they were created by a facade call or not.
        $controller->addOnEventScript($this, self::EVENT_NAME_AFTER_ITEM_ADDED, $this->buildJsEventHandlerUpload('oEvent'));
        $controller->addOnEventScript($this, self::EVENT_NAME_BEFORE_ITEM_REMOVED, $this->buildJsEventHandlerDelete('oEvent'));
        
        $specialCols = [
            $widget->getFilenameColumn(),
            $widget->getMimeTypeColumn()
        ];
        if ($widget->hasDownloadUrlColumn()) {
            $specialCols[] = $widget->getDownloadUrlColumn();
        }
        if ($widget->hasThumbnailColumn()) {
            $specialCols[] = $widget->getThumbnailColumn();
        }
        
        $attributesConstructors = '';
        foreach ($widget->getColumns() as $col) {
            if (in_array($col, $specialCols, true) || $col->isHidden()) {
                continue;
            }
            
            $cellWidget = $col->getCellWidget();
            if ($col->getVisibility() === WidgetVisibilityDataType::OPTIONAL) {
                $cellWidget->setHidden(true);
            }
            
            $objectAttribute = new UI5ObjectAttribute($cellWidget, $this->getFacade());
            $objectAttribute->setValueBindingPrefix('');
            $attributesConstructors .= $objectAttribute->buildJsConstructor($oControllerJs) . ',';
        }
        
        return <<<JS

        new sap.m.upload.UploadSet('{$this->getId()}', {
            showIcons: true,
            selectionChanged: {$controller->buildJsEventHandler($this, self::EVENT_NAME_CHANGE, true)},
            {$this->buildJsPropertyUpload()}
            beforeItemRemoved: {$controller->buildJsEventHandler($this, self::EVENT_NAME_BEFORE_ITEM_REMOVED, true)},
            items: {
    			path: '/rows',
                template: new sap.m.upload.UploadSetItem({
                    fileName: "{{$widget->getFilenameColumn()->getDataColumnName()}}",
					mediaType: "{{$widget->getMimeTypeColumn()->getDataColumnName()}}",
                    visibleEdit: false,
					{$this->buildJsItemPropertyUrl()}
					{$this->buildJsItemPropertyThumbnail()}
					attributes: [
                        $attributesConstructors
					]
                })
    		},
            toolbar: {$toobarJs}
        })

JS;
    }
    
    /**
     * 
     * @throws FacadeRuntimeError
     * @return string
     */
    protected function buildJsPropertyUpload() : string
    {
        $widget = $this->getWidget();
        
        if ($widget->isUploadEnabled()) {
            $uploader = $widget->getUploader();
            $maxFilenameLength = $widget->getUploader()->getMaxFilenameLength() ?? 'null';
            $maxFileSize = $widget->getUploader()->getMaxFileSizeMb() ?? 'null';
            $instantUpload = $uploader->isInstantUpload() ? 'true' : 'false';
            
            return <<<JS

            uploadEnabled: true,
            instantUpload: $instantUpload,
    		terminationEnabled: true,
            maxFileNameLength: {$maxFilenameLength},
    		maxFileSize: {$maxFileSize},
            afterItemAdded: {$this->getController()->buildJsEventHandler($this, self::EVENT_NAME_AFTER_ITEM_ADDED, true)},        
            {$this->buildJsPropertyFileTypes()}
            {$this->buildJsPropertyMediaTypes()}
JS;
        } else {
            return "uploadEnabled: false,";
        }
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsPropertyFileTypes() : string
    {
        $types = $this->getWidget()->getUploader()->getAllowedFileExtensions();
        if (! empty($types)) {
            return 'fileTypes: "' . mb_strtolower(implode(',', array_unique($types))) . '",';
        }
        return '';
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsPropertyMediaTypes() : string
    {
        $types = $this->getWidget()->getUploader()->getAllowedMimeTypes();
        if (! empty($types)) {
            return 'mediaTypes: "' . mb_strtolower(implode(',', array_unique($types))) . '",';
        }
        return '';
    }
    
    /**
     * 
     * @param string $oEventJs
     * @return string
     */
    protected function buildJsEventHandlerUpload(string $oEventJs) : string
    {
        $widget = $this->getWidget();
        $uploader = $this->getUploader();
        
        $uploadAction = $uploader->getInstantUploadAction();
        $uploadButtonEl = $this->getFacade()->getElement($uploader->getInstantUploadButton());
        
        if ($uploader->isInstantUpload()) {
            // When the upload action succeeds, we need to refresh the list to ensure, that
            // additional columns are filled correctly - e.g. uploading user, etc.
            // While uploading the list shows an "incomplete" item, which needs to be removed
            // after the real item is loaded from the server. Make sure to remove the incomplete
            // item AFTER the refresh-request completes because otherwise it would disapear for
            // a second, which look really weired!
            $onUploadCompleteJs = <<<JS
        
            var oUploadSetModel = oUploadSet.getModel();
            var oRowsBinding = new sap.ui.model.Binding(oUploadSetModel, '/rows', oUploadSetModel.getContext('/rows'));
            oRowsBinding.attachChange(function(oEvent) {
                try {
                    oUploadSet.removeIncompleteItem(oItem);
                    oItem.destroy();
                } catch (e) {
                    // silence errors - the data will be refreshed anyway.
                }
                oRowsBinding.destroy();
            });

            if (oResponseModel.getProperty('/success') !== undefined){
           		{$this->buildJsShowMessageSuccess("oResponseModel.getProperty('/success')")}
			}
            
            {$this->buildJsBusyIconHide()};
            {$uploadButtonEl->buildJsTriggerActionEffects($uploadAction)};
            
JS;
        
            $onFileCheckedSaveIt = <<<JS

                fileReader.onload = function () {
                    var oResponseModel = new sap.ui.model.json.JSONModel({
                        oId: "{$widget->getMetaObject()->getId()}",
                        rows: [
                            {$this->buildJsFileDataRow('file', 'fileReader.result')}
                        ] 
                    });
                    {$this->buildJsDataLoaderOnLoadedHandleWidgetLinks('oResponseModel')}
                    var oUploadParams = {
                        action: "{$uploadAction->getAliasWithNamespace()}",
    					resource: "{$this->getPageId()}",
    					element: "{$uploadAction->getWidgetDefinedIn()->getId()}",
    					object: "{$widget->getMetaObject()->getId()}",
                        data: oResponseModel.getData()
                    };
                    {$this->buildJsBusyIconShow()}
                    {$this->getServerAdapter()->buildJsServerRequest($uploadAction, 'oResponseModel', 'oUploadParams', $onUploadCompleteJs, $onUploadCompleteJs)}
                };
                fileReader.readAsBinaryString(file);

JS;
        } else {
            $onFileCheckedSaveIt = <<<JS
            
                var iFilesOnServer = (oUploadSet.getModel().rows || []).length;
                var iFilesPending = 0;
                var oModelPending = oUploadSet.getModel('uploads_pending');
                if (oModelPending === undefined) {
                    oModelPending = new sap.ui.model.json.JSONModel({rows: []})
                    oUploadSet.setModel(oModelPending, 'uploads_pending');
                }
                iFilesPending = oModelPending.getData().rows.length;
                if (iMaxFiles !== null && iFilesOnServer + iFilesPending >= iMaxFiles) {
                    {$this->buildJsShowError('"' . $this->translate('WIDGET.FILELIST.ERROR_MAX_FILES') . '"')};
                    oUploadSet.removeIncompleteItem(oItem);
                    oItem.destroy();
                    return;
                }
                
                var file = oItem.getFileObject();
                var fileReader = new FileReader();
                fileReader.onload = function () {
                    var oRow = {$this->buildJsFileDataRow('file', 'fileReader.result')};
                    oModelPending.getData().rows.push(oRow);
                };
                fileReader.readAsBinaryString(file);

JS;
        }
        
        $maxFilesJs = $uploader->getMaxFiles() ?? 'null';
        
        return <<<JS

                var oItem = $oEventJs.getParameters().item;
                var oUploadSet = $oEventJs.getSource();
                var iMaxFiles = $maxFilesJs;

                var file = oItem.getFileObject();
                var fileReader = new FileReader( );

                // Check extension
                var sError;
                var aFileTypes = oUploadSet.getFileTypes();
                if (aFileTypes && aFileTypes.length > 0) {
                    var fileExt = (/(?:\.([^.]+))?$/).exec((file.name || '').toLowerCase())[1];
                    if (! aFileTypes.includes(fileExt)) {
                        sError = "{$this->translate('WIDGET.FILELIST.ERROR_EXTENSION_NOT_ALLOWED', ['%ext%' => ' +"\"" + fileExt  + "\"" + '])}";
                    }
                }
                // Check mime type
                var aMediaTypes = oUploadSet.getMediaTypes();
                if (aMediaTypes && aMediaTypes.length > 0) {
                    if (! aMediaTypes.includes((file.type || '').toLowerCase())) {
                        sError = "{$this->translate('WIDGET.FILELIST.ERROR_MIMETYPE_NOT_ALLOWED', ['%type%' => ' +"\"" + file.type  + "\"" + '])}";
                    }
                }
                // Check size
                var iMaxSize = oUploadSet.getMaxFileSize();
                if (iMaxSize && iMaxSize > 0) {
                    if (iMaxSize * 1000000 < file.size) {
                        sError = "{$this->translate('WIDGET.FILELIST.ERROR_FILE_TOO_BIG', ['%mb%' => '" + iMaxSize + "'])}";
                    }
                }
                // Check filename length
                var iMaxLength = oUploadSet.getMaxFileNameLength();
                if (iMaxLength && iMaxLength > 0) {
                    if (iMaxLength < file.name.length) {
                        sError = "{$this->translate('WIDGET.FILELIST.ERROR_FILE_NAME_TOO_LONG', ['%length%' => '" + iMaxLength + "'])}";
                    }
                }
                if (sError !== undefined) {
                    {$this->buildJsShowError('sError')}
                    try {
                        oUploadSet.removeIncompleteItem(oItem);
                        oItem.destroy();
                    } catch (e) {
                        // silence errors - the data will be refreshed anyway.
                        throw e;
                    }
                    return;
                }

                $onFileCheckedSaveIt
JS;
    }
    
    protected function buildJsFileDataRow(string $fileJs, string $fileReaderResultJs) : string
    {
        $widget = $this->getWidget();
        $uploader = $this->getUploader();
        
        $fileColumnsJs = "{$widget->getFilenameColumn()->getDataColumnName()}: {$fileJs}.name,";
        
        if ($uploader->hasFileModificationTimeAttribute()) {
            $fileColumnsJs .= DataColumn::sanitizeColumnName($uploader->getFileModificationTimeAttribute()->getAliasWithRelationPath()) . ": {$fileJs}.lastModified,";
        }
        if ($uploader->hasFileSizeAttribute()) {
            $fileColumnsJs .= DataColumn::sanitizeColumnName($uploader->getFileSizeAttribute()->getAliasWithRelationPath()) . ": {$fileJs}.size,";
        }
        if ($uploader->hasFileMimeTypeAttribute()) {
            $fileColumnsJs .= DataColumn::sanitizeColumnName($uploader->getFileMimeTypeAttribute()->getAliasWithRelationPath()) . ": {$fileJs}.type,";
        }
        
        $fileColumnsJs .= "{$widget->getFileContentColumnName()}: {$this->buildJsFileContentEncoder($uploader->getFileContentAttribute()->getDataType(), $fileReaderResultJs, "{$fileJs}.type")},";
        
        return '{' . $fileColumnsJs . '}';
    }
    
    /**
     * 
     * @param string $oEventJs
     * @return string
     */
    protected function buildJsEventHandlerDelete(string $oEventJs) : string
    {
        $widget = $this->getWidget();
        if (! $widget->isDeleteEnabled()) {
            return '';
        }
        $deleteButton = $widget->getDeleteButton();
        $deleteAction = $deleteButton->getAction();
        
        // Need to destroy the deleted list item manually for some reason - otherwise
        // the next uploaded item will cause a duplicate-event error!
        $onSuccessJs = <<<JS
                
                oUploadSet.removeItem(oItem);
                oItem.destroy();
                {$this->buildJsBusyIconHide()};
                {$this->getFacade()->getElement($deleteButton)->buildJsTriggerActionEffects($deleteAction)};
                if (oResponseModel.getProperty('/success') !== undefined){
               		{$this->buildJsShowMessageSuccess("oResponseModel.getProperty('/success')")}
				}

JS;
        // If an error occurs, it's even stranger: we still MUST manually delete the item,
        // but additionally we need to manually remove the row in the data - otherwise the
        // item will never get shown again - even if the refresh button is pressed.
   		$onErrorJs = <<<JS

                var oModel = oUploadSet.getModel();
                var aRows = oModel.getProperty('/rows');
                var iDelIndex = aRows.indexOf(oItem.getBindingContext().getProperty());
                oItem.destroy(); 
                if (iDelIndex > -1) {
                    aRows.splice(iDelIndex, 1);
                    oModel.setProperty('/rows', aRows);
                } else {
                    oModel.setData({});
                }
                {$this->buildJsBusyIconHide()}
                {$this->buildJsRefresh()}

JS;
        
        return <<<JS

                var oItem = $oEventJs.getParameters().item; 
                var oUploadSet = $oEventJs.getSource();
                var oContext = oItem.getBindingContext();
                var bError = false;
                var oRow;
                var bIncompleteItem = oUploadSet.getInstantUpload() === false && oUploadSet.getIncompleteItems().includes(oItem);

                if (oContext === undefined) {
                    if (bIncompleteItem === false) {
                        bError = true;
                    }
                } else {
                    oRow = oContext.getObject();
                    if (oRow === undefined) bError = true;
                }
                if (bError === true) {
                    {$this->buildJsShowError('"' . $this->translate('WIDGET.FILELIST.ERROR_DELETE') . '"')};
                    return;
                }

                setTimeout(function() {
                    var oConfDialog = sap.ui.getCore().byId(oUploadSet.getId() + '-deleteDialog');
                    var oButtonOK = oConfDialog.getButtons()[0];
                    oButtonOK.setType(sap.m.ButtonType.Emphasized);
                    oButtonOK.attachPress(function(oEventPress){
                        var oResponseModel, oParams, iIncompleteIdx;
                        var oIncompleteModel = oUploadSet.getModel('uploads_pending');

                        if (bIncompleteItem) {
                            iIncompleteIdx = oUploadSet.indexOfIncompleteItem(oItem);
                            if (iIncompleteIdx > -1 && oIncompleteModel.getData().rows && (oIncompleteModel.getData().rows.length > iIncompleteIdx)) {
                                oIncompleteModel.getData().rows.splice(iIncompleteIdx, 1);
                            }
                            oUploadSet.removeIncompleteItem(oItem);
                            oItem.destroy(); 
                            return;
                        }

                        oResponseModel = new sap.ui.model.json.JSONModel();
                        oParams = {
                            action: "{$deleteAction->getAliasWithNamespace()}",
        					resource: "{$this->getPageId()}",
        					element: "{$deleteButton->getId()}",
        					object: "{$widget->getMetaObject()->getId()}",
                            data: {
                                oId: "{$widget->getMetaObject()->getId()}",
                                rows: [
                                    oItem.getBindingContext().getObject()
                                ] 
                            }
                        }
                        {$this->buildJsBusyIconShow()};
                        {$this->getServerAdapter()->buildJsServerRequest($deleteAction, 'oResponseModel', 'oParams', $onSuccessJs, $onErrorJs)}
                    });
                },0);
JS;
    }
    
    /**
     * 
     * @see JqueryDataTableTrait::isEditable()
     */
    protected function isEditable() : bool
    {
        return $this->getWidget()->isEditable();
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsItemPropertyThumbnail() : string
    {
        $widget = $this->getWidget();
        if ($widget->hasThumbnailColumn()) {
            return "thumbnailUrl: '{{$widget->getThumbnailColumn()->getDataColumnName()}}',";
        }
        return '';
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsItemPropertyUrl() : string
    {
        $widget = $this->getWidget();
        if ($widget->hasDownloadUrlColumn()) {
            return "url: '{{$widget->getDownloadUrlColumn()->getDataColumnName()}}',";
        }
        return '';
    }
    
    /**
     * 
     * @param string $oModelJs
     * @return string
     */
    protected function buildJsDataLoaderOnLoaded(string $oModelJs = 'oModel') : string
    {
        $widget = $this->getWidget();
        
        if ($widget->isUploadEnabled() && ($maxFiles = $widget->getUploader()->getMaxFiles()) > 0) {
            $checkMaxFilesJs = <<<JS

            (function(){
                var oUploadSet = sap.ui.getCore().byId('{$this->getId()}');
                if ($oModelJs.getData() && $oModelJs.getData().rows && $oModelJs.getData().rows.length < $maxFiles) {
                    oUploadSet.setUploadEnabled(true);
                } else {
                    oUploadSet.setUploadEnabled(false);
                }
            })();
JS;
        }
        
        return $this->buildJsDataLoaderOnLoadedViaTrait($oModelJs)
        . $checkMaxFilesJs . <<<JS
        
            setTimeout(function(){
                sap.ui.getCore().byId('{$this->getId()}').getList().getItems().forEach(function(oItem){
                    oItem.setType('Active');
                });
            },0);
            
JS;
    }
    
    /**
     *
     * @see UI5DataElementTrait::buildJsGetRowsSelected()
     */
    protected function buildJsGetRowsSelected(string $oControlJs) : string
    {
        return <<<JS
                function() {
                    var oList = $oControlJs.getList();
                    var aRows = [];
                    var oModelData = oList.getModel().getData();
                    if (! oModelData || oModelData.rows === undefined) {
                        return aRows;
                    }
                    oList.getSelectedItems().forEach(function(oItem){
                        aRows.push(oModelData.rows[oList.indexOfItem(oItem)]);
                    });
                    return aRows;
                }()
JS;
    }
    
    protected function buildJsGetRowsAll(string $oControlJs) : string
    {
        $widget = $this->getWidget();
        $uploader = $widget->getUploader();
        if ($uploader->isInstantUpload()) {
            return "{$oControlJs}.getModel().getData().rows";
        }
        return <<<JS
                function() {
                    var aRows = $oControlJs.getModel().getData().rows || [];
                    var aRowsPending = $oControlJs.getModel('uploads_pending').getData().rows || [];
                    return [...aRows, ...aRowsPending];
                }()
JS;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsDataGetter()
     */
    public function buildJsDataGetter(ActionInterface $action = null)
    {
        $widget = $this->getWidget();
        $dataObj = $this->getMetaObjectForDataGetter($action);
        
        // Determine the columns we need in the actions data
        $colNamesList = implode(',', $widget->getActionDataColumnNames());
        
        if ($action !== null && $action->isDefinedInWidget() && $action->getWidgetDefinedIn() instanceof DataButton) {
            $customMode = $action->getWidgetDefinedIn()->getInputRows();
        } else {
            $customMode = null;
        }
        
        switch (true) {
            // Editable tables with modifying actions return all rows either directly or as subsheet
            case $customMode === DataButton::INPUT_ROWS_ALL_AS_SUBSHEET:
            case $widget->isUploadEnabled() && ($action instanceof iModifyData):
            case $widget->isUploadEnabled() && ($action instanceof iCallOtherActions) && $action->containsActionClass(iModifyData::class):
                $aRowsJs = "oTable.getModel().getData().rows || []";
                switch (true) {
                    case $dataObj->is($widget->getMetaObject()) && $customMode !== DataButton::INPUT_ROWS_ALL_AS_SUBSHEET:
                    case $action->getInputMapper($widget->getMetaObject()) !== null && $customMode !== DataButton::INPUT_ROWS_ALL_AS_SUBSHEET:
                        return <<<JS
    function() {
        var bDataPending = {$this->buildJsIsDataPending()};
        var aRows;
        
        if (bDataPending) {
            return {};
        }

        aRows = {$this->buildJsGetRowsAll("sap.ui.getCore().byId('{$this->getId()}')")};
        // Remove any keys, that are not in the columns of the widget
        aRows = aRows.map(({ $colNamesList }) => ({ $colNamesList }));
        return {
            oId: '{$this->getWidget()->getMetaObject()->getId()}',
            rows: aRows
        };
    }()

JS;
                    default:
                        // If the data is intended for another object, make it a nested data sheet
                        // If the action is based on the same object as the widget's parent, use the widget's
                        // logic to find the relation to the parent. Otherwise try to find a relation to the
                        // action's object and throw an error if this fails.
                        if ($widget->hasParent() && $dataObj->is($widget->getParent()->getMetaObject()) && $relPath = $widget->getObjectRelationPathFromParent()) {
                            $relAlias = $relPath->toString();
                        } elseif ($relPath = $dataObj->findRelationPath($widget->getMetaObject())) {
                            $relAlias = $relPath->toString();
                        }
                        
                        if ($relAlias === null || $relAlias === '') {
                            throw new WidgetLogicError($widget, 'Cannot use editable table with object "' . $widget->getMetaObject()->getName() . '" (alias ' . $widget->getMetaObject()->getAliasWithNamespace() . ') as input widget for action "' . $action->getName() . '" with object "' . $dataObj->getName() . '" (alias ' . $dataObj->getAliasWithNamespace() . '): no forward relation could be found from action object to widget object!', '7B7KU9Q');
                        }
                        $aRowsJs = $this->buildJsGetRowsAll('oTable');
                        $data = <<<JS
{
            oId: '{$dataObj->getId()}',
            rows: [
                {
                    '{$relAlias}': {
                        oId: '{$widget->getMetaObject()->getId()}',
                        rows: aRows
                    }
                }
            ]
        }
        
JS;
                }
                break;
            
            // In all other cases the data are the selected rows
            default:
                return $this->buildJsDataGetterViaTrait($action);
                
        }
        
        // FIX move this copy-pasted code to UI5DataElementTrait and remove it from UI5DataTable too.
        
        return <<<JS
    function() {
        var oTable = sap.ui.getCore().byId('{$this->getId()}');
        var oDirtyColumn = sap.ui.getCore().byId('{$this->getDirtyFlagAlias()}');
        var aRows = {$aRowsJs};
        // Remove any keys, that are not in the columns of the widget
        aRows = aRows.map(({ $colNamesList }) => ({ $colNamesList }));
        
        return $data;
    }()
JS;
    }
    
    /**
     * 
     * @return UI5Button|NULL
     */
    protected function getButtonUploadElement() : ?UI5Button
    {
        if ($btn = $this->getWidget()->getButtonUpload()) {
            return $this->getFacade()->getElement($btn);
        }
        return null;
    }
    
    /**
     * Changes the default "Upload" button to a more typical "Browse" button visually.
     * 
     * @return string
     */
    protected function buildJsCustomizeUploaderButton() : string
    {
        $btnText = $this->escapeJsTextValue($this->translate('WIDGET.FILELIST.BROWSE'));
        return <<<JS

            (function(){
                var oUploader = sap.ui.getCore().byId('{$this->getId()}-uploader');
                if (oUploader) {
                    oUploader.setIcon('sap-icon://open-folder');
                    if (sap.ui.getCore().byId('{$this->getId()}').getUploadEnabled() === false) {
                        oUploader.setVisible(false);
                    }
                    // oUploader.setButtonText("{$btnText}");
                }
            })();

JS;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see UI5DataElementTrait::buildJsClickHandlerLeftClick()
     */
    protected function buildJsClickHandlerLeftClick($oControllerJsVar = 'oController') : string
    {        
        // Single click. Currently only supports one click action - the first one in the list of buttons
        if ($leftclick_button = $this->getWidget()->getButtonsBoundToMouseAction(EXF_MOUSE_ACTION_LEFT_CLICK)[0]) {
            return <<<JS
            
            .attachItemPress(function(oEvent) {
                {$this->getFacade()->getElement($leftclick_button)->buildJsClickEventHandlerCall($oControllerJsVar)};
            })
JS;
        }
        
        return '';
    }
    
    /**
     * Returns an inline JS-condition, that evaluates to TRUE if the given oTargetDom JS expression
     * is a DOM element inside a list item or table row.
     *
     * This is important for handling browser events like dblclick. They can only be attached to
     * the entire control via attachBrowserEvent, while we actually only need to react to events
     * on the items, not on headers, footers, etc.
     *
     * @param string $oTargetDomJs
     * @return string
     */
    protected function buildJsClickIsTargetRowCheck(string $oTargetDomJs = 'oTargetDom') : string
    {
        return "{$oTargetDomJs} !== undefined && $({$oTargetDomJs}).parents('li.sapMLIB').length > 0";
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsResetter()
     */
    public function buildJsDataResetter() : string
    {
        return "sap.ui.getCore().byId('{$this->getId()}').setModel(new sap.ui.model.json.JSONModel({rows: []}), 'uploads_pending').removeAllIncompleteItems(); " . $this->buildJsDataResetterViaTrait();
    }
}