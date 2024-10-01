<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Widgets\DialogButton;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JqueryButtonTrait;
use exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement;
use exface\Core\Widgets\Button;
use exface\Core\Interfaces\Actions\iShowDialog;
use exface\Core\Interfaces\Actions\iRunFacadeScript;
use exface\Core\DataTypes\StringDataType;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\CommonLogic\Constants\Colors;
use exface\Core\Exceptions\Facades\FacadeUnsupportedWidgetPropertyWarning;
use exface\Core\Actions\SendToWidget;
use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;
use exface\Core\Factories\UiPageFactory;
use exface\UI5Facade\Facades\UI5Facade;

/**
 * Generates sap.m.Button for Button widgets.
 * 
 * ## Custom facade options
 * 
 * - `custom_request_data_script` [string] - allows to process the javascript variable `requestData`
 * right before the action is actually performed. Either return FALSE to prevent the action, or return
 * FALSE and call the passed callback `fnRequest` explicitly at some time later.
 * 
 * Example:
 * 
 * ```
 * {
 *  "widget_type": "Button",
 *  "facade_options": {
 *      "exface.UI5Facade.UI5Facade": {
 *          "custom_request_data_script": "
 *              return (function(requestData, fnRequest){
 *              	var sMsg = '';
 *              	switch (true) {
 *              	    case requestData.rows.length === 0:
 *              	       sMsg = 'Really send empty data?';
 *              		case fVal < 0:
 *              	       return true; // Continue normally
 *              	}
 *              	if (sMsg !== '') {
 *              		var dialog = new sap.m.Dialog({
 *              			title: 'Wert außerhalb der Toleranzgrenze!',
 *              			type: 'Message',
 *              			state: 'Warning',
 *              			content: new sap.m.Text({
 *              				text: sMsg
 *              			}),
 *              			beginButton: new sap.m.Button({
 *              				text: 'OK',
 *              				press: function () {
 *              					fnRequest();
 *              					dialog.close();
 *              				}
 *              			}),
 *              			endButton: new sap.m.Button({
 *              				text: 'Cancel',
 *              				press: function () {
 *              					dialog.close();
 *              				}
 *              			}),
 *              			afterClose: function() {
 *              				dialog.destroy();
 *              			}
 *              		});
 *              		dialog.open();
 *              		return false;
 *              	}
 *              }(requestData, fnRequest) !== false);
 *          "
 *      }
 *  }
 * }
 * 
 * ```
 * 
 * @method Button getWidget()
 *
 * @author Andrej Kabachnik
 *        
 */
class UI5Button extends UI5AbstractElement
{
    use JqueryButtonTrait {
        buildJsNavigateToPage as buildJsNavigateToPageViaTrait;
        buildJsClickSendToWidget as buildJsClickSendToWidgetViaTrait;
        buildJsRequestDataCollector as buildJsRequestDataCollectorViaTrait;
    }
    
    private $button_type = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsConstructor()
     */
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {
        $this->registerExternalModules($this->getController());
        
        return <<<JS

        new sap.m.Button("{$this->getId()}", { 
            {$this->buildJsProperties()}
        })
        .addStyleClass("{$this->buildCssElementClass()}")
        {$this->buildJsPseudoEventHandlers()}

JS;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::registerExternalModules()
     */
    public function registerExternalModules(UI5ControllerInterface $controller) : UI5AbstractElement
    {
        // Get the java script required for the action itself
        $action = $this->getAction();
        if ($action) {
            // Actions with facade scripts may contain some helper functions or global variables.
            // Print the here first.
            if ($action && $action instanceof iRunFacadeScript) {
                $controller->addOnInitScript($action->buildScriptHelperFunctions($this->getFacade()));
                foreach ($action->getIncludes($this->getFacade()) as $includePath) {
                    if (mb_stripos($includePath, '.css') !== false) {
                        if (StringDataType::startsWith($includePath, '<link')) {
                            $matches = [];
                            preg_match('/(<link.*href=[\"\'])(.*css)([\"\'].[^>]*)/i', $includePath, $matches);
                            if ($matches[2]) {
                                $includePath = $matches[2];
                            }
                        }
                        $controller->addExternalCss($includePath);
                    } else {
                        $moduleName = str_replace([':', '-'], '', $includePath);
                        $moduleName = str_replace('/', '.', $moduleName);
                        $varName = StringDataType::convertCaseUnderscoreToPascal(str_replace(['/', '.', '-', ':'], '_', $includePath));
                        $controller->addExternalModule($moduleName, $includePath, $varName);
                    }
                }
            }
        }
        return parent::registerExternalModules($controller);
    }
    
    public function buildJsProperties()
    {
        $widget = $this->getWidget();
        $handler = $this->buildJsClickViewEventHandlerCall();
        $press = $handler !== '' ? 'press: ' . $handler . ',' : '';
        if ($widget->getShowIcon(true) && null !== $icon = $widget->getIcon()) {
            $icon = "icon: '{$this->getIconSrc($icon)}',";
        } else {
            $icon = '';
        }
        
        $options = <<<JS

    text: "{$this->getCaption()}",
    {$icon}
    {$this->buildJsPropertyButtonType()}
    {$press}
    {$this->buildJsPropertyTooltip()}
    {$this->buildJsPropertyVisibile()}

JS;
        return $options;
    }
    
    protected function buildJsPropertyButtonType() : string
    {
        $widget = $this->getWidget();
        $type = '';
        $layoutData = '';
        $defaultButtonType = 'Default';
        if ($this->button_type !== null) {
            return "type: '{$this->button_type}',";
        }
        switch ($widget->getVisibility()) {
            case EXF_WIDGET_VISIBILITY_PROMOTED:
                $type = 'type: "Emphasized",';
                $layoutData = 'layoutData: new sap.m.OverflowToolbarLayoutData({priority: "High"}),'; break;
            case EXF_WIDGET_VISIBILITY_OPTIONAL:
                $type = "type: '{$defaultButtonType}',";
                $layoutData = 'layoutData: new sap.m.OverflowToolbarLayoutData({priority: "AlwaysOverflow"}),'; break;
            case EXF_WIDGET_VISIBILITY_NORMAL:
            default:
                if ($color = $widget->getColor()) {
                    if (Colors::isSemantic($color) === true) {
                        if ($semType = $this->getColorSemanticMap()[$color]) {
                            $type = 'type: "' . $semType . '",';
                        } else {
                            $err = new FacadeUnsupportedWidgetPropertyWarning('Color "' . $color . '" not supported for button widget in UI5 - only semantic colors usable!');
                            $this->getWorkbench()->getLogger()->logException($err);
                            $type = "type: '{$defaultButtonType}',";
                        }
                    }
                } else {
                    $type = "type: '{$defaultButtonType}',";
                }
                
        }
        return $type . $layoutData;
    }
    
    /**
     * Returns the JS to call the press event handler from the view.
     * 
     * Typical output would be `[oController.onPressXXX, oController]`.
     * 
     * Use buildJsClickEventHandlerCall() to get the JS to use in a controller.
     * 
     * Use buildJsClickFunctionName() to the name of the handler within the controller (e.g.
     * just `onPressXXX`);
     * 
     * @see buildJsClickFunctionName()
     * @see buildJsClickEventHandlerCall()
     * 
     * @return string
     */
    public function buildJsClickViewEventHandlerCall(string $default = '') : string
    {
        $controller = $this->getController();
        $clickJs = $this->buildJsClickFunction();
        $controller->addOnEventScript($this, 'press', ($clickJs ? $clickJs : $default));
        return $this->getController()->buildJsEventHandler($this, 'press', true);        
    }
    
    /**
     * 
     * @param string $oControllerJsVar
     * @return string
     */
    public function buildJsClickEventHandlerCall(string $oControllerJsVar = null) : string
    {
        $methodName = $this->getController()->buildJsEventHandlerMethodName('press');
        if ($oControllerJsVar === null) {
            return $this->getController()->buildJsMethodCallFromController($methodName, $this, '');
        } else {
            return $this->getController()->buildJsMethodCallFromController($methodName, $this, '', $oControllerJsVar);
        }
        
    }
    
    /**
     * 
     * @return string
     */
    public function buildJsClickFunctionName()
    {
        $controller = $this->getController();
        return $controller->buildJsMethodName($controller->buildJsEventHandlerMethodName('press'), $this);
    }

    /**
     * Returns the JS to open the dialogs UI5 view.
     * 
     * If the dialog is maximized, it is simply a navigation to another view. For non-maximized
     * dialogs a `sap.m.Dialog` is opened and the view is appended to it manually. In this case
     * all navigation events are triggered manually too.
     * 
     * **NOTE:** if custom effects are set for the ShowDialog actions, they are handled by the
     * UI5Dialog itself as they need to be triggered when the dialog is closed!
     * 
     * @param ActionInterface $action
     * @param AbstractJqueryElement $input_element
     * @return string
     */
    protected function buildJsClickShowDialog(ActionInterface $action, string $jsRequestData) : string
    {
        $widget = $this->getWidget();
        $controller = $this->getController();
        
        /* @var $prefill_link \exface\Core\CommonLogic\WidgetLink */
        $prefill = '';
        if ($prefill_link = $action->getPrefillWithDataFromWidgetLink()) {
            if ($prefill_link->getTargetPageAlias() === null || $prefill_link->getPage()->is($widget->getPage())) {
                $prefill = ", prefill: " . $this->getFacade()->getElement($prefill_link->getTargetWidget())->buildJsDataGetter($action);
            }
        }
        
        $targetWidget = $action->getWidget();
        
        // Close the dialog on action success only if it navigates to a view.
        // If it opens a sap.m.Dialog, the parent dialog will be closed after
        // the user closes this new dialog - see JS attachAfterClose() below
        if ($this->opensDialogPage()) {
            $closeDialogJs = $this->buildJsCloseDialog();
        } else {
            $closeDialogJs = '';
        }

        $checkChangesJs = '';
        if ($this->isCheckForUnsavedChangesRequired()) {
            $checkChangesJs = <<<JS
                        if(true === {$this->getInputElement()->buildJsCheckForUnsavedChanges(true, 'fnAction')}) {
                            return;
                        }
JS;
        }
        
        // Build the AJAX request
        $js = <<<JS
                        {$this->buildJsBusyIconShow()}
                        var xhrSettings = {
							data: {
								data: {$jsRequestData}
								{$prefill}
							},
                            success: function(data, textStatus, jqXHR) {
                                {$closeDialogJs}                                                
                            },
                            complete: function() {
                                {$this->buildJsBusyIconHide()}
                            }
						};

JS;
        
        // Load the view and open the dialog or page
        if ($this->opensDialogPage()) {
            // If the dialog is actually a UI5 page, just navigate to the respecitve view.
            $js .= <<<JS
                        {$controller->buildJsControllerGetter($this)}.navTo('{$targetWidget->getPage()->getAliasWithNamespace()}', '{$this->getController()->getWebapp()->getWidgetIdForViewControllerName($targetWidget)}', xhrSettings);

JS;
        } else {
            // If it's a dialog, load the view and open the dialog after it has been loaded.
            
            // Note, that the promise resolves _before_ the content of the view is rendered,
            // so opening the dialog right away will make it appear blank. Instead, we use
            // setTimeout() to wait for the view to render completely.
            
            // Also make sure, the view model receives route parameters despite the fact, that
            // it was not actually handled by a router. This is importat as all kinds of on-show
            // handler will use route parameters (e.g. data, prefill, etc.) for their own needs.
            
            // All the onShow/Hide events need to be triggered manually too.
            // Basic idea taken from here: https://stackoverflow.com/questions/36792358/access-model-in-js-view-to-render-programmatically
            
            // If the dialog is not cacheable, we need to force the loader to fetch the view from
            // the server. Otherwise, it will be in the module cache of UI5 after being first loaded
            
            $forceLoadFromServerJs = $targetWidget->isCacheable() ? 'false' : 'true';
            $js .= <<<JS

                        var oController = {$controller->buildJsControllerGetter($this)};
                        var sViewName = oController.getViewName('{$targetWidget->getPage()->getAliasWithNamespace()}', '{$targetWidget->getId()}'); 
                        var sViewId = oController.getViewId(sViewName);
                        var oComponent = oController.getOwnerComponent();
                        
                        var jqXHR = oController._loadView(sViewName, function(){ 
                            var oView = sap.ui.getCore().byId(sViewId);
                            var oParentView = {$controller->getView()->buildJsViewGetter($this)};
                            var oApp = sap.ui.getCore().byId('{$controller->getWebapp()->getName()}');
                            var oNavInfoOpen = {
                				from: oParentView || null,
                				fromId: (oParentView !== undefined ? oParentView.getId() : null),
                				to: oView || null,
                				toId: (oView ? oView.getId() : null),
                				firstTime: true,
                				isTo: false,
                				isBack: false,
                				isBackToTop: false,
                				isBackToPage: false,
                				direction: "initial"
                			};
                            var oNavInfoClose = {
                				from: oView || null,
                				fromId: (oView ? oView.getId() : null),
                				to: oParentView || null,
                				toId: (oParentView !== undefined ? oParentView.getId() : null),
                				firstTime: true,
                				isTo: false,
                				isBack: false,
                				isBackToTop: false,
                				isBackToPage: true,
                				direction: "initial"
                			};
                     
                            if (oView === undefined) {
                                oComponent.runAsOwner(function(){
                                    return sap.ui.core.mvc.JSView.create({
                                        id: sViewId,
                                        viewName: "{$controller->getWebapp()->getViewName($targetWidget)}"
                                    }).then(function(oView){
                                        oNavInfoOpen.to = oView;
                                        oNavInfoOpen.toId = oView.getId();
                                        oNavInfoClose.from = oView;
                                        oNavInfoClose.fromId = oView.getId();

                                        if (oParentView !== undefined) {
                                            oParentView.addDependent(oView);
                                        }
                                        
                                        if (oView.getModel('view') === undefined) {
                                            oView.setModel(new sap.ui.model.json.JSONModel(), 'view');    
                                        }
                                        oView.getModel('view').setProperty("/_route", {params: xhrSettings.data});

                                        setTimeout(function() {
                                            var oDialog = oView.getContent()[0];
                                            if (oDialog instanceof sap.m.Dialog) {
                                                // Attach replacements for navigation events
                                                oDialog
                                                .attachBeforeOpen(function() {
                                        			var oEvent = jQuery.Event("BeforeShow", oNavInfoOpen);
                                        			oEvent.srcControl = oApp;
                                        			oEvent.data = {};
                                        			oEvent.backData = {};
                                        			oView._handleEvent(oEvent);  
                                                })
                                                .attachAfterOpen(function() {
                                        			var oEvent = jQuery.Event("AfterShow", oNavInfoOpen);
                                        			oEvent.srcControl = oApp;
                                        			oEvent.data = {};
                                        			oEvent.backData = {};
                                        			oView._handleEvent(oEvent);
                                                })
                                                .attachBeforeClose(function() {
                                        			var oEvent = jQuery.Event("BeforeHide", oNavInfoClose);
                                        			oEvent.srcControl = oApp;
                                        			oEvent.data = {};
                                        			oEvent.backData = {};
                                        			oView._handleEvent(oEvent);
                                                })
                                                .attachAfterClose(function() {
                                                    var oEvent = jQuery.Event("AfterHide", oNavInfoClose);
                                        			oEvent.srcControl = oApp;
                                        			oEvent.data = {};
                                        			oEvent.backData = {};
                                        			oView._handleEvent(oEvent);
                                                    {$this->buildJsCloseDialog()}
                                                })
                                                .addEventDelegate({
                                                    "onBeforeRendering": function () {
                                                        oView.fireBeforeRendering();
                                                    },
                                                    "onAfterRendering": function () {
                                                        oView.fireAfterRendering();
                                                    }
                                                }, oController); /* Make oController be the "this" inside onBeforeRendering, etc. - but is this really correct?*/
                                                oDialog.open();
                                            } else {
                                                if (oDialog instanceof sap.m.Page || oDialog instanceof sap.m.MessagePage) {
                                                    oDialog.setShowNavButton(false);
                                                }
                                                {$this->buildJsOpenDialogForUnexpectedView('oDialog')};
                                            }
                                        }, 0);
                                    });
                                });
                            } else {
                                oView.getModel('view').setProperty("/_route", {params: xhrSettings.data});
                                        
                                var oDialog = oView.getContent()[0];
                                if (oDialog instanceof sap.m.Dialog) {
                                    oDialog.open();
                                } else {
                                    {$this->buildJsOpenDialogForUnexpectedView('oDialog')};
                                }
                            }
                        }, xhrSettings, $forceLoadFromServerJs);
                        
JS;
        }
        
        // Place the code for the action in a callable. Perform confirmation checks, etc. and
        // call the code at the very end giving the checks the possibility to cancel the whole
        // thing.
        // TODO #confirmation move this to JqueryButtonTrait!
        return <<<JS
                    
                    var fnAction = function() {
                        $js
                    };

                    {$checkChangesJs}

                    fnAction();

JS;
    }
    
    protected function buildJsOpenDialogForUnexpectedView(string $oViewContent) : string
    {
        return <<<JS

                                                var oWrapper = new sap.m.Dialog({
                                                    stretch: true,
                                                    verticalScrolling: false,
                                                    title: {$this->escapeString($this->getCaption())},
                                        			content: [ {$oViewContent} ],
                                                    buttons: [
                                                        new sap.m.Button({
                                                            icon: "{$this->getIconSrc(Icons::CLOSE)}",
                                                            text: "{$this->getWorkbench()->getCoreApp()->getTranslator()->translate('WIDGET.DIALOG.CLOSE_BUTTON_CAPTION')}",
                                                            press: function() {oWrapper.close();},
                                                        })
                                                    ]
                                        		}).open();

JS;
    }
    
    /**
     * 
     * {@inheritdoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\JqueryButtonTrait::buildJsNavigateToPage
     */
    protected function buildJsNavigateToPage(string $pageSelector, string $urlParams = '', AbstractJqueryElement $inputElement, bool $newWindow = false) : string
    {
        
        $targetPage = UiPageFactory::createFromModel($this->getWorkbench(), $pageSelector);
        $targetFacade = $targetPage->getFacade();
        $currentFacade = UiPageFactory::createFromModel($this->getWorkbench(), $this->getPageId())->getFacade();
        
        // facade in target page is set but not a UI5Facade
        $facadeSetNotUI5 = ! ($currentFacade instanceof UI5Facade) || ($targetPage->isFacadeSet() && ! ($targetFacade instanceof UI5Facade));        
        // facade in target page is UI5 but with different density or theme
        $facadeDifferentUI5 = ($targetFacade instanceof UI5Facade) && ($currentFacade instanceof UI5Facade) && (($targetFacade->getContentDensity() !== $currentFacade->getContentDensity()) || $targetFacade->getTheme() !== $currentFacade->getTheme());
        
        if ($newWindow === true || $facadeSetNotUI5 || $facadeDifferentUI5) {
            return <<<JS
            
                        {$this->buildJsNavigateToPageViaTrait($pageSelector, $urlParams, $inputElement, $newWindow)}
                        {$inputElement->buildJsBusyIconHide()}
JS;
        }
        
        return <<<JS
						var sUrlParams = '{$urlParams}';
                        var oUrlParams = {};
                        var vars = sUrlParams.split('&');
                    	for (var i = 0; i < vars.length; i++) {
                    		var pair = vars[i].split('=');
                            if (pair[0]) {
                                var val = decodeURIComponent(pair[1]);
                                if (val.substring(0, 1) === '{') {
                                    try {
                                        val = JSON.parse(val);
                                    } catch (error) {
                                        // Do nothing, val will remain a string
                                    }
                                }
            		            oUrlParams[pair[0]] = val;
                            }
                    	} 
                        this.navTo("{$pageSelector}", '', {
                            data: oUrlParams,
                            success: function(){ 
                                {$inputElement->buildJsBusyIconHide()} 
                            },
                            error: function(){ 
                                {$inputElement->buildJsBusyIconHide()} 
                            }
                        });

JS;
    }

    /**
     * Returns javascript code with global variables and functions needed for certain button types
     */
    protected function buildJsGlobals()
    {
        $output = '';
        /*
         * Commented out because moved to generate_js()
         * // If the button reacts to any hotkey, we need to declare a global variable to collect keys pressed
         * if ($this->getWidget()->getHotkey() == 'any'){
         * $output .= 'var exfHotkeys = [];';
         * }
         */
        return $output;
    }
    
    protected function buildJsCloseDialog(bool $checkChanges = null) : string
    {
        $widget = $this->getWidget();
        if (($widget instanceof DialogButton) && $widget->getCloseDialogAfterActionSucceeds()) {
            $checkChanges = $checkChanges ?? $this->isCheckForUnsavedChangesRequired();
            return $this->getFacade()->getElement($widget->getDialog())->buildJsCloseDialog($checkChanges);
        }
        return '';
    }
    
    protected function opensDialogPage()
    {
        $action = $this->getAction();
        
        if ($action instanceof iShowDialog) {
            return $this->getFacade()->getElement($action->getDialogWidget())->isMaximized();
        } 
        
        return false;
    }
   
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsBusyIconShow()
     */
    public function buildJsBusyIconShow($global = false)
    {
        return parent::buildJsBusyIconShow(true);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsBusyIconHide()
     */
    public function buildJsBusyIconHide($global = false)
    {
        return parent::buildJsBusyIconHide(true);
    }
    
    /**
     * 
     * @param ActionInterface $action
     * @param string $jsRequestData
     * @param string $jsOnSuccess
     * @return string
     */
    protected function buildJsClickCallServerAction(ActionInterface $action, string $jsRequestData, string $jsOnSuccess = '') : string
    {
        $widget = $this->getWidget();
        $input_element = $this->getInputElement();
        // Before triggering action effects, double check, that the input element is stil there!
        // This is important because it may be gone after a dialog was closed, which would result
        // in JS errors. Checking input element and not button element here to make it work with
        // actions without a rendered button - e.g. drop actions
        $onModelLoadedJs = <<<JS

								
								{$this->buildJsBusyIconHide()}
                                if (sap.ui.getCore().byId("{$input_element->getId()}") !== undefined) {
                                    {$this->buildJsCloseDialog(false)}
								    {$this->buildJsTriggerActionEffects($action)}
                                }
		                       	{$this->buildJsBusyIconHide()}

                                if (oResultModel.getProperty('/success') !== undefined || oResultModel.getProperty('/undoURL')){
		                       		{$this->buildJsShowMessageSuccess("oResultModel.getProperty('/success') + (response.undoable ? ' <a href=\"" . $this->buildJsUndoUrl($action) . "\" style=\"display:block; float:right;\">UNDO</a>' : '')")}
									/* TODO redirects do not work in UI5 that easily. Additionally server adapters don't return any response variable.*/
                                    var sRedirect;
                                    if((sRedirect = oResultModel.getProperty('/redirect')) !== undefined){
                                        switch (true) {
										    case sRedirect.indexOf('target=_blank') !== -1:
											    window.open(sRedirect.replace('target=_blank',''), '_newtab');
                                                break;
                                            case sRedirect === '':
                                                {$this->getFacade()->getElement($widget->getPage()->getWidgetRoot())->buildJsBusyIconShow()}
                                                window.location.reload();
                                                break;
                                            default: 
                                                {$this->getFacade()->getElement($widget->getPage()->getWidgetRoot())->buildJsBusyIconShow()}
                                                window.location.href = sRedirect;
										}
                   					}
                                    
                                    if(oResultModel.getProperty('/download')){
                                        // Workaround to force the browser to download even if it is a text file!
                                        var a = document.createElement('A');
                                        var sDlPath = oResultModel.getProperty('/download')
                                        a.href = sDlPath;
                                        a.download = sDlPath.substr(sDlPath.lastIndexOf('/') + 1);
                                        document.body.appendChild(a);
                                        a.click();
                                        document.body.removeChild(a);
                   					}
                                    {$jsOnSuccess}
								}
JS;
                                    
        $onErrorJs = <<<JS

                            {$this->buildJsBusyIconHide()}
                            {$this->buildJsOnErrorScript()}
JS;
		                       		
        if ($facadeOptUxon = $this->getWidget()->getFacadeOptions($this->getFacade())) {
            if ($facadeOptUxon->hasProperty('custom_request_data_script')) {
                $customScript .= $facadeOptUxon->getProperty('custom_request_data_script');
            }
        }
        return <<<JS
                            
                var fnRequest = function() {
                    if ({$input_element->buildJsValidator()}) {
                        {$this->buildJsCheckRequestDataSize($jsRequestData, $this->getAjaxPostSizeMax())}
                        {$this->buildJsBusyIconShow()}
                        var oResultModel = new sap.ui.model.json.JSONModel();
                        var params = {
    							{$this->buildJsRequestCommonParams($widget, $action)}
    							data: {$jsRequestData}
    					}
                        {$this->getServerAdapter()->buildJsServerRequest($action, 'oResultModel', 'params', $onModelLoadedJs, $onErrorJs)}
    				} else {
    					{$input_element->buildJsValidationError()}
    				}
                };
                {$customScript}

                fnRequest();
				
JS;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsSetDisabled()
     */
    public function buildJsSetDisabled(bool $trueOrFalse) : string
    {
        if ($trueOrFalse === true) {
            return "sap.ui.getCore().byId('{$this->getId()}')?.setEnabled(false);";
        } else {
            return "sap.ui.getCore().byId('{$this->getId()}')?.setEnabled(true);";
        }
    }
    
    protected function getColorSemanticMap() : array
    {
        $semCols = [];
        foreach (Colors::getSemanticColors() as $semCol) {
            switch ($semCol) {
                case Colors::SEMANTIC_ERROR: $btnType = 'Reject'; break;
                case Colors::SEMANTIC_WARNING: $btnType = 'Reject'; break;
                case Colors::SEMANTIC_OK: $btnType = 'Accept'; break;
            }
            $semCols[$semCol] = $btnType;
        }
        return $semCols;
    }
    
    /**
     * 
     * @see JqueryButtonTrait::buildJsClickSendToWidget()
     */
    protected function buildJsClickSendToWidget(SendToWidget $action, string $jsRequestData) : string
    {
        $this->getFacade()->createController($this->getFacade()->getElement($this->getWidget()->getPage()->getWidgetRoot()));
        return $this->buildJsClickSendToWidgetViaTrait($action, $jsRequestData);
    }
    
    /**
     * 
     * @see JqueryButtonTrait::buildJsRequestDataCollector()
     */
    protected function buildJsRequestDataCollector(ActionInterface $action, AbstractJqueryElement $input_element, string $jsVariable = 'requestData')
    {
        $js = $this->buildJsRequestDataCollectorViaTrait($action, $input_element, $jsVariable);
        
        /*if ($facadeOptUxon = $this->getWidget()->getFacadeOptions($this->getFacade())) {
            if ($facadeOptUxon->hasProperty('custom_request_data_script')) {
                $js .= $facadeOptUxon->getProperty('custom_request_data_script');
            }
        }*/
        
        return $js;
    }
    
    /**
     *
     * @param string $functionName
     * @return string
     */
    public function buildJsCallFunction(string $functionName = null, array $parameters = []) : string
    {
        switch (true) {
            case $functionName === null:
            case $functionName === Button::FUNCTION_PRESS:
                return $this->buildJsClickEventHandlerCall();
            case $functionName === Button::FUNCTION_FOCUS:
                return "sap.ui.getCore().byId('{$this->getId()}').focus()";
        }
        return parent::buildJsCallFunction($functionName, $parameters);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildCssElementClass()
     */
    public function buildCssElementClass()
    {
        $cls = parent::buildCssElementClass();
        if ($this->getWidget()->getIconSet() === 'svg') {
            $cls .= ' exf-svg-icon';
        }
        return $cls;
    }
    
    public function setUI5ButtonType(string $button_type) : UI5Button
    {
        $this->button_type = $button_type;
        return $this;
    }
}