<?php
namespace exface\UI5Facade\Actions;

use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\UI5Facade\UI5FacadeApp;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Factories\UiPageFactory;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\Interfaces\AppInterface;
use exface\Core\DataTypes\StringDataType;
use exface\UI5Facade\Webapp;
use exface\UI5Facade\Facades\UI5Facade;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Factories\FacadeFactory;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Exceptions\Facades\FacadeRuntimeError;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iTriggerAction;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\CommonLogic\AbstractActionDeferred;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Factories\ResultFactory;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\Interfaces\Actions\iModifyData;
use exface\Core\Interfaces\Actions\iShowWidget;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Actions\iCanBeCalledFromCLI;
use exface\Core\CommonLogic\Tasks\ResultMessageStream;
use exface\Core\CommonLogic\Actions\ServiceParameter;
use exface\Core\Interfaces\Tasks\CliTaskInterface;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Exceptions\Actions\ActionRuntimeError;
use exface\Core\Factories\TaskFactory;
use GuzzleHttp\Psr7\ServerRequest;
use exface\Core\DataTypes\BooleanDataType;
use exface\UI5Facade\Exceptions\UI5ExportUnsupportedException;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Tasks\ResultMessageStreamInterface;

/**
 * Generates the code for a selected Fiori Webapp project.
 * 
 * @author Andrej Kabachnik
 * 
 * @method UI5FacadeApp getApp()
 *
 */
class ExportFioriWebapp extends AbstractActionDeferred implements iModifyData, iCanBeCalledFromCLI
{
    private $facadeSelectorString = 'exface\\UI5Facade\\Facades\\UI5Facade';
    
    private $errors = [];
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::init()
     */
    protected function init()
    {
        parent::init();
        $this->setInputRowsMin(1);
        $this->setInputRowsMax(1);
        $this->setInputObjectAlias('exface.UI5Facade.FIORI_WEBAPP');
        $this->setIcon(Icons::HDD_O);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractActionDeferred::performImmediately()
     */
    protected function performImmediately(TaskInterface $task, DataTransactionInterface $transaction, ResultMessageStreamInterface $result) : array
    {
        if ($task instanceof CliTaskInterface) {
            $input = DataSheetFactory::createFromObject($this->getInputObjectExpected());
            $input->getFilters()->addConditionFromString('app_id', $task->getCliArgument('app_id'), ComparatorDataType::EQUALS);
        } else {
            $input = $this->getInputDataSheet($task);
        }
        $input->getColumns()->addFromAttributeGroup($input->getMetaObject()->getAttributes());
        
        if (! $input->isFresh()) {
            if ($input->hasUidColumn(true) === true) {
                $input->getFilters()->addConditionFromColumnValues($input->getUidColumn());
            }
            $input->dataRead();
        }
        
        $row = $input->getRows()[0];
        $row['component_path'] = str_replace('.', '/', $row['app_id']);
        $row['assets_path'] = './';
        $row['use_combined_viewcontrollers'] = 'false';
        
        $rootPage = UiPageFactory::createFromModel($this->getWorkbench(), $row['root_page_alias']);
        $facade = FacadeFactory::createFromString($this->facadeSelectorString, $this->getWorkbench());
        $facadeConfig = $facade->getConfig();
        
        // Modify the facade config to work with the export
        // Server adapter
        if ($row['odata_adapater_class']) {
            $facadeConfig->setOption('DEFAULT_SERVER_ADAPTER_CLASS', $row['odata_adapater_class']);
        } else {
            $facadeConfig->setOption('DEFAULT_SERVER_ADAPTER_CLASS', $facade->getConfig()->getOption('WEBAPP_EXPORT.SERVER_ADAPTER_CLASS'));
        }
        // OData options
        $facadeConfig->setOption('WEBAPP_EXPORT.ODATA.EXPORT_CONNECTION_CREDENTIALS', BooleanDataType::cast($row['odata_export_credentials']));
        $facadeConfig->setOption('WEBAPP_EXPORT.ODATA.EXPORT_CONNECTION_SAP_CLIENT', BooleanDataType::cast($row['odata_export_sap_client']));
        $facadeConfig->setOption('WEBAPP_EXPORT.ODATA.USE_BATCH_DELETES', BooleanDataType::cast($row['odata_use_batch_deletes']));
        $facadeConfig->setOption('WEBAPP_EXPORT.ODATA.USE_BATCH_WRITES', BooleanDataType::cast($row['odata_use_batch_writes']));
        $facadeConfig->setOption('WEBAPP_EXPORT.ODATA.USE_BATCH_FUNCTION_IMPORTS', BooleanDataType::cast($row['odata_use_batch_function_imports']));
        $facadeConfig->setOption('WEBAPP_EXPORT.MANIFEST.DATASOURCES_USE_RELATIVE_URLS', BooleanDataType::cast($row['odata_use_relative_urls']));
        
        // use short Widget IDs for export
        $facadeConfig->setOption('WIDGET.USE_SHORT_ID', true);
        
        // Disable all global actions as they cannot be used with the oData adapter
        $facade->getWorkbench()->getConfig()->setOption('WIDGET.DATATOOLBAR.GLOBAL_ACTIONS', new UxonObject());
        
        return [$rootPage, $facade, $row, $input, $transaction];
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractActionDeferred::performDeferred()
     */
    protected function performDeferred(UiPageInterface $rootPage = null, UI5Facade $facade = null, array $row = [], DataSheetInterface $input = null, DataTransactionInterface $transaction = null) : \Generator
    {
        if ($rootPage === null || $facade === null || empty($row) || $input === null || $transaction === null) {
            yield PHP_EOL . 'Export failed due to missing parameters!';
        }
        $appGen = $this->exportWebapp($rootPage, $facade, $row);
        yield from $appGen;
        $webappFolder = $appGen->getReturn();
        
        // Update build-timestamp
        $updSheet = DataSheetFactory::createFromObject($input->getMetaObject());
        $updSheet->addRow([
            $input->getMetaObject()->getUidAttributeAlias() => $row[$input->getUidColumn()->getName()],
            'current_version_date' => DateTimeDataType::now(),
            'MODIFIED_ON' => $row['MODIFIED_ON']
        ]);
        
        $updSheet->dataUpdate(false, $transaction);
        
        if ($this->hasErrors()) {
            yield PHP_EOL . 'Export failed with ' . count($this->getErrors()) . ' errors! Previous state of the export folder has been restored.';
        } else {
            yield PHP_EOL . 'Exported to ' . $webappFolder;
        }
    }
    
    protected function getExportPath(array $appData) : string
    {
        $path = $appData['export_folder'];
        $path = StringDataType::replacePlaceholders($path, $appData, true);
        $fm = $this->getWorkbench()->filemanager();
        if ($fm::pathIsAbsolute($path) === false) {
            $path = $fm::pathJoin([$fm->getPathToBaseFolder(), $path]);
        }
        
        return $path;
    }
    
    protected function exportWebapp(UiPageInterface $rootPage, UI5Facade $facade, array $appDataRow) : \Generator
    {
        $appPath = $this->getExportPath($appDataRow);
        $backupPath = $appPath . '.bkp';
        if (file_exists($backupPath)) {
            Filemanager::deleteDir($backupPath);
        }
        $webcontentPath = $appPath . DIRECTORY_SEPARATOR . 'WebContent';
        if (file_exists($appPath)) {
            @rename($appPath, $backupPath);
        }
        
        try {
            Filemanager::pathConstruct($webcontentPath);
            
            $webcontentPath = $webcontentPath . DIRECTORY_SEPARATOR;
            /* @var $webapp \exface\UI5Facade\Webapp */ 
            $webapp = $facade->initWebapp($appDataRow['app_id'], $appDataRow);
            
            if (! file_exists($webcontentPath . 'view')) {
                Filemanager::pathConstruct($webcontentPath . 'view');
            }
            if (! file_exists($webcontentPath . 'controller')) {
                Filemanager::pathConstruct($webcontentPath . 'controller');
            }
            if (! file_exists($webcontentPath . 'libs')) {
                Filemanager::pathConstruct($webcontentPath . 'libs');
            }
                 
            yield 'Exporting to ' . $appPath . ':' . PHP_EOL . PHP_EOL;
            yield from $this->exportFile($webapp, 'index.html', $webcontentPath, '  ');
            yield from $this->exportFile($webapp, 'Component.js', $webcontentPath, '  ');
            yield from $this->exportTranslations(($rootPage->hasApp() ? $rootPage->getApp() : $this->getApp()), $webapp, $webcontentPath, '  ');
            yield '  view' . DIRECTORY_SEPARATOR . ' + controller' . DIRECTORY_SEPARATOR . PHP_EOL;
            yield from $this->exportStaticViews($webapp, $webcontentPath, '    ');
            yield from $this->exportPages($webapp, $webcontentPath, '    ');
            yield from $this->exportFile($webapp, 'manifest.json', $webcontentPath, '  ');
        } catch (\Throwable $e) {
            $this->getWorkbench()->getLogger()->logException($e);
            $this->addError($e);
        }
        
        if ($this->hasErrors()) {
            Filemanager::deleteDir($appPath);
            if (file_exists($backupPath)) {
                @rename($backupPath, $appPath);
            }
        }
        
        return $appPath;
    }
    
    protected function exportTranslations(AppInterface $app, Webapp $webapp, string $exportFolder, string $msgIndent) : \Generator
    {
        $defaultLang = $app->getLanguageDefault();
        $i18nFolder = 'i18n' . DIRECTORY_SEPARATOR;
        $i18nFolderPathAbs = $exportFolder . $i18nFolder;
        if (! file_exists($i18nFolderPathAbs)) {
            Filemanager::pathConstruct($i18nFolderPathAbs);
        }
        
        yield $msgIndent . $i18nFolder . PHP_EOL;
        
        foreach ($app->getLanguages() as $lang) {
            $i18nSuffix = (strcasecmp($lang, $defaultLang) === 0) ? '' : '_' . $lang; 
            $i18nFile = 'i18n' . $i18nSuffix . '.properties';
            $i18nFilePath = $exportFolder . $i18nFolder . $i18nFile;
            $i18nRoute = Filemanager::pathNormalize($i18nFolder . $i18nFile, '/');
            file_put_contents($i18nFilePath, $webapp->get($i18nRoute));
            yield $msgIndent.$msgIndent . $i18nFile . PHP_EOL;
        }
    }
    
    /**
     * 
     * @param Webapp $webapp
     * @param string $exportFolder
     * @return ExportFioriWebapp
     */
    protected function exportStaticViews(Webapp $webapp, string $exportFolder, string $msgIndent) : \Generator
    {
        foreach ($webapp->getBaseViews() as $route) {
            yield from $this->exportFile($webapp, $route, $exportFolder, $msgIndent);
        }
        foreach ($webapp->getBaseControllers() as $route) {
            yield from $this->exportFile($webapp, $route, $exportFolder, $msgIndent);
        }
    }
    
    protected function exportPages(Webapp $webapp, string $exportFolder, string $msgIndent) : \Generator
    {
        yield from $this->exportPage($webapp, $webapp->getRootPage(), $exportFolder, $msgIndent);
    }
    
    protected function exportPage(Webapp $webapp, UiPageInterface $page, string $exportFolder, string $msgIndent, int $linkDepth = 5) : \Generator
    {     
        try {
            $widget = $page->getWidgetRoot();
            yield from $this->exportWidget($webapp, $widget, $exportFolder, $linkDepth, $msgIndent);
        } catch (\Throwable $e) {
            $this->getWorkbench()->getLogger()->logException($e);
            $this->addError($e);
            yield 'ERROR exporting views for page "' . $page->getAliasWithNamespace() . '": ' . PHP_EOL . $e->getMessage() . PHP_EOL . '... in ' . $e->getFile() . ' on line ' . $e->getLine() . PHP_EOL;
        }
    }
    
    protected function exportWidget(Webapp $webapp, WidgetInterface $widget, string $exportFolder, int $linkDepth, string $msgIndent) : \Generator
    {
        $unsupported = null;
        try {
            $facade = FacadeFactory::createFromString('exface.UI5Facade.UI5Facade', $this->getWorkbench());
            $request = new ServerRequest('GET', '');
            $task = TaskFactory::createHttpTask($facade, $request);
            $widget = $webapp->handlePrefill($widget, $task);
            // IMPORTANT: generate the view first to allow it to add controller methods!
            $view = $webapp->getViewForWidget($widget);
            $controller = $view->getController();
            
            $viewJs = $view->buildJsView();
            $viewJs = StringDataType::encodeUTF8($viewJs);
            
            $controllerJs = $controller->buildJsController();
        } catch (UI5ExportUnsupportedException $unsupported) {
            $this->addError($unsupported);
            yield $msgIndent . '+ ERROR in widget ' . $widget->getWidgetType() . ' "' . $widget->getCaption() . '": UI configuration not supported by export!' . PHP_EOL;
            foreach ($unsupported->getErrors() as $error) {
                yield $msgIndent . '|  - ' . $error . PHP_EOL;
            }
        } catch (\Throwable $e) {
            throw new ActionRuntimeError($this, 'Cannot export view/controller for widget ' . $widget->getWidgetType() . ' "' . $widget->getCaption() . '" (object "' . $widget->getMetaObject()->getAliasWithNamespace() . '", id "' . $widget->getId() . '") in page "' . $widget->getPage()->getAliasWithNamespace() . '": ' . $e->getMessage(), null, $e);
        }
        
        if (! $unsupported) {
            if (! $viewJs) {
                throw new ActionRuntimeError($this, 'Cannot export view for for widget "' . $widget->getId() . '" in page "' . $widget->getPage()->getAliasWithNamespace() . '": the generated UI5 view is empty!');
            }
            
            if (! $controllerJs) {
                throw new ActionRuntimeError($this, 'Cannot export controller for for widget "' . $widget->getId() . '" in page "' . $widget->getPage()->getAliasWithNamespace() . '": the generated UI5 controller is empty!');
            }
            
            // Copy external includes and replace their paths in the controller
            $libsGen = $this->exportExternalLibs($controllerJs, $exportFolder . DIRECTORY_SEPARATOR . 'libs', $msgIndent);
            yield from $libsGen;
            $controllerJs = $libsGen->getReturn();
            
            // Save view and controller as files
            $controllerFile = rtrim($exportFolder, "\\/") . DIRECTORY_SEPARATOR . $controller->getPath(true);
            $controllerDir = pathinfo($controllerFile, PATHINFO_DIRNAME);
            if (! is_dir($controllerDir)) {
                Filemanager::pathConstruct($controllerDir);
            }
            
            $viewFile = rtrim($exportFolder, "\\/") . DIRECTORY_SEPARATOR . $view->getPath(true);
            $viewDir = pathinfo($viewFile, PATHINFO_DIRNAME);
            if (! is_dir($viewDir)) {
                Filemanager::pathConstruct($viewDir);
            }
            
            yield $msgIndent . '+ Widget ' . $widget->getWidgetType() . ' "' . $widget->getCaption() . '": ' . PHP_EOL;
            file_put_contents($viewFile, $viewJs);
            yield $msgIndent . '| ' . StringDataType::substringAfter($view->getPath(), $webapp->getComponentPath() . '/') . PHP_EOL;
            file_put_contents($controllerFile, $controllerJs);
            yield $msgIndent . '| ' . StringDataType::substringAfter($controller->getPath(), $webapp->getComponentPath() . '/') . PHP_EOL;
        }
        
        if ($linkDepth > 0) {
            foreach ($this->findLinkedViewWidgets($widget) as $dialog) {
                yield from $this->exportWidget($webapp, $dialog, $exportFolder, ($linkDepth-1), $msgIndent . '| ');
            }
        }
    }
    
    protected function findLinkedViewWidgets(WidgetInterface $widget) : array
    {
        $results = [];
        foreach ($widget->getChildren() as $child) {
            if ($child instanceof iTriggerAction && $child->hasAction() && $child->getAction() instanceof iShowWidget) {
                $results[] = $child->getAction()->getWidget();
            } else {
                $results = array_merge($results, $this->findLinkedViewWidgets($child));
            }
        }
        return $results;
    }
    
    protected function exportExternalLibs(string $controllerJs, string $libsFolder, string $msgIndent) : \Generator
    {
        $filemanager = $this->getWorkbench()->filemanager();
        
        // See what files already exist, so we only yield exported libs once - the first time they are exported
        // This greatly reduces useless output :)
        $existingFiles = Filemanager::getDirContentsRecursive($libsFolder, true, true, '/');
        // "libs/" - need to strip this off the the beginning of each $pathExported in order to search for
        // that path in the $existingFiles
        $libsSlash = pathinfo($libsFolder, PATHINFO_BASENAME) . '/';
        
        // Process JS files
        $matches = [];
        preg_match_all('/jQuery\.sap\.registerModulePath\((?>[\'"].*[\'"], )?[\'"](.*)["\']\)/mi', $controllerJs, $matches);
        $jsIncludes = $matches[1];
        
        foreach ($jsIncludes as $path) {
            if ($this->isExternalUrl($path)) {
                continue;
            }
            $pathExported = $this->exportExternalLib($path . '.js', $libsFolder, $filemanager);
            if (! in_array(substr($pathExported, strlen($libsSlash)), $existingFiles)) {
                yield $msgIndent . $pathExported . PHP_EOL;
            }
            $controllerJs = str_replace($path, substr($pathExported, 0, -3), $controllerJs);
        }
        
        // Process CSS files
        $matches = [];
        preg_match_all('/jQuery\.sap\.includeStyleSheet\((?>[\'"].*[\'"], )?[\'"](.*)["\']\)/mi', $controllerJs, $matches);
        $cssIncludes = $matches[1];
        
        foreach ($cssIncludes as $path) {
            if ($this->isExternalUrl($path)) {
                continue;
            }
            $pathExported = $this->exportExternalLib($path, $libsFolder, $filemanager);
            if (! in_array(substr($pathExported, strlen($libsSlash)), $existingFiles)) {
                yield $msgIndent . $pathExported . PHP_EOL;
            }
            $controllerJs = str_replace($path, $pathExported, $controllerJs);
        }
        
        return $controllerJs;
    }
    
    protected function exportExternalLib(string $includePath, string $libsFolder, Filemanager $filemanager) : string
    {
        $pathInVendorFolder = Filemanager::pathNormalize(StringDataType::substringAfter($includePath, 'vendor/'));
        $file = $filemanager->getPathToVendorFolder() . DIRECTORY_SEPARATOR . $pathInVendorFolder;
        if (! file_exists($file)) {
            throw new RuntimeException('Cannot export external library with path "' . $includePath . '": file "' . $file . '" not found!');
        }
        
        $folder = pathinfo($pathInVendorFolder, PATHINFO_DIRNAME);
        foreach ($this->getExternalLibFiles($pathInVendorFolder) as $copyFrom => $copyTo) {
            $copyFrom = $filemanager->getPathToVendorFolder() . DIRECTORY_SEPARATOR . $copyFrom;
            $copyTo = $libsFolder . DIRECTORY_SEPARATOR . $copyTo;
            if (! file_exists($copyTo)) {
                if (is_dir($copyFrom) === true) {
                    $filemanager->copyDir($copyFrom, $copyTo);
                } else {
                    $filemanager->copyFile($copyFrom, $copyTo);
                }
            }
        }
        
        if (strcasecmp($folder, $this->getApp()->getDirectory()) === 0) {
            $pathInVendorFolder = str_replace('/Facades/js', '', $pathInVendorFolder);
        }
        
        return pathinfo($libsFolder, PATHINFO_BASENAME) . '/' . $pathInVendorFolder;
    }
    
    protected function getExternalLibFiles(string $libPathInVendorFolder) : array
    {
        $pathMap = [];
        
        // Copy folder containing the required include
        $folder = pathinfo($libPathInVendorFolder, PATHINFO_DIRNAME);
        if (strcasecmp($folder, $this->getApp()->getDirectory()) === 0) {
            $jsFolder = '/Facades/js';
            $copyFromFolder = $folder . $jsFolder;
        } else {
            $copyFromFolder = $folder;
        }
        $pathMap[$copyFromFolder] = $folder;
        
        // Look for license and package files, that we should preserve
        $pathParts = explode('/', $libPathInVendorFolder);
        $packageRoot = $pathParts[0] . DIRECTORY_SEPARATOR . $pathParts[1] . DIRECTORY_SEPARATOR;
        $packageRootAbs = $this->getWorkbench()->filemanager()->getPathToVendorFolder() . DIRECTORY_SEPARATOR . $packageRoot;
        if (file_exists($packageRootAbs . 'package.json')) {
            $pathMap[$packageRoot . 'package.json'] = $packageRoot . 'package.json';
        }
        if (file_exists($packageRootAbs . 'LICENSE')) {
            $pathMap[$packageRoot . 'LICENSE'] = $packageRoot . 'LICENSE';
        }
        if (file_exists($packageRootAbs . 'bower.json')) {
            $pathMap[$packageRoot . 'bower.json'] = $packageRoot . 'bower.json';
        }
        if (file_exists($packageRootAbs . 'composer.json')) {
            $pathMap[$packageRoot . 'composer.json'] = $packageRoot . 'composer.json';
        }
        if (file_exists($packageRootAbs . 'fonts')) {
            $pathMap[$packageRoot . 'fonts'] = $packageRoot . 'fonts';
        }
        
        return $pathMap;
    }
    
    protected function isExternalUrl(string $uri) : bool
    {
        return StringDataType::startsWith($uri, 'https:', false) || StringDataType::startsWith($uri, 'http:', false) || StringDataType::startsWith($uri, 'ftp:', false);
    }
    
    protected function buildPathToPageAsset(UiPageInterface $page, string $exportFolder, string $assetType = 'view') : string
    {
        $subfolder = str_replace(AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER, DIRECTORY_SEPARATOR, $page->getNamespace());
        $destination = $exportFolder . $assetType . DIRECTORY_SEPARATOR . ($subfolder ? $subfolder . DIRECTORY_SEPARATOR : '');
        if (! file_exists($destination)) {
            Filemanager::pathConstruct($destination);
        }
        return $destination;
    }
    
    protected function exportFile(Webapp $webapp, string $route, string $exportFolder, string $msgIndent) : \Generator
    {
        file_put_contents($exportFolder . $route, $webapp->get($route));
        yield $msgIndent . $route . PHP_EOL;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see iCanBeCalledFromCLI::getCliArguments()
     */
    public function getCliArguments() : array
    {
        return [
            (new ServiceParameter($this))
            ->setName('app_id')
            ->setDescription('Fiori app id to be exported. By default it is the full alias (with namespace) of the root page of the app')
            ->setRequired(true)
        ];
    }
    
    /**
     *
     * {@inheritdoc}
     * @see iCanBeCalledFromCLI::getCliOptions()
     */
    public function getCliOptions() : array
    {
        return [];
    }
    
    /**
     * 
     * @param \Throwable $e
     * @return ExportFioriWebapp
     */
    protected function addError(\Throwable $e) : ExportFioriWebapp
    {
        $this->errors[] = $e;
        return $this;
    }
    
    /**
     * 
     * @return \Throwable[]
     */
    protected function getErrors() : array
    {
        return $this->errors;   
    }
    
    /**
     * 
     * @return bool
     */
    protected function hasErrors() : bool
    {
        return ! empty($this->errors);
    }
}