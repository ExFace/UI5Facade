<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Interfaces\Widgets\iShowSingleAttribute;
use exface\UI5Facade\Facades\Interfaces\UI5ValueBindingInterface;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JqueryDataCarouselTrait;
use exface\Core\Factories\ActionFactory;
use exface\Core\Actions\ShowDialog;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\Widgets\WidgetGrid;

/**
 * Generates a sap.ui.layout.Splitter with logic similar to the FlexibleColumnLayout.
 * 
 * Theoretically, a DataCarousel widget would match the `sap.f.FlexibleColumnLayout` in 
 * Fiori best, but the FlexibleColumnLayout only works properly if used as the view's
 * top-level control - not in ObjectPages, etc. 
 * 
 * Instead we use simple Splitter with on pane being the data widget and the other - the
 * details packed inside a `sap.m.Panel` with the required expand/collapse buttons.
 * 
 * The current expand/collapse state is stored in a special model attached to the Splitter,
 * so it can easily be accessed from any code.
 * 
 * **NOTE**: The previous/next buttons in the details panel will only show if the data
 * widget's UI5 implementation has the method `buildJsSelectRowByIndex()`!
 *
 * @author Andrej Kabachnik
 * 
 * @method \exface\Core\Widgets\DataCarousel getWidget()
 *        
 */
class UI5DataCarousel extends UI5Split
{
    use JqueryDataCarouselTrait;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Split::buildJsConstructor()
     */
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {
        $this->registerSyncOnMaster();
        
        $initSplitter = $this->buildJsSetSizesInitial("sap.ui.getCore().byId('{$this->getId()}')") . "setTimeout(function(){ {$this->buildJsEmptyHintShow()} }, 100);";
        $this->getController()
            // Reset splitter position when the view is loaded for the first time
            ->addOnInitScript($initSplitter)
            // Reset when it's hidden, so the splitter will be in the initial
            // position when the view/dialog is reopened
            ->addOnHideViewScript($initSplitter, true);
        
        $heightDim = $this->getWidget()->getHeight();
        if ($heightDim->isUndefined()) {
            $heightCss = '100%';
        } else {
            $heightCss = $this->buildCssHeight();
        }
        
        $splitter = <<<JS
        
    new sap.ui.layout.Splitter("{$this->getId()}", {
        height: "{$heightCss}",
        width: "100%",
        orientation: "{$this->getOrientation()}",
        contentAreas: [
            {$this->buildJsChildrenConstructors()}
        ]
    })
    .setModel(new sap.ui.model.json.JSONModel({
        detailsExpanded: false,
        dataExpanded: sap.ui.Device.system.phone
    }), "_innerState")
    {$this->buildJsPseudoEventHandlers()}

JS;
        if ($this->hasPageWrapper() === true) {
            return $this->buildJsPageWrapper($splitter);
        }
        
        return $splitter;
    }
    
    public function buildJsChildrenConstructors() : string
    {
        $widget = $this->getWidget();
        $dataElem = $this->getFacade()->getElement($widget->getDataWidget());
        $detailElem = $this->getFacade()->getElement($widget->getDetailsWidget());
        
        if ($detailTitle = $detailElem->getCaption()) {
            $headerText = $this->escapeJsTextValue($detailTitle);
            $detailElem->getWidget()->setCaption("");
        } else {
            $headerText = '""';
        }
        
        if (($detailElem->getWidget() instanceof iFillEntireContainer) && ! ($widget->getDetailsWidget() instanceof WidgetGrid)) {
            $detailClasses = 'sapUiNoContentPadding';
        }
        
        if (method_exists($dataElem, 'buildJsSelectRowByIndex')) {
            $prevNextButtonsJs = <<<JS

                            new sap.m.Button('{$this->getId()}-details-btn-prev', {
                                icon: "sap-icon://navigation-left-arrow",
                                tooltip: "{i18n>WIDGET.DATACAROUSEL.DETAILS_PREV}",
                                press: function(oEvent) {
                                    var oSplitter = sap.ui.getCore().byId('{$this->getId()}');
                                    var oTable = sap.ui.getCore().byId('{$dataElem->getId()}');
                                    var iRowIdx = oSplitter.getModel('_innerState').getProperty('/currentRowIdx');
                                    iRowIdx = iRowIdx - 1;
                                    {$dataElem->buildJsSelectRowByIndex('oTable', 'iRowIdx')}
                                }
                            }),
                            new sap.m.Button('{$this->getId()}-details-btn-next', {
                                icon: "sap-icon://navigation-right-arrow",
                                tooltip: "{i18n>WIDGET.DATACAROUSEL.DETAILS_NEXT}",
                                press: function(oEvent) {
                                    var oSplitter = sap.ui.getCore().byId('{$this->getId()}');
                                    var oTable = sap.ui.getCore().byId('{$dataElem->getId()}');
                                    var iRowIdx = oSplitter.getModel('_innerState').getProperty('/currentRowIdx');
                                    iRowIdx = iRowIdx + 1;
                                    {$dataElem->buildJsSelectRowByIndex('oTable', 'iRowIdx')}
                                }
                            }),
JS;
        }
        
        if ($detailElem->getWidget() instanceof iFillEntireContainer) {
            $contentJs = $detailElem->buildJsConstructor();
            $contentJs .= ".addStyleClass('exf-datacarousel-details-filler')";
        }
        
        return <<<JS

            {$dataElem->buildJsConstructor()},
            new sap.m.Panel('{$this->getId()}-DetailPanel', {
                headerText: {$headerText},
                height: "{$detailElem->buildCssHeight()}",
                headerToolbar: [
                    new sap.m.OverflowToolbar({
                        content: [
                            new sap.m.ToolbarSpacer(),
                            {$prevNextButtonsJs}
                            new sap.m.Button({
                                icon: "{= \${_innerState>/detailsExpanded} === true ? 'sap-icon://exit-full-screen' : 'sap-icon://full-screen'}",
                                press: function(oEvent) {
                                    var oButton = oEvent.getSource();
                                    var oSplitter = sap.ui.getCore().byId('{$this->getId()}');
                                    if (oSplitter.getModel('_innerState').getProperty('/detailsExpanded') === false) {
                                        {$this->buildJsSetSizesExpandDetails('oSplitter')}
                                    } else {
                                        {$this->buildJsSetSizesInitial('oSplitter')}
                                    }
                                }
                            }),
                            new sap.m.Button({
                                icon: "sap-icon://decline",
                                tooltip: "{i18n>WIDGET.DATACAROUSEL.DETAILS_HIDE}",
                                press: function(oEvent) {
                                    var oSplitter = sap.ui.getCore().byId('{$this->getId()}');
                                    {$this->buildJsSetSizesExpandData('oSplitter')}
                                }
                            })
                        ]
                    })
                ],
                content: [
                    {$contentJs}
                ]
            })
            .addStyleClass("{$this->buildCssElementClass()} {$detailClasses} exf-panel-no-border exf-datacarousel-details")

JS;
    }
    
    /**
     * 
     * @return \exface\UI5Facade\Facades\Elements\UI5DataCarousel
     */
    protected function registerSyncOnMaster()
    {
        $dataIsEditable = $this->getDataElement()->isEditable();
        foreach ($this->getWidget()->getChildrenToSyncWithDataWidget($this->getWidget()->getDetailsWidget()) as $child) {
            if (! ($child instanceof iShowSingleAttribute)) {
                continue;
            }
            if(! $child->isBoundToAttribute() && ! $child->getDataColumnName()) {
                continue;
            }
            
            if (! $dataIsEditable) {
                $this->getDataElement()->setEditable(true);
            }
            $childElement = $this->getFacade()->getElement($child);
            if ($childElement instanceof UI5ValueBindingInterface) {
                $childElement->setValueBoundToModel(true);
            }
            if ($childElement instanceof UI5ValueBindingInterface) {
                $bindings .= <<<JS

            oControl = sap.ui.getCore().byId("{$childElement->getId()}");
            oBindingInfo = oControl.getBindingInfo("{$childElement->buildJsValueBindingPropertyName()}");
            oBindingInfo.parts[0].path = sPath + "{$childElement->getValueBindingPath()}";
            oControl.setModel(oModel).bindProperty("{$childElement->buildJsValueBindingPropertyName()}", oBindingInfo);
            oControl.setBindingContext(new sap.ui.model.Context(oModel, sPath + "{$childElement->getValueBindingPath()}"));
JS;
            }
        }
        
        // Determine the currently selected row and replace the binding path of each
        // details widget with the path to the selected row in the model of the data
        // widget. This way, they will be bound to each-other.
        // Use a fake show-dialog-action to make the data getter behave as required
        // Also make sure the details area is shown when an item is select - but only
        // if the area was previously hidden because otherwise each click would restore
        // area sizes to default values!
        $action = ActionFactory::createFromString($this->getWorkbench(), ShowDialog::class);
        $bindingScript = <<<JS

        (function() {
            var oSplit = sap.ui.getCore().byId('{$this->getId()}');
            var oDetailArea = oSplit.getContentAreas()[1];
            var oTable = sap.ui.getCore().byId('{$this->getDataElement()->getId()}');
            var oRowSelected = {$this->getDataElement()->buildJsDataGetter($action)}.rows[0];
            var oModel = oTable.getModel();
            var iRowIdx = oModel.getData().rows.indexOf(oRowSelected);
            var sPath = '/rows/' + iRowIdx;
            var oControl, oBindingInfo;
            var oBtnPrev = sap.ui.getCore().byId('{$this->getId()}-details-btn-prev');
            var oBtnNext = sap.ui.getCore().byId('{$this->getId()}-details-btn-next');

            if (iRowIdx >= 0) {
                {$this->buildJsEmptyHintHide()}
                oSplit.getModel('_innerState').setProperty('/currentRowIdx', iRowIdx);
            } else {
                {$this->buildJsEmptyHintShow()}
                oSplit.getModel('_innerState').setProperty('/currentRowIdx', -1);
            }

            if (oBtnPrev) {
                oBtnPrev.setEnabled(iRowIdx !== 0);
            }

            if (oBtnNext) {
                oBtnNext.setEnabled(iRowIdx !== oModel.getData().rows.length - 1);
            }

            {$this->getDetailsElement()->buildJsResetter()};

            {$bindings}
            
            if (iRowIdx > -1 && oSplit.getModel('_innerState').getProperty('/dataExpanded') === true) {
                if (sap.ui.Device.system.phone) {
                    {$this->buildJsSetSizesExpandDetails('oSplit')}
                } else {
                    {$this->buildJsSetSizesInitial('oSplit')}
                }
            }
        })();
        
JS;
        if (method_exists($this->getDataElement(), 'addOnSelectScript')) {
            $this->getDataElement()->addOnSelectScript($bindingScript);
        } else {
            $this->getDataElement()->addOnChangeScript($bindingScript);
        }
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsSetSizesExpandData(string $oSplitJs) : string
    {
        return <<<JS

        sap.ui.getCore().byId('{$this->getId()}').getContentAreas().forEach(function(oControl, i){
            oControl.setLayoutData(
                new sap.ui.layout.SplitterLayoutData({size: (i === 0 ? '100%' : '0px')})
            );
        });

        $oSplitJs.getModel('_innerState').setProperty('/dataExpanded', true);
        $oSplitJs.getModel('_innerState').setProperty('/detailsExpanded', false);
JS;
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsSetSizesExpandDetails(string $oSplitJs) : string
    {
        return <<<JS
        
            $oSplitJs.getContentAreas().forEach(function(oControl, i){
                oControl.setLayoutData(
                    new sap.ui.layout.SplitterLayoutData({size: (i === 1 ? '100%' : '0px')})
                );
            });
            
        $oSplitJs.getModel('_innerState').setProperty('/dataExpanded', false);
        $oSplitJs.getModel('_innerState').setProperty('/detailsExpanded', true);
JS;
    }
    
    protected function buildJsSetSizesInitial(string $oSplitJs) : string
    {
        // Calculate initial sizes of the split areas:
        // 1) collect height/width dimensions of split panels depending on orientation
        // 2) calculate UI5 sizes from them and remember the results
        // 3) replace panel dimensions with standard values to avoid percentual values
        // being applied multiple times (e.g. 30% of 30%)
        // NOTE: caching UI5 sizes is important because the original widths of split
        // panels are lost after first run and the method should always yield
        // identical results!
        if (empty($this->sizesInitial)) {
            $widget = $this->getWidget();
            
            foreach ($widget->getPanels() as $panel) {
                if ($widget->isSideBySide()) {
                    $dims[] = $panel->getWidth();
                    if (! $panel->getWidth()->isUndefined()) {
                        $panel->setWidth('100%');
                    }
                } else {
                    $dims[] = $panel->getHeight();
                    if (! $panel->getHeight()->isUndefined()) {
                        $panel->setHeight(null);
                    }
                }
                
            }
            
            foreach ($dims as $dim) {
                switch (true) {
                    case $dim->isUndefined():
                    case $dim->isMax():
                        $this->sizesInitial[] = null;
                        break;
                    case $dim->isRelative():
                        $this->sizesInitial[] = (($widget->isSideBySide() ? $this->getWidthRelativeUnit() : $this->getHeightRelativeUnit()) * $dim->getValue()) . 'px';
                        break;
                    default:
                        $this->sizesInitial[] = $dim->getValue();
                }
            }
        }
        
        $sizesJson = json_encode($this->sizesInitial);
        
        $expandedSizesJs = <<<JS
        
            // Restore initial sizes of split areas
            (function(){
                var aSizes = $sizesJson;
                $oSplitJs.getContentAreas().forEach(function(oControl, i){
                    if (aSizes.length > i) {
                        oControl.setLayoutData(
                            new sap.ui.layout.SplitterLayoutData({size: aSizes[i]})
                        );
                    }
                });
            })();
            
JS;
        return <<<JS

            if (sap.ui.Device.system.phone) {
                {$this->buildJsSetSizesExpandData($oSplitJs)}
            } else {
                $expandedSizesJs;
                $oSplitJs.getModel('_innerState').setProperty('/dataExpanded', false);
                $oSplitJs.getModel('_innerState').setProperty('/detailsExpanded', false);
            }

JS;
    }
    
    /**
     * 
     * @return string
     */
    protected function getModelNameForState() : string
    {
        return 'expandState';
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsEmptyHintShow() : string
    {
        return <<<JS

        sap.ui.getCore().byId('{$this->getId()}-DetailPanel')
        .setBusyIndicatorDelay(0)
        .setBusy(true)
        .addStyleClass('exf-busy-text');
        setTimeout(function(){
            $('#{$this->getId()}-DetailPanel .sapUiLocalBusyIndicator').prepend($('<div class="exf-busy-text-content">{$this->translate('WIDGET.DATACAROUSEL.DETAILS_EMPTY_HINT')}</div>'));
        },0);
JS;
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsEmptyHintHide() : string
    {
        return <<<JS
        
        sap.ui.getCore().byId('{$this->getId()}-DetailPanel')
        .setBusy(false)
        .removeStyleClass('exf-busy-text');
JS;
    }
    
    /**
     * 
     * @return UI5AbstractElement
     */
    protected function getDetailsElement() : UI5AbstractElement
    {
        return $this->getFacade()->getElement($this->getWidget()->getDetailsWidget());
    }
}