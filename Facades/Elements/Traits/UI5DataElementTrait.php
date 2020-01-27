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
use exface\Core\Interfaces\Widgets\iCanPreloadData;
use exface\UI5Facade\Facades\UI5Facade;
use exface\UI5Facade\Facades\Interfaces\UI5ServerAdapterInterface;
use exface\UI5Facade\Facades\Elements\ServerAdapters\OData2ServerAdapter;

/**
 * This trait helps wrap thrid-party data widgets (like charts, image galleries, etc.) in 
 * UI5 panels with standard toolbars, a configurator dialog, etc. 
 * 
 * How it works:
 * 
 * The method buildJsConstructor() is pre-implemented and takes care of creating the report floorplan,
 * toolbars, the P13n-Dialog, etc. The control to be placed with the report floorplan is provided by the
 * method buildJsConstructorForControl(), which nees to be implemented in every class using the trait.
 * 
 * The trait also provides a default data loader implementation via buildJsDataLoader(), which supports
 * lazy loading and data preload out of the box. The data loaded is automatically placed in the main
 * model of the control (use `sap.ui.getCore().byId({$this->getId()}).getModel()` to access it). However,
 * you can still customize the data loading logic by implementing 
 * 
 * - buildJsDataLoaderPrepare() - called right before the default loading starts.
 * - buildJsDataLoaderParams() - called after the default request parameters were computed and allowing
 * to customize them
 * - buildJsDataLoaderOnLoaded() - called right after the data was placed in the model, but before
 * the busy-state is dismissed. This is the place, where you would add all sorts of postprocessing or
 * the logic to load the data into a non-UI5 control.
 * 
 * NOTE: The main model of the control and it's page wrapper (if the report floorplan is used) is 
 * NOT the view model - it's a separate one. It contains the data set loaded. There is also a secondary
 * model for the configurator (i.e. filter values, sorting options, etc.) - it's name can be obtained
 * from `getModelNameForConfigurator()`.
 * 
 * You can also customize the toolbars by overriding
 * - buildJsToolbar() - returns the constructor of the top toolbar (sap.m.OverflowToolbar by default)
 * - buildJsToolbarContent() - returns the toolbar content (i.e. title, buttons, etc.)
 * - buildJsQuickSearchConstructor()
 * 
 * @author Andrej Kabachnik
 *
 * @method UI5Facade getFacade()
 */
trait UI5DataElementTrait {
    
    use UI5HelpButtonTrait;
    
    private $quickSearchElement = null;
    
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
        
        $controller->addMethod('onUpdateFilterSummary', $this, '', $this->buildJsFilterSummaryUpdater());
        $controller->addMethod('onLoadData', $this, 'oControlEvent, keep_page_pos', $this->buildJsDataLoader());
        $this->initConfiguratorControl($controller);
        
        // Reload the data every time the view is shown. This is important, because otherwise 
        // old rows may still be visible if a dialog is open, closed and then reopened for another 
        // instance.
        // The data resetter will empty the table as soon as the view is opened, then the refresher
        // is run after all the view loading logic finished - that's what the setTimeout() is for -
        // otherwise the refresh would run before the view finished initializing, before the prefill
        // is started and will probably be empty.
        $controller->addOnShowViewScript("try { {$this->buildJsDataResetter()} } catch (e) {} setTimeout(function(){ {$this->buildJsRefresh()} }, 0);");
        
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
            $controller->addOnDefineScript("exfPreloader.addPreload('{$this->getMetaObject()->getAliasWithNamespace()}', {$preloadDataCols}, {$preloadImgCols}, '{$widget->getPage()->getId()}', '{$widget->getId()}');");
        }
        
        $js = $this->buildJsConstructorForControl();
        
        $initModels = ".setModel(new sap.ui.model.json.JSONModel()).setModel(new sap.ui.model.json.JSONModel(), '{$this->getModelNameForConfigurator()}')";
        
        if ($this->isWrappedInDynamicPage()){
            return $this->buildJsPage($js) . $initModels;
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
        return <<<JS
        new sap.m.Panel({
            height: "100%",
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
        return <<<JS

			new sap.m.OverflowToolbar({
                design: "Transparent",
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
        $rightExtras = $rightExtras === null ? '' : rtrim($leftExtras, ", ") . ',';
        
        return <<<JS

                    {$heading}
                    {$leftExtras}
			        new sap.m.ToolbarSpacer(),
                    {$this->buildJsButtonsConstructors()}
                    {$rightExtras}
                    {$this->buildJsQuickSearchConstructor()}
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
                        
    abstract protected function buildJsDataResetter() : string;
    
    /**
     * Returns the definition of a javascript function to fill the table with data: onLoadDataTableId(oControlEvent).
     *
     * @return string
     */
    protected function buildJsDataLoader($oControlEventJsVar = 'oControlEvent', $keepPagePosJsVar = 'keep_page_pos')
    {
        // Before we load anything, we need to make sure, the view data is loaded.
        // The view model has a special property to indicate if view (prefill) data
        // is being loaded. So we check that property and, if it shows a prefill
        // running right now, we listen for changes on the property. Once it is not
        // set to true anymore, we can do the refresh. The setTimeout() wrapper is
        // needed to make sure all filters bound to the prefill model got their values!
        $js = <<<JS
        
                var oViewModel = sap.ui.getCore().byId("{$this->getId()}").getModel("view");
                var sPendingPropery = "/_prefill/pending";
                if (oViewModel.getProperty(sPendingPropery) === true) {
                    var oPrefillBinding = new sap.ui.model.Binding(oViewModel, sPendingPropery, oViewModel.getContext(sPendingPropery));
                    var fnPrefillHandler = function(oEvent) {
                        oPrefillBinding.detachChange(fnPrefillHandler);
                        setTimeout(function() {
                            {$this->buildJsRefresh()};
                        }, 0);
                    };
                    oPrefillBinding.attachChange(fnPrefillHandler);
                    return;
                }

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
    protected function buildJsDataLoaderFromServer($oControlEventJsVar = 'oControlEvent', $keepPagePosJsVar = 'keep_page_pos')
    {
        $widget = $this->getWidget();
        
        $doLoad = $this->getServerAdapter()->buildJsServerRequest(
            $widget->getLazyLoadingAction(),
            'oModel',
            'params',
            $this->buildJsBusyIconHide() . '; ' . $this->buildJsDataLoaderOnLoaded('oModel'),
            $this->buildJsBusyIconHide(),
            $this->buildJsOfflineHint('oTable')
        );
        
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
                
                {$this->buildJsCheckRequiredFilters($this->buildJsShowMessageOverlay($widget->getAutoloadDisabledHint()) . "; return;")}
                
                {$this->buildJsBusyIconShow()}
                
        		// Add quick search
                {$quickSearchParam}
                
                // Add configurator data
                params.data = {$this->getP13nElement()->buildJsDataGetter()};
                
                // Add sorters and filters from P13nDialog
                var aSortItems = sap.ui.getCore().byId('{$this->getP13nElement()->getIdOfSortPanel()}').getSortItems();
                for (var i in aSortItems) {
                    params.sort = (params.sort ? params.sort+',' : '') + aSortItems[i].getColumnKey();
                    params.order = (params.order ? params.order+',' : '') + (aSortItems[i].getOperation() == 'Ascending' ? 'asc' : 'desc');
                }
                
                {$this->buildJsDataLoaderParams($oControlEventJsVar, 'params', $keepPagePosJsVar)}
                
                {$doLoad}
                
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
                
    protected function buildJsDataLoaderParams(string $oControlEventJsVar = 'oControlEvent', string $oParamsJs = 'params') : string
    {
        return '';
    }
    
    
    protected function buildJsDataLoaderOnLoaded(string $oModelJs = 'oModel') : string
    {
        if ($this->isWrappedInDynamicPage()) {
            $dynamicPageFixes = <<<JS
            
                        if (sap.ui.Device.system.phone) {
                            sap.ui.getCore().byId('{$this->getIdOfDynamicPage()}').setHeaderExpanded(false);
                        }
                        // Redraw the table to make it fit the page height agian. Otherwise it would be
                        // of default height after dialogs close, etc.
                        sap.ui.getCore().byId('{$this->getId()}').invalidate();
JS;
        }
        
        return <<<JS
        
            oTable.getModel("{$this->getModelNameForConfigurator()}").setProperty('/filterDescription', {$this->getController()->buildJsMethodCallFromController('onUpdateFilterSummary', $this, '', 'oController')});
            {$dynamicPageFixes}
			
JS;
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
    protected function isWrappedInDynamicPage()
    {
        return $this->getWidget()->hasParent() || $this->getWidget()->getHideHeader() ? false : true;
    }
    
    /**
     * Returns TRUE if this table uses a remote data source and FALSE otherwise.
     *
     * @return boolean
     */
    protected function isLazyLoading()
    {
        return $this->getWidget()->getLazyLoading(true);
    }
    
    /**
     * Wraps the given content in a constructor for the sap.f.DynamicPage used to create the Fiori list report floorplan.
     *
     * @param string $content
     * @return string
     */
    protected function buildJsPage(string $content) : string
    {
        foreach ($this->getWidget()->getToolbarMain()->getButtonGroupForSearchActions()->getButtons() as $btn) {
            if ($btn->getAction()->isExactly('exface.Core.RefreshWidget')){
                $btn->setShowIcon(false);
                $btn->setHint($btn->getCaption());
                $btn->setCaption($this->translate('WIDGET.DATATABLE.GO_BUTTON_TEXT'));
            }
            $top_buttons .= $this->getFacade()->getElement($btn)->buildJsConstructor() . ',';
        }
        
        if ($this->getView()->isWebAppRoot() === true) {
            $title = <<<JS
            
                            new sap.m.Title({
                                text: "{$this->getCaption()}"
                            })
                            
JS;
        } else {
            $title = <<<JS
            
                            new sap.m.HBox({
                                items: [
                                    new sap.m.Button({
                                        icon: "sap-icon://nav-back",
                                        press: [oController.onNavBack, oController],
                                        type: sap.m.ButtonType.Transparent
                                    }).addStyleClass('exf-page-heading-btn'),
                                    new sap.m.Title({
                                        text: "{$this->getCaption()}"
                                    })
                                ]
                            })
                            
JS;
        }
        
        return <<<JS
        
        new sap.f.DynamicPage("{$this->getIdOfDynamicPage()}", {
            fitContent: true,
            preserveHeaderStateOnScroll: true,
            headerExpanded: true,
            title: new sap.f.DynamicPageTitle({
				expandedHeading: [
                    {$title}
				],
                snappedHeading: [
                    new sap.m.VBox({
                        items: [
        					{$title},
                            new sap.m.Text({
                                text: "{{$this->getModelNameForConfigurator()}>/filterDescription}"
                            })
                        ]
                    })
				],
				actions: [
				    {$top_buttons}
				]
            }),
            
			header: new sap.f.DynamicPageHeader({
                pinnable: true,
				content: [
                    new sap.ui.layout.Grid({
                        defaultSpan: "XL2 L3 M4 S12",
                        content: [
							{$this->getFacade()->getElement($this->getWidget()->getConfiguratorWidget())->buildJsFilters()}
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
    
    /**
     *
     * @return string
     */
    protected function buildJsFilterSummaryUpdater()
    {
        $filter_checks = '';
        foreach ($this->getDataWidget()->getFilters() as $fltr) {
            $elem = $this->getFacade()->getElement($fltr);
            $filter_checks .= 'if(' . $elem->buildJsValueGetter() . ") {filtersCount++; filtersList += (filtersList == '' ? '' : ', ') + '{$elem->getCaption()}';} \n";
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
    protected function buildJsFilterSummaryFunctionName() {
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
            return '';
        }
        
        $qsElement = $this->getQuickSearchElement();
        if ($qsElement instanceof UI5SearchField) {
            $qsElement->setSearchCallbackJs($this->getController()->buildJsMethodCallFromView('onLoadData', $this));
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
     * Fires the onChange event and triggers all onChange-scripts if the current value really changed.
     * 
     * Set $buildForView=true if the snippet is to be used in a view (i.e. as value of a control property)
     * and $buildForView=false if you simply need to call the event handler from some other controller code.
     * 
     * @param bool $buildForView
     * @return string
     */
    protected function buildJsOnChangeTrigger(bool $buildForView) : string
    {
        // TODO check if the selected row and it's data really changed - like in jEasyUI
        return $this->getController()->buildJsEventHandler($this, self::EVENT_NAME_CHANGE, $buildForView);
    }
}