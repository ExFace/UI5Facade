<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Widgets\Tabs;
use exface\Core\Widgets\Tab;
use exface\Core\Widgets\Image;
use exface\Core\Interfaces\Widgets\iTriggerAction;
use exface\Core\Interfaces\Widgets\iHaveValue;
use exface\Core\Interfaces\Actions\iShowWidget;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\Factories\ActionFactory;
use exface\Core\Interfaces\Actions\iShowDialog;
use exface\Core\Widgets\Split;

/**
 * In OpenUI5 dialog widgets are either rendered as sap.m.Page (if maximized) or as sap.m.Dialog.
 * 
 * A non-maximized `Dialog` widget will be rendered as a sap.m.Dialog. If the widget includes
 * tabs, they will be rendered normally (sap.m.IconTabBar)
 * 
 * A maximized `Dialog` will be rendered as a sap.m.Page with the following content:
 * - if the `Dialog` contains the `Tabs` widget, the `sap.uxap.ObjectPageLayout` will be used with a
 * `sap.uxap.ObjectPageSection` and a single `sap.uxap.ObjectPageSubsection` for every `Tab` widget.
 * - if the `Dialog` contains multiple widgets, they will all be placed in a single section and 
 * subsection of a `sap.uxap.ObjectPageLayout`.
 * - if the `Dialog` contains a single visible widget with `iFillEntireContainer` and
 *  - if the `Dialog` has no header, the child widget will be placed directly into int `sap.m.Page`
 *  without the ObjectPageLayout. This is important, because most of these widget will have their
 *  own layouts. Also, the ObjectPageLayout canno stretch it's content to full height and filling
 *  widgets must be stretched.
 *  - if the `Dialog` has a header, the child widget will be placed into a single section and
 *  subsection of the `sap.uxap.ObjectPageLayout` - this might look strange, but it seems the only
 *  way, to make the header look similar to multi-widget dialogs.
 * 
 * @method \exface\Core\Widgets\Dialog getWidget()
 *        
 * @author Andrej Kabachnik
 *        
 */
class UI5Dialog extends UI5Form
{
    const PREFILL_WITH_INPUT = 'input';
    const PREFILL_WITH_PREFILL = 'prefill';
    const PREFILL_WITH_CONTEXT = 'context';
    const PREFILL_WITH_ANY = 'any';
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Form::buildJsConstructor()
     */   
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {
        $widget = $this->getWidget();
                
        // If we need a prefill, we need to let the view model know this, so all the wigdget built
        // for this dialog can see, that a prefill will be done. This is especially important for
        // widget with lazy loading (like tables), that should postpone loading until the prefill data
        // is there.
        if ($this->needsPrefill()) {
            $this->getController()->addOnInitScript('this.getView().getModel("view").setProperty("/_prefill/pending", true);');
        }
        
        $this->registerSubmitOnEnter($oControllerJs);
        $triggerElement = $this->getFacade()->getElement($this->getDialogOpenButton());
        $triggerAction = $this->getDialogOpenAction();
        $controller = $this->getController();
        $controller->addOnShowViewScript("{$controller->getView()->buildJsViewGetter($this)}.getModel('view').setProperty('/_closed', false);");
        if ($this->isMaximized() === false) {
            $controller->addMethod('closeDialog', $this, 'oEvent', <<<JS

                try { 
                    var oViewModel = this.getView().getModel('view');
                    oViewModel.setProperty('/_prefill/current_data_hash', null); 
                    oViewModel.setProperty('/_closed', true);
                    sap.ui.getCore().byId('{$this->getFacade()->getElement($widget)->getId()}').close(); 
                } catch (e) { 
                    console.error('Could not close dialog: ' + e); 
                }
                {$triggerElement->buildJsTriggerActionEffects($triggerAction)}
JS
            );
            return $this->buildJsDialog();
        } else {
            $controller->addMethod('closeDialog', $this, 'oEvent', <<<JS

                var oViewModel = this.getView().getModel('view');
                oViewModel.setProperty('/_prefill/current_data_hash', null); 
                oViewModel.setProperty('/_closed', true);
                this.onNavBack(oEvent);
                {$triggerElement->buildJsTriggerActionEffects($triggerAction)}
JS
            );
            if ($this->isObjectPageLayout()) {
                return $this->buildJsPage($this->buildJsChildrenConstructors());
            } else {
                return $this->buildJsPage($this->buildJsObjectPageLayout($oControllerJs), $oControllerJs);
            }
        }        
    }
    
    /**
     * 
     * @return bool
     */
    protected function isObjectPageLayout () : bool
    {
        $widget = $this->getWidget();
        $visibleChildren = $widget->getWidgets(function(WidgetInterface $widget) {
            return $widget->isHidden() === false;
        });
        return $widget->hasHeader() === false && count($visibleChildren) === 1 && $visibleChildren[0] instanceof iFillEntireContainer && ! $visibleChildren[0] instanceof Tabs;
    }
    
    /**
     * Returns an inline JS snippet, that returns `true` if the dialog is currently closed and `false`/`undefined` otherwise.
     * 
     * @return string
     */
    public function buildJsCheckDialogClosed() : string
    {
        return "{$this->getController()->getView()->buildJsViewGetter($this)}.getModel('view').getProperty('/_closed')";
    }
    
    /**
     * Returns TRUE if the dialog is maximized (i.e. should be rendered as a page) and FALSE otherwise (i.e. rendering as dialog).
     * @return boolean
     */
    public function isMaximized()
    {
        $widget = $this->getWidget();
        $widget_setting = $widget->isMaximized();
        if (is_null($widget_setting)) {
            $width = $widget->getWidth();
            if ($width->isRelative()) {
                return false;
            }
            if ($width->isMax()) {
                return true;
            }
            if ($width->isPercentual() && $width->getValue() === '100%') {
                return true;
            }
            if ($action = $this->getDialogOpenAction()) {
                $action_setting = $this->getFacade()->getConfigMaximizeDialogByDefault($action);
                return $action_setting;
            }
            return false;
        }
        return $widget_setting;
    }
    
    protected function buildJsObjectPageLayout(string $oControllerJs = 'oController')
    {
        // useIconTabBar: true did not work for some reason as tables were not shown when
        // entering a tab for the first time - just at the second time. There was also no
        // difference between creating tables with new sap.ui.table.table or function(){ ... }()
        return <<<JS

        new sap.uxap.ObjectPageLayout({
            useIconTabBar: false,
            upperCaseAnchorBar: false,
            enableLazyLoading: false,
			{$this->buildJsHeader($oControllerJs)},
			sections: [
				{$this->buildJsObjectPageSections($oControllerJs)}
			]
		})

JS;
    }
				
    protected function buildJsPageHeaderContent(string $oControllerJs = 'oController') : string
    {
        return $this->buildJsHelpButtonConstructor($oControllerJs);
    }
        
    protected function buildJsHeader(string $oControllerJs = 'oController')
    {
        $widget = $this->getWidget();
        
        if ($widget->hasHeader()) {
            foreach ($widget->getHeader()->getChildren() as $child) {
                if ($child instanceof Image) {
                    $imageElement = $this->getFacade()->getElement($child);
                    $image = <<<JS

                    objectImageURI: {$imageElement->buildJsValue()},
			        objectImageShape: "Circle",
JS;
                    $child->setHidden(true);
                    break;
                }
            }
            
            
            $header_content = $this->getFacade()->getElement($widget->getHeader())->buildJsConstructor();
        }
        
        return <<<JS

            showTitleInHeaderContent: true,
            headerTitle:
				new sap.uxap.ObjectPageHeader({
					objectTitle: {$this->buildJsObjectTitle()},
				    showMarkers: false,
				    isObjectIconAlwaysVisible: false,
				    isObjectTitleAlwaysVisible: false,
				    isObjectSubtitleAlwaysVisible: false,
                    isActionAreaAlwaysVisible: false,
                    {$image}
					actions: [
						
					]
				}),
			headerContent:[
				{$header_content}
			]
JS;
    }
				
    protected function buildJsObjectTitle() : string
    {
        $widget = $this->getWidget();

        if ($widget->getHideCaption()) {
            return '""';
        }
        
        // If the dialog has a header and it has a fixed or prefilled title, take it as is.
        if ($widget->hasHeader()) {
            $header = $widget->getHeader();
            if ($header->getHideCaption() === true) {
                return '""';
            }
            if (! $header->isTitleBoundToAttribute()) {
                $caption = $header->getCaption() ? $header->getCaption() : $widget->getCaption();
                return '"' . $this->escapeJsTextValue($caption) . '"';
            }
        }
        
        // Otherwise try to find a good title
        $object = $widget->getMetaObject();
        if ($widget->hasHeader()) {
            $title_attr = $widget->getHeader()->getTitleAttribute();
        } elseif ($object->hasLabelAttribute()) {
            $title_attr = $object->getLabelAttribute();
        } elseif ($object->hasUidAttribute()) {
            $title_attr = $object->getUidAttribute();
        } else {
            // If no suitable attribute can be found, use the object name as static title
            return '"' . $this->escapeJsTextValue($widget->getCaption() ? $widget->getCaption() : $object->getName()) . '"';
        }
        
        // Once a title attribute is found, create an invisible display widget and
        // let it's element produce a binding.
        /* @var $titleElement \exface\UI5Facade\Facades\Elements\UI5Display */
        $titleWidget = WidgetFactory::createFromUxon($widget->getPage(), new UxonObject([
            'widget_type' => 'Display',
            'hidden' => true,
            'attribute_alias' => $title_attr->getAliasWithRelationPath()
        ]), $widget);
        $titleElement = $this->getFacade()->getElement($titleWidget);
        
        // If there is a caption binding in the view model, use it in the title element
        if ($header !== null) {
            $model = $this->getView()->getModel();
            if ($model->hasBinding($header, 'caption')) {
                $titleElement->setValueBindingPath($model->getBindingPath($header, 'caption'));
            }
        }
        
        return $titleElement->buildJsValue();
    }
    
    /**
     * 
     * @param MetaAttributeInterface $attribute
     * @return iHaveValue|NULL
     */
    protected function findWidgetByAttribute(MetaAttributeInterface $attribute) : ?iHaveValue
    {
        $widget = $this->getWidget();
        $found = null;
        
        $found = $widget->findChildrenByAttribute($attribute)[0];
        if ($found === null) {
            if ($widget->hasHeader()) {
                $found = $widget->getHeader()->findChildrenByAttribute($attribute)[0];
            }
        }
        
        return $found;
    }
				
    protected function buildJsDialog()
    {
        $widget = $this->getWidget();
        $icon = $widget->getIcon() ? 'icon: "' . $this->getIconSrc($widget->getIcon()) . '",' : '';
        
        $content = $this->buildJsLayoutConstructor();
        
        // If the dialog requires a prefill, we need to load the data once the dialog is opened.
        if ($this->needsPrefill()) {
            $prefill = $this->buildJsPrefillLoader('oView');
        } else {
            $prefill = 'if (typeof this._onPrefill === "function") {this._onPrefill();}';
        }
        
        // Finally, instantiate the dialog
        return <<<JS

        new sap.m.Dialog("{$this->getId()}", {
			{$icon}
            {$this->buildJsPropertyContentHeight()}
            {$this->buildJsPropertyContentWidth()}
            stretch: jQuery.device.is.phone,
            title: "{$this->getCaption()}",
			buttons : [ {$this->buildJsDialogButtons()} ],
			content : [ {$content} ],
            beforeOpen: function(oEvent) {
                var oDialog = oEvent.getSource();
                var oView = {$this->getController()->getView()->buildJsViewGetter($this)};
                {$prefill}
            }
		})
        {$this->buildJsPseudoEventHandlers()}
JS;
    }

    /**
     * 
     * @return string
     */
    protected function buildJsPropertyContentHeight() : string
    {
        $height = '';
        
        $dim = $this->getWidget()->getHeight();
        switch (true) {
            case $dim->isPercentual():
            case $dim->isFacadeSpecific() && strtolower($dim->getValue()) !== 'auto':
                $height = json_encode($dim->getValue());
                break;
            case $dim->isRelative():
                $height = json_encode(($dim->getValue() * $this->getHeightRelativeUnit()) . 'px');
                break;
            default:
                if ($this->isLargeDialog()) {
                    $height = '"70%"';
                }
        }
        
        return $height ? 'contentHeight: ' . $height . ',' : '';
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsPropertyContentWidth() : string
    {
        $width = '';
        
        $dim = $this->getWidget()->getWidth();
        switch (true) {
            case $dim->isPercentual():
            case $dim->isFacadeSpecific():
                $width = json_encode($dim->getValue());
                break;
            case $dim->isRelative():
                $width = json_encode(($dim->getValue() * $this->getHeightRelativeUnit()) . 'px');
                break;
            default:
                if ($this->isLargeDialog()) {
                    $width = '"65rem"'; // This is the size of a P13nDialog used for data configurator
                }
        }
        
        return $width ? 'contentWidth: ' . $width . ',' : '';
    }
    
    /**
     * Returns TRUE if the dialog is non-maximized, but should be "large" - e.g. to house a table.
     * 
     * @return bool
     */
    protected function isLargeDialog() : bool
    {
        $widget = $this->getWidget();
        $filterCallback = function(WidgetInterface $w) {
            return $w->isHidden() === false;
        };
        if ($widget->countWidgets($filterCallback) === 1) {
            $firstEl = $this->getFacade()->getElement($widget->getWidgetFirst($filterCallback));
            switch (true) {
                case $firstEl instanceof UI5Tabs:
                    // TODO Replace with interface (e.g. UI5PageControlInterface)
                case $firstEl instanceof UI5DataTable:
                case $firstEl instanceof UI5Chart:
                    return true;
            }
        }
        return false;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::getCaption()
     */
    protected function getCaption() : string
    {
        $caption = parent::getCaption();
        $objectName = $this->getWidget()->getMetaObject()->getName();
        return $caption === $objectName ? $caption : $caption . ': ' . $objectName;
    }
    
    /**
     * Returns the JS constructor for the sap.m.Page used as the top-level control when rendering
     * the dialog as an object page layout. 
     * 
     * The page will have a floating toolbar with all dialog buttons and a header with a title and
     * the close/back button.
     * 
     * @param string $content_js
     * @return string
     */
    protected function buildJsPage($content_js, string $oControllerJs = 'oController')
    {
        if ($this->needsPrefill()) {
            $prefillJs = $this->buildJsPrefillLoader('oView');
        } else {
            $prefillJs = 'this._onPrefill();';
        }
        $this->getController()->addOnRouteMatchedScript($prefillJs, 'loadPrefill');
        
        return <<<JS
        
        new sap.m.Page("{$this->getId()}", {
            title: "{$this->getCaption()}",
            showNavButton: true,
            navButtonPress: {$this->getController()->buildJsMethodCallFromView('closeDialog', $this, $oControllerJs)},
            content: [
                {$content_js}
            ],
            headerContent: [
                {$this->buildJsPageHeaderContent($oControllerJs)}
            ],
            footer: {$this->buildJsFloatingToolbar()}
        }).addStyleClass('exf-dialog-page')
        {$this->buildJsPseudoEventHandlers()}

JS;
    }
        
    /**
     * Returns TRUE if the dialog needs to be prefilled and FALSE otherwise.
     * 
     * @param string $prefillType
     * @return bool
     */
    protected function needsPrefill(string $prefillType = self::PREFILL_WITH_ANY) : bool
    {
        if (($action = $this->getDialogOpenAction()) instanceof iShowWidget) {
            switch (true) {
                case $action->getPrefillWithInputData() && ($prefillType === self::PREFILL_WITH_ANY || $prefillType === self::PREFILL_WITH_INPUT):
                    return true;
                case $action->getPrefillWithPrefillData() && ($prefillType === self::PREFILL_WITH_ANY || $prefillType === self::PREFILL_WITH_PREFILL):
                    return true;
            }
        }
        
        return false;
    }
          
    /**
     * Returns the JS code to load prefill data for the dialog. 
     * 
     * TODO will this work with with explicit prefill data too? 
     * 
     * @param string $oViewJs
     * @return string
     */
    protected function buildJsPrefillLoader(string $oViewJs = 'oView') : string
    {
        $widget = $this->getWidget();
        $triggerWidget = $this->getDialogOpenButton() ?? $widget;
        
        // FIXME #DataPreloader this will force the form to use any preload - regardless of the columns.
        if ($widget->isPreloadDataEnabled() === true) {
            $this->getController()->addOnDefineScript("exfPreloader.addPreload('{$this->getMetaObject()->getAliasWithNamespace()}');");
        } 
        
        // If the prefill cannot be fetched due to being offline, show the offline message view
        // (if the dialog is a page) or an error-popup (if the dialog is a regular dialog).
        if ($this->isMaximized()) {
            $showOfflineMsgJs = $oViewJs . '.getController().getRouter().getTargets().display("offline")';
        } else {
            $showOfflineMsgJs = <<<JS
            
            {$this->getController()->buildJsComponentGetter()}.showDialog('{$this->translate('WIDGET.DATATABLE.OFFLINE_ERROR_TITLE')}', '{$this->translate('WIDGET.DATATABLE.OFFLINE_ERROR')}', 'Error');
            {$this->buildJsCloseDialog()}
            
JS;
        }
        
        $action = ActionFactory::createFromString($this->getWorkbench(), 'exface.Core.ReadPrefill', $widget);
        
        switch (true) {
            case ! $this->needsPrefill(self::PREFILL_WITH_INPUT):
                $filterRequestParams = "if (data.data !== undefined) {delete data.data}";
                break;
            case ! $this->needsPrefill(self::PREFILL_WITH_PREFILL):
                $filterRequestParams = "if (data.prefill !== undefined) {delete data.prefill}";
                break;
            default: $filterRequestParams = '';  
        }
        
        $hideBusyJs = <<<JS

                {$this->buildJsBusyIconHide()}; 
                oViewModel.setProperty('/_prefill/pending', false);

JS;
        
        // FIXME use buildJsPrefillLoaderSuccess here somewere?
        
        return <<<JS

        (function(){
            {$this->buildJsBusyIconShow()}
            var oViewModel = {$oViewJs}.getModel('view');
            var oResultModel = {$oViewJs}.getModel();
            
            var oRouteParams = oViewModel.getProperty('/_route');
            var data = $.extend({}, {
                action: "exface.Core.ReadPrefill",
				resource: "{$widget->getPage()->getAliasWithNamespace()}",
				element: "{$triggerWidget->getId()}",
            }, oRouteParams.params);
            
            var oLastRouteString = oViewModel.getProperty('/_prefill/current_data_hash');
            var oCurrentRouteString = JSON.stringify(data);
            
            oViewModel.setProperty('/_prefill/pending', true);
            
            {$filterRequestParams}
            
            if (oLastRouteString === oCurrentRouteString) {
                {$this->buildJsBusyIconHide()}
                oViewModel.setProperty('/_prefill/pending', false);
                return;
            } else {
                {$oViewJs}.getModel().setData({});
                oViewModel.setProperty('/_prefill/current_data_hash', oCurrentRouteString);    
            }

            oViewModel.setProperty('/_prefill/started', true);
            oViewModel.setProperty('/_prefill/data', {});

            oResultModel.setData({});
            
            {$this->getServerAdapter()->buildJsServerRequest(
                $action,
                'oResultModel',
                'data',
                $hideBusyJs . " setTimeout(function(){ oViewModel.setProperty('/_prefill/data', JSON.parse(oResultModel.getJSON())) }, 0);",
                $hideBusyJs,
                $showOfflineMsgJs
            )}
        })();
			
JS;
    }
                        
    protected function buildJsPrefillLoaderSuccess(string $responseJs = 'response', string $oViewJs = 'oView', string $oViewModelJs = 'oViewModel') : string
    {
        // IMPORTANT: We must ensure, ther is no model data before replacing it with the prefill! 
        // Otherwise the model will not fire binding changes properly: InputComboTables will loose 
        // their values! But only reset the model if it has data, because the reset will trigger
        // an update of all bindings.
        return <<<JS

                    {$oViewModelJs}.setProperty('/_prefill/pending', false);
                    if (Object.keys(oDataModel.getData()).length !== 0) {
                        oDataModel.setData({});
                    }
                    if (Array.isArray({$responseJs}.rows)) {
                        if ({$responseJs}.rows.length === 1) {
                            oDataModel.setData({$responseJs}.rows[0]);
                        } else if ({$responseJs}.rows.length > 1) {
                            {$this->buildJsShowMessageError('"Error prefilling view with data: received " + {$responseJs}.rows.length + " rows instead of 0 or 1! Only the first data row is visible!"')};
                        }
                    }
                    {$this->buildJsBusyIconHide()}

JS;
    }
    
    /**
     * Returns JS constructors for page sections of the object page layout.
     * 
     * If the dialog contains tabs, page sections will be generated automatically for
     * every tab. Otherwise all widgets will be placed in a single page section.
     * 
     * @return string
     */
    protected function buildJsObjectPageSections(string $oControllerJs = 'oController')
    {
        $widget = $this->getWidget();
        $js = '';
        $nonTabHiddenWidgets = [];
        $nonTabChildrenWidgets = [];
        $hasSingleVisibleChild = false;
        
        foreach ($widget->getWidgets() as $child) {
            switch (true) {
                // Tabs are transformed to PageSections and all other widgets are collected and put into a separate page section
                // lager on.
                case $child instanceof Tabs:
                    foreach ($child->getTabs() as $tab) {
                        $js .= $this->buildJsObjectPageSectionFromTab($tab);
                    }
                    continue 2;
                // Most dialogs will have hidden system fields at top level. They need to be placed at the very end - otherwise
                // they break the SimpleForm generated for the non-tab PageSection. If they come first, the SimpleForm will allocate
                // space for them (even though not visible) and put the actual content way in the back.
                case $child->isHidden() === true:
                    $nonTabHiddenWidgets[] = $child;
                    break;
                // Large widgets need to be handled differently if the fill the entire dialog (i.e. being
                // the only visible widget). In this case, we don't need any layout - just the big filling
                // widget.
                case (! $this->getFacade()->getElement($child)->getNeedsContainerContentPadding()):
                    if ($widget->countWidgetsVisible() === 1) {
                        $hasSingleVisibleChild = true;
                    }
                    $nonTabChildrenWidgets[] = $child;
                    break;
                default:
                    $nonTabChildrenWidgets[] = $child;
            }
        }
        
        // Append hidden non-tab elements after the visible ones
        if (! empty($nonTabHiddenWidgets)) {
            $nonTabChildrenWidgets = array_merge($nonTabChildrenWidgets, $nonTabHiddenWidgets);
        }
        
        // Build an ObjectPageSection for the non-tab elements
        if (! empty($nonTabChildrenWidgets)) {
            $sectionContent = '';
            if ($hasSingleVisibleChild) {
                foreach ($nonTabChildrenWidgets as $child) {
                    $sectionContent .= $this->getFacade()->getElement($child)->buildJsConstructor() . ',';
                }
                $sectionContent = substr($sectionContent, 0, -1);                
                $sectionCssClass = 'sapUiNoContentPadding';
            } else {
                $sectionContent = $this->buildJsLayoutConstructor($nonTabChildrenWidgets);
                $sectionCssClass = 'sapUiTinyMarginTop';
            }
            $js .= $this->buildJsObjectPageSection($sectionContent, $sectionCssClass);
        }
        
        return $js;
    }
    
    /**
     * Returns the JS constructor for a general page section with no title and a single subsection.
     * 
     * The passed content is placed in the blocks aggregation of the subsection.
     * 
     * @param string $content_js
     * @return string
     */
    protected function buildJsObjectPageSection($content_js, $cssClass = null)
    {
        $suffix = $cssClass !== null ? '.addStyleClass("' . $cssClass . '")' : '';
        return <<<JS

                // BOF ObjectPageSection
                new sap.uxap.ObjectPageSection({
                    showTitle: false,
                    subSections: new sap.uxap.ObjectPageSubSection({
						blocks: [
                            {$content_js}
                        ]
					})
				}){$suffix}
                // EOF ObjectPageSection

JS;
    }
    
    /**
     * Returns the JS constructor for a page section representing the given tab widget.
     * 
     * @param Tab $tab
     * @return string
     */
    protected function buildJsObjectPageSectionFromTab(Tab $tab) 
    {
        if ($tab->isFilledBySingleWidget()) {
            $cssClass = 'sapUiNoContentPadding';
            // Some controls do not play nice with object page sections, so we need to set custom height
            // for them if it is not done by the user.
            $fillerWidget = $tab->getFillerWidget();
            switch (true) {
                case $fillerWidget instanceof Split:
                    if ($fillerWidget->getHeight()->isUndefined() || $fillerWidget->getHeight()->isMax()) {
                        $fillerWidget->setHeight('70vh');
                    }
            }
        }
        $tabElement = $this->getFacade()->getElement($tab);
        return <<<JS

                // BOF ObjectPageSection
                new sap.uxap.ObjectPageSection({
					title:"{$tab->getCaption()}",
                    titleUppercase: false,
					subSections: new sap.uxap.ObjectPageSubSection({
						blocks: [
                            {$tabElement->buildJsLayoutConstructor()}
                        ]
					})
				}).addStyleClass('{$cssClass}'),
                // EOF ObjectPageSection
                
JS;
    }
    
    /**
     * Returns the button constructors for the dialog buttons.
     * 
     * @return string
     */
    protected function buildJsDialogButtons()
    {
        $toolbarEl = $this->getFacade()->getElement($this->getWidget()->getToolbarMain());
        return $toolbarEl->buildJsConstructorsForLeftButtons() . $toolbarEl->buildJsConstructorsForRightButtons();
    }
    
    /**
     * returns javascript to close a dialog
     * 
     * @return string
     */
    public function buildJsCloseDialog() : string
    {
        return $this->getController()->buildJsMethodCallFromController('closeDialog', $this, '') . ';';
    }
    
    /**
     *
     * @return string
     */
    protected function buildJsLayoutFormFixes() : string
    {
        $fixContainerQueryJs = <<<JS
        
                    var oGrid = sap.ui.getCore().byId($("#{$this->getId()}-scrollCont > .sapUiForm > .sapUiFormResGrid > .sapUiRGLContainer > .sapUiRGLContainerCont > .sapUiRespGrid").attr("id"));
                    if (oGrid !== undefined) {
                        oGrid.setContainerQuery(false);
                    }
                    
JS;
        $this->addPseudoEventHandler('onAfterRendering', $fixContainerQueryJs);
        
        return '';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Container::hasButtonBack()
     */
    public function hasButtonBack() : bool
    {
        return true;
    }
    
    /**
     * Returns the button that opened the dialog or NULL if not available
     * 
     * @return iTriggerAction|NULL
     */
    protected function getDialogOpenButton() : ?iTriggerAction
    {
        if ($this->getWidget()->hasParent()) {
            $parent = $this->getWidget()->getParent();
            if ($parent instanceof iTriggerAction) {
                return $parent;
            }
        }
        return null;
    }
    
    /**
     * Returns the action that opened the dialog or NULL if not available
     * 
     * @return iShowDialog|NULL
     */
    protected function getDialogOpenAction() : ?iShowDialog
    {
        if ($button = $this->getDialogOpenButton()) {
            if ($button->hasAction()) {
                return $button->getAction();
            }
        }
        return null;
    }
}