<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\Interfaces\Widgets\iHaveValue;
use exface\Core\DataTypes\StringDataType;
use exface\UI5Facade\Exceptions\UI5ControllerNotInitializedException;
use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;
use exface\Core\Exceptions\LogicException;
use exface\UI5Facade\Facades\Interfaces\UI5ServerAdapterInterface;
use exface\UI5Facade\Facades\Interfaces\UI5ViewInterface;
use exface\Core\Interfaces\Widgets\iHaveIcon;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JsConditionalPropertyTrait;
use exface\Core\Facades\AbstractAjaxFacade\Interfaces\AjaxFacadeElementInterface;
use exface\UI5Facade\Facades\Elements\ServerAdapters\OfflineServerAdapter;
use exface\Core\Exceptions\Facades\FacadeRuntimeError;
use exface\UI5Facade\Events\OnControllerSetEvent;
use exface\Core\DataTypes\SvgDataType;
use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;

/**
 *
 * @method \exface\UI5Facade\Facades\UI5Facade getFacade()
 *        
 * @author Andrej Kabachnik
 *        
 */
abstract class UI5AbstractElement extends AbstractJqueryElement
{
    use JsConditionalPropertyTrait;
    
    const EVENT_NAME_CHANGE = 'change';
    
    const EVENT_NAME_REFRESH = 'refresh';
    
    private $jsVarName = null;
    
    private $useWidgetId = true;
    
    private $controller = null;
    
    private $layoutData = null;
    
    private array $listenersForControllerSet = [];
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::init()
     */
    protected function init()
    {
        parent::init();
        $this->addOnControllerSet([$this, 'registerConditionalProperties']);
        
        // Register tour steps if there are any
        foreach ($this->getWidget()->getTourSteps() as $tourStep) {
            $this->getFacade()->getTourDriver($this->getWidget())->registerStep($tourStep);
        }
    }
    
    /**
     * 
     * @var array [ event_name => [code, code, ...] ]
     */
    private $pseudo_events = [];
    
    /**
     * Returns the JS constructor for this element (without the semicolon!): e.g. "new sap.m.Button()" etc.
     * 
     * For complex widgets (e.g. requireing a model, init-scripts, etc.) you can use the following approaches:
     * - create custom controller methods via $this->getController()->add...
     * - add code to the onInit-method of the controller via $this->getController()->addOnInitScript()  
     * - use an immediately invoked function expression like "function(){...}()" as constructor (not recommended!)
     * 
     * @see getController()
     *
     * @return string
     */
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {
        return '';
    }
    
    public function buildJsProperties()
    {
        return <<<JS

        {$this->buildJsPropertyVisibile()}

JS;
    }

    public function buildJsInlineEditorInit()
    {
        return '';
    }

    public function buildJsBusyIconShow($global = false)
    {
        if ($global) {
            return 'sap.ui.core.BusyIndicator.show(0);';
        } else {
            return 'sap.ui.getCore().byId("' . $this->getId() . '")?.setBusyIndicatorDelay(0).setBusy(true);';
        }
    }

    public function buildJsBusyIconHide($global = false)
    {
        if ($global) {
            return 'sap.ui.core.BusyIndicator.hide();';
        } else {
            return 'sap.ui.getCore().byId("' . $this->getId() . '")?.setBusy(false);';
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsShowMessageError()
     */
    public function buildJsShowMessageError($message_body_js, $title = null)
    {
        $title = ($title ? $title : '"' . $this->translate('MESSAGE.ERROR_TITLE') . '"');
        return <<<JS
                var dialog = new sap.m.Dialog({
    				title: {$title},
    				type: 'Message',
    				state: 'Error',
                    contentWidth: '400px',
    				content: new sap.m.Text({
    					text: {$message_body_js}
    				}),
    				beginButton: new sap.m.Button({
    					text: 'OK',
                        type: 'Emphasized',
    					press: function () {
    						dialog.close();
    					}
    				}),
    				afterClose: function() {
    					dialog.destroy();
    				}
    			});
    
    			dialog.open();
JS;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsShowError()
     */
    public function buildJsShowError($message_body_js, $title_js = null)
    {
        $title_js = $title_js ? $title_js : '"' . $this->translate('MESSAGE.ERROR_TITLE') . '"';
        return $this->getController()->buildJsComponentGetter() . ".showErrorDialog({$message_body_js}, {$title_js});";
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsShowMessageSuccess()
     */
    public function buildJsShowMessageSuccess($message_body_js, $title = null)
    {
        return <<<JS

        (function(sBody, sTitle){
            if (sBody.length > 200) {
               {$this->getController()->buildJsComponentGetter()}.showHtmlInDialog(sTitle, '<pre style="margin: 1rem; white-space: break-spaces">' + sBody + '</pre>', sap.ui.core.ValueState.Success);
            } else {
                sap.m.MessageToast.show(function(){
                    var tmp = document.createElement("DIV");
                    tmp.innerHTML = sBody;
                    return tmp.textContent || tmp.innerText || "";
                }());
            }
        })({$message_body_js}, {$this->escapeString($title)});
JS;
    }
    
    /**
     * Returns the SAP icon URI (e.g. "sap-icon://edit") for the given icon name
     * 
     * @param string $icon_name
     * @return string
     */
    protected function getIconSrc($icon_name)
    {
        $widget = $this->getWidget();
        if ($widget instanceof iHaveIcon) {
            $iconSet = $widget->getIconSet();
        }
        
        switch (true) {
            // Icon properties of some controls like sap.m.Button accept data-URLs for SVG
            case Icons::isIconSetSVG($iconSet) === true:
                $path = 'data:image/svg+xml;utf8,';
                try {
                    $xml = SvgDataType::cast($icon_name);
                } catch (DataTypeCastingError $e) {
                    throw new WidgetPropertyInvalidValueError($widget, 'Invalid SVG icon for widget "' . $widget->getWidgetType() . '"!', null, $e);
                }
                $icon_name = rawurlencode($xml);
                break;
            case Icons::isDefined($icon_name) === true:
            case $iconSet === 'fa':
                $path = 'sap-icon://font-awesome/';
                break;
            case StringDataType::startsWith($icon_name, 'sap-icon://', false):
                $path = '';
                break;
            case StringDataType::startsWith($iconSet, 'sap-icon://', false):
                $path = $iconSet;
                break;
        }
        return $path . $icon_name;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildCssIconClass($icon)
     */
    public function buildCssIconClass(?string $icon) : string
    {
        return $icon ? $this->getIconSrc($icon) : '';
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsValueGetter()
     */
    public function buildJsValueGetter()
    {
        $widget = $this->getWidget();
        if ($widget instanceof iHaveValue) {
            return "sap.ui.getCore().byId('{$this->getId()}').{$this->buildJsValueGetterMethod()}";
        } else {
            return '""';
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsValueGetterMethod()
     */
    public function buildJsValueGetterMethod()
    {
        return "getValue()";
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsValueSetter()
     */
    public function buildJsValueSetter($valueJs)
    {
        $widget = $this->getWidget();
        if ($widget instanceof iHaveValue) {
            return "sap.ui.getCore().byId('{$this->getId()}').{$this->buildJsValueSetterMethod($valueJs)}";
        } else {
            return '""';
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsValueSetterMethod()
     */
    public function buildJsValueSetterMethod($valueJs)
    {
        return "setValue(" . $valueJs . ")";
    }
        
    /**
     * Escapes a string to be used in a UI5 property
     * 
     * This is similar to `escapeString()`, but includes some UI5 specifics like escaping
     * curly braces, that would otherwise be treated as bindings.
     * 
     * Rule of thumb:
     * - use `escapeJsTextValue()` to escape values in UI5 view properties
     * - use `escapeString()` to escape strings in regular javascript
     * 
     * @param mixed $text
     * @return string
     */
    protected function escapeJsTextValue($text, bool $escapeCurlyBraces = true)
    {
        if ($text === null || $text === '') {
            return $text;
        }
        
        if ($escapeCurlyBraces) {
            $text = str_replace(['{', '}'], ['\\{', '\\}'], $text);
        }
        
        // json_encode() escapes " and ' really well
        $escaped = json_encode(str_replace(['\u'], ['&#92;u'], $text), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        // however, the result is enclosed in double quotes if it's a string. If so, we
        // need to remove the first an last character (the quotes). Note: trim() won't
        // work here because if the $text was already beginning or ending with " it will
        // get trimmed off too!
        if (mb_substr($escaped, 0, 1) === '"') {
            $escaped = substr($escaped, 1, -1);   
        }
        
        return $escaped;
    }
    
    /**
     * Returns "visible: false," if the element is not visible (e.g. widget has visibility=hidden).
     * 
     * NOTE: The returned string is either empty or ends with a comma
     * 
     * @return string
     */
    protected function buildJsPropertyVisibile()
    {
        if (! $this->isVisible()) {
            return 'visible: false, ';
        }
        return '';
    }
    
    /**
     * Returns TRUE if the element is visible and FALSE otherwise
     * @return boolean
     */
    protected function isVisible() : bool
    {
        return ! $this->getWidget()->isHidden();
    }
    
    /**
     * Retruns the JS code, that hides or shows the control depending on the $visible argument
     * 
     * Additionally the DOM element MUST fire the custom `visibleChange` event, so other controls
     * can react to visibility changes. UI5 itself does not seem to provide a hide/show event.
     * 
     * @param bool $hidden
     * @param string $elementId
     * @return string
     */
    protected function buildJsSetHidden(bool $hidden, string $elementId = null) : string
    {
        $bVisibleJs = ($hidden ? 'false' : 'true');
        $elementId = $elementId ?? $this->getId();
        return <<<JS
(function(bVisible, oCtrl){
    if (! oCtrl || bVisible === oCtrl.getVisible()) return;
    oCtrl.setVisible(bVisible).$()?.trigger('visibleChange', [{visible: bVisible}]);
})($bVisibleJs, sap.ui.getCore().byId('{$elementId}'))
JS;
    }
    
    /**
     * Returns the JS code adding pseudo event handlers to a control: i.e. .addEventDelegate(...).
     * 
     * NOTE: the string is either empty or starts with a leading dot and ends with a closing
     * brace (no semicolon!)
     * 
     * @see addPseudoEventHandler()
     * 
     * @return string
     */
    protected function buildJsPseudoEventHandlers()
    {
        $js = '';
        foreach ($this->pseudo_events as $event => $code_array) {
            $code = implode("\n", $code_array);
            $js .= <<<JS
            
            {$event}: function(oEvent) {
                {$code}
            },
            
JS;
        }
        
        // .addEventdelegate actually takes a second argument, that will be the `this` in the handler
        // we can't use that here however, as the control is not initialized yet and thus cannot be
        // found by id :(
        if ($js) {
            $js = <<<JS
            
        .addEventDelegate({
            {$js}
        })
        
JS;
        }
        
        return $js;
    }
    
    /**
     * Registers the given JS code to be executed on a specified pseudo event for this control: `.addEventDelegate(...)`.
     * 
     * Note: the event fired will be available via the oEvent javascript variable.
     * 
     * Example: UI5Input::addPseudoEventHandler('onsapenter', 'console.log("Enter pressed:", oEvent)')
     * 
     * @link https://openui5.hana.ondemand.com/#/api/jQuery.sap.PseudoEvents
     * 
     * @param string $event_name
     * @param string $js
     * @return \exface\UI5Facade\Facades\Elements\UI5AbstractElement
     */
    public function addPseudoEventHandler($event_name, $js)
    {
        $this->pseudo_events[$event_name][] = $js;
        return $this;
    }
    
    protected function buildJsPropertyTooltip()
    {
        $widget = $this->getWidget();
        return 'tooltip: "' . $this->escapeJsTextValue($widget->getHint() ? $widget->getHint() : $widget->getCaption()) . '",';
    }
    
    public function setUseWidgetId($true_or_false)
    {
        $this->useWidgetId = $true_or_false;
        return $this;
    }
    
    public function getUseWidgetId()
    {
        return $this->useWidgetId;
    }
    
    /**
     * {@inheritDoc}
     * 
     * Since pages are loaded asynchronously in UI5, we need to make sure, the element ids include
     * page ids to avoid conflicts.
     * 
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::getId()
     */
    public function getId()
    {
        if ($this->getUseWidgetId() === false) {
            return '';
        }
        
        return substr($this->getWidget()->getPage()->getUid(), 1) . '__' . parent::getId();
    }
    
    /**
     * UI5-Elements do not have a general buildJs() method, because there is no place in the controller
     * where it's global variables and methods can be defined in "regular" JS syntax. E.g. instead of
     * "function ... () {}" a controller method must be defined as "...: function(){}", etc.
     * 
     * Making this method final makes sure, no element makes use of it unintentionally (e.g.
     * via trait).
     * 
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJs()
     */
    public final function buildJs()
    {
        return '';
    }
    
    /**
     * UI5-Elements do not produce HTML, but rather views in JS/XML.
     * 
     * Making this method final makes sure, no element makes use of it unintentionally (e.g.
     * via trait). 
     * 
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildHtml()
     */
    public final function buildHtml()
    {
        return '';
    }
    
    /**
     * Executes given PHP code once the element has a controller.
     * 
     * If a UI5 controller is already assigned, the code is executed immediately. Otherwise, it is
     * postponed till a controller is assigned to this element or one of its parents.
     * 
     * This method is mainly useful for `Element::init()` logic, that requires a UI5 controller.
     * The `init()` method is often called before a controller was initialized, so it may not yet
     * be accessible. 
     * 
     * @param callable $function
     * @return UI5AbstractElement
     */
    public function addOnControllerSet(callable $function) : UI5AbstractElement
    {
        try {
            $controller = $this->getController();
            $function($controller);
        } catch (ExceptionInterface $e) {
            if (! ($e instanceof UI5ControllerNotInitializedException || null !== $e->findPrevious(UI5ControllerNotInitializedException::class))) {
                throw $e;
            }
            if (in_array($function, $this->listenersForControllerSet, true) === true) {
                return $this;
            }
            $this->listenersForControllerSet[] = $function;
            $this->getWorkbench()->eventManager()->addListener(OnControllerSetEvent::getEventName(), function(OnControllerSetEvent $event) use ($function) {
                $thisWidget = $this->getWidget();
                $eventWidget = $event->getWidget();
                $controller = $event->getController();
                $parent = $thisWidget;
                while (null !== $parent) {
                    if ($parent === $eventWidget) {
                        $function($controller);
                        return;
                    }
                    $parent = $parent->getParent();
                }
            });
        }
        return $this;
    }

    /**
     * 
     * @throws FacadeRuntimeError
     * @return UI5ControllerInterface
     */
    public function getController() : UI5ControllerInterface
    {
        if ($this->controller === null) {
            if (null !== $parent = $this->getWidget()->getParent()) {
                $parentEl = $this->getFacade()->getElement($parent);
                $this->controller = $parentEl->getController();
            } else {
                throw new UI5ControllerNotInitializedException('No controller was initialized for page "' . $this->getWidget()->getPage()->getAliasWithNamespace() . '"!');
            }
        }
        return $this->controller;
    }
    
    /**
     * 
     * @return UI5ViewInterface
     */
    public function getView() : UI5ViewInterface
    {
        return $this->getController()->getView();
    }
    
    /**
     * Assign a UI5 controller to this element and all its children
     * 
     * @param UI5ControllerInterface $controller
     * 
     * @triggers \exface\UI5Facade\Events\OnControllerSetEvent
     * 
     * @throws LogicException
     * 
     * @return UI5AbstractElement
     */
    public function setController(UI5ControllerInterface $controller) : UI5AbstractElement
    {
        if (! $this->controller === null) {
            throw new LogicException('Cannot change the controller of a UI5 element after it had been set initially!');
        }
        $this->controller = $controller;
        $this->getWorkbench()->eventManager()->dispatch(new OnControllerSetEvent($controller, $this));
        return $this;
    }
    
    public final function buildHtmlHeadTags()
    {
        return [];
    }
    
    public function addOnBindingChangeScript(string $bindingName, string $script, string $oEventJs = 'oEvent') : UI5AbstractElement
    {
        $handler = <<<JS

                sap.ui.getCore().byId("{$this->getId()}")
                    .getBinding("{$bindingName}")
                    .attachChange(function({$oEventJs}){
                    {$script}
                });
JS;
        $this->getController()->addOnInitScript($handler);
        return $this;
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::addOnChangeScript()
     */
    public function addOnChangeScript($string)
    {
        parent::addOnChangeScript($string);
        $this->getController()->addOnEventScript($this, self::EVENT_NAME_CHANGE, $string);
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::addOnRefreshScript()
     */
    public function addOnRefreshScript(string $js) : AjaxFacadeElementInterface
    {
        parent::addOnChangeScript($js);
        $this->getController()->addOnEventScript($this, self::EVENT_NAME_REFRESH, $js);
        return $this;
    }
    
    public function getServerAdapter() : UI5ServerAdapterInterface
    {
        $adapterclass = $this->getFacade()->getConfig()->getOption("DEFAULT_SERVER_ADAPTER_CLASS");
        $adapter = new $adapterclass($this);
        
        if ($this->getFacade()->getWebapp()->isPWA()) {
            $adapter = new OfflineServerAdapter($this, $adapter);
        }
        return $adapter;
    }
    
    /**
     * Allows to add pre-/post-processing to event handler scripts.
     * 
     * The controller collects all event handlers via $controller->addOnEventScript() while the view
     * is rendered. After all elements are were generated, the controller generates event handler
     * methods for every registered event by calling buildJsOnEventScript() on the trigger element
     * of this event. 
     * 
     * By overriding this method, you can add additional code to certain events - e.g. a filter, that
     * only actually executes the handlers on certain conditions or stop event propagation once all
     * handlers are executed to prevent the UI5 logic from further handling the event. 
     * 
     * This method is called after all handler script were collected by the controller. These handler 
     * scripts are passed to this method as $scriptJs. By default the method simply returns $scriptJs 
     * without changes. Refer to the UI5DataTables class or the included UI5DataElementTrait for usage
     * examples.
     * 
     * @param string $eventName
     * @param string $scriptJs
     * @param string $oEventJs
     * @return string
     */
    public function buildJsOnEventScript(string $eventName, string $scriptJs, string $oEventJs) : string 
    {
        return $scriptJs;
    }
    
    /**
     * 
     * @return string
     */
    public function buildJsPropertyLayoutData() : string
    {
        if ($this->layoutData === null) {
            return '';
        } else {
            return "layoutData: [{$this->layoutData}],";
        }
    }
    
    /**
     * Sets the layout data for the control: e.g. "new sap.m.FlexItemData({growFactor: 1})".
     * 
     * @param string $layoutDataConstructorJs
     * @return UI5AbstractElement
     */
    public function setLayoutData(string $layoutDataConstructorJs) : UI5AbstractElement
    {
        $this->layoutData = $layoutDataConstructorJs;
        return $this;
    }
    
    /**
     * 
     * @param UI5ControllerInterface $controller
     * @return UI5AbstractElement
     */
    public function registerExternalModules(UI5ControllerInterface $controller) : UI5AbstractElement
    {
        return $this;
    }
    
    /**
     * 
     * @return UI5AbstractElement
     */
    public function registerConditionalProperties() : UI5AbstractElement
    {
        if ($this->isUnrendered()) {
            return $this;
        }
        
        $widget = $this->getWidget();
        
        // hidden_if
        if ($this->isVisible()) {
            if ($condProp = $widget->getHiddenIf()) {
                $funcOnTrue = $condProp->getFunctionOnTrue();
                $funcOnFalse = $condProp->getFunctionOnFalse();
                $this->registerConditionalPropertyUpdaterOnLinkedElements(
                    $condProp,
                    $this->buildJsSetHidden(true) . ';' . ($funcOnTrue !== null ? $this->buildJsCallFunction($funcOnTrue) : ''),
                    $this->buildJsSetHidden(false) . ';' . ($funcOnFalse !== null ? $this->buildJsCallFunction($funcOnFalse) : '')
                );
                $js = $this->buildJsConditionalProperty(
                    $condProp,
                    $this->buildJsSetHidden(true),
                    $this->buildJsSetHidden(false),
                    true
                );
                $this->getController()
                ->addOnPrefillDataChangedScript($js)
                ->addOnInitScript($js);
            }
        }
        
        // disabled_if
        if ($condProp = $widget->getDisabledIf()) {
            $funcOnTrue = $condProp->getFunctionOnTrue();
            $funcOnFalse = $condProp->getFunctionOnFalse();
            $this->registerConditionalPropertyUpdaterOnLinkedElements(
                $condProp, 
                $this->buildJsSetDisabled(true) . ';' . ($funcOnTrue !== null ? $this->buildJsCallFunction($funcOnTrue) : ''), 
                $this->buildJsSetDisabled(false) . ';' . ($funcOnFalse !== null ? $this->buildJsCallFunction($funcOnFalse) : '')
            );
            $js = $this->buildJsConditionalProperty(
                $condProp, 
                $this->buildJsSetDisabled(true), 
                $this->buildJsSetDisabled(false), 
                true
            );
            $this->getController()
            ->addOnInitScript($js)
            ->addOnPrefillDataChangedScript($js);
        }
        
        return $this;
    }
    
    /**
     * Don't use default logic from AbstractJqueryElement as it will empty bound models!!!
     * 
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsResetter()
     */
    public function buildJsResetter() : string
    {
        return '';
    }
    
    /**
     * Returns FALSE if this widget should not have padding if placed inside a container like
     * sap.m.Panel or similar.
     * 
     * By default this depends on the iFillEntireContainer interface of widgets, but each
     * facade element can override this logic.
     * 
     * @return bool
     */
    public function needsContainerContentPadding() : bool
    {
        return ! (($this->getWidget() instanceof iFillEntireContainer) || $this->getWidget()->getWidth()->isMax());
    }

    /**
     * Returns TRUE if the element requires a container height to scale properly (like sap.ui.table.Table).
     * 
     * Some UI5 controls do not scale properly inside layouts: e.g. sap.ui.table.Table or various page-like
     * controls placed in another page or in an ObjectPageLayout. These cases require workarounds for the
     * controls to be displayed in full height.
     * 
     * By default this method returns FALSE. It should be overridden in facade elements that use 
     * UI5 controls mentioned above.
     * 
     * @return bool
     */
    public function needsContainerHeight() : bool
    {
        return false;
    }
    
    /**
     * Return TRUE if the widget has its own nav-back button (e.g. because it is a UI5 page-like control)
     * 
     * It is a good idea to only show a single back-button per screen, so for nested 
     * containers there should be some kind of logic to determine, which of the back-buttons 
     * to show.
     * 
     * @return bool
     */
    public function hasButtonBack() : bool
    {
        return false;
    }
    
    /**
     * Returns TRUE if this element is not rendered in HTML and thus cannot be interacted with.
     * 
     * Unrendered controls cannot react to events or have live references!
     * 
     * @return bool
     */
    protected function isUnrendered() : bool
    {
        return false;
    }
    
    /**
     * Returns a JS snippet that resolves to an array of JSON objects with depicting changed values within this element
     * 
     * Structure:
     * 
     * ```
     *  [
     *      {
     *          elementId: <id of template element>,
     *          caption:
     *          valueOld:
     *          valueNew:
     *      }
     *  ]
     * ```
     * 
     * @return string
     */
    public function buildJsChangesGetter(bool $onlyVisible = false) : string
    {
        return '[]';
    }

    /**
     * Returns a JS snippet that yields TRUE if there are unsaved changes in this widget or FALSE otherwise
     * 
     * @return string
     */
    public function buildJsCheckUnsavedChanges() : string
    {
        // only do the check if the controller of the element is actually initialized
        // for the Buttons opening HelpDialog, the NotificationDialog etc. in the UI5 header toolbar
        //the input element controller is not initalized or there is none
        try {
            $controller = $this->getController();
        } catch (FacadeRuntimeError $e) {
            return 'false';
        }
        
        return <<<JS

            (function(){
                var aChanges = {$this->buildJsChangesGetter()};
                // Ignore changes in invisible controls because the user does not see them!
                aChanges = aChanges.filter(function(oChange) {
                    var oCtrl;
                    if (! oChange.elementId) return true;
                    oCtrl = sap.ui.getCore().byId(oChange.elementId);
                    if (oCtrl && oCtrl.getVisible !== undefined) {
                        return oCtrl.getVisible();
                    }
                    return true;
                });
                
                return aChanges.length > 0;
            })()
JS;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Interfaces\AjaxFacadeElementInterface::buildJsCheckInitialized()
     */
    public function buildJsCheckInitialized() : string
    {
        return "(sap.ui.getCore().byId('{$this->getId()}') !== undefined)";
    }
    
    /**
     * 
     * @param string $css
     * @param string $id
     * @return UI5AbstractElement
     */
    protected function registerCustomCSS(string $css, string $id = '_custom_css') : UI5AbstractElement
    {
        $css = $this->escapeString(StringDataType::stripLineBreaks($css));
        
        $cssId = $this->getId();
        if (! $this->getUseWidgetId()) {
            $this->setUseWidgetId(true);
            $cssId = $this->getId();
            $this->setUseWidgetId(false);
        }
        $cssId .= $id;
        
        $this->getController()->addOnShowViewScript(<<<JS
            
(function(){
    var jqTag = $('#{$cssId}');
    if (jqTag.length === 0) {
        $('head').append($('<style type="text/css" id="{$cssId}"></style>').text($css));
    }
})();

JS, false);
        
        $this->getController()->addOnHideViewScript("$('#{$cssId}').remove();");
        
        return $this;
    }

    /**
     * Returns inline JS function calls like `.addStyleClass('.exfw .exfw-InputComboTable')`
     * @return string
     */
    protected function buildJsAddCssWidgetClasses() : string
    {
        return ".addStyleClass('{$this->buildCssWidgetClass()}')";
        
    }

    /**
     * Registers resize listeners on controller init, that will call this elements resize script.
     * 
     * Many facade elements must react to their parent being resized. UI5 seems to provide a
     * comfortable ResizeHandler, but it turns out, it does not always work. 
     * 
     * Known issues of the ResizeHandler being solved here:
     * - Cannot attach a ResizeHandler to sap.m.IconTabFilter - JS error (missing DOM element?)
     * - Extending/collapsing the header of a sap.f.DynamicPage does not trigger ResizeHandler at all
     * 
     * @return UI5AbstractElement
     */
    protected function registerResizeListeners() : UI5AbstractElement
    {
        $this->getController()->addOnInitScript(<<<JS

(function(oController, oControl){
    var oDynamicPage = oController.findParentOfType(oControl, sap.f.DynamicPage);
    var oParent = oControl.getParent();
    
    while (oParent instanceof sap.m.IconTabFilter) {
        oParent = oParent.getParent();
    }
    if (oParent) {
        sap.ui.core.ResizeHandler.register(oParent, function(){
            setTimeout(function(){
                {$this->getOnResizeScript()}
            }, 0);
        });
    }
    if (oDynamicPage) {
        sap.ui.core.ResizeHandler.register(oDynamicPage.getHeader(), function(){
            setTimeout(function(){
                {$this->getOnResizeScript()}
            }, 0);
        });
    }
})(oController, sap.ui.getCore().byId('{$this->getId()}'));
JS);
        
        return $this;
    }
}