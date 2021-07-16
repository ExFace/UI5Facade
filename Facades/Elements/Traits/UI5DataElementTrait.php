<?php
namespace exface\UI5Facade\Facades\Elements\Traits;

use exface\Core\Widgets\Data;
use exface\Core\Widgets\DataTable;
use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;
use exface\UI5Facade\Facades\Elements\UI5AbstractElement;
use exface\UI5Facade\Facades\Elements\UI5DataConfigurator;
use exface\Core\Interfaces\Widgets\iShowImage;
use exface\UI5Facade\Facades\Elements\UI5SearchField;
use exface\Core\Widgets\Input;
use exface\Core\Interfaces\Widgets\iHaveColumns;
use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\Widgets\Dialog;
use exface\Core\DataTypes\WidgetVisibilityDataType;
use exface\UI5Facade\Facades\Elements\UI5DataPaginator;
use exface\Core\Widgets\Button;
use exface\Core\Widgets\MenuButton;
use exface\Core\Widgets\ButtonGroup;
use exface\Core\Exceptions\Facades\FacadeLogicError;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Actions\iReadData;
use exface\UI5Facade\Facades\Elements\ServerAdapters\UI5FacadeServerAdapter;
use exface\UI5Facade\Facades\Elements\ServerAdapters\PreloadServerAdapter;

/**
 * This trait helps wrap thrid-party data widgets (like charts, image galleries, etc.) in 
 * UI5 panels with standard toolbars, a configurator dialog, etc. 
 * 
 * ## How it works:
 * 
 * The method buildJsConstructor() is pre-implemented and takes care of creating the report floorplan,
 * toolbars, the P13n-Dialog, etc. The control to be placed with the report floorplan is provided by the
 * method buildJsConstructorForControl(), which nees to be implemented in every class using the trait.
 * 
 * ### Default data loader
 * 
 * The trait also provides a default data loader implementation via buildJsDataLoader(), which supports
 * different server adapter (i.e. to switch to direct OData communication in exported Fiori apps), lazy 
 * loading and data preload out of the box. 
 * 
 * It is definitely a good idea to use this data loader in all data controls like tables, lists, etc.! 
 * 
 * The data loaded is automatically placed in the main model of the control (use 
 * `sap.ui.getCore().byId({$this->getId()}).getModel()` to access it). However, you can still customize 
 * the data loading logic by implementing 
 * 
 * - `buildJsDataLoaderPrepare()` - called right before the default loading starts.
 * - `buildJsDataLoaderParams()` - called after the default request parameters were computed and allowing
 * to customize them
 * - `buildJsDataLoaderOnLoaded()` - called right after the data was placed in the model, but before
 * the busy-state is dismissed. This is the place, where you would add all sorts of postprocessing or
 * the logic to load the data into a non-UI5 control.
 * 
 * **NOTE:** The main model of the control and it's page wrapper (if the report floorplan is used) is 
 * NOT the view model - it's a separate one. It contains the data set loaded. 
 * 
 * ### Wrapping the control in sap.f.DynamicPage
 * 
 * The trait will automatically wrap the data control in a sap.f.DynamicPage turning it into a "report
 * floorplan" if the method `isWrappedInDynamicPage()` returns `true`. Override this method to implement
 * a custom wrapping condition.
 * 
 * The page will have a collapsible header with filters (instead of the filter tab in the widget 
 * configurator (see below). The behavior of the dynamic page can be customized via
 * 
 * - `getDynamicPageXXX()` methods - override them to change the page's behavior from the element class
 * - `setDynamicPageXXX()` methods - call them from other classes to set available options externally 
 * 
 * ### Toolbars
 * 
 * The trait provides methods to generate the standard toolbar with the widget's `caption`, buttons,
 * quick search field and the configurator-button.
 * 
 * You can also customize the toolbars by overriding
 * - `buildJsToolbar()` - returns the constructor of the top toolbar (sap.m.OverflowToolbar by default)
 * - `buildJsToolbarContent()` - returns the toolbar content (i.e. title, buttons, etc.)
 * - `buildJsQuickSearchConstructor()`
 * 
 * ### Configurator: filters, sorters, advanced search query builder, etc.
 * 
 * There is also a secondary
 * model for the configurator (i.e. filter values, sorting options, etc.) - it's name can be obtained
 * from `getModelNameForConfigurator()`.
 * 
 * ### Editable columns and tracking changes
 * 
 * The trait will automatically track changes for editable columns if the built-in data loader is used 
 * (see above). All changes are strored in a separate model (see. `getModelNameForChanges()`). The trait 
 * provides `buildJsEditableChangesXXX()` methods use in your JS. If the data widget has 
 * `editable_changes_reset_on_refresh` set to `false`, the trait will automatically restore changes
 * after every refresh.
 * 
 * @author Andrej Kabachnik
 *
 * @method UI5Facade getFacade()
 */
trait UI5DataElementTrait {
    
    use UI5HelpButtonTrait;
    
    private $quickSearchElement = null;
    
    private $dynamicPageHeaderCollapsed = null;
    
    private $dynamicPageShowToolbar = false;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::init()
     */
    protected function init()
    {
        parent::init();
        $configuratorElement = $this->getConfiguratorElement();
        $configuratorElement->setModelNameForConfig($this->getModelNameForConfigurator());
        
        if ($this->isWrappedInDynamicPage()) {
            $configuratorElement->setIncludeFilterTab(false);
        }
        
        // Manually create an element for the quick search input, because we need a sap.m.SearchField
        // instead of regular input elements.
        if ($this->hasQuickSearch()) {
            $qsWidget = $this->getWidget()->getQuickSearchWidget();
            // TODO need to add support for autosuggest. How can we use InputCombo or InputComboTable widget here?
            if ($qsWidget instanceof Input) {
                $this->quickSearchElement = new UI5SearchField($qsWidget, $this->getFacade());
                $this->getFacade()->registerElement($this->quickSearchElement);
                $this->quickSearchElement
                    ->setPlaceholder($this->getWidget()->getQuickSearchPlaceholder());
            } else {
                $this->quickSearchElement = $this->getFacade()->getElement($this->getWidget()->getQuickSearchWidget());
            }
        }
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsConstructor()
     */
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {
        $widget = $this->getDataWidget();
        $controller = $this->getController();
        
        $this->registerExternalModules($this->getController());
        
        // Add placeholders for the custom events here. If not done so, at least the select-event will be
        // added too late and won't be there in the generated controller.
        $this->getController()->addOnEventScript($this, 'select', '');
        $this->getController()->addOnEventScript($this, 'refresh', '');
        
        $controller->addMethod('onUpdateFilterSummary', $this, '', $this->buildJsFilterSummaryUpdater());
        $controller->addMethod('onLoadData', $this, 'oControlEvent, bKeepPagingPos', $this->buildJsDataLoader());
        $this->initConfiguratorControl($controller);
        
        if ($this->hasPaginator()) {
            $this->getPaginatorElement()->registerControllerMethods();
        }
        
        // Reload the data every time the view is shown. This is important, because otherwise 
        // old rows may still be visible if a dialog is open, closed and then reopened for another 
        // instance.
        // The data resetter will empty the table as soon as the view is opened, then the refresher
        // is run after all the view loading logic finished - that's what the setTimeout() is for -
        // otherwise the refresh would run before the view finished initializing, before the prefill
        // is started and will probably be empty.
        if ($widget->hasAutoloadData()) {
            $autoloadJs = <<<JS

                (function() {
                    var bIsBack = false;
                    if (oEvent && (oEvent.isBack || oEvent.isBackToPage || oEvent.isBackToTop)) {
                        bIsBack = true;
                    }
                    if (bIsBack === false) {
                        try { 
                            {$this->buildJsDataResetter()} 
                        } catch (e) {} 
                        setTimeout(function(){ 
                            {$this->buildJsRefresh()} 
                        }, 0);
                    }
                })();

JS;
            $controller->addOnShowViewScript($autoloadJs);
        } else {
            $controller->addOnShowViewScript($this->buildJsShowMessageOverlay($widget->getAutoloadDisabledHint()));
        }
        
        // Handle preload
        if ($widget->isPreloadDataEnabled()) {
            $dataCols = [];
            $imgCols = [];
            foreach ($widget->getColumns() as $col) {
                $dataCols[] = $col->getDataColumnName();
                if ($col->getCellWidget() instanceof iShowImage) {
                    $imgCols[] = $col->getDataColumnName();
                }
            }
            $preloadDataCols = json_encode($dataCols);
            $preloadImgCols = json_encode($imgCols);
            $controller->addOnDefineScript("exfPreloader.addPreload('{$this->getMetaObject()->getAliasWithNamespace()}', {$preloadDataCols}, {$preloadImgCols}, '{$widget->getPage()->getUid()}', '{$widget->getId()}', '{$widget->getMetaObject()->getUidAttributeAlias()}', '{$widget->getMetaObject()->getName()}');");
        }
        
        // Generate the constructor for the inner widget
        $js = $this->buildJsConstructorForControl();
        
        $initModels = <<<JS

        .setModel(new sap.ui.model.json.JSONModel())
        .setModel(new sap.ui.model.json.JSONModel(), '{$this->getModelNameForConfigurator()}')
JS;
        
        // If the table has editable columns, we need to track changes made by the user.
        // This is done by listening to changes of the /rows property of the model and
        // comparing it's current state with the initial state. This IF initializes
        // the whole thing. The rest ist handlede by buildJsEditableChangesWatcherXXX()
        // methods.
        if ($this->isEditable()) {
            $initModels .= <<<JS

        .setModel(new sap.ui.model.json.JSONModel(), '{$this->getModelNameForDataLastLoaded()}')
        .setModel(new sap.ui.model.json.JSONModel({changes: {}, watching: false}), '{$this->getModelNameForChanges()}')
JS;
            $controller->addMethod('updateChangesModel', $this, 'oDataChanged', $this->buildJsEditableChangesWatcherUpdateMethod('oDataChanged'));
            $bindChangeWatcherJs = <<<JS

            var oRowsBinding = new sap.ui.model.Binding(sap.ui.getCore().byId('{$this->getId()}').getModel(), '/rows', sap.ui.getCore().byId('{$this->getId()}').getModel().getContext('/rows'));
            oRowsBinding.attachChange(function(oEvent){
                var oBinding = oEvent.getSource();
                var oDataChanged = oBinding.getModel().getData();
                {$controller->buildJsMethodCallFromController('updateChangesModel', $this, 'oDataChanged')};
            });
JS;
            $controller->addOnInitScript($bindChangeWatcherJs);
        }
        
        if ($this->isWrappedInDynamicPage()){
            return $this->buildJsPage($js, $oControllerJs) . $initModels;
        } else {
            return $js . $initModels;
        }
    }
    
    /**
     * Returns the constructor for the inner data control (e.g. table, chart, etc.)
     * 
     * @param string $oControllerJs
     * @return string
     */
    abstract protected function buildJsConstructorForControl($oControllerJs = 'oController') : string;
    
    /**
     * Wraps the given content in a sap.m.Panel with data-specific toolbars (configurator button, etc.).
     * 
     * This is usefull for third-party widget libraries, that need this wrapper to look like UI5 controls.
     * 
     * @param string $contentConstructorsJs
     * @param string $oControllerJs
     * @param string $caption
     * 
     * @return string
     */
    protected function buildJsPanelWrapper(string $contentConstructorsJs, string $oControllerJs = 'oController', string $toolbar = null)  : string
    {
        $toolbar = $toolbar ?? $this->buildJsToolbar($oControllerJs);
        $hDim = $this->getWidget()->getHeight();
        if (! $hDim->isUndefined()) {
            $height = $this->getHeight();
        } else {
            $height = '100%';
        }
        return <<<JS
        new sap.m.Panel({
            height: "$height",
            headerToolbar: [
                {$toolbar}.addStyleClass("sapMTBHeader-CTX")
            ],
            content: [
                {$contentConstructorsJs}
            ]
        })
        
JS;
    }
    
    /**
     * Returns the constructor for the table's main toolbar (OverflowToolbar).
     *
     * The toolbar contains the caption, all the action buttons, the quick search
     * and the button for the personalization dialog as well as the P13nDialog itself.
     *
     * The P13nDialog is appended to the toolbar wrapped in an invisible container in
     * order not to affect the overflow behavior. The dialog must be included in the
     * toolbar to ensure it is destroyed with the toolbar and does not become an
     * orphan (e.g. when the view containing the table is destroyed).
     * 
     * @param string $oControllerJsVar
     * @param string $leftExtras
     * @param string $rightExtras
     *
     * @return string
     */
    protected function buildJsToolbar($oControllerJsVar = 'oController', string $leftExtras = null, string $rightExtras = null)
    {
        $visible = $this->getWidget()->getHideHeader() === true && $this->getWidget()->getHideCaption() ? 'false' : 'true';
        
        // Remove bottom line of the toolbar if it is to be integrated into the dynamic page header
        if ($this->getDynamicPageShowToolbar() === true) {
            $style = 'style: "Clear",';
        }
        
        return <<<JS

			new sap.m.OverflowToolbar({
                design: "Transparent",
                {$style}
                visible: {$visible},
				content: [
					{$this->buildJsToolbarContent($oControllerJsVar, $leftExtras, $rightExtras)}
				]
			})

JS;
    }
    
    /**
     * 
     * @param string $oControllerJsVar
     * @param string $leftExtras
     * @param string $rightExtras
     * @return string
     */
    protected function buildJsToolbarContent($oControllerJsVar = 'oController', string $leftExtras = null, string $rightExtras = null) : string
    {        
        $heading = $this->isWrappedInDynamicPage() ? '' : 'new sap.m.Label({text: ' . json_encode($this->getCaption()) . '}),';
        
        $leftExtras = $leftExtras === null ? '' : rtrim($leftExtras, ", ") . ',';
        $rightExtras = $rightExtras === null ? '' : rtrim($rightExtras, ", ") . ',';
        
        if ($this->getDynamicPageShowToolbar() === false) {
            $quickSearch = $this->buildJsQuickSearchConstructor() . ',';
        } else {
            $quickSearch = '';
        }
        
        return <<<JS

                    {$heading}
                    {$leftExtras}
			        new sap.m.ToolbarSpacer(),
                    {$this->buildJsButtonsConstructors()}
                    {$rightExtras}
                    {$quickSearch}
					{$this->buildJsConfiguratorButtonConstructor()}
                    {$this->buildJsHelpButtonConstructor()}

JS;
    }
    
    /**
     * Returns the text to be shown a table title
     *
     * @return string
     */
    public function getCaption() : string
    {
        $widget = $this->getWidget();
        return $widget->getCaption() ? $widget->getCaption() : $widget->getMetaObject()->getName();
    }

    /**
     * 
     * @return bool
     */
    protected function hasActionButtons() : bool
    {
        return $this->getWidget()->hasButtons();
    }
    
    /**
     * Returns a comma separated list of javascript constructors for all buttons of the table.
     *
     * Must end with a comma unless it is an empty string!
     * 
     * @return string
     */
    protected function buildJsButtonsConstructors()
    {
        if ($this->hasActionButtons() === false) {
            return '';
        }
        
        $widget = $this->getWidget();
        $buttons = '';
        foreach ($widget->getToolbars() as $toolbar) {
            if ($toolbar->getIncludeSearchActions()){
                $search_button_group = $toolbar->getButtonGroupForSearchActions();
            } else {
                $search_button_group = null;
            }
            foreach ($widget->getToolbarMain()->getButtonGroups() as $btn_group) {
                if ($btn_group === $search_button_group){
                    continue;
                }
                $buttons .= ($buttons && $btn_group->getVisibility() > EXF_WIDGET_VISIBILITY_OPTIONAL ? ",\n new sap.m.ToolbarSeparator()" : '');
                foreach ($btn_group->getButtons() as $btn) {
                    $buttons .= $this->getFacade()->getElement($btn)->buildJsConstructor() . ",\n";
                }
            }
        }
        return $buttons;
    }
    
    /**
     * Returns the JS constructor for the configurator button.
     * 
     * Must end with a comma unless it is an empty string!
     * 
     * @param string $oControllerJs
     * @return string
     */
    protected function buildJsConfiguratorButtonConstructor(string $oControllerJs = 'oController', string $buttonType = 'Default') : string
    {
        return <<<JS
        
                    new sap.m.OverflowToolbarButton({
                        type: sap.m.ButtonType.{$buttonType},
                        icon: "sap-icon://action-settings",
                        text: "{$this->translate('WIDGET.DATATABLE.SETTINGS_DIALOG.TITLE')}",
                        tooltip: "{$this->translate('WIDGET.DATATABLE.SETTINGS_DIALOG.TITLE')}",
                        layoutData: new sap.m.OverflowToolbarLayoutData({priority: "High"}),
                        press: function() {
                			{$this->getController()->buildJsDependentControlSelector('oConfigurator', $this, $oControllerJs)}.open();
                		}
                    }),
                    
JS;
    }
    
    /**
     * Initializes the configurator control (sap.m.P13nDialog or similar) and makes it available in the given controller.
     * 
     * Use buildJsConfiguratorOpen() to show the configurator dialog. 
     * 
     * @param UI5ControllerInterface $controller
     * 
     * @return UI5AbstractElement
     */
    protected function initConfiguratorControl(UI5ControllerInterface $controller) : UI5AbstractElement
    {
        $controller->addDependentControl('oConfigurator', $this, $this->getFacade()->getElement($this->getWidget()->getConfiguratorWidget()));
        return $this;
    }
    
    /**
     *
     * @return bool
     */
    protected function hasQuickSearch() : bool
    {
        return $this->getWidget() instanceof DataTable && $this->getWidget()->getQuickSearchEnabled() !== false;
    }
    
    /**
     * Returns a JS snippet, that performs the given $onFailJs if required filters are missing.
     * 
     * @param string $onFailJs
     * @return string
     */
    protected function buildJsCheckRequiredFilters(string $onFailJs) : string
    {
        $configurator_element = $this->getFacade()->getElement($this->getWidget()->getConfiguratorWidget());
        return <<<JS

                try {
                    if (! {$configurator_element->buildJsValidator()}) {
                        {$onFailJs};
                    }
                } catch (e) {
                    console.warn('Could not check filter validity - ', e);
                }      
                
JS;
    }
    
    /**
     * Empties the table by replacing it's model by an empty object.
     *
     * @return string
     */
    protected function buildJsDataResetter() : string
    {
        return "sap.ui.getCore().byId('{$this->getId()}').getModel().setData({})";
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsRefresh()
     */
    public function buildJsRefresh(bool $keepPagingPosition = false)
    {
        if ($keepPagingPosition === false) {
            return $this->getController()->buildJsMethodCallFromController('onLoadData', $this, '');
        } else {
            return $this->getController()->buildJsMethodCallFromController('onLoadData', $this, 'undefined, true');
        }
    }
    
    /**
     * Returns the body of a controller method to fill the widget with data: onLoadDataTableId(oControlEvent).
     * 
     * **IMPORTANT:** The controller metho MUST return a promise that resolves to the JSONModel
     * containing the loaded data. Thus, if you add cancelling logic, don't forget to return
     * a promise - see the `if (oViewModel.getProperty(sPendingPropery) === true)` branch below
     * for an example. 
     *
     * @param string $oControlEventJsVar
     * @param string $keepPagePosJsVar
     * 
     * @return string
     */
    protected function buildJsDataLoader($oControlEventJsVar = 'oControlEvent', $keepPagePosJsVar = 'bKeepPagingPos')
    {
        $widget = $this->getWidget();
        if ($widget instanceof iShowData && $widget->isEditable()) {
            $disableEditableChangesWatcher = <<<JS
                
                // Disable editable-column-change-watcher because reloading from server
                // changes the data but does not mean a change by the editor
                {$this->buildJsEditableChangesWatcherDisable()}
JS;
        } else {
            $disableEditableChangesWatcher = '';
        }
        
        // Before we load anything, we need to make sure, the view data is loaded.
        // The view model has a special property to indicate if view (prefill) data
        // is being loaded. So we check that property and, if it shows a prefill
        // running right now, we listen for changes on the property. Once it is not
        // set to true anymore, we can do the refresh. The setTimeout() wrapper is
        // needed to make sure all filters bound to the prefill model got their values!
        // NOTE: the setTimeout() in the prefill-change-handler is needed because it seems
        // to take time for the model bindings to get their values. Without a setTimeout()
        // or with setTimeout(..., 0) the filters till have their values from before the
        // prefill!
        $js = <<<JS
        
                var oViewModel = sap.ui.getCore().byId("{$this->getId()}").getModel("view");
                var sPendingPropery = "/_prefill/pending";
                if (oViewModel.getProperty(sPendingPropery) === true) {
                    {$this->buildJsBusyIconShow()}
                    var oPrefillBinding = new sap.ui.model.Binding(oViewModel, sPendingPropery, oViewModel.getContext(sPendingPropery));
                    var fnPrefillHandler = function(oEvent) {
                        oPrefillBinding.detachChange(fnPrefillHandler);
                        {$this->buildJsBusyIconHide()};
                        setTimeout(function() {
                            {$this->buildJsRefresh()};
                        }, 10);
                    };
                    oPrefillBinding.attachChange(fnPrefillHandler);
                    return Promise.resolve(sap.ui.getCore().byId('{$this->getId()}').getModel());
                }
                
                {$disableEditableChangesWatcher}
                {$this->buildJsDataLoaderPrepare()}

JS;
                
                if (! $this->isLazyLoading()) {
                    $js .= $this->buildJsDataLoaderFromLocal($oControlEventJsVar, $keepPagePosJsVar);
                } else {
                    $js .= $this->buildJsDataLoaderFromServer($oControlEventJsVar, $keepPagePosJsVar);
                }
                
                return $js;
    }
    
    /**
     *
     * @return string
     */
    protected function buildJsDataLoaderFromLocal($oControlEventJsVar = 'oControlEvent', $keepPagePosJsVar = 'bKeepPagingPos')
    {
        $widget = $this->getWidget();
        $data = $widget->prepareDataSheetToRead($widget->getValuesDataSheet());
        if (! $data->isFresh()) {
            $data->dataRead();
        }
        
        // Since non-lazy loading means all the data is embedded in the view, we need to make
        // sure the the view is not cached: so we destroy the view after it was hidden!
        $this->getController()->addOnHideViewScript($this->getController()->getView()->buildJsViewGetter($this). '.destroy();', false);
        
        // FIXME make filtering, sorting, pagination, etc. work in non-lazy mode too!
        
        return <<<JS
        
                try {
        			var data = {$this->getFacade()->encodeData($this->getFacade()->buildResponseData($data, $widget))};
        		} catch (err){
                    console.error('Cannot load data into widget {$this->getId()}!');
                    return;
        		}
                sap.ui.getCore().byId("{$this->getId()}").getModel().setData(data);
                
JS;
    }
    
    /**
     * Returns the JS code to load data from the server and return a promise resolving to the loaded JSONModel.
     * 
     * This code is used in the controller method `onLoadDataTableId(oControlEvent)` generated by
     * `buildJsDataLoader` if the table uses lazy loading.
     * 
     * The loader remembers the `data` request parameter of the last refresh attempted and compares
     * it to every subsequent refresh until a response from the server is received. This helps avoid
     * concurrent server requests. If a refresh with other data is attempted, it is performed once
     * the current server request comes back. Refreshes with same data are simply ignored. This logic
     * is especially important when multiple filters with apply_on_change are used. Their auto-refreshes
     * may get triggered totally independently, so this logic here makes sure the last one wins and
     * there are as few server requests as possible: not more than two in case the filters change 
     * while data is being loaded.
     * 
     * Note: the request-skipping logic is currently implemented for server-requests only, while the
     * general wait-for-prefill logic is executed by `buildJsDataLoader()`.
     * 
     * @see buildJsDataLoader()
     * 
     * @return string
     */
    protected function buildJsDataLoaderFromServer($oControlEventJsVar = 'oControlEvent', $keepPagePosJsVar = 'bKeepPagingPos')
    {
        $widget = $this->getDataWidget();
        
        $onLoadedJs = <<<JS
            
            if (oTable._exfPendingData !== undefined && oTable._exfPendingData !== sCurrentRequestData) {
                delete oTable._exfPendingData;
                {$this->buildJsRefresh()}
                return Promise.resolve(oModel);
            } else {
                delete oTable._exfPendingData;
            }

            {$this->buildJsBusyIconHide()};
            {$this->buildJsDataLoaderOnLoaded('oModel')}

JS;
            
        $onErrorJs = 'delete oTable._exfPendingData; ' . $this->buildJsBusyIconHide();
        $onOfflineJs = $onErrorJs . $this->buildJsOfflineHint('oTable');
        
        if ($this->hasQuickSearch()) {
            $quickSearchParam = "params.q = {$this->getQuickSearchElement()->buildJsValueGetter()};";
        }
        
        return <<<JS
        
        		var oTable = sap.ui.getCore().byId("{$this->getId()}");
                var params = {
					action: "{$widget->getLazyLoadingActionAlias()}",
					resource: "{$this->getPageId()}",
					element: "{$widget->getId()}",
					object: "{$widget->getMetaObject()->getId()}"
				};
        		var oModel = oTable.getModel();
                var oData = oModel.getData();
                var oController = this;
                var aSortItems = [];
                var sCurrentRequestData = '';
                
                {$this->buildJsCheckRequiredFilters($this->buildJsShowMessageOverlay($widget->getAutoloadDisabledHint()) . "; return;")}
                
                {$this->buildJsBusyIconShow()}
                
        		// Add quick search
                {$quickSearchParam}
                
                // Add configurator params
                {$this->getP13nElement()->buildJsDataLoaderParams('params')}

                // Add custom params for  
                {$this->buildJsDataLoaderParams($oControlEventJsVar, 'params', $keepPagePosJsVar)}

                sCurrentRequestData = JSON.stringify(params.data);

                if (oTable._exfPendingData !== undefined) {
                    // Skip server request if still waiting for a response
                    if (oTable._exfPendingData !== sCurrentRequestData) {
                        // Update pending data if current request has other data to make sure
                        // an auto-refresh is done afterwards to show latest information
                        oTable._exfPendingData = sCurrentRequestData;
                    }
                    return Promise.resolve(oModel);
                } else {
                    // Remember current data to make it possible to skip concurrent requests
                    // via the above if()
                    oTable._exfPendingData = sCurrentRequestData;
                }
                
                {$this->getServerAdapter()->buildJsServerRequest(
                    $widget->getLazyLoadingAction(),
                    'oModel',
                    'params',
                    $onLoadedJs,
                    $onErrorJs,
                    $onOfflineJs
                )}
                
JS;
    }
              
    /**
     * Returns a JS snippet to show a message instead of data: e.g. "Please set filters first"
     * or the autoload_disabled_hint of the data widget.
     * 
     * NOTE: by default, this methos simply empties the control using the data resetter. If you want
     * a message to be shown, override this mehtod!
     * 
     * @return string
     */
    protected function buildJsShowMessageOverlay(string $message) : string
    {
        return $this->buildJsDataResetter();
    }
       
    /**
     * Returns control-specific parameters for the data loader AJAX request.
     * 
     * @param string $oControlEventJsVar
     * @param string $oParamsJs
     * @return string
     */
    protected function buildJsDataLoaderParams(string $oControlEventJsVar = 'oControlEvent', string $oParamsJs = 'params', $keepPagePosJsVar = 'bKeepPagingPos') : string
    {
        $js = '';
        
        if ($this->hasPaginator()) {
            $js = $this->buildJsDataLoaderParamsPaging($oParamsJs, $keepPagePosJsVar);
        }
        
        return $js;
    }
    
    /**
     * Adds pagination parameters to the JS object $oParamsJs holding the AJAX request parameters.
     * 
     * @param string $oParamsJs
     * @param string $keepPagePosJsVar
     * @return string
     */
    protected function buildJsDataLoaderParamsPaging(string $oParamsJs = 'params', $keepPagePosJsVar = 'bKeepPagingPos') : string
    {
        $paginationSwitch = $this->getDataWidget()->isPaged() ? 'true' : 'false';
        
        return <<<JS
        
        		// Add pagination
                if ({$paginationSwitch}) {
                    var paginator = {$this->getPaginatorElement()->buildJsGetPaginator('oController')};
                    if (typeof {$keepPagePosJsVar} === 'undefined' || ! {$keepPagePosJsVar}) {
                        paginator.resetAll();
                    }
                    {$oParamsJs}.start = paginator.start;
                    {$oParamsJs}.length = paginator.pageSize;
                }
                
JS;
    }
    
    /**
     * 
     * @param string $oModelJs
     * @return string
     */
    protected function buildJsDataLoaderOnLoaded(string $oModelJs = 'oModel') : string
    {
        $widget = $this->getWidget();
        if ($this->isWrappedInDynamicPage()) {
            if ($this->getDynamicPageHeaderCollapsed() === null) {
                $dynamicPageFixes = <<<JS
                
                            if (sap.ui.Device.system.phone) {
                                sap.ui.getCore().byId('{$this->getIdOfDynamicPage()}').setHeaderExpanded(false);
                            }
JS;
            } else {
               $dynamicPageFixes = $this->getDynamicPageHeaderCollapsed() === true ? "sap.ui.getCore().byId('{$this->getIdOfDynamicPage()}').setHeaderExpanded(false);" : '';
            }
            $dynamicPageFixes .= <<<JS

                            // Redraw the table to make it fit the page height agian. Otherwise it would be
                            // of default height after dialogs close, etc.
                            sap.ui.getCore().byId('{$this->getId()}').invalidate();

JS;
        }
        
        if ($widget instanceof iShowData && $widget->isEditable()) {
            // Enable watching changes for editable columns from now on
            $editableTableWatchChanges = <<<JS
            
            oTable.getModel("{$this->getModelNameForDataLastLoaded()}").setData(JSON.parse(JSON.stringify($oModelJs.getData())));
            {$this->buildJsEditableChangesApplyToModel($oModelJs)} 
            {$this->buildJsEditableChangesWatcherEnable()}

JS;
        }
        
        return <<<JS

            oTable.getModel("{$this->getModelNameForConfigurator()}").setProperty('/filterDescription', {$this->getController()->buildJsMethodCallFromController('onUpdateFilterSummary', $this, '', 'oController')});
            {$dynamicPageFixes}
            {$this->buildJsDataLoaderOnLoadedHandleWidgetLinks($oModelJs)}
            {$editableTableWatchChanges}          
            {$this->buildJsMarkRowsAsDirty($oModelJs)}
            {$this->getController()->buildJsEventHandler($this, 'refresh', false)}
		
JS;
    }
    
    /**
     * Returns the JS code to add values from widget links to the given UI5 model.
     * 
     * While formulas and other expressions are evaluated in the backend, current values
     * of linked widgets are only known in the front-end, so they need to be added
     * here via JS.
     * 
     * @param string $oModelJs
     * @return string
     */
    protected function buildJsDataLoaderOnLoadedHandleWidgetLinks(string $oModelJs) : string
    {
        $addLocalValuesJs = '';
        $linkedEls = [];
        foreach ($this->getDataWidget()->getColumns() as $col) {
            $cellWidget = $col->getCellWidget();
            if ($cellWidget->hasValue() === false) {
                continue;
            }
            $valueExpr = $cellWidget->getValueExpression();
            if ($valueExpr->isReference() === true) {
                $linkedEl = $this->getFacade()->getElement($valueExpr->getWidgetLink($cellWidget)->getTargetWidget());
                $linkedEls[] = $linkedEl;
                $addLocalValuesJs .= <<<JS
                
                                oRow["{$col->getDataColumnName()}"] = {$linkedEl->buildJsValueGetter()};
JS;
            }
        }
        if ($addLocalValuesJs) {
            $addLocalValuesJs = <<<JS
            
                            // Add widget link values
                            ($oModelJs.getData().rows || []).forEach(function(oRow){
                                {$addLocalValuesJs}
                            });
                            $oModelJs.updateBindings();
JS;
            $addLocalValuesOnChange = <<<JS
                            
                            var $oModelJs = sap.ui.getCore().byId("{$this->getId()}").getModel();
                            {$addLocalValuesJs}
JS;
            foreach ($linkedEls as $linkedEl) {
                $linkedEl->addOnChangeScript($addLocalValuesOnChange);
            }
        }
        return $addLocalValuesJs;
    }
            
    protected function getModelNameForConfigurator() : string
    {
        return 'configurator';
    }
    
    /**
     *
     * @return UI5DataConfigurator
     */
    protected function getP13nElement()
    {
        return $this->getFacade()->getElement($this->getWidget()->getConfiguratorWidget());
    }
    
    protected function getIdOfDynamicPage() : string
    {
        return $this->getId() . "_DynamicPageWrapper";
    }
    
    protected function buildJsDataLoaderPrepare() : string
    {
        return '';
    }
    
    protected function buildJsOfflineHint(string $oTableJs = 'oTable') : string
    {
        return '';
    }
    
    /**
     * Returns TRUE if the table will be wrapped in a sap.f.DynamicPage to create a Fiori ListReport
     *
     * @return boolean
     */
    public function isWrappedInDynamicPage()
    {
        $widget = $this->getWidget();
        if ($widget->getHideHeader() === null) {
            return $widget->hasParent() === false || ($widget->getParent() instanceof Dialog && $widget->getParent()->isFilledBySingleWidget());
        } else {
            return $widget->getHideHeader() === false;
        }
    }
    
    /**
     * Returns TRUE if this table uses a remote data source and FALSE otherwise.
     *
     * @return boolean
     */
    protected function isLazyLoading()
    {
        return $this->getDataWidget()->getLazyLoading(true);
    }
    
    protected abstract function isEditable();
    
    /**
     * Wraps the given content in a constructor for the sap.f.DynamicPage used to create the Fiori list report floorplan.
     *
     * @param string $content
     * @return string
     */
    protected function buildJsPage(string $content, string $oControllerJs) : string
    {
        // If the data widget is the root of the page, prefill data from the URL can be used
        // to prefill filters. The default prefill-logic of the view will not work, however,
        // because it will load data into the view's default model and this will not have any
        // effect on the table because it's default model is a different one. Thus, we need
        // to do the prefill manually at this point. 
        // If the widget is not the root, the URL prefill will be applied to the view normally
        // and it will work fine. 
        if ($this->getWidget()->hasParent() === false) {
            $this->getController()->addOnInitScript($this->buildJsPrefillFiltersFromRouteParams());
        }
        
        $top_buttons = '';
        
        // Add the search-button
        foreach ($this->getWidget()->getToolbarMain()->getButtonGroupForSearchActions()->getButtons() as $btn) {
            if ($btn->getAction()->isExactly('exface.Core.RefreshWidget')){
                $btn->setShowIcon(false);
                $btn->setHint($btn->getCaption());
                $btn->setCaption($this->translate('WIDGET.DATATABLE.GO_BUTTON_TEXT'));
                $btn->setVisibility(WidgetVisibilityDataType::PROMOTED);
            }
            $top_buttons .= $this->getFacade()->getElement($btn)->buildJsConstructor() . ',';
        }
        
        // Add a title. If the dynamic page is actually the view, the title should be the name
        // of the page, the view represents - otherwise it's the caption of the table widget.
        // Since the back-button is also only shown when the dynamic page is the view itself,
        // we can use the corresponding getter here.
        $caption = $this->getDynamicPageShowBackButton() ? $this->getWidget()->getPage()->getName() : $this->getCaption();
        $title = <<<JS
        
                            new sap.m.Title({
                                text: "{$this->escapeJsTextValue($caption)}"
                            }),
                            
JS;
        
        // Place the back-button next to the title if we need one
        $backButton = <<<JS
                                    new sap.m.Button({
                                        icon: "sap-icon://nav-back",
                                        press: [oController.onNavBack, oController],
                                        type: sap.m.ButtonType.Transparent
                                    }).addStyleClass('exf-page-heading-btn'),
JS;
        if ($this->getWidget()->getHideCaption() === true) {
            $title = '';
        }
        if ($this->getDynamicPageShowBackButton() === false) {
            $backButton = '';
        }
        $titleExpanded = $this->buildJsTitleHeading($title, $backButton);
        $textSnapped = <<<JS
                                    new sap.m.Text({
                                        text: "{{$this->getModelNameForConfigurator()}>/filterDescription}"
                                    }),
JS;
        $titleSnapped = $this->buildJsTitleHeading($textSnapped, $backButton);
        
        
        
        // Build the top toolbar with title, actions, etc.
        $titleAreaShrinkRatio = '';
        if ($this->getDynamicPageShowToolbar() === true) {
            if ($qsEl = $this->getQuickSearchElement()) {
                $qsEl->setWidthCollapsed('200px');
            }
            $toolbar = $this->buildJsToolbar($oControllerJs, $this->buildJsQuickSearchConstructor($oControllerJs), $top_buttons);

            // due to the SearchField being right aligned, set the shrinkfactor so that the right side shrink the least
            $titleAreaShrinkRatio = 'areaShrinkRatio: "1.6:1.6:1"';
        } else {
            $toolbar = $top_buttons;
        }
        
        // Make sure, the filters in the header of the page use the same model as the filters
        // in the configurator's P13nDialog would do. Otherwise the prefill of tables with
        // page-wrappers would not work properly, as the filter's model would be the one with
        // table rows and not the default model of the view.
        $useConfiguratorModelForHeaderFiltersJs = <<<JS

        (function(){
            var oPage = sap.ui.getCore().byId("{$this->getIdOfDynamicPage()}");
            var oP13nDialog = sap.ui.getCore().byId("{$this->getConfiguratorElement()->getid()}");
            oPage.getHeader().setModel(oP13nDialog.getModel());
        })();
JS;
        $this->getController()->addOnInitScript($useConfiguratorModelForHeaderFiltersJs);
        
        // Now build the page's code for the view
        return <<<JS
        
        new sap.f.DynamicPage("{$this->getIdOfDynamicPage()}", {
            fitContent: true,
            preserveHeaderStateOnScroll: true,
            headerExpanded: (sap.ui.Device.system.phone === false),
            title: new sap.f.DynamicPageTitle({
				expandedHeading: [
                    {$titleExpanded}
				],
                snappedHeading: [
                    {$titleSnapped}
				],
				actions: [
				    {$toolbar}
				],
                {$titleAreaShrinkRatio}
            }),
            
			header: new sap.f.DynamicPageHeader({
                pinnable: true,
				content: [
                    new sap.ui.layout.Grid({
                        defaultSpan: "XL2 L3 M4 S12",
                        containerQuery: true,
                        content: [
							{$this->getConfiguratorElement()->buildJsFilters()}
						]
                    })
				]
			}),
			
            content: [
                {$content}
            ]
        })
JS;
    }
    
    protected function buildJsTitleHeading(string $title, string $backButton) : string
    {
        return <<<JS
                            new sap.m.HBox({
                                height: "23px",
                                renderType: 'Bare',
                                alignItems: 'Center',
                                items: [
                                    {$backButton}
                                    {$title}
                                ]
                            })
JS;
    }
    
    /**
     * Returns the JS code to give filters default values if there is prefill data
     * @return string
     */
    protected function buildJsPrefillFiltersFromRouteParams() : string
    {
        $filters = $this->getWidget()->getConfiguratorWidget()->getFilters();
        foreach ($filters as $filter) {
            $alias = $filter->getAttributeAlias();
            $setFilterValues .= <<<JS
                
                                var alias = '{$alias}';
                                if (cond.expression === alias) {
                                    var condVal = cond.value;
                                    {$this->getFacade()->getElement($filter)->buildJsValueSetter('condVal')}
                                }
                                
JS;
        }
            
        return <<<JS

                setTimeout(function(){
                    var oViewModel = sap.ui.getCore().byId("{$this->getId()}").getModel("view");
                    var fnPrefillFilters = function() {
                        var oRouteData = oViewModel.getProperty('/_route');
                        if (oRouteData === undefined) return;
                        if (oRouteData.params === undefined) return;
                        
                        var oPrefillData = oRouteData.params.prefill;
                        if (oPrefillData === undefined) return;

                        if (oPrefillData.oId !== undefined && oPrefillData.filters !== undefined) {
                            var oId = oPrefillData.oId;
                            var routeFilters = oPrefillData.filters;
                            if (oId === '{$this->getWidget()->getMetaObject()->getId()}') {
                                if (Array.isArray(routeFilters.conditions)) {
                                    routeFilters.conditions.forEach(function (cond) {
                                        {$setFilterValues}
                                    })
                                }
                            }
                        }
                    };
                    var sPendingPropery = "/_prefill/pending";
                    if (oViewModel.getProperty(sPendingPropery) === true) {
                        var oPrefillBinding = new sap.ui.model.Binding(oViewModel, sPendingPropery, oViewModel.getContext(sPendingPropery));
                        var fnPrefillHandler = function(oEvent) {
                            oPrefillBinding.detachChange(fnPrefillHandler);
                            setTimeout(function() {
                                fnPrefillFilters();
                            }, 0);
                        };
                        oPrefillBinding.attachChange(fnPrefillHandler);
                        return;
                    } else {
                        fnPrefillFilters();
                    }
                }, 0);
                
JS;
    }
    
    /**
     *
     * @return string
     */
    protected function buildJsFilterSummaryUpdater()
    {
        $filter_checks = '';
        foreach ($this->getDataWidget()->getFilters() as $fltr) {
            $elem = $this->getFacade()->getElement($fltr);
            $filterName = $this->escapeJsTextValue($elem->getCaption());
            $filter_checks .= "if({$elem->buildJsValueGetter()}) {filtersCount++; filtersList += (filtersList == '' ? '' : ', ') + \"{$filterName}\";} \n";
        }
        return <<<JS
                var filtersCount = 0;
                var filtersList = '';
                {$filter_checks}
                if (filtersCount > 0) {
                    return '{$this->translate('WIDGET.DATATABLE.FILTERED_BY')} (' + filtersCount + '): ' + filtersList;
                } else {
                    return '{$this->translate('WIDGET.DATATABLE.FILTERED_BY')}: {$this->translate('WIDGET.DATATABLE.FILTERED_BY_NONE')}';
                }
JS;
    }
    
    /**
     *
     * @return string
     */
    protected function buildJsFilterSummaryFunctionName() 
    {
        return "{$this->buildJsFunctionPrefix()}CountFilters";
    }
    
    /**
     * Returns the constructor for the sap.m.SearchField for toolbar quick search.
     *
     * Must end with a comma unless it is an empty string!
     *
     * @return string
     */
    protected function buildJsQuickSearchConstructor($oControllerJs = 'oController') : string
    {
        if ($this->hasQuickSearch() === false) {
            return <<<JS

                    new sap.m.OverflowToolbarButton({
                        icon: "sap-icon://refresh",
                        press: function(oEvent){
                            {$this->buildJsRefresh()}
                        }
                    })

JS;
        }
        
        $qsElement = $this->getQuickSearchElement();
        if ($qsElement instanceof UI5SearchField) {
            $qsElement->setSearchCallbackJs("function(oEvent){ {$this->buildJsRefresh()} }");
        }
        return $qsElement->buildJsConstructorForMainControl($oControllerJs);
    }
        
    /**
     * Returns the data widget.
     * 
     * Override this method to use the trait to render iUseData widgets (like Chart),
     * because their getWidget() method would not return Data, but the visualizer
     * widget.
     * 
     * @return Data
     */
    protected function getDataWidget() : Data
    {
        return $this->getWidget();
    }
    
    /**
     * 
     * @return UI5AbstractElement|NULL
     */
    protected function getQuickSearchElement() : ?UI5AbstractElement
    {
        if ($this->hasQuickSearch()) {
            // The quick search element is instantiated in the init() method above, because we need
            // to make sure, it is created before anyone can attempt to get it via $facade->getElement()
            return $this->quickSearchElement;
        }
        return null;
    }
    
    /**
     * Returns an inline JS snippet to compare two data rows represented by JS objects.
     *
     * If this widget has a UID column, only the values of this column will be compared,
     * unless $trustUid is FALSE. This is handy if you need to compare if the rows represent
     * the same object (e.g. when selecting based on a row).
     *
     * If this widget has no UID column or $trustUid is FALSE, the JSON-representations of
     * the rows will be compared.
     *
     * @param string $leftRowJs
     * @param string $rightRowJs
     * @param bool $trustUid
     * @return string
     */
    protected function buildJsRowCompare(string $leftRowJs, string $rightRowJs, bool $trustUid = true) : string
    {
        $widget = $this->getWidget();
        if ($trustUid === true && $widget instanceof iHaveColumns && $widget->hasUidColumn()) {
            $uid = $widget->getUidColumn()->getDataColumnName();
            return "{$leftRowJs}['{$uid}'] == {$rightRowJs}['{$uid}']";
        } else {
            return "(JSON.stringify({$leftRowJs}) == JSON.stringify({$rightRowJs}))";
        }
    }
    
    protected function getConfiguratorElement() : UI5DataConfigurator
    {
        return $this->getFacade()->getElement($this->getWidget()->getConfiguratorWidget());
    }
    
    /**
     * Returns whether the dynamic page header should be collapsed or not, or if this has not been defined for this object.
     * 
     * @return bool|NULL
     */
    protected function getDynamicPageHeaderCollapsed() : ?bool
    {
        return $this->dynamicPageHeaderCollapsed;
    }
    
    /**
     * Set whether the dynamic page header of this widget should be collapsed or not.
     * 
     * @param bool $value
     * @return self
     */
    public function setDynamicPageHeaderCollapsed(bool $value) : self
    {
        $this->dynamicPageHeaderCollapsed = $value;
        return $this;
    }
    
    /**
     * Getter for whether the back button of this page should be instanciated or not, or if this has not been defined.
     * 
     * @return bool
     */
    protected function getDynamicPageShowBackButton() : bool
    {
        // No back-button if we are on the root view (there is nowhere to go back)
        if ($this->getView()->isWebAppRoot()) {
            return false;
        }
        
        // Show back-button if the table is the view root (and the view is not app root - see above)
        $viewRootEl = $this->getView()->getRootElement();
        if ($viewRootEl === $this) {
            return true;
        }
        
        // In all other cases see if any parent already has a back-button
        $parent = $this->getWidget()->getParent();
        while ($parent) {
            $parentEl = $this->getFacade()->getElement($parent);
            // If back-button found - don't show another one
            if ($parentEl->hasButtonBack() === true) {
                return false;
            }
            // If we reached the view root, stop looking (otherwise we will get a controller
            // not initialized exception!)
            if ($parentEl === $viewRootEl) {
                break;
            }
            // Next parent
            $parent = $parent->getParent();
        }
        
        // If no parent has a back-button, place one here
        return true;
    }
    
    /**
     * Setter for whether the toolbar for this page should be displayed or not.
     * 
     * @param bool $trueOrFalse
     * @return self
     */
    public function setDynamicPageShowToolbar(bool $trueOrFalse) : self
    {
        $this->dynamicPageShowToolbar = $trueOrFalse;
        return $this;
    }
    
    /**
     * Getter for whether the toolbar for this page should be displayed or not.
     * 
     * @return bool
     */
    protected function getDynamicPageShowToolbar() : bool
    {
        return $this->dynamicPageShowToolbar;
    }
    
    protected function getModelNameForChanges() : string
    {
        return 'data_changes';
    }
    
    protected function getModelNameForDataLastLoaded() : string
    {
        return 'data_last_loaded';
    }
    
    protected function getEditableColumnNamesJson() : string
    {
        $editabelColNames = [];
        foreach ($this->getWidget()->getColumns() as $col) {
            if ($col->isEditable()) {
                $editabelColNames[] = $col->getDataColumnName();
            }
        }
        return json_encode($editabelColNames);
    }
    
    protected function buildJsEditableChangesWatcherUpdateMethod(string $changedDataJs) : string
    {
        if ($this->getWidget()->hasUidColumn() === false) {
            return '';
        }
        
        $uidColName = $this->getWidget()->getUidColumn()->getDataColumnName();
        
        return <<<JS

            var oTable = sap.ui.getCore().byId('{$this->getId()}');
            var oChangesModel = oTable.getModel('{$this->getModelNameForChanges()}');
            
            if (oChangesModel.getProperty('/watching') !== true) return;
            
            var oDataLastLoaded = oTable.getModel('{$this->getModelNameForDataLastLoaded()}').getData();
            var oDataChanged = $changedDataJs;
            var oChanges = oChangesModel.getProperty('/changes');
            var aEditableColNames = {$this->getEditableColumnNamesJson()};        

            if (oDataChanged.rows === undefined || oDataChanged.rows.lenght === 0) return;

            oDataChanged.rows.forEach(function(oRowChanged) {
                var oRowLast;
                var sUid = oRowChanged['$uidColName'];
                for (var i in oDataLastLoaded.rows) {
                    if (oDataLastLoaded.rows[i]['$uidColName'] === sUid) {
                        oRowLast = oDataLastLoaded.rows[i];
                        break;
                    }
                }
                if (oRowLast) {
                    aEditableColNames.forEach(function(sFld){
                        if (oRowChanged[sFld] != oRowLast[sFld]) {
                            if (oChanges[sUid] === undefined) {
                                oChanges[sUid] = {};
                            }
                            oChanges[sUid][sFld] = oRowChanged[sFld];
                        } else {
                            if (oChanges[sUid] && oChanges[sUid][sFld]) {
                                delete oChanges[sUid][sFld];
                                if (Object.keys(oChanges[sUid]).length === 0) {
                                    delete oChanges[sUid];
                                }
                            }
                        }
                    });
                }
            });

            oChangesModel.setProperty('/changes', oChanges);

JS;
    }
    
    protected function buildJsEditableChangesWatcherDisable(string $oTableJs = null) : string
    {
        return $this->buildJsEditableChangesModelGetter($oTableJs) . ".setProperty('/watching', false);";
    }
    
    protected function buildJsEditableChangesWatcherEnable(string $oTableJs = null) : string
    {
        return $this->buildJsEditableChangesModelGetter($oTableJs) . ".setProperty('/watching', true);";
    }
    
    protected function buildJsEditableChangesWatcherReset(string $oTableJs = null) : string
    {
        return $this->buildJsEditableChangesModelGetter($oTableJs) . ".setData({changes: {}, watching: false});";
    }
    
    protected function buildJsEditableChangesGetter(string $oTableJs = null) : string
    {
        return $this->buildJsEditableChangesModelGetter($oTableJs) . ".getProperty('/changes')";
    }
    
    protected function buildJsEditableChangesModelGetter(string $oTableJs = null) : string
    {
        return ($oTableJs ?? "sap.ui.getCore().byId('{$this->getId()}')") . ".getModel('{$this->getModelNameForChanges()}')";
    }
    
    protected function buildJsEditableChangesApplyToModel(string $oModelJs) : string
    {
        $widget = $this->getWidget();
        if ($widget->hasUidColumn() === false || $widget->getEditableChangesResetOnRefresh()) {
            return '';
        }
        $uidColName = $widget->getUidColumn()->getDataColumnName();
        
        return <<<JS
        
            // Keep previous values of all editable column in case the had changed
            (function(){
                var aEditableColNames = {$this->getEditableColumnNamesJson()};
                var oData = $oModelJs.getData();
                var aRows = oData.rows;
                if (aRows === undefined || aRows.length === 0) return;
                
                var bDataUpdated = false;
                var oChanges = {$this->buildJsEditableChangesGetter()};
                
                for (var iRow in aRows) {
                    var sUid = aRows[iRow]['$uidColName'];
                    if (oChanges[sUid]) {
                        for (var sFld in oChanges[sUid]) {
                            aRows[iRow][sFld] = oChanges[sUid][sFld];
                            bDataUpdated = true;
                        }
                    }
                }
                
                if (bDataUpdated) {
                    oData.rows = aRows;
                    $oModelJs.setData(oData);
                }
            })();
            
JS;
    }
    
    /**
     * 
     * @param string $oModelJs
     * @return string
     */
    protected function buildJsMarkRowsAsDirty(string $oModelJs) : string
    {
        if (! $this->hasDirtyColumn()) {
            return '';
        }
        
        $widget = $this->getWidget();
        $uidAttributeAlias = $widget->getMetaObject()->getUidAttributeAlias();
        return <<<JS

        (function(){
            var oData = $oModelJs.getData();
            var aRows = oData.rows;
            var bRowsDirty = false;
            exfPreloader.getOfflineActionsEffects('{$widget->getMetaObject()->getAliasWithNamespace()}')
            .then(function(aEffects) {
                var oDirtyColumn = sap.ui.getCore().byId('{$this->getDirtyFlagAlias()}');
                aEffects.forEach(function(oEffect){
                    for (var j = 0; j < aRows.length; j++) {
                        if (oEffect.key_values.indexOf(aRows[j]['{$uidAttributeAlias}']) > -1) {
                            aRows[j]['{$this->getDirtyFlagAlias()}'] = true;
                            bRowsDirty = true;
                            break;
                        }
                    }
                });
                if (oDirtyColumn) {
                    oDirtyColumn.setVisible(bRowsDirty);
                }

                if (bRowsDirty) {
                    oData._dirty = true;
                }

                oData.rows = aRows;
                $oModelJs.setData(oData);                
            })
        })();

JS;
        
    }
    
    /**
     * 
     * @return string
     */
    protected function getDirtyFlagAlias() : string
    {
        return "{$this->getId()}" . "DirtyFlag";
    }
    
    /**
     * 
     * @return bool
     */
    protected function hasDirtyColumn() : bool
    {
        $adapter = $this->getServerAdapter();
        if (! ($adapter instanceof UI5FacadeServerAdapter || $adapter instanceof PreloadServerAdapter)) {
            return false;
        }
        return $this->getDataWidget()->hasUidColumn();
    }
    
    /**
     *
     * @return bool
     */
    protected function hasPaginator() : bool
    {
        return ($this->getDataWidget() instanceof Data);
    }
    
    /**
     *
     * @return UI5DataPaginator
     */
    protected function getPaginatorElement() : UI5DataPaginator
    {
        return $this->getFacade()->getElement($this->getDataWidget()->getPaginator());
    }
    
    
    
    protected function buildJsContextMenuTrigger($eventJsVar = 'oEvent') {
        return <<<JS
        
                var oMenu = {$this->buildJsContextMenu($this->getWidget()->getButtons())};
                var eFocused = $(':focus');
                var eDock = sap.ui.core.Popup.Dock;
                oMenu.open(true, eFocused, eDock.CenterCenter, eDock.CenterBottom,  {$eventJsVar}.target);
                
JS;
    }
    
    /**
     * Returns a chainable method call to attach left/right/double click handlers to the control.
     * 
     * This method should be called on the control constructor when it is bein initialized. The
     * result will look like this:
     * 
     * ```
     * new sap.m.Table()
     * .attachLeftClick(function(){})
     * .attachRightClick(function(){})
     * .attachDoubleClick(function(){})
     * 
     * ```
     * 
     * To override click handlers for a specific click event, override the corresponding methods
     * in the class, that uses the trait. See UI5DataTable or UI5Scheduler for examples.
     * 
     * @see buildJsClickHandlerDoubleClick()
     * @see buildJsClickHandlerRightClick()
     * @see buildJsClickHandlerSingleClick()
     * 
     * @param string $oControllerJsVar
     * @return string
     */
    protected function buildJsClickHandlers($oControllerJsVar = 'oController') : string
    {
        return $this->buildJsClickHandlerDoubleClick($oControllerJsVar)
        . $this->buildJsClickHandlerRightClick($oControllerJsVar)
        . $this->buildJsClickHandlerLeftClick($oControllerJsVar);
    }
    
    /**
     * 
     * @param string $oControllerJsVar
     * @return string
     */
    protected function buildJsClickHandlerDoubleClick($oControllerJsVar = 'oController') : string
    {        
        // Double click. Currently only supports one double click action - the first one in the list of buttons
        if ($dblclick_button = $this->getWidget()->getButtonsBoundToMouseAction(EXF_MOUSE_ACTION_DOUBLE_CLICK)[0]) {
            return <<<JS
            
            .attachBrowserEvent("dblclick", function(oEvent) {
                var oTargetDom = oEvent.target;
                var iRowIdx = -1;
                if(! ({$this->buildJsClickIsTargetRowCheck('oTargetDom')})) return;
                
        		iRowIdx = {$this->buildJsClickGetRowIndex('oTargetDom')};
                if (iRowIdx !== -1) {
                    {$this->buildJsSelectRowByIndex("sap.ui.getCore().byId('{$this->getId()}')", 'iRowIdx', false, 'false')}
                }
                
                {$this->getFacade()->getElement($dblclick_button)->buildJsClickEventHandlerCall($oControllerJsVar)};
            })
JS;
        }
        return '';
    }
    
    /**
     * 
     * @param string $oControllerJsVar
     * @return string
     */
    protected function buildJsClickHandlerRightClick($oControllerJsVar = 'oController') : string
    {
        // Double click. Currently only supports one double click action - the first one in the list of buttons
        $rightclick_script = '';
        if ($rightclick_button = $this->getWidget()->getButtonsBoundToMouseAction(EXF_MOUSE_ACTION_RIGHT_CLICK)[0]) {
            $rightclick_script = $this->getFacade()->getElement($rightclick_button)->buildJsClickEventHandlerCall($oControllerJsVar);
        } else {
            $rightclick_script = $this->buildJsContextMenuTrigger();
        }
        
        if ($rightclick_script) {
            return <<<JS
            
            .attachBrowserEvent("contextmenu", function(oEvent) {
                var oTargetDom = oEvent.target;
                var iRowIdx = -1;
                if(! ({$this->buildJsClickIsTargetRowCheck('oTargetDom')})) return;
                
                oEvent.preventDefault();

                iRowIdx = {$this->buildJsClickGetRowIndex('oTargetDom')};
                if (iRowIdx !== -1) {
                    {$this->buildJsSelectRowByIndex("sap.ui.getCore().byId('{$this->getId()}')", 'iRowIdx', false, 'false')}
                }
                
                {$rightclick_script}
        	})
        	
JS;
        }
        return '';
    }
    
    public function buildJsSelectRowByIndex(string $oTableJs = 'oTable', string $iRowIdxJs = 'iRowIdx', bool $deSelect = false, string $bScrollToJs = 'true') : string
    {
        return '';
    }
    
    protected function buildJsClickGetRowIndex(string $oDomElementClickedJs) : string
    {
        return "$({$oDomElementClickedJs}).parents('tr').index()";
    }
    
    /**
     * 
     * @param string $oControllerJsVar
     * @return string
     */
    protected function buildJsClickHandlerLeftClick($oControllerJsVar = 'oController') : string
    {
        $onClickJs = $this->getController()->buildJsEventHandler($this, 'select', false);
        // Single click. Currently only supports one click action - the first one in the list of buttons
        if ($onClickJs || $leftclick_button = $this->getWidget()->getButtonsBoundToMouseAction(EXF_MOUSE_ACTION_LEFT_CLICK)[0]) {
            $btnJs = $leftclick_button ? $this->getFacade()->getElement($leftclick_button)->buildJsClickEventHandlerCall($oControllerJsVar) : '';
            return <<<JS
            
            .attachBrowserEvent("click", function(oEvent) {
        		var oTargetDom = oEvent.target;
                if(! ({$this->buildJsClickIsTargetRowCheck('oTargetDom')})) return;
                {$onClickJs}
                {$btnJs};
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
        return "{$oTargetDomJs} !== undefined";
    }
    
    /**
     * IDEA move context-menu-related methods to separate element UI5ContextMenu
     * 
     * @param Button[]
     * @return string
     */
    protected function buildJsContextMenu(array $buttons)
    {
        return <<<JS
        
                new sap.ui.unified.Menu({
                    items: [
                        {$this->buildJsContextMenuButtons($buttons)}
                    ],
                    itemSelect: function(oEvent) {
                        var oMenu = oEvent.getSource();
                        var oItem = oEvent.getParameters().item;
                        if (! oItem.getSubmenu()) {
                            oMenu.destroy();
                        }
                    }
                })
JS;
    }
    
    /**
     * IDEA move context-menu-related methods to separate element UI5ContextMenu
     * 
     * @param Button[] $buttons
     * @return string
     */
    protected function buildJsContextMenuButtons(array $buttons)
    {
        $context_menu_js = '';
        
        $last_parent = null;
        foreach ($buttons as $button) {
            if ($button->isHidden()) {
                continue;
            }
            if ($button->getParent() == $this->getWidget()->getToolbarMain()->getButtonGroupForSearchActions()) {
                continue;
            }
            if (! is_null($last_parent) && $button->getParent() !== $last_parent) {
                $startSection = true;
            }
            $last_parent = $button->getParent();
            
            $context_menu_js .= ($context_menu_js ? ',' : '') . $this->buildJsContextMenuItem($button, $startSection);
        }
        
        return $context_menu_js;
    }
    
    /**
     *
     * IDEA move context-menu-related methods to separate element UI5ContextMenu
     * 
     * @param Button $button
     * @param boolean $startSection
     * @return string
     */
    protected function buildJsContextMenuItem(Button $button, $startSection = false)
    {
        $menu_item = '';
        
        $startsSectionProperty = $startSection ? 'startsSection: true,' : '';
        
        /* @var $btn_element \exface\UI5Facade\Facades\Elements\UI5Button */
        $btn_element = $this->getFacade()->getElement($button);
        
        if ($button instanceof MenuButton){
            if ($button->getParent() instanceof ButtonGroup && $button === $this->getFacade()->getElement($button->getParent())->getMoreButtonsMenu()){
                $caption = $button->getCaption() ? $button->getCaption() : '...';
            } else {
                $caption = $button->getCaption();
            }
            $menu_item = <<<JS
            
                        new sap.ui.unified.MenuItem({
                            icon: "{$btn_element->buildCssIconClass($button->getIcon())}",
                            text: "{$caption}",
                            {$startsSectionProperty}
                            submenu: {$this->buildJsContextMenu($button->getButtons())}
                        })
JS;
        } else {
            $handler = $btn_element->buildJsClickViewEventHandlerCall();
            $select = $handler !== '' ? 'select: ' . $handler . ',' : '';
            $menu_item = <<<JS
            
                        new sap.ui.unified.MenuItem({
                            icon: "{$btn_element->buildCssIconClass($button->getIcon())}",
                            text: "{$button->getCaption()}",
                            enabled: function(){
                                var oBtn = sap.ui.getCore().byId('{$btn_element->getId()}');
                                return oBtn ? oBtn.getEnabled() : false;
                            }(),
                            {$select}
                            {$startsSectionProperty}
                        })
JS;
        }
        return $menu_item;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::registerExternalModules()
     */
    public function registerExternalModules(UI5ControllerInterface $controller) : UI5AbstractElement
    {
        $f = $this->getFacade();
        
        foreach ($this->getDataWidget()->getColumns() as $col) {
            $f->getElement($col)->registerExternalModules($controller);
        }
        return $this;
    }
    
    /**
     * 
     * @param string $js
     * @return UI5AbstractElement
     */
    public function addOnSelectScript(string $js) : UI5AbstractElement
    {
        $this->getController()->addOnEventScript($this, 'select', $js);
        return $this;
    }
    
    /**
     *
     * @param string $js
     * @return UI5AbstractElement
     */
    public function addOnRefreshScript(string $js) : UI5AbstractElement
    {
        $this->getController()->addOnEventScript($this, 'refresh', $js);
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsValueGetterMethod()
     */
    public function buildJsValueGetterMethod()
    {
        throw new FacadeLogicError('Cannot call buildJsValueGetterMethod() on a UI5 data element: use buildJsValueGetter() instead!');
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsValueSetterMethod()
     */
    public function buildJsValueSetterMethod($valueJs)
    {
        throw new FacadeLogicError('Cannot call buildJsValueSetterMethod() on a UI5 data element: use buildJsValueSetter() instead!');
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsValueGetter()
     */
    public function buildJsValueGetter($dataColumnName = null, $rowNr = null)
    {
        $widget = $this->getDataWidget();
        $rows = $this->buildJsGetSelectedRows('oTable');
        if ($dataColumnName !== null) {
            /* @var $col \exface\Core\Widgets\DataColumn */
            if (! $col = $widget->getColumnByDataColumnName($dataColumnName)) {
                if ($col = $widget->getColumnByAttributeAlias($dataColumnName)) {
                    $dataColumnName = $col->getDataColumnName();
                }
            }
            if (! $col && ! ($widget->getMetaObject()->getUidAttributeAlias() === $dataColumnName)) {
                throw new WidgetConfigurationError($this->getWidget(), 'Cannot build live value getter for ' . $this->getWidget()->getWidgetType() . ': column "' . $dataColumnName . '" not found!');
            }
            $delim = $col && $col->isBoundToAttribute() ? $col->getAttribute()->getValueListDelimiter() : EXF_LIST_SEPARATOR;
            $colMapper = '.map(function(value,index) { return value === undefined ? "" : value["' . $dataColumnName . '"];}).join("' . $delim . '")';
        } else {
            $colMapper = '';
        }
        
        return <<<JS
        
(function(){
    var oTable = sap.ui.getCore().byId('{$this->getId()}');
    return {$rows}{$colMapper};
}() || '')

JS;
    }
    
    /**
     * Returns an inline-snippet (without `;`) to get an array of the currently selected data rows.
     * 
     * If no data is selected, the JS snippet must resolve to an empty array.
     * 
     * Each item of the array must be a JS object with the same structure as the rows in
     * the main model of the data widget (in other words, it should contain a selection of
     * those rows).
     * 
     * For single-select widget, this method must return an array with one or zero elements.
     * 
     * @param string $oControlJs
     * @return string
     */
    protected abstract function buildJsGetSelectedRows(string $oControlJs) : string;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsDataGetter()
     */
    public function buildJsDataGetter(ActionInterface $action = null)
    {
        if ($action === null) {
            $getRows = "var rows = oControl.getModel().getData().rows;";
        } elseif ($action instanceof iReadData) {
            // If we are reading, than we need the special data from the configurator
            // widget: filters, sorters, etc.
            return $this->getFacade()->getElement($this->getWidget()->getConfiguratorWidget())->buildJsDataGetter($action);
        } else {
            $getRows = 'var rows = ' . $this->buildJsGetSelectedRows('oControl') . ';';
        }
        return <<<JS
    function() {
        var oDirtyCtrl = sap.ui.getCore().byId('{$this->getDirtyFlagAlias()}');
        var oControl = sap.ui.getCore().byId('{$this->getId()}');
        {$getRows}
        rows = rows || [];

        if (oControl.getModel().getProperty('/_dirty') || (oDirtyCtrl && oDirtyCtrl.getVisible() === true)) {
            for (var i = 0; i < rows.length; i++) {
                delete rows[i]['{$this->getDirtyFlagAlias()}'];
            }
        }

        return {
            oId: '{$this->getWidget()->getMetaObject()->getId()}',
            rows: rows
        };
    }()
JS;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsOnEventScript()
     */
    public function buildJsOnEventScript(string $eventName, string $scriptJs, string $oEventJs) : string
    {
        $parentResult = parent::buildJsOnEventScript($eventName, $scriptJs, $oEventJs);
        switch ($eventName) {
            // Before the change event is fired, check if the selection actually did change by
            // comparing it to a saved copy of the previous selection.
            case self::EVENT_NAME_CHANGE:
                return <<<JS
                
            // Perform the on-select scripts in any case
            {$this->getController()->buildJsEventHandler($this, 'select', false)}
            
            // Check, if selection actually changed. Return here if not.
            if (
                (function(){
                    var oControl = sap.ui.getCore().byId('{$this->getId()}');
                    var newSelection = {$this->buildJsGetSelectedRows('oControl')};
                    var oldSelection = oControl.data('exfPreviousSelection') || [];
                    oControl.data('exfPreviousSelection', newSelection);
                    return {$this->buildJsRowCompare('oldSelection', 'newSelection', false)};
                })()
            ) {
                return;
            }
            {$parentResult}
            
JS;
            default:
                return $parentResult;
        }
    }
    
    public function hasButtonBack() : bool
    {
        return $this->isWrappedInDynamicPage() && $this->getDynamicPageShowBackButton();
    }
}