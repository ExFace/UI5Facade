<?php
namespace exface\UI5Facade\Facades\Formatters;

use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;
use exface\UI5Facade\Facades\Interfaces\UI5BindingFormatterInterface;
use exface\UI5Facade\Facades\UI5Facade;

/**
 *  
 * @author Andrej Kabachnik
 *
 */
trait UI5MomentFormatterTrait
{    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Formatters\AbstractUI5BindingFormatter::registerExternalModules()
     */
    public function registerExternalModules(UI5ControllerInterface $controller) : UI5BindingFormatterInterface
    {
        $this->registerUi5CustomType($controller);
        return $this;
    }
    
    /**
     * 
     * @param UI5Facade $facade
     * @param UI5ControllerInterface $controller
     * 
     * @return void
     */
    public static function registerMoment(UI5Facade $facade, UI5ControllerInterface $controller)
    {
        $localesPath = $facade->getWorkbench()->filemanager()->getPathToVendorFolder() . DIRECTORY_SEPARATOR . $facade->getConfig()->getOption('LIBS.MOMENT.LOCALES');
        $fullLocale = $facade->getWorkbench()->getContext()->getScopeSession()->getSessionLocale();
        $locale = str_replace("_", "-", $fullLocale);
        $momentLocale = '';
        if (file_exists($localesPath . DIRECTORY_SEPARATOR . $locale . '.js')) {
            $momentLocale = $locale;
        }
        $locale = substr($fullLocale, 0, strpos($fullLocale, '_'));
        if (file_exists($localesPath . DIRECTORY_SEPARATOR . $locale . '.js')) {
            $momentLocale = $locale;
        }
        
        $controller->addExternalModule('libs.moment.moment', $facade->buildUrlToSource("LIBS.MOMENT.JS"), null, 'moment');
        if ($momentLocale !== '') {
            $controller->addExternalModule('libs.moment.locale', $facade->buildUrlToSource("LIBS.MOMENT.LOCALES", false) . '/' . $momentLocale . '.js', null);
        }
        
        return;
    }
    
    /**
     * 
     * @param UI5ControllerInterface $controller
     * @return UI5BindingFormatterInterface
     */
    protected function registerUi5CustomType(UI5ControllerInterface $controller) : UI5BindingFormatterInterface
    {
        $facade = $controller->getWebapp()->getFacade();
        $controller->addExternalModule('libs.exface.ui5Custom.dataTypes.MomentDateType', $facade->buildUrlToSource("LIBS.UI5CUSTOM.DATETYPE.JS"));
        return $this;
    }
}