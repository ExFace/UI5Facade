<?php
namespace exface\UI5Facade\Facades\Interfaces;

use exface\UI5Facade\Facades\Elements\UI5AbstractElement;
use exface\UI5Facade\Webapp;
use exface\Core\Exceptions\Facades\FacadeLogicError;
use exface\Core\Exceptions\OutOfBoundsException;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
interface UI5ControllerInterface {
    
    /**
     * Returns the name of the controller method, that will handle the given event.
     * 
     * E.g. `onPress` for the event `press`.
     * 
     * @param string $eventName
     * @return string
     */
    public function buildJsEventHandlerMethodName(string $eventName) : string;
    
    /**
     * Returns the handler for the given event, that is usable within a view.
     * 
     * Use this method to obtain the values for constructor properties like `press`. Set $buildForView=true 
     * if the snippet is to be used in a view (i.e. as value of a control property) and $buildForView=false 
     * if you simply need to call the event handler from some other controller code.
     * 
     * @param UI5AbstractElement $triggerElement
     * @param string $eventName
     * @param bool $buildForView
     * 
     * @return string
     */
    public function buildJsEventHandler(UI5AbstractElement $triggerElement, string $eventName, bool $buildForView) : string;
    
    /**
     * Adds a JS script to be performed on the given event.
     * 
     * The event object will be made available in the $js via `oEvent`.
     * 
     * @param UI5AbstractElement $triggerElement
     * @param string $eventName
     * @param string $js
     * 
     * @return UI5ControllerInterface
     */
    public function addOnEventScript(UI5AbstractElement $triggerElement, string $eventName, string $js) : UI5ControllerInterface;
    
    /**
     *
     * @param string $js
     * @throws FacadeLogicError
     * @return UI5AbstractElement
     */
    public function addProperty(string $name, string $js) : UI5ControllerInterface;
    
    /**
     * Adds a controller method for a specific owner control.
     * 
     * **NOTE:** adding a method with the same name again will overwrite the previous version.
     * No checks are done regarding compatibility! 
     * 
     * E.g. `$controller->addMethod('onLoad', $control, 'param1, param2', 'console.log(param1), 'My method')`
     * will result in the following code somewhere in the controller:
     * 
     * ```
     *  // My method
     *  onLoadControlId: function(param1, param2) {
     *      console.log(param1)
     *  },
     *  
     * ```
     * 
     * Adding methods this way allows to use short method names with automatically generated
     * namespace suffixes. Also it makes sure, the method is added only once.
     * 
     * @param string $methodName
     * @param UI5AbstractElement $methodOwner
     * @param string $params
     * @param string $body
     * @param string $comment
     * @return UI5ControllerInterface
     */
    public function addMethod(string $methodName, UI5AbstractElement $methodOwner, string $params, string $body, $comment = '') : UI5ControllerInterface;
    
    /**
     * 
     * @param UI5AbstractElement $element
     * @param string $name
     * @return UI5ControllerInterface
     */
    public function addDependentControl(string $name, UI5AbstractElement $ownerElement, UI5AbstractElement $dependentElement) : UI5ControllerInterface;
    
    public function buildJsController() : string;
    
    /**
     * Returns the name of the controller: e.g. my.app.root.controller.my.app.page_alias
     * 
     * @return string
     */
    public function getName() : string;
    
    /**
     * Returns the path to the controller: e.g. my/app/root/controller/my/app/page_alias.controller.js
     * 
     * @return string
     */
    public function getPath() : string;
    
    public function getId() : string;
    
    public function getWebapp() : Webapp;
    
    /**
     * 
     * @param string $js
     * @param string $id
     * @return UI5ControllerInterface
     */
    public function addOnInitScript(string $js) : UI5ControllerInterface;
    
    /**
     * Adds a script to be performed before or after the view of this controller is shown.
     * 
     * @param string $js
     * @param bool $onBeforeShow
     * @return UI5ControllerInterface
     */
    public function addOnShowViewScript(string $js, bool $onBeforeShow = true) : UI5ControllerInterface;
    
    /**
     * Adds a script to be performed before or after the view of this controller is hidden.
     * 
     * @param string $js
     * @param bool $onBeforeHide
     * @return UI5ControllerInterface
     */
    public function addOnHideViewScript(string $js, bool $onBeforeHide = true) : UI5ControllerInterface;
    
    /**
     * Executes the provided script once a route to the view of this controller is matched
     * 
     * @param string $js
     * @param string $id
     * @return UI5ControllerInterface
     */
    public function addOnRouteMatchedScript(string $js, string $id) : UI5ControllerInterface;
    
    /**
     * Executes the provided JS script every time the view's prefill data changes.
     * 
     * The script is executed in the following cases:
     * - a prefill request was made using the server adapter and a response was saved as prefill data
     * - the view is initialized and no prefill is required
     * - the view is initialized and prefill is required, but the request was not sent because
     * it was empty.
     * 
     * The script is not executed if the prefill hash did not change or any other condition 
     * except from the listed above prevented the server request.
     * 
     * Each script is executed once - even if it was added multiple times.
     * 
     * @param string $js
     * @return UI5ControllerInterface
     */
    public function addOnPrefillDataChangedScript(string $js) : UI5ControllerInterface;
    
    /**
     * Executes the provided JS script right before the prefill request is passed to the server adapter.
     * 
     * The script is only executed if the prefill data is about to change (e.g a request is made 
     * using the server adapter). It is not executed if the prefill hash did not change or 
     * any other condition prevented fetching new prefill data.
     * 
     * Each script is executed once - even if it was added multiple times.
     * 
     * @param string $js
     * @return UI5ControllerInterface
     */
    public function addOnPrefillBeforeLoadScript(string $js) : UI5ControllerInterface;
    
    /**
     * Executes a script before the controller is initialized: before it's define()
     * 
     * @param string $js
     * @return UI5ControllerInterface
     */
    public function addOnDefineScript(string $js) : UI5ControllerInterface;
    
    /**
     * 
     * @param string $methodName
     * @param UI5AbstractElement $callerElement
     * @param string $oController
     * 
     * @throws OutOfBoundsException if method not found in the controller
     * 
     * @return string
     */
    public function buildJsMethodCallFromView(string $methodName, UI5AbstractElement $callerElement, $oController = 'oController') : string;
    
    /**
     * 
     * @param string $methodName
     * @param UI5AbstractElement $methodOwner
     * @param string $paramsJs
     * @param string $oControllerJsVar
     * 
     * @throws OutOfBoundsException if method not found in the controller
     * 
     * @return string
     */
    public function buildJsMethodCallFromController(string $methodName, UI5AbstractElement $methodOwner, string $paramsJs, string $oControllerJsVar = null) : string;
    
    /**
     * 
     * @param string $methodName
     * @param UI5AbstractElement $ownerElement
     * @return string
     */
    public function buildJsMethodName(string $methodName, UI5AbstractElement $ownerElement) : string;
    
    /**
     * Registers an external JS dependencies in the controller.
     * 
     * The module name ($name) is the first argument of jQuery.sap.registerModulePath(). External UI5
     * components have their own module names, but for non-UI5 includes the name can be anything.
     * 
     * The module name is automatically converted to the include path (in the first argument of define())
     * by replacing `.` with `/`.
     * 
     * If you need a specific variable inside the controller, set the $controllerArgumentName. If
     * not set, it will be generated automatically.
     * 
     * If the dependency requires a JS global (e.g. `moment` for moment.js), provide a $globalVarName.
     * 
     * Output example:
     * 
     * ```
     * jQuery.sap.registerModulePath('libs.font_awesome.plugin', 'exface/vendor/bower-asset/font-awesome-openui5/dist/font-awesome-openui5.min');
     * jQuery.sap.registerModulePath('exface/vendor/exface/core/Facades/AbstractAjaxFacade/js/echarts/echarts.custom.min', 'exface/vendor/exface/core/Facades/AbstractAjaxFacade/js/echarts/echarts.custom.min');
     * /* global echarts /  
     * sap.ui.define([
     *	 "powerui/demomes/fertigung/controller/BaseController",
     *	 "libs/font_awesome/plugin",
     *   "exface/core/Facades/AbstractAjaxFacade/js/echarts/echarts.custom.min"
     * ], function (BaseController, fontAwesomePlugin, echartsCustomMin) {
     * }
     * 
     * ```
     * 
     * @param string $name qualified module name in dot-notation
     * @param string $urlRelativeToAppRoot path to source file
     * @param string $controllerArgumentName
     * @param string $globalVarName
     * @return UI5ControllerInterface
     */
    public function addExternalModule(string $name, string $urlRelativeToAppRoot, string $controllerArgumentName = null, string $globalVarName = null) : UI5ControllerInterface;
    
    /**
     * Returns an array with module names for keys and respecitve JS include paths for values (relative to site root)
     * 
     * @return string[]
     */
    public function getExternalModulePaths() : array;
    
    /**
     * 
     * @param string $path
     * @param string $id
     * @return UI5ControllerInterface
     */
    public function addExternalCss(string $path, string $id = null) : UI5ControllerInterface;
    
    /**
     * 
     * @param string $objectName
     * @param UI5AbstractElement $ownerElement
     * @return string
     */
    public function buildJsObjectName(string $objectName, UI5AbstractElement $ownerElement) : string;
    
    /**
     * 
     * @param string $objectName
     * @param UI5AbstractElement $ownerElement
     * @param string $construtorJs
     * @return UI5ControllerInterface
     */
    public function addDependentObject(string $objectName, UI5AbstractElement $ownerElement, string $construtorJs) : UI5ControllerInterface;
    
    /**
     * 
     * @param UI5AbstractElement $fromElement
     * @return string
     */
    public function buildJsControllerGetter(UI5AbstractElement $fromElement) : string;
    
    /**
     * 
     * @return UI5ViewInterface
     */
    public function getView() : UI5ViewInterface;
    
    /**
     * 
     * @param string $name
     * @return bool
     */
    public function hasProperty(string $name) : bool;
    
    /**
     * 
     * @param string $name
     * @param UI5AbstractElement $ownerElement
     * @return bool
     */
    public function hasMethod(string $name, UI5AbstractElement $ownerElement) : bool;
    
    /**
     * 
     * @param string $name
     * @param UI5AbstractElement $ownerElement
     * @return bool
     */
    public function hasDependent(string $name, UI5AbstractElement $ownerElement) : bool;
    
    /**
     * Returns an inline JS snippet to get a dependent control saved previously via addDependentControl().
     * 
     * @param string $controlName
     * @param UI5AbstractElement $ownerElement
     * @param string $oControllerJsVar
     * 
     * @throws OutOfBoundsException if dependent control not found in controller
     * 
     * @return string
     */
    public function buildJsDependentControlSelector(string $controlName, UI5AbstractElement $ownerElement, string $oControllerJsVar = null) : string;
    
    /**
     * Returns an inline JS snippet to get the dependen object (controller property)
     * 
     * @param string $objectName
     * @param UI5AbstractElement $ownerElement
     * @param string $oControllerJsVar
     * 
     * @return string
     */
    public function buildJsDependentObjectGetter(string $objectName, UI5AbstractElement $ownerElement, string $oControllerJsVar = null) : string;
    /**
     * 
     * @return string
     */
    public function buildJsComponentGetter() : string;
    
    /**
     * Returns the JS to include an external CSS.
     *
     * CSS files are automatically included only once.
     *
     * @return string[]
     */
    public function buildJsImportCSS() : array;
    
    /**
     *
     * @return string[]
     */
    public function buildJsImportModuleRegistrations() : array;
}