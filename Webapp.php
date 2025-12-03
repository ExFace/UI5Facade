<?php
namespace exface\UI5Facade;

use exface\Core\Exceptions\Facades\FacadeRuntimeError;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\CommonLogic\Workbench;
use exface\UI5Facade\Facades\UI5Facade;
use exface\UI5Facade\Exceptions\UI5RouteInvalidException;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\LogicException;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\Exceptions\FileNotFoundError;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\UI5Facade\Facades\Interfaces\UI5ViewInterface;
use GuzzleHttp\Psr7\Uri;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\Interfaces\Tasks\HttpTaskInterface;
use exface\Core\Interfaces\Widgets\iTriggerAction;
use exface\Core\Exceptions\Facades\FacadeLogicError;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\Exceptions\Facades\FacadeRoutingError;
use exface\Core\CommonLogic\Security\Authorization\UiPageAuthorizationPoint;
use exface\Core\Widgets\LoginPrompt;
use exface\Core\Exceptions\Security\AuthenticationFailedError;
use exface\Core\Interfaces\Exceptions\AuthorizationExceptionInterface;
use exface\Core\DataTypes\OfflineStrategyDataType;
use exface\Core\CommonLogic\Tasks\GenericTask;
use exface\Core\Actions\ShowWidget;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\DataTypes\MessageTypeDataType;

class Webapp implements WorkbenchDependantInterface
{
    const VIEW_FILE_SUFFIX = '.view.js';
    
    const CONTROLLER_FILE_SUFFIX = '.controller.js';
    
    const WIDGET_DELIMITER = 'widget';
    
    private $workbench = null;
    
    private $facade = null;
    
    private $appId = null;
    
    private $rootPage = null;
    
    private $facadeFolder = null;
    
    private $config = [];
    
    private $controllers = [];
    
    private $models = [];
    
    private $viewControllerNames = [];
    
    private $preloadPages = [];
    
    
    public function __construct(UI5Facade $facade, string $ui5AppId, string $facadeFolder, array $config)
    {
        $this->workbench = $facade->getWorkbench();
        $this->facade = $facade;
        $this->appId = $ui5AppId;
        $this->facadeFolder = $facadeFolder;
        $this->config = $config;
    }
    
    public function getWorkbench() : Workbench
    {
        return $this->workbench;
    }
    
    /**
     * Prefills the given widget if required by the task.
     * 
     * This method takes care of prefilling widgets loaded via app routing (i.e. in views and viewcontrollers).
     * These resources are loaded without any input data, so just performing the corresponding task would not
     * result in a prefill. If the widget is actually supposed to be prefilled (e.g. an edit dialog), we need
     * that prefill operation to figure out model bindings. 
     * 
     * Technically, the task is being modified in a way, that the prefill is performed. Than, the task is being
     * handled by the workbench and the resulting prefilled widget is returned.
     * 
     * @param WidgetInterface $widget
     * @param TaskInterface $task
     * 
     * @return WidgetInterface
     */
    public function handlePrefill(UI5ViewInterface $view, TaskInterface $task) : Webapp
    {
        // The whole trick only makes sense for widgets, that are created by actions (e.g. via button press).
        // Otherwise we would not be able to find out, if the widget is supposed to be prefilled because 
        // actions controll the prefill.
        if ($view->getRootElement()->getWidget()->getParent() instanceof iTriggerAction) {
            $model = $this->createViewModel($view);
            $model->addBindingsFromTask($task);
            $this->addViewModel($model);
        }
        
        return $this;
    }
    
    public function get(string $route, HttpTaskInterface $task = null) : string
    {
        switch (true) {
            case $route === 'manifest.json':
                return $this->getManifestJson();
            case $route === 'Component-preload.js' && $this->facade->getConfig()->getOption('UI5.USE_COMPONENT_PRELOAD'):
                return $this->getComponentPreload();
            case $route === 'Offline-preload.js' && $this->isPWA():
                return $this->getComponentPreloadForOffline();
            case StringDataType::startsWith($route, 'i18n/'):
                $lang = explode('_', pathinfo($route, PATHINFO_FILENAME))[1];
                return $this->getTranslation($lang);
            case file_exists($this->getFacadesFolder() . $route):
                if ($route === 'controller/BaseController.js' && $this->isPWA()) {
                    $extraPlaceholders = [
                        'onInit' => $this->buildJsPWAInit()
                    ];
                } else {
                    $extraPlaceholders = [
                        'onInit' => ''
                    ];
                }
                return $this->getFromFileTemplate($route, $extraPlaceholders);
            case StringDataType::startsWith($route, 'view/'):
                $path = StringDataType::substringAfter($route, 'view/');
                if (StringDataType::endsWith($path, $this->getViewFileSuffix())) {
                    $path = StringDataType::substringBefore($path, $this->getViewFileSuffix());
                    try {
                        $widget = $this->getWidgetFromPath($path);
                        if ($widget) {
                            $view = $this->getViewForWidget($widget);
                            $this->handlePrefill($view, $task);
                            return $view->buildJsView();
                        } 
                    } catch (\Throwable $e) {
                        $this->getWorkbench()->getLogger()->logException(
                            new FacadeRuntimeError('Cannot render UI5 view. ' . $e->getMessage(), null, $e)
                        );
                        $errorViewName = $this->getViewNamespace() . str_replace('/', '.', $path);
                        return $this->getErrorView($e, $errorViewName);
                    }
                    return '';
                }
            case StringDataType::startsWith($route, 'controller/'):
                $path = StringDataType::substringAfter($route, 'controller/');
                if (StringDataType::endsWith($path, $this->getControllerFileSuffix())) {
                    $path = StringDataType::substringBefore($path, $this->getControllerFileSuffix());
                    try {
                        $widget = $this->getWidgetFromPath($path);
                        if ($widget) {
                            $controller = $this->getControllerForWidget($widget);
                            $this->handlePrefill($controller->getView(), $task);
                            return $controller->buildJsController();
                        }
                    } catch (\Throwable $e) {
                        $this->getWorkbench()->getLogger()->logException(
                            new FacadeLogicError('Cannot render UI5 controller. ' . $e->getMessage(), null, $e)
                        );
                        return $this->getErrorController($e);
                    }
                    return '';
                }
            case StringDataType::startsWith($route, 'viewcontroller/'):
                $path = StringDataType::substringAfter($route, 'viewcontroller/');
                if (StringDataType::endsWith($path, '.viewcontroller.js')) {
                    $path = StringDataType::substringBefore($path, '.viewcontroller.js');
                    try {
                        $widget = $this->getWidgetFromPath($path);
                        if ($widget) {
                            $controller = $this->getControllerForWidget($widget);
                            $this->handlePrefill($controller->getView(), $task);
                            return $controller->buildJsController() . "\n\n" . $controller->getView()->buildJsView();
                        }
                    } catch (\Throwable $e) {
                        if ($controller) {
                            $errorViewName = $controller->getView()->getName();
                        } else {
                            $errorViewName = $this->getViewNamespace() . str_replace('/', '.', $path);
                        }
                        $this->getWorkbench()->getLogger()->logException(
                            new FacadeRuntimeError('Cannot render UI5 viewcontroller. ' . $e->getMessage(), null, $e)
                        );
                        return $this->getErrorView($e, $errorViewName);
                    }
                    return '';
                }
            default:
                throw new UI5RouteInvalidException('Cannot match route "' . $route . '"!');
        }
    }
    
    public function has(string $route) : bool
    {
        try {
            $this->get($route);
        } catch (UI5RouteInvalidException $e) {
            return false;
        }
        return true;
    }
    
    protected function getManifestJson() : string
    {
        $placeholders = $this->config;
        $tpl = file_get_contents($this->getFacadesFolder() . 'manifest.json');
        $tpl = str_replace('[#app_id#]', $placeholders['app_id'], $tpl);
        $json = json_decode($tpl);
        $json->_version = $this->getManifestVersion($placeholders['ui5_min_version']);
        $json->{'sap.app'}->id = $placeholders['app_id'];
        $json->{'sap.app'}->title = $this->getTitle();
        $json->{'sap.app'}->subTitle = $placeholders['app_subTitle'] ? $placeholders['app_subTitle'] : '';
        $json->{'sap.app'}->shortTitle = $placeholders['app_shortTitle'] ? $placeholders['app_shortTitle'] : '';
        $json->{'sap.app'}->info = $placeholders['app_info'] ? $placeholders['app_info'] : '';
        $json->{'sap.app'}->description = $placeholders['app_description'] ? $placeholders['app_description'] : '';
        $json->{'sap.app'}->applicationVersion->version = $placeholders['current_version'];
        $json->{'sap.ui5'}->dependencies->minUI5Version = $placeholders['ui5_min_version'];
        
        $json->exface->useCombinedViewControllers = $placeholders['use_combined_viewcontrollers'];
        
        if ($this->hasPWAConfig() === true) {
            $json->short_name = $this->getName();
            $json->name = $this->getTitle();
            // TODO #nocms
            //$json->icons = $this->getWorkbench()->getCMS()->getFavIcons();
            $json->start_url = $this->getStartUrl();
            $json->scope = $this->getRootUrl() . "/";
            $json->background_color = $this->config['pwa_background_color'];
            $json->theme_color = $this->config['pwa_theme_color'];
            $json->display = "standalone";
        }
        
        $json->{'sap.app'}->dataSources = $this->facade->getConfig()->getOption('WEBAPP_EXPORT.MANIFEST.DATASOURCES')->toArray();
        
        return json_encode($json, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
    }
    
    /**
     *
     * @return string
     */
    public function getFacadesFolder() : string
    {
        return $this->facadeFolder;
    }
    
    protected function getFromFileTemplate(string $pathRelativeToWebappFolder, array $placeholders = []) : string
    {
        $path = $this->getFacadesFolder() . $pathRelativeToWebappFolder;
        if (! file_exists($path)) {
            throw new FileNotFoundError('Cannot load facade file "' . $pathRelativeToWebappFolder . '": file does not exist!');
        }
        
        $tpl = file_get_contents($path);
        
        if ($tpl === false) {
            throw new RuntimeException('Cannot read facade file "' . $pathRelativeToWebappFolder . '"!');
        }
        
        $phs = array_merge($this->config, $placeholders);
        
        try {
            return StringDataType::replacePlaceholders($tpl, $phs);
        } catch (\exface\Core\Exceptions\RangeException $e) {
            throw new LogicException('Incomplete  UI5 webapp configuration - ' . $e->getMessage(), null, $e);
        }
    }
    
    /**
     * @see https://sapui5.hana.ondemand.com/sdk/#/topic/be0cf40f61184b358b5faedaec98b2da.html
     * @param string $ui5Version
     * @return string
     */
    protected function getManifestVersion(string $ui5Version) : string
    {
        switch (true) {
            case strcmp($ui5Version, '1.30') < 0: return '1.0.0';
            case strcmp($ui5Version, '1.32') < 0: return '1.1.0';
            case strcmp($ui5Version, '1.34') < 0: return '1.2.0';
            case strcmp($ui5Version, '1.38') < 0: return '1.3.0';
            case strcmp($ui5Version, '1.42') < 0: return '1.4.0';
            case strcmp($ui5Version, '1.46') < 0: return '1.5.0';
            case strcmp($ui5Version, '1.48') < 0: return '1.6.0';
            case strcmp($ui5Version, '1.50') < 0: return '1.7.0';
            case strcmp($ui5Version, '1.52') < 0: return '1.8.0';
            default: return '1.9.0';
        }
    }
    
    public function getTranslation(string $locale = null) : string
    {
        try {
            $app = $this->getRootPage()->getApp();
        } catch (\Throwable $errRootPageApp) {
            try {
                if ($this->getRootPage()->isEmpty() === false) {
                    $rootObj = $this->getRootPage()->getWidgetRoot()->getMetaObject();
                    $app = $rootObj->getApp();
                }
            } catch (\Throwable $errObjectApp) {
                // Do nothing - we will use the default locale
            }
        }
        
        if ($locale === null) {
            if ($app) {
                $locale = $app->getLanguageDefault();
            } else {
                $locale = $this->getWorkbench()->getCoreApp()->getLanguageDefault();
            }
        }
        
        $tplTranslator = $this->facade->getApp()->getTranslator($locale);
        $dict = $tplTranslator->getDictionary();
        
        if ($app) {
            $dict = array_merge($dict, $app->getTranslator()->getDictionary($locale));
        }
        
        // Transform the array into a properties file
        foreach ($dict as $key => $text) {
            $output .= $key . '=' . $text. "\n";
        }
        
        return $output;
    }
    
    public function getRootPageAlias() : string
    {
        return $this->appId;
    }
    
    /**
     * 
     * @throws FacadeRoutingError
     * @return UiPageInterface
     */
    public function getRootPage() : UiPageInterface
    {
        if ($this->rootPage === null) {
            try {
                $appRootPage = UiPageFactory::createFromModel($this->getWorkbench(), $this->getRootPageAlias());
            } catch (\Throwable $e) {
                throw new FacadeRoutingError('Root page for UI5 app "' . $this->appId . '" not found!', null, $e);
            }
            
            try {
                $pageAP = $this->getWorkbench()->getSecurity()->getAuthorizationPoint(UiPageAuthorizationPoint::class);
                $appRootPage = $pageAP->authorize($appRootPage);
            } catch (AuthorizationExceptionInterface $accessError) {
                $authError = new AuthenticationFailedError($this->getWorkbench()->getSecurity(), $accessError->getMessage(), null, $accessError);
                $appRootPage = $this->createLoginPage($authError, $this->getRootPageAlias());
            } 
            $this->rootPage = $appRootPage;
        }
        return $this->rootPage;
    }
    
    /**
     * Returns the widget, that the given path (e.g. appRoot/view/mypage/widget/widget_id) points to.
     * 
     * SAP UI5 computes it's routes (= pathes) by replacing `.` in view or controller names by `/` - in
     * other words, namespaces become subfolders. View and controller names for widgets are created by
     * `getViewName()` and `getControllerName()`. This method basically does the opposite: it split's
     * the path into it's components and puzzles them back into a page selector and a widget id. Those
     * two together define a widget unambiguosly.
     * 
     * @see getViewName()
     * @see getControllerName()
     * @see Facades/Webapp/Controller/BaseController.js::_getUrlFromRoute()
     * @see Facades/Webapp/Controller/BaseController.js::getViewName()
     * 
     * @param string $path
     * @throws UI5RouteInvalidException
     * @return WidgetInterface|NULL
     */
    protected function getWidgetFromPath(string $path) : ?WidgetInterface
    {
        $parts = explode('/', $path);
        $cnt = count($parts);
        if ($cnt === 1 || $cnt === 2) {
            // URLs of non-namespaced pages like
            // - appRoot/view/mypage
            // - appRoot/view/mypage/widget_id
            $pageAlias = $parts[0];
        } elseif ($cnt >= 3) {
            // URLs of namespaced pages like
            // - appRoot/view/vendor/app/page
            // - appRoot/view/vendor/app/page/widget_id
            if ($cnt > 4) {
                $widgetId = implode('.', array_slice($parts, 3));
            }
            $pageAlias = $parts[0] . AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER . $parts[1] . AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER . $parts[2];
        } else {
            throw new UI5RouteInvalidException('Route "' . $path . '" not found!');
        }
        
        $page = UiPageFactory::createFromModel($this->getWorkbench(), $pageAlias);
        
        if ($widgetId) {
           $widget = $page->getWidget($widgetId); 
        } elseif ($cnt === 4) {
            $widget = $page->getWidget($parts[3]);
        } elseif ($cnt === 2) {
            $widget = $page->getWidget($parts[1]);
        } else {
            $widget = $page->getWidgetRoot();
        }
        
        return $widget;
    }
    
    public function getComponentName() : string
    {
        return $this->appId;
    }
    
    public function getComponentId() : string
    {
        return $this->appId . '.Component';
    }
    
    public function getComponentPath() : string
    {
        return str_replace('.', '/', $this->getComponentName());
    }
    
    public function getComponentUrl() : string
    {
        return $this->facade->getConfig()->getOption('FACADE.AJAX.BASE_URL') . '/webapps/' . $this->getComponentName() . '/';
    }
    
    /**
     * Computes the view name for a given widget: e.g. my.app.root.view.my.app.page_alias.widget.widget_id...
     * 
     * @see getWidgetFromPath() for more details.
     * 
     * @param WidgetInterface $widget
     * @return string
     */
    public function getViewName(WidgetInterface $widget) : string
    {
        return $this->getRootPage()->getAliasWithNamespace() . '.view.' . $this->getViewNameWithoutNamespace($widget);
    }

    /**
     * Computes the view name for a given widget, but without the namespace: e.g. my.app.page_alias.widget.widget_id...
     *
     * @see getWidgetFromPath() for more details.
     *
     * @param WidgetInterface $widget
     * @return string
     */
    public function getViewNameWithoutNamespace(WidgetInterface $widget) : string
    {
        $appRootPage = $this->getRootPage();
        $pageAlias = $widget->getPage()->getAliasWithNamespace() ? 
            $widget->getPage()->getAliasWithNamespace() : $appRootPage->getAliasWithNamespace();
        
        return $pageAlias . ($widget->hasParent() ? '.' . $this->getWidgetIdForViewControllerName($widget) : '');
    }
    
    /**
     * Returns the suffix of view files - `.view.js` by default.
     * 
     * @see getWidgetFromPath() for more details.
     * 
     * @return string
     */
    public function getViewFileSuffix() : string
    {
        return self::VIEW_FILE_SUFFIX;
    }
    
    /**
     * Returns the controller name for the given widget: e.g. my.app.root.controller.my.app.page_alias.widget.widget_id...
     * 
     * @see getWidgetFromPath() for more details.
     * 
     * @param WidgetInterface $widget
     * @return string
     */
    public function getControllerName(WidgetInterface $widget) : string
    {
        $appRootPage = $this->getRootPage();
        $pageAlias = $widget->getPage()->getAliasWithNamespace() ? $widget->getPage()->getAliasWithNamespace() : $appRootPage->getAliasWithNamespace();
        return $appRootPage->getAliasWithNamespace() . '.controller.' . $pageAlias . ($widget->hasParent() ? '.' . $this->getWidgetIdForViewControllerName($widget) : '');
    }
    
    /**
     * Returns the id for the given widget to use in view and controller names.
     * Normally that id is `$widget->getId()` but for exported apps we need shorter names.
     * For exported apps the id consist of the widget type, the widget caption, the meta object alias
     * and an added counter if ids duplicate.
     * 
     * @param WidgetInterface $widget
     * @return string
     */
    public function getWidgetIdForViewControllerName(WidgetInterface $widget) : string
    {
        if ($this->getFacade()->getConfig()->getOption('WIDGET.USE_SHORT_ID') === true) {
            if (array_key_exists($widget->getId(), $this->viewControllerNames)) {
                return $this->viewControllerNames[$widget->getId()];
            }
            $name = $widget->getWidgetType() . '_' . filter_var($widget->getCaption(), FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH) . '_' . $widget->getMetaObject()->getAlias();
            if (in_array($name, $this->viewControllerNames)) {
                $duplicate = true;
                $idx = 2;
                while ($duplicate === true) {
                    $potentialName = $name . '_' . strval($idx);
                    if (in_array($potentialName, $this->viewControllerNames)) {
                        $idx++;
                    } else {
                        $duplicate = false;
                        $name = $potentialName;
                    }
                }
            }
            $this->viewControllerNames[$widget->getId()] = $name;
            return $name;
        } else {
            return $widget->getId();
        }
        
    }
    
    /**
     * Returns the suffix of view files - `.controller.js` by default.
     *
     * @see getWidgetFromPath() for more details.
     * 
     * @return string
     */
    public function getControllerFileSuffix() : string
    {
        return self::CONTROLLER_FILE_SUFFIX;
    }
    
    public function getComponentPreload() : string
    {
        $prefix = $this->getComponentPath() . '/';
        $resources = [];
        
        // Component and manifest
        $resources[$prefix . 'Component.js'] = $this->get('Component.js');
        $resources[$prefix . 'manifest.json'] = $this->get('manifest.json');
        
        // Base views and controllers (App.view.js, NotFound.view.js, BaseController.js, etc.)
        foreach ($this->getBaseControllers() as $controllerPath) {
            $resources[$prefix . $controllerPath] = $this->get($controllerPath);
        }
        foreach ($this->getBaseViews() as $viewPath) {
            $resources[$prefix . $viewPath] = $this->get($viewPath);
        }
        
        // i18n
        $currentLocale = $this->getWorkbench()->getContext()->getScopeSession()->getSessionLocale();
        $locales = [StringDataType::substringBefore($currentLocale, '_')];
        $resources = array_merge($resources, $this->getComponentPreloadForLangs($locales));
        
        // Root view and controller
        try {
            $rootPage = $this->getRootPage();
            $rootWidget = $rootPage->getWidgetRoot();
            $rootController = $this->getControllerForWidget($rootWidget);
            $resources = array_merge($resources, $this->getComponentPreloadForController($rootController));
        } catch (\Throwable $e) {
            $this->getWorkbench()->getLogger()->logException(
                new FacadeRuntimeError('Cannot render UI5 `ComponentPreload.js`. ' . $e->getMessage(), null, $e)
            );
            if ($rootController) {
                $viewPath = $rootController->getView()->getPath();
                $viewName = $rootController->getView()->getName();
                $controllerPath = $rootController->getPath();
                
                $resources[$viewPath] = $this->getErrorView($e, $viewName);
                $resources[$controllerPath] = $this->getErrorController($e);
            }
        }
        
        return $this->getComponentPreloadFromResources($resources, $prefix . 'Component-preload');
    }
    
    /**
     * 
     * @param UI5ControllerInterface $controller
     * @return string[]
     */
    protected function getComponentPreloadForController(UI5ControllerInterface $controller) : array
    {
        $resources = [
            $controller->getView()->getPath() => $controller->getView()->buildJsView(),
            $controller->getPath() => $controller->buildJsController()
        ];
        
        // External libs for
        foreach ($controller->getExternalModulePaths() as $name => $path) {
            $filePath = StringDataType::endsWith($path, '.js', false) ? $path : $path . '.js';
            // FIXME At this point a path (not URL) of the external module is required. Where do we get it? What do we
            // do with scripts hosted on other servers? The current script simply assumes, that all external modules
            // reside in subfolders of the platform and the platform itself is located in the subfolder exface of the
            // server root.
            $filePath = $this->getWorkbench()->filemanager()->getPathToBaseFolder() . DIRECTORY_SEPARATOR . $filePath;
            $lib = str_replace('.', '/', $name);
            $lib = StringDataType::endsWith($lib, '.js', false) ? $lib : $lib . '.js';
            if (file_exists($filePath)) {
                $resources[$lib] = file_get_contents($filePath);
            } else {
                $this->getWorkbench()->getLogger()->logException(new FileNotFoundError('File "' . $filePath . '" not found for required UI5 module "' . $name . '"!'), LoggerInterface::ERROR);
            }
        }
        
        return $resources;
    }
    
    /**
     * 
     * @param string[] $scripts
     * @param string $name
     * @return string
     */
    protected function getComponentPreloadFromResources(array $scripts, string $name) : string
    {
        return 'sap.ui.require.preload(' . json_encode($scripts, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT) . ', "' . $name . '");';
    }
    
    /**
     * 
     * @return string
     */
    protected function getComponentPreloadForOffline()
    {
        $resources = [];
        $rootPage = $this->getRootPage();   
        if (! $rootPage->isPWA()) {
            return '';   
        }
        
        $pwa = $this->getFacade()->getPWA($rootPage->getPWASelector());
        $authToken = $this->getWorkbench()->getSecurity()->getAuthenticatedToken();
        $cacheSheet = $pwa->getBuildCache('Offline-preload.js', $authToken);
        $moduleRegistrations = [];
        $cssIncludes = [];
        if (! $cacheSheet->isEmpty()) {
            $preloadJs = $cacheSheet->getCellValue('CONTENT', 0);
        } else {
            $pwa->loadModel([
                OfflineStrategyDataType::PRESYNC,
                OfflineStrategyDataType::CLIENT_SIDE
            ]);
            foreach ($pwa->getRoutes() as $route) {
                if ($route->getOfflineStrategy() !== OfflineStrategyDataType::PRESYNC) {
                    continue;
                }
                try {
                    $routePath = $this->convertNameToPath($route->getUrl(), '');
                    $widget = $this->getWidgetFromPath($routePath);
                    if ($widget) {
                        $routeController = $this->getControllerForWidget($widget);
                        $routeLoadTask = (new GenericTask($this->getWorkbench()))->setActionSelector($route->getAction() ? $route->getAction()->getSelector() : ShowWidget::class);
                        $this->handlePrefill($routeController->getView(), $routeLoadTask);
                    }
                    $resources = array_merge($resources, $this->getComponentPreloadForController($routeController));
                    $moduleRegistrations = array_merge($moduleRegistrations, $routeController->buildJsImportModuleRegistrations());
                    $cssIncludes = array_merge($cssIncludes, $routeController->buildJsImportCSS());
                } catch (\Throwable $e) {
                    $ex = new FacadeLogicError('Failed to preload offline route ' . $route->getPWA()->getDescriptionOf($route) . ': ' . $e->getMessage(), null, $e);
                    $this->getWorkbench()->getLogger()->logException($ex);
                }
            }
            $moduleRegistrations = array_unique($moduleRegistrations);
            $cssIncludes = array_unique($cssIncludes);
            $importsJs = implode("\n", $moduleRegistrations) . "\n" . implode("\n", $cssIncludes) . "\n";
            $preloadJs = $importsJs . $this->getComponentPreloadFromResources($resources, $this->getComponentPath() . '/' . 'Offline-preload') . ';';
            $pwa->setBuildCache('Offline-preload.js', $preloadJs, 'text/javascript', $authToken);
        }
        return $preloadJs;
    }
    
    /**
     * 
     * @param array $locales
     * @return array
     */
    protected function getComponentPreloadForLangs(array $locales) : array
    {
        $resources = [];
        foreach ($locales as $loc) {
            $cldrPath = $this->facade->getUI5LibrariesPath() . 'resources/sap/ui/core/cldr/' . $loc . '.json';
            if (file_exists($cldrPath)) {
                $resources['sap/ui/core/cldr/' . $loc . '.json'] = file_get_contents($cldrPath);
            }
        }
        return $resources;
    }
    
    /**
     * Converts given name into a path by replacing all '.' with '/' and adding the suffix
     * 
     * @see getWidgetFromPath() for more details.
     * 
     * @param string $name
     * @param string $suffix
     * @return string
     */
    public function convertNameToPath(string $name, string $suffix = self::VIEW_FILE_SUFFIX) : string
    {
        $path = str_replace('.', '/', $name);
        return $path . $suffix;
    }
    
    /**
     * 
     * @return string[]
     */
    public function getBaseControllers() : array
    {
        return [
            'controller/BaseController.js',
            'controller/App' . $this->getControllerFileSuffix(),
            'controller/NotFound' . $this->getControllerFileSuffix(),
            'controller/Offline' . $this->getControllerFileSuffix(),
            'controller/Error' . $this->getControllerFileSuffix()
        ];
    }
    
    /**
     * 
     * @return string[]
     */
    public function getBaseViews() : array
    {
        return [
            'view/App' . $this->getViewFileSuffix(),
            'view/NotFound' . $this->getViewFileSuffix(),
            'view/Offline' . $this->getViewFileSuffix()
        ];
    }
    
    public function getViewForWidget(WidgetInterface $widget) : UI5ViewInterface
    {
        return $this->getControllerForWidget($widget)->getView();
    }
    
    public function getControllerForWidget(WidgetInterface $widget) : UI5ControllerInterface
    {
        return $this->facade->createController($this->facade->getElement($widget));
    }
    
    public function isPWA() : bool
    {
        return $this->getRootPage()->isPWA();
    }
    
    public function hasPWAConfig() : bool
    {
        return $this->config['pwa_flag'] ? true : false;
    }
    
    public function getStartUrl() : string
    {
        $uri = new Uri($this->facade->buildUrlToPage($this->getRootPage()));
        $path = $uri->getPath();
        $path = '/' . ltrim($path, "/");        
        return $path;
    }
    
    public function getRootUrl() : string
    {
        return $this->config['root_url'];
    }
    
    public function getName() : string
    {
        return $this->config['name'] ? $this->config['name'] : $this->getRootPage()->getName();
    }
    
    public function getTitle() : string
    {
        return $this->config['app_title'] ? $this->config['app_title'] : $this->getRootPage()->getName();
    }
    
    /**
     * 
     * @param UI5ViewInterface $view
     * @param string $modelName
     * @return UI5Model
     */
    protected function createViewModel(UI5ViewInterface $view, string $modelName = '') : UI5Model
    {
        $model = new UI5Model($view->getRootElement()->getWidget(), $view->getName(), $modelName);
        return $model;
    }
    
    /**
     * 
     * @param UI5Model $model
     * @return Webapp
     */
    protected function addViewModel(UI5Model $model) : Webapp
    {
        $this->models[$model->getViewName() . ':' . $model->getName()] = $model;
        return $this;
    }
    
    /**
     * 
     * @param UI5ViewInterface $view
     * @param string $modelName
     * @return UI5Model
     */
    public function getViewModel(UI5ViewInterface $view, string $modelName = '') : UI5Model
    {
        $viewName = $view->getName();
        $model = $this->models[$viewName . ':' . $modelName];
        if ($model === null) {
            $model = $this->createViewModel($view, $modelName);
            $this->addViewModel($model);
        }
        return $model;
    }
    
    /**
     * Returns the code for the error view with information about the given exception.
     * 
     * If $originalViewPath is passed, the error view can be used instead of that view.
     * 
     * @param \Throwable $exception
     * @param string $originalViewPath
     * @return string
     */
    public function getErrorView(\Throwable $exception, string $originalViewPath = null) : string
    {
        if ($exception instanceof ExceptionInterface) {
            $logId = $exception->getId();
            $alias =  $exception->getAlias();
            $text = $exception->getMessageModel($this->getWorkbench())->getTitle();
            $description = $this->facade->isShowingErrorMessage($exception) ? $exception->getMessage() : '';
            if ($logId) {
                $title = "{i18n>MESSAGE.ERROR.SERVER_ERROR_TITLE}: Log ID $logId";
            } else {
                $title = "{i18n>MESSAGE.ERROR.SERVER_ERROR_TITLE}";
            }
            if ($alias) {
                $text = "{i18n>MESSAGE.TYPE.{$exception->getMessageModel($this->getWorkbench())->getType(MessageTypeDataType::ERROR)}} $alias: $text";
            } else {
                $text = "{i18n>MESSAGE.TYPE.ERROR}";
            }
            $text = json_encode($text);
            $description = json_encode($description);
            $title = json_encode($title);
        } else {
            $title = '"{i18n>MESSAGE.ERROR.SERVER_ERROR_TITLE}"';
            if ($this->facade->isShowingErrorMessage($exception) === true) {
                $text = json_encode($exception->getMessage());
                $description = '"Please view logs for details"';
            } else {
                $text = '"{i18n>MESSAGE.ERROR.UNKNOWN_TITLE}"';
                $description = '"{i18n>MESSAGE.ERROR.UNKNOWN_DESCRIPTION}"';
            }
        }
        $placeholders = [
            'error_view_name' => ($originalViewPath ?? 'Error'),
            'error_title' => $title,
            'error_text' => $text,
            'error_description' => $description            
        ];
        return $this->getFromFileTemplate('view/Error' . $this->getViewFileSuffix(), $placeholders);
    }
    
    /**
     * Returns the code for the controller for the error view from getErrorView().
     * 
     * @param \Throwable $exception
     * @return string
     */
    protected function getErrorController(\Throwable $exception) : string
    {
        return $this->getFromFileTemplate('controller/Error' . $this->getControllerFileSuffix());
    }
    
    /**
     * Returns the controller namespace for the app: e.g. "myComponent.controller."
     * 
     * @return string
     */
    public function getControllerNamespace() : string
    {
        return $this->getComponentName() . '.controller.';
    }
    
    /**
     * Returns the controller namespace for the app: e.g. "myComponent.view."
     *
     * @return string
     */
    public function getViewNamespace() : string
    {
        return $this->getComponentName() . '.view.';
    }
    
    /**
     * 
     * @return UI5Facade
     */
    public function getFacade() : UI5Facade
    {
        return $this->facade;
    }
    
    /**
     * Creates a new UI page with the login prompt derived from the given exception.
     * 
     * @param \Throwable $authError
     * @param string $pageAlias
     * @return UiPageInterface
     */
    public function createLoginPage(\Throwable $authError, string $pageAlias) : UiPageInterface
    {
        $pageAlias = $pageAlias ?? $this->getRootPageAlias();
        $loginPage = UiPageFactory::createBlank($this->getWorkbench(), $pageAlias);
        
        $loginPrompt = LoginPrompt::createFromException($loginPage, $authError);
        if ($pageAlias === $this->getRootPageAlias()) {
            $loginPrompt->setCaption($this->getWorkbench()->getConfig()->getOption('SERVER.TITLE'));
        }
        
        $loginPage->addWidget($loginPrompt);
        
        return $loginPage;
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsPWAInit() : string
    {
        $pwa = $this->facade->getPWA($this->getRootPage()->getPWASelector());
        $url = ltrim($this->getComponentUrl(), '/');
        return <<<JS
        
(function(oController){
    var oBtnOffline;
    if (! exfPWA.ui5Preloaded) {
        oBtnOffline = sap.ui.getCore().byId('exf-network-indicator');
        oBtnOffline.setBusyIndicatorDelay(0).setBusy(true);
        exfPWA.ui5Preloaded = true;
        $.ajax({
    		url: '{$url}Offline-preload.js',
    		dataType: "script",
    		cache: true,
    		success: function(script, textStatus) {
                oBtnOffline.setBusy(false);
    			console.log('offline stuff loaded');
    		},
    		error: function(jqXHR, textStatus, errorThrown) {
                oBtnOffline.setBusy(false);
    			console.warn("Failed loading offline data from $url");
    			oController.getOwnerComponent().showAjaxErrorDialog(jqXHR);
    		}
    	});
        exfPWA.model.addPWA('{$pwa->getURL()}');
    }
})(this);

JS;
    }
}