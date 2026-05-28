<?php
namespace exface\UI5Facade\Facades;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iTriggerAction;
use exface\Core\Interfaces\Actions\iShowWidget;
use exface\Core\CommonLogic\PWA\AbstractPWA;
use exface\Core\CommonLogic\PWA\PWARoute;
use exface\UI5Facade\Webapp;
use exface\UI5Facade\Facades\Interfaces\UI5ViewInterface;
use exface\Core\Interfaces\Actions\iReadData;
use exface\Core\DataTypes\OfflineStrategyDataType;
use exface\Core\Actions\ShowHelpDialog;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Widgets\iSupportLazyLoading;
use exface\Core\Actions\ReadPrefill;
use exface\Core\Widgets\Dialog;
use exface\Core\Widgets\Data;
use exface\Core\Actions\RefreshWidget;
use exface\Core\Actions\CallWidgetFunction;
use exface\Core\Interfaces\Widgets\iUseInputWidget;
use exface\Core\Interfaces\Actions\iExportData;
use exface\Core\Interfaces\Actions\iNavigate;
use exface\Core\Factories\ActionFactory;
use exface\Core\Actions\ShowWidget;
use exface\Core\Widgets\KPI;
use exface\Core\Widgets\Filter;
use exface\Core\Widgets\DataLookupDialog;
use exface\Core\Interfaces\Actions\iRefreshInputWidget;
use exface\Core\Interfaces\Actions\iResetWidgets;

/**
 * 
 * @author andrej.kabachnik
 * 
 * @method UI5Facade getFacade()
 *
 */
class UI5PWA extends AbstractPWA
{
    private $widgetsProcessed = [];
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\PWA\AbstractPWA::generateModelForWidget()
     */
    protected function generateModelForWidget(WidgetInterface $widget, int $linkDepth = 100, string $logIndent = '  ') : \Generator
    {
        if (in_array($widget, $this->widgetsProcessed, true) === true) {
            return;
        } else {
            $this->widgetsProcessed[] = $widget;
        }
        
        if ($widget->hasParent() === false) {
            if ($widget->getPage()->isPublished() === false && $this->isAvailableOfflineUnpublished() === false) {
                return;
            }
            $route = new PWARoute(
                $this, 
                $this->getViewForWidget($widget)->getRouteName(), 
                $widget, 
                ActionFactory::createFromString($this->getWorkbench(), ShowWidget::class, $widget)
            );
            $this->addRoute($route);
            yield "Page {$widget->getPage()->getName()} ({$widget->getPage()->getAliasWithNamespace()}):" . PHP_EOL;
        }
        
        if ($linkDepth <= 0) {
            return;
        }
        
        switch (true) {
            // Exclude any widget setup widgets/actions!
            case $widget->getMetaObject()->getAliasWithNamespace() === 'exface.Core.WIDGET_SETUP':
                return;
            // Data widgets with lazy loading
            case $widget instanceof iSupportLazyLoading:
                if ($this->isWidgetLazyLoading($widget) && $widget->hasAction()) {
                    $action = $widget->getAction();
                    $widget->getLazyLoadingAction();
                    $this->addAction($action, $widget);
                    if ($action->getOfflineStrategy() === null || $action->getOfflineStrategy() === OfflineStrategyDataType::PRESYNC) {
                        $data = $action instanceof ReadPrefill ? $widget->prepareDataSheetToPrefill() : $widget->prepareDataSheetToRead();
                        $dataSet = $this->addData($data, $action, $widget);
                        yield $logIndent . 'Data for ' . $this->getDescriptionOf($dataSet) . PHP_EOL;
                    }
                }
                break;
            // Buttons
            case $widget instanceof iTriggerAction:
                // Don't bother if the button has no action
                if (! $widget->hasAction()) {
                    break;
                }
                $action = $widget->getAction();
                
                // Some actions are ignored - e.g. iRefreshInputWidget - stop here and do not process children of the trigger
                // There is no point even marking them as client-only as they only will bloat the PWA model
                if ($this->isActionIgnored($action)) {
                    return;
                }
                
                // Add the action to the PWA model in any case
                $this->addAction($action, $widget);
                
                // Add other stuff like data or routes for certain actions
                switch (true) {
                    // Buttons with actions to read data
                    case $action instanceof iReadData:
                    // ... or other types of actions that are used to read data by lazy loading widgets like data widgets
                    case $widget instanceof iSupportLazyLoading && $widget->getLazyLoadingAction() === $action:
                        // Will need the create an offline data set for these actions if they are to be presynced
                        $inputWidget = $widget instanceof iUseInputWidget ? $widget->getInputWidget() : $widget;
                        if ($this->getActionOfflineStrategy($action) !== OfflineStrategyDataType::PRESYNC) {
                            break;
                        }
                        $data = $action instanceof ReadPrefill ? $inputWidget->prepareDataSheetToPrefill() : $inputWidget->prepareDataSheetToRead();
                        $dataSet = $this->addData($data, $action, $widget);
                        yield $logIndent . 'Data for ' . $this->getDescriptionOf($dataSet) . PHP_EOL;
                        break;
                    // Buttons with actions that show widgets
                    case $action instanceof iShowWidget:
                        // If the action shows a specific widget (e.g. dialog) within a page
                        // - add the route in any case
                        // - add prefill data set if the action should work offline
                        // - continue to search for actions within that widget if it should work offline
                        // Otherwise, if the action points to an entire page, simply call generateModelForWidget for
                        // the root of that page - it will take care of everything.
                        if (null !== $widgetActionWidget = $widget->getAction()->getWidget()) {
                            $route = new PWARoute(
                                $this,
                                $this->getViewForWidget($widgetActionWidget)->getRouteName(),
                                $widgetActionWidget,
                                $widget->getAction()
                            );
                            $this->addRoute($route);
                            yield $logIndent . 'Route for ' . $this->getDescriptionOf($route) . PHP_EOL;
                            
                            // Stop here if the action is online-only. Remember, that the "ignore" strategy is
                            // handled above already! Thus, we will have in the PWA model:
                            // - the route but saying, that it is online-only)
                            // - No prefill data
                            // - Nothing for child widgets
                            if ($this->getActionOfflineStrategy($action) === OfflineStrategyDataType::ONLINE_ONLY) {
                                yield $logIndent . $logIndent . 'Online-only route: stop processing nested routes' . PHP_EOL;
                                return;
                            }
                            
                            // Make sure, prefill data is available offline if the action is not online-only
                            $this->addDataForPrefill($widgetActionWidget, $action);
                            yield $logIndent . 'Prefill data for ' . $this->getDescriptionOf($route) . PHP_EOL;
                            
                            // Continue to search for actions inside the dialog/widget
                            yield from $this->generateModelForWidget($widgetActionWidget, ($linkDepth-1), $logIndent . '  ');
                            
                        } elseif (null !== $widgetActionPage = $widget->getAction()->getPage()) {
                            yield from $this->generateModelForWidget($widgetActionPage->getWidgetRoot(), ($linkDepth-1), $logIndent . '  ');
                        }
                        break;
                }
                break;
        }
        
        // In any case, continue to search for actions among the children of the widget
        
        $furtherDepth = ($linkDepth-1);
        // Do not load offline data for KPI filters as they are not visible in UI5
        if ($widget instanceof KPI) {
            $furtherDepth = 1;
        }
        // Do not take autosuggest-filters inside of lookup dialogs offline - this will produce a lot of useless data
        if (($widget instanceof Filter) && $widget->getParentByClass(DataLookupDialog::class)) {
            return;
        }
        foreach ($widget->getChildren() as $child) {
            yield from $this->generateModelForWidget($child, $furtherDepth);
        }
    }
    
    /**
     * 
     * @param ActionInterface $action
     * @return bool
     */
    protected function isActionIgnored(ActionInterface $action) : bool
    {
        switch (true) {
            // Ignore refresh, reload and search-buttons as they behave exactly like their target widget
            case $action instanceof iRefreshInputWidget:
                return true;
            case $action instanceof iResetWidgets:
                return true;
            case $action->getOfflineStrategy() === OfflineStrategyDataType::IGNORE:
                return true;
        }
        return false;
    }
    
    protected function isWidgetLazyLoading(WidgetInterface $widget) : bool
    {
        switch (true) {
            case $widget instanceof Dialog:
            case $widget instanceof Data:
                $default = true;
                break;
            default:
                $default = false;
        }
        return $widget->getLazyLoading($default);
    }
    
    protected function findOfflineStrategy(ActionInterface $action, WidgetInterface $triggerWidget) : string
    {
        $strategy = $action->getOfflineStrategy();
        switch (true) {
            case $strategy !== null: 
                return $strategy;
            case $action instanceof RefreshWidget:
            case $action instanceof CallWidgetFunction:
                return OfflineStrategyDataType::CLIENT_SIDE;
            case $action instanceof iExportData:
                return OfflineStrategyDataType::ONLINE_ONLY;
            case $this->isAvailableOffline() === true && $action instanceof iReadData: 
                return OfflineStrategyDataType::PRESYNC;
            case $this->isAvailableOfflineHelp() === false && $action instanceof ShowHelpDialog:
                return OfflineStrategyDataType::ONLINE_ONLY;
            case $this->isAvailableOfflineUnpublished() === false && $triggerWidget && $triggerWidget->getPage()->isPublished() === false: 
                return OfflineStrategyDataType::ONLINE_ONLY;
            // TODO what offline strategy do non-lazy dialogs get?
            case $action instanceof iShowWidget: 
                return OfflineStrategyDataType::PRESYNC;
            case $action instanceof iNavigate:
                return OfflineStrategyDataType::CLIENT_SIDE;
        }
        return OfflineStrategyDataType::ENQUEUE;
    }
    
    protected function getWebapp() : Webapp
    {
        return $this->getFacade()->getWebapp();
    }
    
    protected function getViewForWidget(WidgetInterface $widget) : UI5ViewInterface
    {
        return $this->getWebapp()->getViewForWidget($widget);
    }
}